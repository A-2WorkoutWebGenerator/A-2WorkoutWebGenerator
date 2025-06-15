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
    error_log("FORGOT_PASSWORD: Script started");
    
    $input = file_get_contents("php://input");
    error_log("FORGOT_PASSWORD: Raw input received: " . $input);
    
    $data = json_decode($input);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("FORGOT_PASSWORD: JSON decode error: " . json_last_error_msg());
        $response['message'] = "Invalid JSON data";
        echo json_encode($response);
        exit();
    }
    
    if (empty($data->email)) {
        $response['message'] = "Email address is required.";
        echo json_encode($response);
        exit();
    }
    
    $email = filter_var(trim($data->email), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $response['message'] = "Please enter a valid email address.";
        echo json_encode($response);
        exit();
    }
    
    error_log("FORGOT_PASSWORD: Processing email: " . $email);
    
    $conn = getConnection();
    if ($conn === false) {
        error_log("FORGOT_PASSWORD: Database connection failed");
        $response['message'] = "Database connection failed";
        echo json_encode($response);
        exit();
    }
    $query = "SELECT id, username, email FROM users WHERE email = $1";
    $result = pg_query_params($conn, $query, array($email));
    
    if (!$result) {
        $error = pg_last_error($conn);
        error_log("FORGOT_PASSWORD: Query failed: " . $error);
        $response['message'] = "Database query failed";
        echo json_encode($response);
        exit();
    }
    
    if (pg_num_rows($result) === 0) {
        error_log("FORGOT_PASSWORD: Email not found: " . $email);
        $response['success'] = true;
        $response['message'] = "If an account with that email exists, we've sent a password reset link.";
        echo json_encode($response);
        exit();
    }
    
    $user = pg_fetch_assoc($result);
    error_log("FORGOT_PASSWORD: User found: " . $user['username']);
    $resetToken = bin2hex(random_bytes(32));
    $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $updateQuery = "UPDATE users SET reset_token = $1, reset_token_expiry = $2 WHERE id = $3";
    $updateResult = pg_query_params($conn, $updateQuery, array($resetToken, $resetExpiry, $user['id']));
    
    if (!$updateResult) {
        $error = pg_last_error($conn);
        error_log("FORGOT_PASSWORD: Update failed: " . $error);
        $response['message'] = "Failed to generate reset token";
        echo json_encode($response);
        exit();
    }
    
    error_log("FORGOT_PASSWORD: Reset token generated for user: " . $user['username']);

    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.html?token=" . $resetToken;
    $emailSent = EmailConfig::sendPasswordResetEmail($email, $user['username'], $resetLink, $resetToken);
    
    if ($emailSent) {
        error_log("FORGOT_PASSWORD: Email sent successfully to: " . $email);
        $response['success'] = true;
        $response['message'] = "Password reset link has been sent to your email.";
    } else {
        error_log("FORGOT_PASSWORD: Email failed to send to: " . $email);
        $response['message'] = "Failed to send email. Please try again later.";
    }
    
    pg_close($conn);
    
} catch (Exception $e) {
    error_log("FORGOT_PASSWORD: Exception caught: " . $e->getMessage());
    $response['message'] = "Server error occurred. Please try again later.";
} catch (Error $e) {
    error_log("FORGOT_PASSWORD: Error caught: " . $e->getMessage());
    $response['message'] = "Server error occurred. Please try again later.";
}

error_log("FORGOT_PASSWORD: Final response: " . json_encode($response));
echo json_encode($response);
?>