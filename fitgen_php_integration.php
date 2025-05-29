<?php
class FitGenDatabase {
    private $connection;
    private $lastError;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $host = "db";
        $port = "5432";
        $dbname = "fitgen";
        $username = "postgres";
        $password = "postgres";
        
        $connection_string = "host=$host port=$port dbname=$dbname user=$username password=$password";
        
        try {
            $this->connection = @pg_connect($connection_string);
            if (!$this->connection) {
                throw new Exception("Database connection failed: " . pg_last_error());
            }
            
            pg_query($this->connection, "SET search_path TO fitgen");
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    public function callPlpgsqlFunction($functionName, $params = [], $returnType = 'array') {
        try {
            $placeholders = [];
            for ($i = 1; $i <= count($params); $i++) {
                $placeholders[] = '$' . $i;
            }
            
            $query = "SELECT * FROM $functionName(" . implode(', ', $placeholders) . ")";
            
            error_log("Executing PL/pgSQL function: $query with params: " . json_encode($params));
            
            $result = pg_query_params($this->connection, $query, $params);
            
            if (!$result) {
                $error = pg_last_error($this->connection);
                error_log("PL/pgSQL function error: $error");
            
                $customError = $this->parsePostgresError($error);
                throw new Exception($customError);
            }
            
            if ($returnType === 'single') {
                return pg_fetch_assoc($result);
            } elseif ($returnType === 'json') {
                $rows = pg_fetch_all($result);
                return json_encode($rows);
            } else {
                return pg_fetch_all($result);
            }
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("PL/pgSQL function execution failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function parsePostgresError($error) {
        if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:)/s', $error, $matches)) {
            $customMessage = trim($matches[1]);
            $customMessage = preg_replace('/^(RAISE\s+EXCEPTION\s*:\s*|ERROR\s*:\s*)/i', '', $customMessage);
            
            return $customMessage;
        }
        
        if (strpos($error, 'unique_violation') !== false) {
            if (strpos($error, 'username') !== false) {
                return 'Username already exists. Please choose another one.';
            } elseif (strpos($error, 'email') !== false) {
                return 'Email already registered. Please use another email.';
            }
            return 'Duplicate entry found.';
        }
        
        if (strpos($error, 'foreign_key_violation') !== false) {
            return 'Referenced record does not exist.';
        }
        
        if (strpos($error, 'check_violation') !== false) {
            return 'Invalid data provided. Please check your input.';
        }
        
        if (strpos($error, 'not_null_violation') !== false) {
            return 'Required field is missing.';
        }
        
        return $error;
    }
    public function setUserContext($userId, $ipAddress = null, $userAgent = null) {
        try {
            if ($userId) {
                pg_query($this->connection, "SELECT set_config('app.current_user_id', '$userId', false)");
            }
            if ($ipAddress) {
                pg_query($this->connection, "SELECT set_config('app.client_ip', '$ipAddress', false)");
            }
            if ($userAgent) {
                $escapedAgent = pg_escape_string($this->connection, $userAgent);
                pg_query($this->connection, "SELECT set_config('app.user_agent', '$escapedAgent', false)");
            }
        } catch (Exception $e) {
            error_log("Failed to set user context: " . $e->getMessage());
        }
    }
    
    public function __destruct() {
        if ($this->connection) {
            pg_close($this->connection);
        }
    }
}

class UserRegistrationService {
    private $db;
    
    public function __construct() {
        $this->db = new FitGenDatabase();
    }
    
