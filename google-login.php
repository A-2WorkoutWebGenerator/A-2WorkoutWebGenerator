<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $host = 'database-1.cpak6uiam1q1.eu-north-1.rds.amazonaws.com';
    $dbname = 'postgres';
    $username = 'postgres';
    $password = 'postgres';
    
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET search_path TO fitgen, public");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

require_once 'db_connection.php';
require_once 'jwt_utils.php';

use Google\Client;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
const GOOGLE_CLIENT_ID = '452342585871-p9ofgvju1jnjdg1u6mh3urllevoatta0.apps.googleusercontent.com';

function generateRSSToken() {
    return bin2hex(random_bytes(32));
}

function verifyGoogleToken($idToken) {
    $client = new Client(['client_id' => GOOGLE_CLIENT_ID]);
    $payload = $client->verifyIdToken($idToken);
    
    if ($payload) {
        return [
            'success' => true,
            'user_data' => [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
                'given_name' => $payload['given_name'] ?? '',
                'family_name' => $payload['family_name'] ?? '',
                'picture' => $payload['picture'] ?? '',
                'email_verified' => $payload['email_verified'] ?? false
            ]
        ];
    }
    
    return ['success' => false, 'error' => 'Invalid Google token'];
}

function ensureUserHasRSSToken($userId, $pdo) {
    try {
        $checkStmt = $pdo->prepare("SELECT rss_token FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['rss_token'])) {
            $rssToken = generateRSSToken();
            $updateStmt = $pdo->prepare("UPDATE users SET rss_token = ? WHERE id = ?");
            $updateStmt->execute([$rssToken, $userId]);
            
            error_log("RSS token generated for user ID: $userId");
            return $rssToken;
        }
        
        return $result['rss_token'];
    } catch (Exception $e) {
        error_log("Error ensuring RSS token: " . $e->getMessage());
        return null;
    }
}

function findOrCreateGoogleUser($googleData, $pdo) {
    try {
        $pdo->exec("SET search_path TO fitgen");
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$googleData['google_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET email = ?, last_login = CURRENT_TIMESTAMP, profile_picture_url = ?
                WHERE google_id = ?
            ");
            $updateStmt->execute([
                $googleData['email'],
                $googleData['picture'],
                $googleData['google_id']
            ]);
            
            ensureUserHasRSSToken($user['id'], $pdo);
            
            return [
                'success' => true,
                'user' => $user,
                'is_new' => false
            ];
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$googleData['email']]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            $linkStmt = $pdo->prepare("
                UPDATE users 
                SET google_id = ?, profile_picture_url = ?, last_login = CURRENT_TIMESTAMP
                WHERE email = ?
            ");
            $linkStmt->execute([
                $googleData['google_id'],
                $googleData['picture'],
                $googleData['email']
            ]);
            
            ensureUserHasRSSToken($existingUser['id'], $pdo);
            
            return [
                'success' => true,
                'user' => $existingUser,
                'is_new' => false
            ];
        }
        
        $username = generateUsernameFromEmail($googleData['email'], $pdo);
        $rssToken = generateRSSToken();
        
        $dummyPassword = password_hash('google_oauth_' . uniqid(), PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO users (
                username, 
                email, 
                password,
                google_id, 
                profile_picture_url,
                email_verified,
                rss_token,
                created_at,
                updated_at,
                last_login
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id
        ");
        
        $insertStmt->execute([
            $username,
            $googleData['email'],
            $dummyPassword,
            $googleData['google_id'],
            $googleData['picture'],
            $googleData['email_verified'] ? true : false,
            $rssToken
        ]);
        
        $result = $insertStmt->fetch(PDO::FETCH_ASSOC);
        $userId = $result['id'];
        
        $profileStmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, created_at, updated_at) 
            VALUES (?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $profileStmt->execute([$userId]);
        
        $newUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $newUserStmt->execute([$userId]);
        $newUser = $newUserStmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("New Google user created with RSS token: " . $newUser['username']);
        
        return [
            'success' => true,
            'user' => $newUser,
            'is_new' => true
        ];
        
    } catch (Exception $e) {
        error_log("Google user creation error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    }
}

function generateUsernameFromEmail($email, $pdo) {
    $baseUsername = explode('@', $email)[0];
    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername);
    
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'user' . $baseUsername;
    }
    
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $username = $baseUsername . $counter;
        $counter++;
        if ($counter > 1000) {
            $username = $baseUsername . '_' . uniqid();
            break;
        }
    }
    
    return $username;
}
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['credential'])) {
        throw new Exception('Google credential is required');
    }
    
    $verification = verifyGoogleToken($input['credential']);
    
    if (!$verification['success']) {
        throw new Exception('Invalid Google token');
    }
    $userResult = findOrCreateGoogleUser($verification['user_data'], $pdo);
    
    if (!$userResult['success']) {
        throw new Exception($userResult['error']);
    }
    
    $user = $userResult['user'];
    
    $token = create_jwt(
        $user['id'],
        $user['username'],
        $user['email'],
        isset($user['isadmin']) && $user['isadmin'] ? true : false
    );
    try {
        $pdo->exec("SET search_path TO fitgen");
        $logStmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, login_method, ip_address, user_agent, login_time) 
            VALUES (?, 'google', ?, ?, CURRENT_TIMESTAMP)
        ");
        $logStmt->execute([
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $logError) {
        error_log("Login logging error: " . $logError->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => $userResult['is_new'] ? 'Account created successfully!' : 'Login successful!',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $verification['user_data']['given_name'] ?? '',
            'last_name' => $verification['user_data']['family_name'] ?? '',
            'is_new' => $userResult['is_new']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>