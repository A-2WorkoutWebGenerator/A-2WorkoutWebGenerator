<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_connection.php';

$response = array();
$response['success'] = false;

try {
    error_log("VERIFY_RESET_TOKEN: Script started");
    
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = "Invalid JSON data";
        echo json_encode($response);
        exit();
    }
    
    if (empty($data->token)) {
        $response['message'] = "Reset token is required";
        echo json_encode($response);
        exit();
    }
    
    $token = trim($data->token);
    error_log("VERIFY_RESET_TOKEN: Checking token: " . substr($token, 0, 10) . "...");
    
    $conn = getConnection();
    if ($conn === false) {
        $response['message'] = "Database connection failed";
        echo json_encode($response);
        exit();
    }
    $query = "SELECT id, username, email, reset_token_expiry FROM users WHERE reset_token = $1";
    $result = pg_query_params($conn, $query, array($token));
    
    if (!$result) {
        $error = pg_last_error($conn);
        error_log("VERIFY_RESET_TOKEN: Query failed: " . $error);
        $response['message'] = "Database query failed";
        echo json_encode($response);
        exit();
    }
    
    if (pg_num_rows($result) === 0) {
        error_log("VERIFY_RESET_TOKEN: Token not found");
        $response['message'] = "Invalid reset token";
        echo json_encode($response);
        exit();
    }
    
    $user = pg_fetch_assoc($result);
    $expiryTime = $user['reset_token_expiry'];
    $currentTime = date('Y-m-d H:i:s');
    
    if ($currentTime > $expiryTime) {
        error_log("VERIFY_RESET_TOKEN: Token expired for user: " . $user['username']);
        $response['message'] = "Reset token has expired";
        echo json_encode($response);
        exit();
    }
    
    error_log("VERIFY_RESET_TOKEN: Token valid for user: " . $user['username']);
    $response['success'] = true;
    $response['message'] = "Token is valid";
    $response['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email']
    ];
    
    pg_close($conn);
    
} catch (Exception $e) {
    error_log("VERIFY_RESET_TOKEN: Exception: " . $e->getMessage());
    $response['message'] = "Server error occurred";
}

echo json_encode($response);
?>