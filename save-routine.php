<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'db_connection.php';
require_once 'jwt_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$token = substr($authHeader, 7);

try {
    $decoded = decode_jwt($token);
    $userId = $decoded->sub;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    $routineName = $input['name'] ?? '';
    $difficulty = $input['difficulty'] ?? '';
    $description = $input['description'] ?? '';
    $duration = $input['duration'] ?? '';
    $frequency = $input['frequency'] ?? '';
    $icon = $input['icon'] ?? '';
    $exercises = $input['exercises'] ?? [];
    $videoUrl = $input['video_url'] ?? '';
    $category = $input['category'] ?? 'general';
    
    if (empty($routineName)) {
        echo json_encode(['success' => false, 'message' => 'Routine name is required']);
        exit;
    }
    
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    $checkQuery = "SELECT id FROM saved_routines WHERE user_id = $1 AND name = $2";
    $checkResult = pg_query_params($conn, $checkQuery, [$userId, $routineName]);
    
    if (!$checkResult) {
        throw new Exception('Database query failed: ' . pg_last_error($conn));
    }
    
    if (pg_num_rows($checkResult) > 0) {
        pg_close($conn);
        echo json_encode(['success' => false, 'message' => 'Routine already saved']);
        exit;
    }

    $insertQuery = "
        INSERT INTO saved_routines 
        (user_id, name, difficulty, description, duration, frequency, icon, exercises, video_url, category, saved_at) 
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, NOW())
        RETURNING id
    ";
    
    $exercisesJson = json_encode($exercises);
    
    $insertResult = pg_query_params($conn, $insertQuery, [
        $userId, 
        $routineName, 
        $difficulty, 
        $description, 
        $duration, 
        $frequency, 
        $icon, 
        $exercisesJson, 
        $videoUrl, 
        $category
    ]);
    
    if (!$insertResult) {
        throw new Exception('Failed to save routine: ' . pg_last_error($conn));
    }
    
    $row = pg_fetch_assoc($insertResult);
    $routineId = $row['id'];
    
    pg_close($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Routine saved successfully',
        'routine_id' => $routineId
    ]);
    
} catch (Exception $e) {
    error_log("Error saving routine: " . $e->getMessage());
    
    if (isset($conn)) {
        pg_close($conn);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>