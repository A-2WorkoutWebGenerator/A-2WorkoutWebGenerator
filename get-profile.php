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

function getBearerToken() {
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    }
    if ($headers) {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                    return $matches[1];
                }
            }
        }
    }
    return null;
}

$response = array();
$response['success'] = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = getBearerToken();

    if (!empty($token)) {
        try {
            $jwt = decode_jwt($token);
            $user_id = $jwt->sub ?? null;
            $username = $jwt->username ?? null;
            $email = $jwt->email ?? null;

            if (!$user_id) {
                $response['message'] = "User ID missing in token.";
            } else {
                $conn = getConnection();
                if ($conn === false) {
                    $response['message'] = "Database connection failed.";
                    echo json_encode($response);
                    exit();
                }
                $profileQuery = "SELECT * FROM user_profiles WHERE user_id = $1";
                $profileResult = pg_query_params($conn, $profileQuery, array($user_id));
                if ($profileResult && pg_num_rows($profileResult) > 0) {
                    $profile = pg_fetch_assoc($profileResult);
                    $response['profile'] = $profile;
                } else {
                    $response['profile'] = null;
                }
                pg_close($conn);

                $response['success'] = true;
                $response['user'] = [
                    'user_id' => $user_id,
                    'username' => $username,
                    'email' => $email
                ];
                $response['message'] = "User info and profile retrieved successfully.";
            }
        } catch (Exception $e) {
            $response['message'] = "Invalid or expired token: " . $e->getMessage();
        }
    } else {
        $response['message'] = "Authorization token is required.";
    }
} else {
    $response['message'] = "Invalid request method: " . $_SERVER['REQUEST_METHOD'];
}

echo json_encode($response);
?>