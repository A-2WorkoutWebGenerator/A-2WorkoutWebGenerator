<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getConnection() {
    $host = "database-1.cpak6uiam1q1.eu-north-1.rds.amazonaws.com";
    $port = "5432";
    $dbname = "postgres";
    $username = "postgres";
    $password = "postgres";
    
    $connection_string = "host=$host port=$port dbname=$dbname user=$username password=$password";
    
    error_log("Attempting to connect to PostgreSQL: $host:$port/$dbname");
    
    $conn = @pg_connect($connection_string);
    
    if (!$conn) {
        error_log("Database connection failed: " . pg_last_error());
        return false;
    }
    
    $schemaResult = pg_query($conn, "SET search_path TO fitgen, public");
    if (!$schemaResult) {
        error_log("Failed to set search_path: " . pg_last_error($conn));
        return false;
    }
    
    error_log("Successfully connected to PostgreSQL database");
    return $conn;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }

    $requiredFields = ['userName', 'achievement', 'storyText'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    $userName = trim(strip_tags($input['userName']));
    $achievement = trim(strip_tags($input['achievement']));
    $storyText = trim(strip_tags($input['storyText']));
    
    if (strlen($userName) > 100) {
        throw new Exception('Name is too long (max 100 characters)');
    }
    
    if (strlen($achievement) > 255) {
        throw new Exception('Achievement is too long (max 255 characters)');
    }
    
    if (strlen($storyText) > 2000) {
        throw new Exception('Story is too long (max 2000 characters)');
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    $query = "INSERT INTO success_stories (user_name, achievement, story_text, ip_address, user_agent, is_approved)
              VALUES ($1, $2, $3, $4, $5, NULL) RETURNING id, created_at";
    
    $result = pg_query_params($conn, $query, [
        $userName,
        $achievement, 
        $storyText,
        $ipAddress,
        $userAgent
    ]);

    if (!$result) {
        throw new Exception('Failed to save story: ' . pg_last_error($conn));
    }

    $row = pg_fetch_assoc($result);

    pg_close($conn);
    error_log("New success story submitted by: $userName - waiting for admin approval");
    
    echo json_encode([
        'success' => true,
        'message' => 'Story submitted successfully! It will be reviewed by our team before being published.',
        'data' => [
            'id' => $row['id'],
            'created_at' => $row['created_at']
        ]
    ]);

} catch (Exception $e) {
    error_log("Submit story error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>