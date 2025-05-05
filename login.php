<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$input_data = json_decode(file_get_contents("php://input"), true);
if ($input_data && isset($input_data['password'])) {
    $input_data['password'] = '********';
}
error_log("Login request received: " . json_encode($input_data));
require_once 'db_connection.php'; 

$response = array();
$response['success'] = false;

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $conn = getConnection();
    
    if ($conn === false) {
        $response['message'] = "Database connection failed";
        echo json_encode($response);
        exit();
    }
    
    $username = htmlspecialchars(strip_tags($data->username));

    $query = "SELECT id, username, email, password FROM users WHERE username = $1";
    $result = pg_query_params($conn, $query, array($username));
    
    if (pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);

        if (password_verify($data->password, $user['password'])) {
            $token = bin2hex(random_bytes(32));
            $user_id = $user['id'];
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hour'));

            $token_query = "INSERT INTO auth_tokens (user_id, token, expires_at) 
                          VALUES ($1, $2, $3)";
            
            $token_result = pg_query_params($conn, $token_query, array($user_id, $token, $expires_at));
            
            if ($token_result) {
                $response['success'] = true;
                $response['message'] = "Login successful!";
                $response['token'] = $token;
                $response['user'] = array(
                    "id" => $user['id'],
                    "username" => $user['username'],
                    "email" => $user['email']
                );
            } else {
                $error_message = pg_last_error($conn);
                error_log("Token insert failed with error: " . $error_message);
                $response['message'] = "Login failed: " . $error_message;
            }
        } else {
            $response['message'] = "Invalid username or password.";
        }
    } else {
        $response['message'] = "Invalid username or password.";
    }
    
    pg_close($conn);
    
} else {
    $response['message'] = "Missing required fields. Please provide username and password.";
}
error_log("Login response: " . json_encode($response));
echo json_encode($response);
?>