    public function registerUser($username, $email, $password) {
        try {
            $this->db->setUserContext(null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $result = $this->db->callPlpgsqlFunction(
                'register_user',
                [$username, $email, $hashedPassword],
                'single'
            );
            
            return [
                'success' => $result['success'] === 't',
                'message' => $result['message'],
                'user_id' => $result['user_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'user_id' => null
            ];
        }
    }
}

class UserProfileService {
    private $db;
    
    public function __construct() {
        $this->db = new FitGenDatabase();
    }
    
    public function createOrUpdateProfile($userId, $profileData) {
        try {
            $this->db->setUserContext($userId, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
            
            $result = $this->db->callPlpgsqlFunction(
                'create_user_profile',
                [
                    $userId,
                    $profileData['first_name'] ?? null,
                    $profileData['last_name'] ?? null,
                    $profileData['gender'] ?? null,
                    $profileData['age'] ?? null,
                    $profileData['goal'] ?? null,
                    $profileData['activity_level'] ?? null,
                    $profileData['injuries'] ?? null,
                    $profileData['equipment'] ?? 'none'
                ],
                'single'
            );
            
            return [
                'success' => $result['success'] === 't',
                'message' => $result['message'],
                'profile_id' => $result['profile_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'profile_id' => null
            ];
        }
    }
    
    public function getWorkoutRecommendations($userId, $limit = 5) {
        try {
            $this->db->setUserContext($userId);
            
            $recommendations = $this->db->callPlpgsqlFunction(
                'get_workout_recommendations',
                [$userId, $limit]
            );
            
            return [
                'success' => true,
                'recommendations' => $recommendations
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'recommendations' => []
            ];
        }
    }
}

class WorkoutRoutineService {
    private $db;
    
    public function __construct() {
        $this->db = new FitGenDatabase();
    }
    
    public function saveRoutine($userId, $routineId, $notes = null) {
        try {
            $this->db->setUserContext($userId, $_SERVER['REMOTE_ADDR'] ?? null);
            
            $result = $this->db->callPlpgsqlFunction(
                'save_user_routine',
                [$userId, $routineId, $notes],
                'single'
            );
            
            return [
                'success' => $result['success'] === 't',
                'message' => $result['message']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getUserStatistics($userId) {
        try {
            $this->db->setUserContext($userId);
            
            $stats = $this->db->callPlpgsqlFunction(
                'calculate_user_statistics',
                [$userId],
                'single'
            );
            
            return [
                'success' => true,
                'statistics' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'statistics' => null
            ];
        }
    }
    
    public function getLeaderboard($period = 'month', $metric = 'workouts', $limit = 10) {
        try {
            $leaderboard = $this->db->callPlpgsqlFunction(
                'get_user_leaderboard',
                [$period, $metric, $limit]
            );
            
            return [
                'success' => true,
                'leaderboard' => $leaderboard
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'leaderboard' => []
            ];
        }
    }
}

class ReportService {
    private $db;
    
    public function __construct() {
        $this->db = new FitGenDatabase();
    }
    
    public function generateUserReport($userId) {
        try {
            $this->db->setUserContext($userId);
            
            $conn = $this->db->getConnection();
            $result = pg_query_params($conn, "SELECT generate_user_report($1) as report", [$userId]);
            
            if (!$result) {
                throw new Exception("Failed to generate report: " . pg_last_error($conn));
            }
            
            $row = pg_fetch_assoc($result);
            $reportData = json_decode($row['report'], true);
            
            return [
                'success' => true,
                'report' => $reportData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'report' => null
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['SCRIPT_NAME']) === 'register.php') {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            throw new Exception("Missing required fields: username, email, password");
        }
        
        $registrationService = new UserRegistrationService();
        $result = $registrationService->registerUser(
            $input['username'],
            $input['email'],
            $input['password']
        );
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['SCRIPT_NAME']) === 'submit_profile_plpgsql.php') {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    try {
        $authToken = getBearerToken();
        if (!$authToken) {
            throw new Exception("Authentication required");
        }
        
        $userId = verifyAuthToken($authToken);
        if (!$userId) {
            throw new Exception("Invalid or expired token");
        }

        $profileData = [
            'first_name' => $_POST['first_name'] ?? null,
            'last_name' => $_POST['last_name'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'age' => !empty($_POST['age']) ? (int)$_POST['age'] : null,
            'goal' => $_POST['goal'] ?? null,
            'activity_level' => $_POST['activity_level'] ?? null,
            'injuries' => $_POST['injuries'] ?? null,
            'equipment' => $_POST['equipment'] ?? 'none'
        ];
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
            $profileData['profile_picture_path'] = handleProfilePictureUpload($_FILES['profile_pic']);
        }

        $profileService = new UserProfileService();
        $result = $profileService->createOrUpdateProfile($userId, $profileData);
        
        if ($result['success']) {
            $recommendations = $profileService->getWorkoutRecommendations($userId, 3);
            if ($recommendations['success']) {
                $result['recommendations'] = $recommendations['recommendations'];
            }
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['SCRIPT_NAME']) === 'save_routine_plpgsql.php') {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        $authToken = getBearerToken();
        if (!$authToken) {
            throw new Exception("Authentication required");
        }
        
        $userId = verifyAuthToken($authToken);
        if (!$userId) {
            throw new Exception("Invalid or expired token");
        }
        
        if (empty($input['routine_id'])) {
            throw new Exception("Routine ID is required");
        }
        
        $routineService = new WorkoutRoutineService();
        $result = $routineService->saveRoutine(
            $userId,
            $input['routine_id'],
            $input['notes'] ?? null
        );
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && basename($_SERVER['SCRIPT_NAME']) === 'user_statistics_plpgsql.php') {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    try {
        $authToken = getBearerToken();
        if (!$authToken) {
            throw new Exception("Authentication required");
        }
        
        $userId = verifyAuthToken($authToken);
        if (!$userId) {
            throw new Exception("Invalid or expired token");
        }
        
        $routineService = new WorkoutRoutineService();
        $result = $routineService->getUserStatistics($userId);
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && basename($_SERVER['SCRIPT_NAME']) === 'leaderboard_plpgsql.php') {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    try {
        $period = $_GET['period'] ?? 'month';
        $metric = $_GET['metric'] ?? 'workouts';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $routineService = new WorkoutRoutineService();
        $result = $routineService->getLeaderboard($period, $metric, $limit);
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && basename($_SERVER['SCRIPT_NAME']) === 'user_report_plpgsql.php') {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    
    try {
        $authToken = getBearerToken();
        if (!$authToken) {
            throw new Exception("Authentication required");
        }
        
        $userId = verifyAuthToken($authToken);
        if (!$userId) {
            throw new Exception("Invalid or expired token");
        }
        
        $reportService = new ReportService();
        $result = $reportService->generateUserReport($userId);
        
        if (isset($_GET['format']) && $_GET['format'] === 'rss') {
            header("Content-Type: application/rss+xml; charset=UTF-8");
            echo generateRSSFeed($result['report']);
            return;
        }

        if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
            header("Content-Type: application/pdf");
            header("Content-Disposition: attachment; filename=user_report.pdf");
            //to implement pdf
            echo "PDF generation not implemented yet";
            return;
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}


function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

function verifyAuthToken($token) {
    try {
        $db = new FitGenDatabase();
        $conn = $db->getConnection();
        
        $query = "SELECT user_id FROM auth_tokens WHERE token = $1 AND expires_at > NOW()";
        $result = pg_query_params($conn, $query, [$token]);
        
        if ($result && pg_num_rows($result) > 0) {
            $row = pg_fetch_assoc($result);
            return $row['user_id'];
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
}

function handleProfilePictureUpload($file) {
    $allowed = ["jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png"];
    $filename = $file["name"];
    $filetype = $file["type"];
    $filesize = $file["size"];
    
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (!array_key_exists($ext, $allowed)) {
        throw new Exception("Invalid file format. Please use JPG, JPEG, GIF or PNG.");
    }
    
    $maxsize = 5 * 1024 * 1024;
    if ($filesize > $maxsize) {
        throw new Exception("File size exceeds the limit (5MB).");
    }
    
    if (in_array($filetype, $allowed)) {
        $uploadDir = "uploads/profile_pics/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $newFilename = uniqid() . "-" . $filename;
        $uploadPath = $uploadDir . $newFilename;
        
        if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
            return $uploadPath;
        } else {
            throw new Exception("Error uploading file.");
        }
    } else {
        throw new Exception("Invalid file type.");
    }
}

function generateRSSFeed($reportData) {
    $rss = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $rss .= '<rss version="2.0">' . "\n";
    $rss .= '<channel>' . "\n";
    $rss .= '<title>FitGen User Statistics</title>' . "\n";
    $rss .= '<description>Personal fitness statistics and progress</description>' . "\n";
    $rss .= '<link>https://fitgen.app</link>' . "\n";
    
    if (isset($reportData['statistics'])) {
        $stats = $reportData['statistics'];
        $rss .= '<item>' . "\n";
        $rss .= '<title>Weekly Statistics</title>' . "\n";
        $rss .= '<description>Workouts: ' . ($stats['workouts_this_week'] ?? 0) . ', Total Minutes: ' . ($stats['total_minutes'] ?? 0) . '</description>' . "\n";
        $rss .= '<pubDate>' . date('r') . '</pubDate>' . "\n";
        $rss .= '</item>' . "\n";
    }
    
    $rss .= '</channel>' . "\n";
    $rss .= '</rss>';
    
    return $rss;
}

if (basename($_SERVER['SCRIPT_NAME']) === 'test_plpgsql_integration.php') {
    echo "<h1>Testing PL/pgSQL Integration</h1>";
    
    try {
        echo "<h2>Testing User Registration</h2>";
        $registrationService = new UserRegistrationService();
        $regResult = $registrationService->registerUser('testplpgsql', 'test@plpgsql.com', 'password123');
        echo "<pre>" . json_encode($regResult, JSON_PRETTY_PRINT) . "</pre>";
        
        if ($regResult['success']) {
            $userId = $regResult['user_id'];
            
            echo "<h2>Testing Profile Creation</h2>";
            $profileService = new UserProfileService();
            $profileResult = $profileService->createOrUpdateProfile($userId, [
                'first_name' => 'Test',
                'last_name' => 'PgSQL',
                'gender' => 'male',
                'age' => 25,
                'goal' => 'build_muscle',
                'activity_level' => 'moderate',
                'equipment' => 'basic'
            ]);
            echo "<pre>" . json_encode($profileResult, JSON_PRETTY_PRINT) . "</pre>";
            
            echo "<h2>Testing Workout Recommendations</h2>";
            $recommendations = $profileService->getWorkoutRecommendations($userId, 3);
            echo "<pre>" . json_encode($recommendations, JSON_PRETTY_PRINT) . "</pre>";
            echo "<h2>Testing User Statistics</h2>";
            $routineService = new WorkoutRoutineService();
            $stats = $routineService->getUserStatistics($userId);
            echo "<pre>" . json_encode($stats, JSON_PRETTY_PRINT) . "</pre>";
            
            echo "<h2>Testing Leaderboard</h2>";
            $leaderboard = $routineService->getLeaderboard('all', 'workouts', 5);
            echo "<pre>" . json_encode($leaderboard, JSON_PRETTY_PRINT) . "</pre>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<p><strong>PL/pgSQL Integration Testing Complete!</strong></p>";
}

?>