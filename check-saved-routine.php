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
    echo json_encode(['success' => true, 'saved_routines' => []]);
    exit;
}

$token = substr($authHeader, 7);

try {
    $decoded = decode_jwt($token);
    $userId = $decoded->sub;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['routine_names']) || !is_array($input['routine_names'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid routine names array']);
        exit;
    }
    
    $routineNames = $input['routine_names'];
    
    if (empty($routineNames)) {
        echo json_encode(['success' => true, 'saved_routines' => []]);
        exit;
    }

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $placeholders = [];
    $params = [$userId];
    
    for ($i = 0; $i < count($routineNames); $i++) {
        $placeholders[] = '$' . ($i + 2);
        $params[] = $routineNames[$i];
    }
    
    $placeholdersStr = implode(',', $placeholders);
    
    $query = "
        SELECT name 
        FROM saved_routines 
        WHERE user_id = $1 AND name IN ($placeholdersStr)
    ";
    
    $result = pg_query_params($conn, $query, $params);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . pg_last_error($conn));
    }
    
    $savedRoutines = [];
    while ($row = pg_fetch_assoc($result)) {
        $savedRoutines[] = $row['name'];
    }
    
    pg_close($conn);
    
    echo json_encode([
        'success' => true,
        'saved_routines' => $savedRoutines
    ]);
    
} catch (Exception $e) {
    error_log("Error checking saved routines: " . $e->getMessage());
    
    if (isset($conn)) {
        pg_close($conn);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>