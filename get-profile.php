<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connection.php';
$input = file_get_contents("php://input");
error_log("GET PROFILE REQUEST: " . $input);
$headers = getallheaders();
error_log("GET PROFILE HEADERS: " . json_encode($headers));
function getBearerToken() {
    global $input;
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    }
    if ($headers) {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                error_log("Found authorization header: " . $value);
                if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                    return $matches[1];
                }
            }
        }
    }
    $authHeaders = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'AUTHORIZATION'
    ];
    
    foreach ($authHeaders as $header) {
        if (isset($_SERVER[$header])) {
            error_log("Found $header: " . $_SERVER[$header]);
            if (preg_match('/Bearer\s(\S+)/', $_SERVER[$header], $matches)) {
                return $matches[1];
            }
        }
    }
    $data = json_decode($input, true);
    if (isset($data['auth_token'])) {
        error_log("Found auth_token in request body: " . $data['auth_token']);
        return $data['auth_token'];
    }
    
    error_log("No bearer token found in any source");
    return null;
}
function verifyAuthToken($token) {
    $conn = getConnection();
    if ($conn === false) {
        error_log("Connection failed in verifyAuthToken");
        return false;
    }
    $query = "SELECT user_id FROM auth_tokens WHERE token = $1 AND expires_at > NOW()";
    $result = pg_query_params($conn, $query, array($token));
    
    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        error_log("Token valid, user_id: " . $row['user_id']);
        return $row['user_id'];
    }
    
    error_log("Token invalid or expired");
    return false;
}

$response = array();
$response['success'] = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = getBearerToken();
    error_log("Extracted token: " . ($token ? $token : "none"));
    
    $data = json_decode($input, true);
    if (!empty($token)) {
        $user_id = verifyAuthToken($token);
        
        if ($user_id) {
            if (isset($data['user_id']) && $data['user_id'] != $user_id) {
                $response['message'] = "Unauthorized access to another user's profile.";
                error_log("Unauthorized attempt: token user_id=$user_id, request user_id=" . $data['user_id']);
                echo json_encode($response);
                exit();
            }
            $conn = getConnection();
            if ($conn === false) {
                $response['message'] = "Database connection failed.";
                error_log("Database connection failed");
                echo json_encode($response);
                exit();
            }
            $checkTableQuery = "SELECT EXISTS (SELECT 1 FROM pg_tables WHERE tablename = 'user_profiles')";
            $tableResult = pg_query($conn, $checkTableQuery);
            
            if (!$tableResult || pg_fetch_result($tableResult, 0, 0) === 'f') {
                $response['message'] = "Profile table doesn't exist.";
                error_log("user_profiles table doesn't exist");
                echo json_encode($response);
                exit();
            }
            $profileQuery = "SELECT * FROM user_profiles WHERE user_id = $1";
            $profileResult = pg_query_params($conn, $profileQuery, array($user_id));
            if ($profileResult === false) {
                error_log("Profile query failed: " . pg_last_error($conn));
                $response['message'] = "Database query failed: " . pg_last_error($conn);
                echo json_encode($response);
                exit();
            }
            $userQuery = "SELECT email FROM users WHERE id = $1";
            $userResult = pg_query_params($conn, $userQuery, array($user_id));
            if (pg_num_rows($profileResult) > 0) {
                $profile = pg_fetch_assoc($profileResult);
                $response['profile'] = $profile;
                error_log("Profile found for user_id=$user_id");
                
                if ($userResult && pg_num_rows($userResult) > 0) {
                    $user = pg_fetch_assoc($userResult);
                    $response['user'] = array('email' => $user['email']);
                }
                
                $response['success'] = true;
                $response['message'] = "Profile retrieved successfully.";
            } else {
                error_log("No profile found for user_id=$user_id");
                $response['message'] = "No profile found for this user.";
                
                if ($userResult && pg_num_rows($userResult) > 0) {
                    $user = pg_fetch_assoc($userResult);
                    $response['user'] = array('email' => $user['email']);
                    $response['success'] = true;
                }
            }
            pg_close($conn);
        } else {
            $response['message'] = "Invalid or expired token.";
            error_log("Invalid or expired token");
        }
    } else {
        $response['message'] = "Authorization token is required.";
        error_log("No authorization token provided");
    }
} else {
    $response['message'] = "Invalid request method: " . $_SERVER['REQUEST_METHOD'];
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
}
error_log("GET PROFILE RESPONSE: " . json_encode($response));
echo json_encode($response);