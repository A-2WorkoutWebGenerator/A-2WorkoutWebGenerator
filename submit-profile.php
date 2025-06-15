<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'jwt_utils.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    if (isset($_POST['auth_token']) && !empty($_POST['auth_token'])) {
        return $_POST['auth_token'];
    }
    return null;
}

function getUserIdFromJWT($token) {
    try {
        $jwt = decode_jwt($token);
        if (isset($jwt->sub)) {
            return $jwt->sub;
        }
    } catch (Exception $e) {
        error_log("JWT decode error: " . $e->getMessage());
    }
    return false;
}

$validGenders = ['male', 'female', 'other'];

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $auth_token = getBearerToken();

    if (!$auth_token) {
        $response['message'] = "Authentication required. Please log in.";
        echo json_encode($response);
        exit();
    }

    $user_id = getUserIdFromJWT($auth_token);
    if (!$user_id) {
        $response['message'] = "Invalid or expired token. Please log in again.";
        echo json_encode($response);
        exit();
    }

    if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== 0) {
        $response['message'] = "Content-Type must be application/json for PUT request.";
        echo json_encode($response);
        exit();
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!is_array($data)) {
        $response['message'] = "Invalid JSON data.";
        echo json_encode($response);
        exit();
    }

    $firstName = isset($data["first_name"]) ? htmlspecialchars($data["first_name"]) : '';
    $lastName = isset($data["last_name"]) ? htmlspecialchars($data["last_name"]) : '';
    $email = isset($data["email"]) ? htmlspecialchars($data["email"]) : '';
    $gender = isset($data["gender"]) ? htmlspecialchars($data["gender"]) : '';
    $age = isset($data["age"]) ? (int) $data["age"] : null;
    $weight = isset($data["weight"]) ? (float) $data["weight"] : null;
    $goal = isset($data["goal"]) ? htmlspecialchars($data["goal"]) : '';
    $injuries = isset($data["injuries"]) ? htmlspecialchars($data["injuries"]) : '';

    if (!empty($gender) && !in_array($gender, $validGenders)) {
        $response['message'] = "Invalid value for gender. Accepted: male, female, other.";
        echo json_encode($response);
        exit();
    }

    $conn = getConnection();
    if ($conn === false) {
        $response['message'] = "Database connection failed.";
        echo json_encode($response);
        exit();
    }

    $checkQuery = "SELECT id FROM user_profiles WHERE user_id = $1";
    $result = pg_query_params($conn, $checkQuery, array($user_id));

    if ($result) {
        if (pg_num_rows($result) > 0) {
            $row = pg_fetch_assoc($result);
            $profileId = $row['id'];

            $updateFields = [
                "first_name = $1",
                "last_name = $2",
                "gender = $3",
                "age = $4",
                "weight = $5",
                "goal = $6",
                "injuries = $7"
            ];
            $params = [
                $firstName,
                $lastName,
                $gender,
                $age,
                $weight,
                $goal,
                $injuries
            ];

            $updateQuery = "UPDATE user_profiles SET " . implode(", ", $updateFields) . " WHERE id = $" . (count($params) + 1);
            $params[] = $profileId;

            $result = pg_query_params($conn, $updateQuery, $params);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Profile updated successfully!";
            } else {
                $response['message'] = "Error updating profile: " . pg_last_error($conn);
            }
        } else {
            $insertFields = "user_id, first_name, last_name, gender, age, weight, goal, injuries";
            $insertValues = "$1, $2, $3, $4, $5, $6, $7, $8";
            $params = [
                $user_id,
                $firstName,
                $lastName,
                $gender,
                $age,
                $weight,
                $goal,
                $injuries
            ];

            $insertQuery = "INSERT INTO user_profiles ($insertFields) VALUES ($insertValues)";

            $result = pg_query_params($conn, $insertQuery, $params);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Profile created successfully!";
            } else {
                $response['message'] = "Error creating profile: " . pg_last_error($conn);
            }
        }
    } else {
        $response['message'] = "Database query error.";
    }
    if (!empty($email)) {
        $updateEmailQuery = "UPDATE users SET email = $1 WHERE id = $2";
        $emailResult = pg_query_params($conn, $updateEmailQuery, array($email, $user_id));
        if (!$emailResult) {
            error_log("Email update error: " . pg_last_error($conn));
        }
    }

    pg_close($conn);
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth_token = getBearerToken();
    session_start();
    $user_id = false;
    if ($auth_token) {
        $user_id = getUserIdFromJWT($auth_token);
    }
    if (!$user_id && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    if (!$user_id) {
        $response['message'] = "Authentication required. Please log in.";
        echo json_encode($response);
        exit();
    }

    $firstName = isset($_POST["first_name"]) ? htmlspecialchars($_POST["first_name"]) : '';
    $lastName = isset($_POST["last_name"]) ? htmlspecialchars($_POST["last_name"]) : '';
    $email = isset($_POST["email"]) ? htmlspecialchars($_POST["email"]) : '';
    $gender = isset($_POST["gender"]) ? htmlspecialchars($_POST["gender"]) : '';
    $age = isset($_POST["age"]) ? (int) $_POST["age"] : null;
    $weight = isset($_POST["weight"]) ? (float) $_POST["weight"] : null;
    $goal = isset($_POST["goal"]) ? htmlspecialchars($_POST["goal"]) : '';
    $injuries = isset($_POST["injuries"]) ? htmlspecialchars($_POST["injuries"]) : '';

    if (!empty($gender) && !in_array($gender, $validGenders)) {
        $response['message'] = "Invalid value for gender. Accepted: male, female, other.";
        echo json_encode($response);
        exit();
    }

    $removePic = isset($_POST["remove_pic"]) ? ($_POST["remove_pic"] === "1") : false;

    $conn = getConnection();
    if ($conn === false) {
        $response['message'] = "Database connection failed.";
        echo json_encode($response);
        exit();
    }
    $existingProfileQuery = "SELECT profile_picture_path FROM user_profiles WHERE user_id = $1";
    $existingResult = pg_query_params($conn, $existingProfileQuery, array($user_id));
    $oldProfilePicture = null;
    if ($existingResult && pg_num_rows($existingResult) > 0) {
        $existingRow = pg_fetch_assoc($existingResult);
        $oldProfilePicture = $existingRow['profile_picture_path'];
    }

    $profilePicturePath = null;
    $shouldUpdatePicture = false;
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == UPLOAD_ERR_OK) {
        $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
        $filename = $_FILES["profile_pic"]["name"];
        $filetype = $_FILES["profile_pic"]["type"];
        $filesize = $_FILES["profile_pic"]["size"];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $response['message'] = "Invalid file format. Please use JPG, JPEG, GIF or PNG.";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
        $maxsize = 10 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $response['message'] = "File size exceeds the limit (10MB).";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
        if (in_array($filetype, $allowed)) {
            $uploadDir = "uploads/profile_pics/";
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    $response['message'] = "Error creating upload directory.";
                    echo json_encode($response);
                    pg_close($conn);
                    exit();
                }
            }
            $newFilename = $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $uploadPath = $uploadDir . $newFilename;
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $uploadPath)) {
                if ($oldProfilePicture && file_exists($oldProfilePicture)) {
                    @unlink($oldProfilePicture);
                }
                $profilePicturePath = $uploadPath;
                $shouldUpdatePicture = true;
            } else {
                $response['message'] = "Error uploading file.";
                echo json_encode($response);
                pg_close($conn);
                exit();
            }
        } else {
            $response['message'] = "Invalid file type.";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
    } else if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] != UPLOAD_ERR_NO_FILE) {
        $response['message'] = "Upload error code: " . $_FILES["profile_pic"]["error"];
        echo json_encode($response);
        pg_close($conn);
        exit();
    }
    if ($removePic) {
        $shouldUpdatePicture = true;
        $profilePicturePath = null;
        if ($oldProfilePicture && file_exists($oldProfilePicture)) {
            @unlink($oldProfilePicture);
        }
    }

    $checkTableQuery = "SELECT EXISTS (SELECT 1 FROM pg_tables WHERE tablename = 'user_profiles')";
    $tableResult = pg_query($conn, $checkTableQuery);

    if (!$tableResult || pg_fetch_result($tableResult, 0, 0) === 'f') {
        $createTableQuery = "
        CREATE TABLE user_profiles (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            gender VARCHAR(20),
            age INTEGER,
            weight FLOAT,
            goal VARCHAR(50),
            injuries TEXT,
            equipment VARCHAR(50),
            profile_picture_path VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX idx_user_profiles_user_id ON user_profiles(user_id);
        CREATE OR REPLACE FUNCTION update_modified_column()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = now();
            RETURN NEW;
        END;
        $$ language 'plpgsql';
        CREATE TRIGGER update_user_profiles_modtime
            BEFORE UPDATE ON user_profiles
            FOR EACH ROW
            EXECUTE PROCEDURE update_modified_column();
        ";
        $createResult = pg_query($conn, $createTableQuery);

        if (!$createResult) {
            $response['message'] = "Database setup error.";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
    }
    $checkQuery = "SELECT id FROM user_profiles WHERE user_id = $1";
    $result = pg_query_params($conn, $checkQuery, array($user_id));

    if ($result) {
        if (pg_num_rows($result) > 0) {
            $row = pg_fetch_assoc($result);
            $profileId = $row['id'];

            $updateFields = [
                "first_name = $1",
                "last_name = $2",
                "gender = $3",
                "age = $4",
                "weight = $5",
                "goal = $6",
                "injuries = $7"
            ];
            $params = [
                $firstName,
                $lastName,
                $gender,
                $age,
                $weight,
                $goal,
                $injuries
            ];

            if ($shouldUpdatePicture) {
                $updateFields[] = "profile_picture_path = $" . (count($params) + 1);
                $params[] = $profilePicturePath;
            }

            $updateQuery = "UPDATE user_profiles SET " . implode(", ", $updateFields) . " WHERE id = $" . (count($params) + 1);
            $params[] = $profileId;

            $result = pg_query_params($conn, $updateQuery, $params);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Profile updated successfully!";
                if ($shouldUpdatePicture) {
                    $response['profile_picture_path'] = $profilePicturePath;
                }
            } else {
                $response['message'] = "Error updating profile: " . pg_last_error($conn);
            }
        } else {
            $insertFields = "user_id, first_name, last_name, gender, age, weight, goal, injuries";
            $insertValues = "$1, $2, $3, $4, $5, $6, $7, $8";
            $params = [
                $user_id,
                $firstName,
                $lastName,
                $gender,
                $age,
                $weight,
                $goal,
                $injuries
            ];

            if ($shouldUpdatePicture) {
                $insertFields .= ", profile_picture_path";
                $insertValues .= ", $" . (count($params) + 1);
                $params[] = $profilePicturePath;
            }

            $insertQuery = "INSERT INTO user_profiles ($insertFields) VALUES ($insertValues)";

            $result = pg_query_params($conn, $insertQuery, $params);

            if ($result) {
                $response['success'] = true;
                $response['message'] = "Profile created successfully!";
                if ($shouldUpdatePicture) {
                    $response['profile_picture_path'] = $profilePicturePath;
                }
            } else {
                $response['message'] = "Error creating profile: " . pg_last_error($conn);
            }
        }
    } else {
        $response['message'] = "Database query error.";
    }
    if (!empty($email)) {
        $updateEmailQuery = "UPDATE users SET email = $1 WHERE id = $2";
        $emailResult = pg_query_params($conn, $updateEmailQuery, array($email, $user_id));
        if (!$emailResult) {
            error_log("Email update error: " . pg_last_error($conn));
        }
    }

    pg_close($conn);
    echo json_encode($response);
    exit();
}

$response['message'] = "Invalid request method.";
echo json_encode($response);
exit();
?>