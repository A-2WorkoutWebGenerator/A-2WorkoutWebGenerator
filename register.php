<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_connection.php';

$response = array();
$response['success'] = false;

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->email) && !empty($data->password)) {
    $conn = getConnection();
    
    if (isset($conn['error'])) {
        $response['message'] = $conn['error'];
        echo json_encode($response);
        exit();
    }
    
    $username = htmlspecialchars(strip_tags($data->username));
    $email = htmlspecialchars(strip_tags($data->email));
    
    $check_stmt = oci_parse($conn, "SELECT * FROM users WHERE username = :username OR email = :email");
    oci_bind_by_name($check_stmt, ":username", $username);
    oci_bind_by_name($check_stmt, ":email", $email);
    oci_execute($check_stmt);
    
    if (($row = oci_fetch_assoc($check_stmt)) !== false) {
        if ($row['USERNAME'] === $username) {
            $response['message'] = "Username already taken. Please choose another one.";
        } else {
            $response['message'] = "Email already registered. Please use another email or login.";
        }
    } else {
        $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
        
        $insert_stmt = oci_parse($conn, 
            "INSERT INTO users (username, email, password, created_at, updated_at) 
             VALUES (:username, :email, :password, SYSTIMESTAMP, SYSTIMESTAMP)");
        
        oci_bind_by_name($insert_stmt, ":username", $username);
        oci_bind_by_name($insert_stmt, ":email", $email);
        oci_bind_by_name($insert_stmt, ":password", $hashed_password);
        
        if (oci_execute($insert_stmt)) {
            $id_query = oci_parse($conn, "SELECT id FROM users WHERE username = :username");
            oci_bind_by_name($id_query, ":username", $username);
            oci_execute($id_query);
            
            if (($user = oci_fetch_assoc($id_query)) !== false) {
                $response['success'] = true;
                $response['message'] = "Registration successful!";
                $response['user_id'] = $user['ID'];
            } else {
                $response['message'] = "Registration successful, but unable to retrieve user ID.";
            }
            
            oci_free_statement($id_query);
        } else {
            $e = oci_error($insert_stmt);
            $response['message'] = "Registration failed: " . $e['message'];
        }
        
        oci_free_statement($insert_stmt);
    }
    
    oci_free_statement($check_stmt);
    oci_close($conn);
    
} else {
    $response['message'] = "Missing required fields. Please provide username, email, and password.";
}

echo json_encode($response);
?>