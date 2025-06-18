<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'email_config.php';
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

    $requiredFields = ['fullName', 'email', 'message'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    $fullName = trim(strip_tags($input['fullName']));
    $email = trim(strip_tags($input['email']));
    $message = trim(strip_tags($input['message']));
    if (strlen($fullName) > 100) {
        throw new Exception('Name is too long (max 100 characters)');
    }
    
    if (strlen($email) > 255) {
        throw new Exception('Email is too long (max 255 characters)');
    }
    
    if (strlen($message) > 5000) {
        throw new Exception('Message is too long (max 5000 characters)');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $spamCheckQuery = "SELECT COUNT(*) as count FROM contact_messages 
                       WHERE email = $1 AND created_at > NOW() - INTERVAL '1 hour'";
    $spamResult = pg_query_params($conn, $spamCheckQuery, [$email]);
    
    if ($spamResult) {
        $spamRow = pg_fetch_assoc($spamResult);
        if ($spamRow['count'] >= 3) {
            throw new Exception('Too many messages sent. Please wait before sending another message.');
        }
    }

    $query = "INSERT INTO contact_messages (full_name, email, message, ip_address, user_agent) 
              VALUES ($1, $2, $3, $4, $5) RETURNING id, created_at";
    
    $result = pg_query_params($conn, $query, [
        $fullName,
        $email, 
        $message,
        $ipAddress,
        $userAgent
    ]);

    if (!$result) {
        throw new Exception('Failed to save message: ' . pg_last_error($conn));
    }

    $row = pg_fetch_assoc($result);

    pg_close($conn);

    try {
        EmailConfig::sendAutoConfirmation($email, $fullName, $message);
    
        EmailConfig::notifyAdmin($fullName, $email, $message);
        
        error_log("Confirmation and notification emails sent successfully");
    } catch (Exception $emailError) {
        error_log("Email sending failed: " . $emailError->getMessage());
    }

    error_log("Contact message saved successfully - ID: " . $row['id'] . ", Email: " . $email);
    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent successfully! We will get back to you soon. Check your email for confirmation.',
        'data' => [
            'id' => $row['id'],
            'created_at' => $row['created_at']
        ]
    ]);

} catch (Exception $e) {
    error_log("Submit contact error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>