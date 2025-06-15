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
require_once 'email_config.php';

$response = array();
$response['success'] = false;

try {
    error_log("RESET_PASSWORD: Script started");
    
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = "Invalid JSON data";
        echo json_encode($response);
        exit();
    }
    
    if (empty($data->token) || empty($data->password)) {
        $response['message'] = "Token and password are required";
        echo json_encode($response);
        exit();
    }
    
    $token = trim($data->token);
    $password = trim($data->password);
    if (strlen($password) < 8) {
        $response['message'] = "Password must be at least 8 characters long";
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $response['message'] = "Password must contain at least one uppercase letter";
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $response['message'] = "Password must contain at least one lowercase letter";
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/\d/', $password)) {
        $response['message'] = "Password must contain at least one number";
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $response['message'] = "Password must contain at least one special character";
        echo json_encode($response);
        exit();
    }
    
    error_log("RESET_PASSWORD: Processing token: " . substr($token, 0, 10) . "...");
    
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
        error_log("RESET_PASSWORD: Query failed: " . $error);
        $response['message'] = "Database query failed";
        echo json_encode($response);
        exit();
    }
    
    if (pg_num_rows($result) === 0) {
        error_log("RESET_PASSWORD: Token not found");
        $response['message'] = "Invalid or expired reset token";
        echo json_encode($response);
        exit();
    }
    
    $user = pg_fetch_assoc($result);
    $expiryTime = $user['reset_token_expiry'];
    $currentTime = date('Y-m-d H:i:s');
    
    if ($currentTime > $expiryTime) {
        error_log("RESET_PASSWORD: Token expired for user: " . $user['username']);
        
        $cleanupQuery = "UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = $1";
        pg_query_params($conn, $cleanupQuery, array($user['id']));
        
        $response['message'] = "Reset token has expired. Please request a new password reset.";
        echo json_encode($response);
        exit();
    }
    
    error_log("RESET_PASSWORD: Token valid, updating password for user: " . $user['username']);
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $updateQuery = "UPDATE users SET password = $1, reset_token = NULL, reset_token_expiry = NULL WHERE id = $2";
    $updateResult = pg_query_params($conn, $updateQuery, array($hashedPassword, $user['id']));
    
    if (!$updateResult) {
        $error = pg_last_error($conn);
        error_log("RESET_PASSWORD: Update failed: " . $error);
        $response['message'] = "Failed to update password";
        echo json_encode($response);
        exit();
    }
    
    error_log("RESET_PASSWORD: Password updated successfully for user: " . $user['username']);
    $emailSent = EmailConfig::sendPasswordResetConfirmation($user['email'], $user['username']);
    
    if (!$emailSent) {
        error_log("RESET_PASSWORD: Confirmation email failed to send to: " . $user['email']);
    }
    
    $response['success'] = true;
    $response['message'] = "Password has been successfully reset";
    
    pg_close($conn);
    
} catch (Exception $e) {
    error_log("RESET_PASSWORD: Exception: " . $e->getMessage());
    $response['message'] = "Server error occurred. Please try again.";
}

echo json_encode($response);
?>