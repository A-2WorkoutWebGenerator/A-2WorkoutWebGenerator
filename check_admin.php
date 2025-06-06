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

require_once 'jwt_utils.php';

function getBearerToken() {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers) {
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
    }

    $authHeaders = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'AUTHORIZATION'
    ];
    foreach ($authHeaders as $header) {
        if (isset($_SERVER[$header])) {
            if (preg_match('/Bearer\s(\S+)/', $_SERVER[$header], $matches)) {
                return $matches[1];
            }
        }
    }

    return null;
}

$response = array();
$response['success'] = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = getBearerToken();
    
    if (!$token) {
        $response['message'] = "Authorization token required";
        http_response_code(401);
        echo json_encode($response);
        exit();
    }
    
    try {
        $decoded = decode_jwt($token);
        
        if (!isset($decoded->isAdmin) || !$decoded->isAdmin) {
            $response['message'] = "Admin privileges required";
            http_response_code(403);
            echo json_encode($response);
            exit();
        }
        
        $response['success'] = true;
        $response['message'] = "Admin access granted";
        $response['user'] = [
            'id' => $decoded->sub,
            'username' => $decoded->username,
            'email' => $decoded->email,
            'isAdmin' => $decoded->isAdmin
        ];
        
    } catch (Exception $e) {
        $response['message'] = "Invalid or expired token";
        http_response_code(401);
        echo json_encode($response);
        exit();
    }
} else {
    $response['message'] = "Invalid request method";
    http_response_code(405);
}

echo json_encode($response);
?>