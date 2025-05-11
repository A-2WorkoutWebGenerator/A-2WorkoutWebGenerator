<?php
// Activăm afișarea erorilor (dezactivă în producție)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includem conexiunea la baza de date
require_once 'db_connection.php';

// Log pentru debugging
error_log("SUBMIT PROFILE REQUEST RECEIVED: " . json_encode($_POST));

// Funcție pentru extragerea token-ului din diverse surse
function getBearerToken() {
    // Verificăm dacă există token în POST
    if (isset($_POST['auth_token']) && !empty($_POST['auth_token'])) {
        error_log("Found auth_token in POST: " . $_POST['auth_token']);
        return $_POST['auth_token'];
    }
    
    // Obținem headerele
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    }
    
    // Verificăm headerul de autorizare
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
    
    // Verificăm variabilele server
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
    
    return null;
}

// Funcția pentru verificarea token-ului de autentificare
function verifyAuthToken($token) {
    error_log("Verifying token: " . $token);
    
    $conn = getConnection();
    if ($conn === false) {
        error_log("Database connection failed in verifyAuthToken");
        return false;
    }
    
    // Verifică dacă token-ul există și nu a expirat
    $query = "SELECT user_id FROM auth_tokens WHERE token = $1 AND expires_at > NOW()";
    
    error_log("Running token query with token: " . $token);
    $result = pg_query_params($conn, $query, array($token));
    
    if ($result) {
        $numRows = pg_num_rows($result);
        error_log("Token query returned $numRows rows");
        
        if ($numRows > 0) {
            $row = pg_fetch_assoc($result);
            $userId = $row['user_id'];
            error_log("Token verified successfully. User ID: " . $userId);
            return $userId;
        } else {
            error_log("Token not found in database or expired");
        }
    } else {
        error_log("Token query failed: " . pg_last_error($conn));
    }
    
    return false;
}

// Inițializăm răspunsul
$response = array();
$response['success'] = false;

