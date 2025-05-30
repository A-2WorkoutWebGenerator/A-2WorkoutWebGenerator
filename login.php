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

$input_data = json_decode(file_get_contents("php://input"), true);
if ($input_data && isset($input_data['password'])) {
    $input_data['password'] = '********';
}
error_log("Login request received: " . json_encode($input_data));

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
    
    if (!$result) {
        error_log("Login query failed: " . pg_last_error($conn));
        $response['message'] = "Database query failed: " . pg_last_error($conn);
        echo json_encode($response);
        exit();
    }
    
    if (pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);
        
        if (password_verify($data->password, $user['password'])) {
            $response['success'] = true;
            $response['message'] = "Login successful!";

            $response['token'] = create_jwt($user['id'], $user['username'], $user['email']);
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