<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'jwt_utils.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getBearerToken() {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
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

function getUserIdFromJWT($token) {
    try {
        $jwt = decode_jwt($token);
        return $jwt->sub ?? false;
    } catch (Exception $e) {
        return false;
    }
}

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth_token = getBearerToken();
    $user_id = getUserIdFromJWT($auth_token);

    if (!$user_id) {
        $response['message'] = "Authentication required.";
        echo json_encode($response);
        exit();
    }

    $key = 'file';
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = "No file uploaded or upload error.";
        echo json_encode($response);
        exit();
    }

    $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
    $filename = $_FILES[$key]["name"];
    $filetype = $_FILES[$key]["type"];
    $filesize = $_FILES[$key]["size"];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!array_key_exists($ext, $allowed)) {
        $response['message'] = "Invalid file format. Please use JPG, JPEG, GIF or PNG.";
        echo json_encode($response);
        exit();
    }
    $maxsize = 10 * 1024 * 1024;
    if ($filesize > $maxsize) {
        $response['message'] = "File size exceeds the limit (10MB).";
        echo json_encode($response);
        exit();
    }
    if (in_array($filetype, $allowed)) {
        $uploadDir = "uploads/profile_pics/";
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $response['message'] = "Error creating upload directory.";
                echo json_encode($response);
                exit();
            }
        }
        $newFilename = $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
        $uploadPath = $uploadDir . $newFilename;
        if (move_uploaded_file($_FILES[$key]["tmp_name"], $uploadPath)) {

            $conn = getConnection();
            if ($conn === false) {
                $response['message'] = "Database connection failed.";
                echo json_encode($response);
                exit();
            }

            $existingProfileQuery = "SELECT profile_picture_path FROM user_profiles WHERE user_id = $1";
            $existingResult = pg_query_params($conn, $existingProfileQuery, array($user_id));
            if ($existingResult && pg_num_rows($existingResult) > 0) {
                $existingRow = pg_fetch_assoc($existingResult);
                $oldProfilePicture = $existingRow['profile_picture_path'];
                if ($oldProfilePicture && file_exists($oldProfilePicture)) {
                    @unlink($oldProfilePicture);
                }
            }

            $updateProfilePicQuery = "UPDATE user_profiles SET profile_picture_path = $1 WHERE user_id = $2";
            $updateResult = pg_query_params($conn, $updateProfilePicQuery, array($uploadPath, $user_id));
            pg_close($conn);

            if ($updateResult) {
                $response['success'] = true;
                $response['message'] = "Profile picture uploaded!";
                $response['profile_picture_path'] = $uploadPath;

                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'];
                $publicPath = '/' . ltrim($uploadPath, '/');
                $response['profile_picture_url'] = $baseUrl . $publicPath;
            } else {
                $response['message'] = "Error updating profile picture in database.";
            }
        } else {
            $response['message'] = "Error uploading file.";
        }
    } else {
        $response['message'] = "Invalid file type.";
    }
    echo json_encode($response);
    exit();
}

$response['message'] = "Invalid request method.";
echo json_encode($response);
exit();
?>