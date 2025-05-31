<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'jwt_utils.php';

error_log("SUBMIT PROFILE REQUEST RECEIVED: " . json_encode($_POST));

function getBearerToken() {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers) {
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    error_log("Found authorization header: " . $value);
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
            error_log("Found $header: " . $_SERVER[$header]);
            if (preg_match('/Bearer\s(\S+)/', $_SERVER[$header], $matches)) {
                return $matches[1];
            }
        }
    }

    if (isset($_POST['auth_token']) && !empty($_POST['auth_token'])) {
        error_log("Found auth_token in POST: " . $_POST['auth_token']);
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

$response = array();
$response['success'] = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth_token = getBearerToken();
    error_log("Auth token extracted: " . ($auth_token ? $auth_token : "none"));

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
    error_log("Token verified successfully. User ID: " . $user_id);

    $firstName = isset($_POST["first_name"]) ? htmlspecialchars($_POST["first_name"]) : '';
    $lastName = isset($_POST["last_name"]) ? htmlspecialchars($_POST["last_name"]) : '';
    $email = isset($_POST["email"]) ? htmlspecialchars($_POST["email"]) : '';
    $gender = isset($_POST["gender"]) ? htmlspecialchars($_POST["gender"]) : '';
    $age = isset($_POST["age"]) ? (int) $_POST["age"] : null;
    $goal = isset($_POST["goal"]) ? htmlspecialchars($_POST["goal"]) : '';
    $activityLevel = isset($_POST["activity_level"]) ? htmlspecialchars($_POST["activity_level"]) : '';
    $injuries = isset($_POST["injuries"]) ? htmlspecialchars($_POST["injuries"]) : '';
    $equipment = isset($_POST["equipment"]) ? htmlspecialchars($_POST["equipment"]) : '';
    
    $profilePicturePath = null;
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0) {
        $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
        $filename = $_FILES["profile_pic"]["name"];
        $filetype = $_FILES["profile_pic"]["type"];
        $filesize = $_FILES["profile_pic"]["size"];

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) {
            $response['message'] = "Invalid file format. Please use JPG, JPEG, GIF or PNG.";
            echo json_encode($response);
            exit();
        }
        
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $response['message'] = "File size exceeds the limit (5MB).";
            echo json_encode($response);
            exit();
        }

        if (in_array($filetype, $allowed)) {
            $uploadDir = "uploads/profile_pics/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = uniqid() . "-" . $filename;
            $uploadPath = $uploadDir . $newFilename;

            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $uploadPath)) {
                $profilePicturePath = $uploadPath;
            } else {
                $response['message'] = "Error uploading file.";
                echo json_encode($response);
                exit();
            }
        } else {
            $response['message'] = "Invalid file type.";
            echo json_encode($response);
            exit();
        }
    }

    $conn = getConnection();
    if ($conn === false) {
        $response['message'] = "Database connection failed.";
        echo json_encode($response);
        exit();
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
            goal VARCHAR(50),
            activity_level VARCHAR(50),
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
            error_log("Eroare la crearea tabelului user_profiles: " . pg_last_error($conn));
            $response['message'] = "Database setup error. Please contact the administrator.";
            echo json_encode($response);
            exit();
        }
        
        error_log("Tabelul user_profiles a fost creat cu succes.");
    }
    
    $checkQuery = "SELECT id FROM user_profiles WHERE user_id = $1";
    $result = pg_query_params($conn, $checkQuery, array($user_id));
    
    if ($result) {
        if (pg_num_rows($result) > 0) {
            $row = pg_fetch_assoc($result);
            $profileId = $row['id'];
            
            $updateQuery = "UPDATE user_profiles SET 
                first_name = $1, 
                last_name = $2, 
                gender = $3, 
                age = $4, 
                goal = $5, 
                activity_level = $6, 
                injuries = $7, 
                equipment = $8";
            
            $params = array(
                $firstName,
                $lastName,
                $gender,
                $age,
                $goal,
                $activityLevel,
                $injuries,
                $equipment
            );
            
            if ($profilePicturePath) {
                $updateQuery .= ", profile_picture_path = $" . (count($params) + 1);
                $params[] = $profilePicturePath;
            }
            
            $updateQuery .= " WHERE id = $" . (count($params) + 1);
            $params[] = $profileId;
            
            $result = pg_query_params($conn, $updateQuery, $params);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = "Profile updated successfully!";
            } else {
                $response['message'] = "Error updating profile: " . pg_last_error($conn);
            }
        } else {
            $insertQuery = "INSERT INTO user_profiles 
                (user_id, first_name, last_name, gender, age, goal, activity_level, injuries, equipment, profile_picture_path) 
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)";
            
            $result = pg_query_params($conn, $insertQuery, array(
                $user_id,
                $firstName,
                $lastName,
                $gender,
                $age,
                $goal,
                $activityLevel,
                $injuries,
                $equipment,
                $profilePicturePath
            ));
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = "Profile created successfully!";
            } else {
                $response['message'] = "Error creating profile: " . pg_last_error($conn);
            }
        }
    } else {
        $response['message'] = "Error checking profile: " . pg_last_error($conn);
        echo json_encode($response);
        exit();
    }

    if (!empty($email)) {
        $updateEmailQuery = "UPDATE users SET email = $1 WHERE id = $2";
        pg_query_params($conn, $updateEmailQuery, array($email, $user_id));
    }

    pg_close($conn);

    $suggestion = generateWorkoutSuggestion($goal, $equipment, $activityLevel, $injuries, $age, $gender);
    $response['suggestion'] = $suggestion;

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();

} else {
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

function generateWorkoutSuggestion($goal, $equipment, $activityLevel, $injuries, $age, $gender) {
    $suggestion = [];

    switch ($goal) {
        case 'lose_weight':
            $suggestion['title'] = "Weight Loss Program";
            $suggestion['description'] = "A balanced program focusing on calorie deficit through cardio and strength training.";
            if ($equipment === "none") {
                $suggestion['workouts'] = [
                    "HIIT Bodyweight Circuit - 3-4 times per week",
                    "Walking/Jogging - 2-3 times per week",
                    "Active Recovery (stretching) - 1-2 times per week"
                ];
            } elseif ($equipment === "basic") {
                $suggestion['workouts'] = [
                    "Dumbbell Circuit Training - 3 times per week",
                    "Resistance Band HIIT - 2 times per week", 
                    "Cardio Session (moderate intensity) - 2-3 times per week"
                ];
            } else {
                $suggestion['workouts'] = [
                    "Full Body Strength Training - 2 times per week",
                    "HIIT or Circuit Training - 2 times per week",
                    "Steady State Cardio - 2 times per week"
                ];
            }
            break;
        case 'build_muscle':
            $suggestion['title'] = "Muscle Building Program";
            $suggestion['description'] = "A progressive resistance training program with adequate protein intake to build lean muscle.";
            if ($equipment === "none") {
                $suggestion['workouts'] = [
                    "Progressive Calisthenics - 4 times per week",
                    "Bodyweight Supersets - 2 times per week",
                    "Active Recovery - 1 time per week"
                ];
            } elseif ($equipment === "basic") {
                $suggestion['workouts'] = [
                    "Dumbbell Push Workout - 2 times per week",
                    "Dumbbell Pull Workout - 2 times per week",
                    "Legs & Core - 1-2 times per week"
                ];
            } else {
                $suggestion['workouts'] = [
                    "Upper Body Push (Chest/Shoulders/Triceps) - 2 times per week",
                    "Upper Body Pull (Back/Biceps) - 2 times per week",
                    "Lower Body - 2 times per week",
                    "Active Recovery - 1 time per week"
                ];
            }
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
        case 'endurance':
            $suggestion['title'] = "Endurance Building Program";
            $suggestion['description'] = "A program to improve cardiovascular health and stamina for longer physical activity.";
            if ($equipment === "none") {
                $suggestion['workouts'] = [
                    "Progressive Running/Walking - 3-4 times per week",
                    "Bodyweight Circuit (high rep) - 2 times per week",
                    "Long Duration Low Intensity Session - 1 time per week"
                ];
            } else {
                $suggestion['workouts'] = [
                    "Cardio Intervals - 2-3 times per week",
                    "Long Duration Cardio - 1-2 times per week",
                    "Cross Training - 2 times per week",
                    "Recovery Session - 1 time per week"
                ];
            }
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

    switch ($activityLevel) {
        case 'sedentary':
            $suggestion['intensity'] = "Start with low intensity and gradually build up.";
            $suggestion['frequency'] = "Begin with 3 sessions per week.";
            break;
        case 'light':
            $suggestion['intensity'] = "Begin with low to moderate intensity.";
            $suggestion['frequency'] = "Aim for 3-4 sessions per week.";
            break;
        case 'moderate':
            $suggestion['intensity'] = "Work at moderate intensity with some high-intensity intervals.";
            $suggestion['frequency'] = "Aim for 4-5 sessions per week.";
            break;
        case 'active':
            $suggestion['intensity'] = "Include moderate to high intensity with adequate recovery.";
            $suggestion['frequency'] = "5-6 sessions per week with recovery days.";
            break;
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