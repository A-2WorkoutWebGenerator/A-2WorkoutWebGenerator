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
error_log("Register request received: " . json_encode($input_data));

require_once 'db_connection.php';

$response = array();
$response['success'] = false;

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->email) && !empty($data->password)) {
    $conn = getConnection();
    
    if ($conn === false) {
        $response['message'] = "Database connection failed";
        echo json_encode($response);
        exit();
    }
    
    $schemaCheck = pg_query($conn, "SELECT current_schema()");
    if ($schemaCheck) {
        $currentSchema = pg_fetch_result($schemaCheck, 0, 0);
        error_log("Current schema: " . $currentSchema);
    }
    
    $username = htmlspecialchars(strip_tags($data->username));
    $email = htmlspecialchars(strip_tags($data->email));
    
    $check_query = "SELECT * FROM users WHERE username = $1 OR email = $2";
    $result = pg_query_params($conn, $check_query, array($username, $email));
    
    if (!$result) {
        error_log("Check query failed: " . pg_last_error($conn));
        $response['message'] = "Database query failed: " . pg_last_error($conn);
        echo json_encode($response);
        exit();
    }
    
    if (pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        if ($row['username'] === $username) {
            $response['message'] = "Username already taken. Please choose another one.";
        } else {
            $response['message'] = "Email already registered. Please use another email or login.";
        }
    } else {
        $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
        $plpgsql_query = "SELECT * FROM register_user($1, $2, $3)";
        $plpgsql_result = pg_query_params($conn, $plpgsql_query, array($username, $email, $hashed_password));
        
        if ($plpgsql_result && pg_num_rows($plpgsql_result) > 0) {
            $row = pg_fetch_assoc($plpgsql_result);
            if ($row['success'] === 't') {
                $response['success'] = true;
                $response['message'] = $row['message'];
                $response['user_id'] = $row['user_id'];
            } else {
                $response['message'] = $row['message'];
            }
        } else {
            $insert_query = "INSERT INTO users (username, email, password, created_at, updated_at)
                            VALUES ($1, $2, $3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id";
            error_log("Executing query: " . $insert_query . " with values: " . $username . ", " . $email . ", [password]");
            $result = pg_query_params($conn, $insert_query, array($username, $email, $hashed_password));
            
            if ($result) {
                $row = pg_fetch_assoc($result);
                error_log("Insert successful, returned ID: " . $row['id']);
                $response['success'] = true;
                $response['message'] = "Registration successful!";
                $response['user_id'] = $row['id'];
            } else {
                $error_message = pg_last_error($conn);
                error_log("Insert failed with error: " . $error_message);
                $response['message'] = "Registration failed: " . $error_message;
            }
        }
    }
    
    pg_close($conn);
    
} else {
    $response['message'] = "Missing required fields. Please provide username, email, and password.";
}

error_log("Register response: " . json_encode($response));
echo json_encode($response);
?>