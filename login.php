<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_connection.php';

$response = array();
$response['success'] = false;

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $conn = getConnection();
    
    if (isset($conn['error'])) {
        $response['message'] = $conn['error'];
        echo json_encode($response);
        exit();
    }
    
    $username = htmlspecialchars(strip_tags($data->username));
    
    $stmt = oci_parse($conn, "SELECT id, username, email, password FROM users WHERE username = :username");
    oci_bind_by_name($stmt, ":username", $username);
    oci_execute($stmt);
    
    if (($user = oci_fetch_assoc($stmt)) !== false) {
        if (password_verify($data->password, $user['PASSWORD'])) {
            $token = bin2hex(random_bytes(32));
            $user_id = $user['ID'];

            $expiry_time = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $token_stmt = oci_parse($conn, 
                "INSERT INTO auth_tokens (user_id, token, expires_at) 
                 VALUES (:user_id, :token, TO_TIMESTAMP(:expires_at, 'YYYY-MM-DD HH24:MI:SS'))");
            
            oci_bind_by_name($token_stmt, ":user_id", $user_id);
            oci_bind_by_name($token_stmt, ":token", $token);
            oci_bind_by_name($token_stmt, ":expires_at", $expiry_time);
            
            if (oci_execute($token_stmt)) {
                $response['success'] = true;
                $response['message'] = "Login successful!";
                $response['token'] = $token;
                $response['user'] = array(
                    "id" => $user['ID'],
                    "username" => $user['USERNAME'],
                    "email" => $user['EMAIL']
                );
            } else {
                $e = oci_error($token_stmt);
                $response['message'] = "Login failed: " . $e['message'];
            }
            
            oci_free_statement($token_stmt);
        } else {
            $response['message'] = "Invalid username or password.";
        }
    } else {
        $response['message'] = "Invalid username or password.";
    }
    
    oci_free_statement($stmt);
    oci_close($conn);
    
} else {
    $response['message'] = "Missing required fields. Please provide username and password.";
}

echo json_encode($response);
?>