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
require_once 'jwt_utils.php';

$response = array();
$response['success'] = false;

try {
    // Debugging: log că am intrat în script
    error_log("LOGIN: Script started");
    
    $input = file_get_contents("php://input");
    error_log("LOGIN: Raw input received: " . $input);
    
    $data = json_decode($input);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("LOGIN: JSON decode error: " . json_last_error_msg());
        $response['message'] = "Invalid JSON data";
        echo json_encode($response);
        exit();
    }
    
    error_log("LOGIN: Decoded data: " . json_encode($data));
    
    if (empty($data->username) || empty($data->password)) {
        $response['message'] = "Missing required fields. Please provide username and password.";
        echo json_encode($response);
        exit();
    }
    
    error_log("LOGIN: Attempting to get database connection");
    $conn = getConnection();
    
    if ($conn === false) {
        error_log("LOGIN: Database connection failed");
        $response['message'] = "Database connection failed";
        echo json_encode($response);
        exit();
    }
    
    error_log("LOGIN: Database connection successful");
    
    $username = htmlspecialchars(strip_tags($data->username));
    error_log("LOGIN: Looking for user: " . $username);
    
    // Verificăm dacă coloana isAdmin există
    $check_column_query = "SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'isadmin'";
    $column_result = pg_query($conn, $check_column_query);
    
    if (pg_num_rows($column_result) > 0) {
        // Coloana există, folosim query-ul cu isAdmin
        $query = "SELECT id, username, email, password, isAdmin FROM users WHERE username = $1";
        error_log("LOGIN: Using query with isAdmin column");
    } else {
        // Coloana nu există, folosim query-ul fără isAdmin
        $query = "SELECT id, username, email, password FROM users WHERE username = $1";
        error_log("LOGIN: Using query without isAdmin column (will default to false)");
    }
    
    $result = pg_query_params($conn, $query, array($username));
    
    if (!$result) {
        $error = pg_last_error($conn);
        error_log("LOGIN: Query failed: " . $error);
        $response['message'] = "Database query failed: " . $error;
        echo json_encode($response);
        exit();
    }
    
    error_log("LOGIN: Query executed successfully, rows: " . pg_num_rows($result));
    
    if (pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);
        error_log("LOGIN: User found: " . $user['username']);
        
        if (password_verify($data->password, $user['password'])) {
            error_log("LOGIN: Password verified successfully");
            
            // Determinăm isAdmin
            $isAdmin = false;
            if (isset($user['isadmin'])) {
                $isAdmin = ($user['isadmin'] === 't' || $user['isadmin'] === true || $user['isadmin'] === '1');
            }
            
            error_log("LOGIN: User isAdmin status: " . ($isAdmin ? 'true' : 'false'));
            
            $response['success'] = true;
            $response['message'] = "Login successful!";
            
            // Creăm JWT token
            try {
                $response['token'] = create_jwt($user['id'], $user['username'], $user['email'], $isAdmin);
                $response['isAdmin'] = $isAdmin;
                error_log("LOGIN: JWT token created successfully");
            } catch (Exception $e) {
                error_log("LOGIN: JWT creation failed: " . $e->getMessage());
                $response['success'] = false;
                $response['message'] = "Token creation failed";
            }
        } else {
            error_log("LOGIN: Password verification failed");
            $response['message'] = "Invalid username or password.";
        }
    } else {
        error_log("LOGIN: User not found");
        $response['message'] = "Invalid username or password.";
    }
    
    pg_close($conn);
    
} catch (Exception $e) {
    error_log("LOGIN: Exception caught: " . $e->getMessage());
    $response['message'] = "Server error: " . $e->getMessage();
} catch (Error $e) {
    error_log("LOGIN: Error caught: " . $e->getMessage());
    $response['message'] = "Server error: " . $e->getMessage();
}

error_log("LOGIN: Final response: " . json_encode($response));
echo json_encode($response);
?>