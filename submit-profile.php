<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'jwt_utils.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// DEBUGGING: Log toate datele primite
error_log("=== SUBMIT PROFILE DEBUG START ===");
error_log("POST DATA: " . json_encode($_POST));
error_log("FILES DATA: " . json_encode($_FILES));
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);

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

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth_token = getBearerToken();
    error_log("AUTH TOKEN: " . ($auth_token ? "FOUND" : "NOT FOUND"));

    if (!$auth_token) {
        $response['message'] = "Authentication required. Please log in.";
        echo json_encode($response);
        exit();
    }

    $user_id = getUserIdFromJWT($auth_token);
    error_log("USER ID FROM JWT: " . $user_id);
    
    if (!$user_id) {
        $response['message'] = "Invalid or expired token. Please log in again.";
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

    $removePic = isset($_POST["remove_pic"]) ? ($_POST["remove_pic"] === "1") : false;
    error_log("REMOVE PIC: " . ($removePic ? "YES" : "NO"));

    $conn = getConnection();
    if ($conn === false) {
        $response['message'] = "Database connection failed.";
        echo json_encode($response);
        exit();
    }

    // Obține poza existentă
    $existingProfileQuery = "SELECT profile_picture_path FROM user_profiles WHERE user_id = $1";
    $existingResult = pg_query_params($conn, $existingProfileQuery, array($user_id));
    $oldProfilePicture = null;
    if ($existingResult && pg_num_rows($existingResult) > 0) {
        $existingRow = pg_fetch_assoc($existingResult);
        $oldProfilePicture = $existingRow['profile_picture_path'];
    }
    error_log("OLD PROFILE PICTURE: " . ($oldProfilePicture ? $oldProfilePicture : "NONE"));

    $profilePicturePath = null;
    $shouldUpdatePicture = false;

    // DEBUGGING: Verifică dacă avem fișier pentru upload
    error_log("=== FILE UPLOAD CHECK ===");
    if (isset($_FILES["profile_pic"])) {
        error_log("profile_pic FILE EXISTS");
        error_log("File error code: " . $_FILES["profile_pic"]["error"]);
        error_log("File size: " . $_FILES["profile_pic"]["size"]);
        error_log("File name: " . $_FILES["profile_pic"]["name"]);
        error_log("File type: " . $_FILES["profile_pic"]["type"]);
        error_log("File tmp_name: " . $_FILES["profile_pic"]["tmp_name"]);
    } else {
        error_log("NO profile_pic FILE IN REQUEST");
    }

    // Procesează imaginea dacă există
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == UPLOAD_ERR_OK) {
        error_log("=== PROCESSING UPLOAD ===");
        
        $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
        $filename = $_FILES["profile_pic"]["name"];
        $filetype = $_FILES["profile_pic"]["type"];
        $filesize = $_FILES["profile_pic"]["size"];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        error_log("File extension: " . $ext);
        error_log("File type: " . $filetype);

        if (!array_key_exists($ext, $allowed)) {
            $response['message'] = "Invalid file format. Please use JPG, JPEG, GIF or PNG.";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
        
        $maxsize = 10 * 1024 * 1024; // 10MB
        if ($filesize > $maxsize) {
            $response['message'] = "File size exceeds the limit (10MB).";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
        
        if (in_array($filetype, $allowed)) {
            $uploadDir = "uploads/profile_pics/";
            
            // Creează directorul dacă nu există
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("FAILED TO CREATE UPLOAD DIRECTORY: " . $uploadDir);
                    $response['message'] = "Error creating upload directory.";
                    echo json_encode($response);
                    pg_close($conn);
                    exit();
                }
                error_log("CREATED UPLOAD DIRECTORY: " . $uploadDir);
            }

            $newFilename = $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $uploadPath = $uploadDir . $newFilename;
            
            error_log("ATTEMPTING TO MOVE FILE TO: " . $uploadPath);
            error_log("FROM TEMP PATH: " . $_FILES["profile_pic"]["tmp_name"]);

            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $uploadPath)) {
                error_log("FILE SUCCESSFULLY MOVED TO: " . $uploadPath);
                
                // Șterge vechea poză dacă există
                if ($oldProfilePicture && file_exists($oldProfilePicture)) {
                    if (unlink($oldProfilePicture)) {
                        error_log("OLD PROFILE PICTURE DELETED: " . $oldProfilePicture);
                    } else {
                        error_log("FAILED TO DELETE OLD PICTURE: " . $oldProfilePicture);
                    }
                }
                
                // Pregătește calea pentru baza de date
                $profilePicturePath = $uploadPath;
                $shouldUpdatePicture = true;
                
                // DEBUGGING: Verifică calea finală
                error_log("FINAL PROFILE PICTURE PATH FOR DB: " . $profilePicturePath);
                error_log("SHOULD UPDATE PICTURE: " . ($shouldUpdatePicture ? "YES" : "NO"));
                
            } else {
                error_log("FAILED TO MOVE UPLOADED FILE");
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
        // Alte erori de upload
        error_log("UPLOAD ERROR: " . $_FILES["profile_pic"]["error"]);
        $response['message'] = "Upload error code: " . $_FILES["profile_pic"]["error"];
        echo json_encode($response);
        pg_close($conn);
        exit();
    }

    // Procesează ștergerea pozei
    if ($removePic) {
        error_log("=== REMOVING PROFILE PICTURE ===");
        $shouldUpdatePicture = true;
        $profilePicturePath = null;

        if ($oldProfilePicture && file_exists($oldProfilePicture)) {
            if (unlink($oldProfilePicture)) {
                error_log("PROFILE PICTURE REMOVED: " . $oldProfilePicture);
            } else {
                error_log("FAILED TO REMOVE PICTURE: " . $oldProfilePicture);
            }
        }
    }

    // Verifică dacă tabelul există
    $checkTableQuery = "SELECT EXISTS (SELECT 1 FROM pg_tables WHERE tablename = 'user_profiles')";
    $tableResult = pg_query($conn, $checkTableQuery);

    if (!$tableResult || pg_fetch_result($tableResult, 0, 0) === 'f') {
        error_log("CREATING user_profiles TABLE");
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
            error_log("ERROR CREATING TABLE: " . pg_last_error($conn));
            $response['message'] = "Database setup error.";
            echo json_encode($response);
            pg_close($conn);
            exit();
        }
    }

    // Verifică dacă profilul există
    $checkQuery = "SELECT id FROM user_profiles WHERE user_id = $1";
    $result = pg_query_params($conn, $checkQuery, array($user_id));

    if ($result) {
        if (pg_num_rows($result) > 0) {
            // UPDATE
            error_log("=== UPDATING EXISTING PROFILE ===");
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
                error_log("ADDING PICTURE TO UPDATE - PATH: " . ($profilePicturePath ? $profilePicturePath : "NULL"));
            }

            $updateQuery = "UPDATE user_profiles SET " . implode(", ", $updateFields) . " WHERE id = $" . (count($params) + 1);
            $params[] = $profileId;

            error_log("UPDATE QUERY: " . $updateQuery);
            error_log("UPDATE PARAMS: " . json_encode($params));

            $result = pg_query_params($conn, $updateQuery, $params);

            if ($result) {
                error_log("PROFILE UPDATED SUCCESSFULLY");
                $response['success'] = true;
                $response['message'] = "Profile updated successfully!";
                if ($shouldUpdatePicture) {
                    $response['profile_picture_path'] = $profilePicturePath;
                    error_log("RETURNING PICTURE PATH: " . ($profilePicturePath ? $profilePicturePath : "NULL"));
                }
            } else {
                error_log("UPDATE FAILED: " . pg_last_error($conn));
                $response['message'] = "Error updating profile: " . pg_last_error($conn);
            }
        } else {
            // INSERT
            error_log("=== CREATING NEW PROFILE ===");
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
                error_log("ADDING PICTURE TO INSERT - PATH: " . ($profilePicturePath ? $profilePicturePath : "NULL"));
            }

            $insertQuery = "INSERT INTO user_profiles ($insertFields) VALUES ($insertValues)";
            
            error_log("INSERT QUERY: " . $insertQuery);
            error_log("INSERT PARAMS: " . json_encode($params));

            $result = pg_query_params($conn, $insertQuery, $params);

            if ($result) {
                error_log("PROFILE CREATED SUCCESSFULLY");
                $response['success'] = true;
                $response['message'] = "Profile created successfully!";
                if ($shouldUpdatePicture) {
                    $response['profile_picture_path'] = $profilePicturePath;
                    error_log("RETURNING PICTURE PATH: " . ($profilePicturePath ? $profilePicturePath : "NULL"));
                }
            } else {
                error_log("INSERT FAILED: " . pg_last_error($conn));
                $response['message'] = "Error creating profile: " . pg_last_error($conn);
            }
        }
    } else {
        error_log("QUERY FAILED: " . pg_last_error($conn));
        $response['message'] = "Database query error.";
    }
    
    // Update email dacă este furnizat
    if (!empty($email)) {
        $updateEmailQuery = "UPDATE users SET email = $1 WHERE id = $2";
        $emailResult = pg_query_params($conn, $updateEmailQuery, array($email, $user_id));
        if (!$emailResult) {
            error_log("Email update error: " . pg_last_error($conn));
        } else {
            error_log("EMAIL UPDATED SUCCESSFULLY");
        }
    }

    // Generează sugestie de workout
    $suggestion = generateWorkoutSuggestion($goal, '', $injuries, $age, $gender);
    $checkQuery = "SELECT id FROM workout_suggestions WHERE user_id = $1 AND suggestion = $2";
    $checkResult = pg_query_params($conn, $checkQuery, [$user_id, json_encode($suggestion)]);

    if ($checkResult && pg_num_rows($checkResult) == 0) {
        $insertSuggestionQuery = "INSERT INTO workout_suggestions (user_id, generated_at, suggestion) VALUES ($1, NOW(), $2)";
        pg_query_params($conn, $insertSuggestionQuery, [$user_id, json_encode($suggestion)]);
        error_log("WORKOUT SUGGESTION ADDED");
    }
    $response['suggestion'] = $suggestion;
    
    pg_close($conn);
    error_log("=== SUBMIT PROFILE DEBUG END ===");
    echo json_encode($response);
    exit();

} else {
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

function generateWorkoutSuggestion($goal, $equipment, $injuries, $age, $gender) {
    $suggestion = [];
    switch ($goal) {
        case 'lose_weight':
            $suggestion['title'] = "Weight Loss Program";
            $suggestion['description'] = "A balanced program focusing on calorie deficit through cardio and strength training.";
            $suggestion['workouts'] = [
                "HIIT Bodyweight Circuit - 3-4 times per week",
                "Walking/Jogging - 2-3 times per week",
                "Active Recovery (stretching) - 1-2 times per week"
            ];
            break;
        case 'build_muscle':
            $suggestion['title'] = "Muscle Building Program";
            $suggestion['description'] = "A progressive resistance training program with adequate protein intake to build lean muscle.";
            $suggestion['workouts'] = [
                "Progressive Calisthenics - 4 times per week",
                "Bodyweight Supersets - 2 times per week",
                "Active Recovery - 1 time per week"
            ];
            break;
        case 'flexibility':
            $suggestion['title'] = "Flexibility Improvement Program";
            $suggestion['description'] = "A program designed to increase range of motion, reduce stiffness, and improve posture.";
            $suggestion['workouts'] = [
                "Dynamic Stretching Routine - Daily",
                "Yoga Flow Session - 3-4 times per week",
                "Mobility Drills - 2-3 times per week",
                "Static Stretching - Daily"
            ];
            break;
        case 'mobility':
            $suggestion['title'] = "Mobility Enhancement Program";
            $suggestion['description'] = "A comprehensive program to improve joint mobility, movement quality, and functional range of motion.";
            $suggestion['workouts'] = [
                "Dynamic Warm-up Routine - Daily",
                "Joint Mobility Sequence - 4-5 times per week",
                "Functional Movement Patterns - 3 times per week",
                "Deep Stretching Session - 2-3 times per week"
            ];
            break;
        case 'endurance':
            $suggestion['title'] = "Endurance Building Program";
            $suggestion['description'] = "A program to improve cardiovascular health and stamina for longer physical activity.";
            $suggestion['workouts'] = [
                "Progressive Running/Walking - 3-4 times per week",
                "Bodyweight Circuit (high rep) - 2 times per week",
                "Long Duration Low Intensity Session - 1 time per week"
            ];
            break;
        case 'rehab':
            $suggestion['title'] = "Rehabilitation Program";
            $suggestion['description'] = "A gentle program focusing on recovery and gradual strengthening. Always consult a medical professional.";
            $suggestion['workouts'] = [
                "Gentle Mobility Work - Daily",
                "Low-Impact Strengthening - 2-3 times per week",
                "Water-Based Exercises (if available) - 2 times per week",
                "Progressive Range of Motion Exercises - 3-4 times per week"
            ];
            break;
        default:
            $suggestion['title'] = "General Fitness Program";
            $suggestion['description'] = "A balanced approach to overall fitness including strength, cardio, and flexibility.";
            $suggestion['workouts'] = [
                "Full Body Strength - 2 times per week",
                "Cardio Session - 2 times per week",
                "Flexibility & Mobility - 2 times per week",
                "Active Recovery - 1 time per week"
            ];
    }

    if (!empty($injuries)) {
        $suggestion['caution'] = "Due to your reported injuries/conditions, please take the following precautions: 
        1. Start slowly and focus on proper form
        2. Consider consulting a physical therapist or trainer
        3. Modify exercises as needed to avoid pain
        4. Pay attention to pain vs. discomfort";
    }
    if ($age < 18) {
        $suggestion['age_note'] = "For younger athletes, focus on proper technique, variety, and fun rather than intense specialization.";
    } elseif ($age > 60) {
        $suggestion['age_note'] = "Focus on functional movement, balance exercises, and joint-friendly activities.";
    }
    return $suggestion;
}
?>