// Procesăm formularul doar dacă este o cerere POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Obținem token-ul de autentificare
    $auth_token = getBearerToken();
    error_log("Auth token extracted: " . ($auth_token ? $auth_token : "none"));
    
    if (!$auth_token) {
        // Fără autentificare, nu putem continua
        $response['message'] = "Authentication required. Please log in.";
        echo json_encode($response);
        exit();
    }
    
    // Verifică token-ul și obține user_id
    $user_id = verifyAuthToken($auth_token);
    if (!$user_id) {
        $response['message'] = "Invalid or expired token. Please log in again.";
        echo json_encode($response);
        exit();
    }
    
    // Log pentru debugging
    error_log("Token verified successfully. User ID: " . $user_id);
    
    // Acum avem user_id, putem procesa datele formularului
    $firstName = isset($_POST["first_name"]) ? htmlspecialchars($_POST["first_name"]) : '';
    $lastName = isset($_POST["last_name"]) ? htmlspecialchars($_POST["last_name"]) : '';
    $email = isset($_POST["email"]) ? htmlspecialchars($_POST["email"]) : '';
    $gender = isset($_POST["gender"]) ? htmlspecialchars($_POST["gender"]) : '';
    $age = isset($_POST["age"]) ? (int) $_POST["age"] : null;
    $goal = isset($_POST["goal"]) ? htmlspecialchars($_POST["goal"]) : '';
    $activityLevel = isset($_POST["activity_level"]) ? htmlspecialchars($_POST["activity_level"]) : '';
    $injuries = isset($_POST["injuries"]) ? htmlspecialchars($_POST["injuries"]) : '';
    $equipment = isset($_POST["equipment"]) ? htmlspecialchars($_POST["equipment"]) : '';
    
    // Procesează imaginea profilului dacă a fost încărcată
    $profilePicturePath = null;
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0) {
        $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
        $filename = $_FILES["profile_pic"]["name"];
        $filetype = $_FILES["profile_pic"]["type"];
        $filesize = $_FILES["profile_pic"]["size"];
        
        // Verificăm extensia
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) {
            $response['message'] = "Invalid file format. Please use JPG, JPEG, GIF or PNG.";
            echo json_encode($response);
            exit();
        }
        
        // Verificăm dimensiunea (max 5MB)
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $response['message'] = "File size exceeds the limit (5MB).";
            echo json_encode($response);
            exit();
        }
        
        // Verificăm tipul MIME
        if (in_array($filetype, $allowed)) {
            // Creăm directorul de upload dacă nu există
            $uploadDir = "uploads/profile_pics/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generăm un nume unic pentru fișier
            $newFilename = uniqid() . "-" . $filename;
            $uploadPath = $uploadDir . $newFilename;
            
            // Salvăm fișierul
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
    
    // Conectăm la baza de date
    $conn = getConnection();
    if ($conn === false) {
        $response['message'] = "Database connection failed.";
        echo json_encode($response);
        exit();
    }
    
    // Verificăm dacă tabelul user_profiles există
    $checkTableQuery = "SELECT EXISTS (SELECT 1 FROM pg_tables WHERE tablename = 'user_profiles')";
    $tableResult = pg_query($conn, $checkTableQuery);
    
    if (!$tableResult || pg_fetch_result($tableResult, 0, 0) === 'f') {
        // Tabelul nu există, îl creăm
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
    
    // Verificăm dacă utilizatorul are deja un profil
    $checkQuery = "SELECT id FROM user_profiles WHERE user_id = $1";
    $result = pg_query_params($conn, $checkQuery, array($user_id));
    
    if ($result) {
        if (pg_num_rows($result) > 0) {
            // Profilul există, actualizăm datele
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
            
            // Adăugăm calea imaginii numai dacă a fost încărcată una nouă
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
            // Profilul nu există, creăm unul nou
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
    
    // Actualizăm și email-ul utilizatorului dacă e furnizat
    if (!empty($email)) {
        $updateEmailQuery = "UPDATE users SET email = $1 WHERE id = $2";
        pg_query_params($conn, $updateEmailQuery, array($email, $user_id));
    }
    
    // Închidem conexiunea
    pg_close($conn);
    
    // Generăm recomandarea de antrenament bazată pe profilul utilizatorului
    $suggestion = generateWorkoutSuggestion($goal, $equipment, $activityLevel, $injuries, $age, $gender);
    $response['suggestion'] = $suggestion;
    
    // Dacă cererea a fost făcută prin AJAX, returnăm un JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
        exit();
    } else {
        // Dacă a fost un submit normal, afișăm o pagină cu rezultatele
        displayResultPage($firstName, $lastName, $response['message'], $suggestion);
    }
} else {
    // Nu este o cerere POST
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
}

/**
 * Generează o recomandare de antrenament bazată pe profilul utilizatorului
 */
function generateWorkoutSuggestion($goal, $equipment, $activityLevel, $injuries, $age, $gender) {
    $suggestion = [];
    
    // Recomandare de bază în funcție de obiectiv
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
    
    // Ajustează în funcție de nivelul de activitate
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
    
    // Ajustează pentru accidentări/condiții medicale
    if (!empty($injuries)) {
        $suggestion['caution'] = "Due to your reported injuries/conditions, please take the following precautions: 
        1. Start slowly and focus on proper form
        2. Consider consulting a physical therapist or trainer
        3. Modify exercises as needed to avoid pain
        4. Pay attention to pain vs. discomfort";
    }
    
    // Ajustează pentru vârstă
    if ($age < 18) {
        $suggestion['age_note'] = "For younger athletes, focus on proper technique, variety, and fun rather than intense specialization.";
    } elseif ($age > 60) {
        $suggestion['age_note'] = "Focus on functional movement, balance exercises, and joint-friendly activities.";
    }
    
    return $suggestion;
}

/**
 * Afișează pagina cu rezultatele formularului
 */
function displayResultPage($firstName, $lastName, $message, $suggestion) {
    $fullName = $firstName . ' ' . $lastName;
    
    $workoutList = '';
    if (isset($suggestion['workouts'])) {
        foreach ($suggestion['workouts'] as $workout) {
            $workoutList .= "<li>{$workout}</li>";
        }
    }
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Profile Saved - FitGen</title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <link rel='stylesheet' href='profile.css'>
        <style>
            .result-container {
                max-width: 800px;
                margin: 50px auto;
                padding: 30px;
                background: linear-gradient(135deg, #ffffff, #f0fff4);
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            }
            
            .success-message {
                padding: 15px;
                background-color: rgba(46, 204, 113, 0.15);
                color: #2ecc71;
                border: 1px solid #2ecc71;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            
            .back-button {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                transition: all 0.3s ease;
            }
            
            .back-button:hover {
                background-color: #2980b9;
                transform: translateY(-2px);
            }
            
            .workout-plan {
                margin-top: 30px;
                padding: 20px;
                background-color: white;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            }
            
            .workout-plan h3 {
                color: #2ecc71;
                margin-bottom: 10px;
            }
            
            .workout-plan ul {
                margin-top: 15px;
                padding-left: 20px;
            }
            
            .workout-plan li {
                margin-bottom: 10px;
            }
            
            .note {
                margin-top: 20px;
                padding: 15px;
                background-color: #f9f9f9;
                border-left: 4px solid #3498db;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class='result-container'>
            <div class='success-message'>
                {$message}
            </div>
            
            <h2>Thank you, {$fullName}!</h2>
            <p>Your profile has been updated successfully. Based on your profile information, we've created a personalized workout plan for you.</p>
            
            <div class='workout-plan'>
                <h3>" . (isset($suggestion['title']) ? $suggestion['title'] : 'Your Workout Plan') . "</h3>
                <p>" . (isset($suggestion['description']) ? $suggestion['description'] : '') . "</p>
                
                <h4>Recommended Workouts:</h4>
                <ul>
                    {$workoutList}
                </ul>
                
                " . (isset($suggestion['intensity']) ? "<p><strong>Intensity:</strong> {$suggestion['intensity']}</p>" : '') . "
                " . (isset($suggestion['frequency']) ? "<p><strong>Frequency:</strong> {$suggestion['frequency']}</p>" : '') . "
                
                " . (isset($suggestion['caution']) ? "<div class='note'><strong>Important:</strong> {$suggestion['caution']}</div>" : '') . "
                " . (isset($suggestion['age_note']) ? "<div class='note'>{$suggestion['age_note']}</div>" : '') . "
            </div>
            
            <a href='profile.html' class='back-button'>Back to Profile</a>
        </div>
    </body>
    </html>";
}
?>