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
    
    if (!$input || !isset($input['routine_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Routine ID is required']);
        exit;
    }
    
    $routineId = $input['routine_id'];
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $checkQuery = "SELECT id FROM saved_routines WHERE id = $1 AND user_id = $2";
    $checkResult = pg_query_params($conn, $checkQuery, [$routineId, $userId]);
    
    if (!$checkResult) {
        throw new Exception('Database query failed: ' . pg_last_error($conn));
    }
    
    if (pg_num_rows($checkResult) === 0) {
        pg_close($conn);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Routine not found']);
        exit;
    }
    $deleteQuery = "DELETE FROM saved_routines WHERE id = $1 AND user_id = $2";
    $deleteResult = pg_query_params($conn, $deleteQuery, [$routineId, $userId]);
    
    if (!$deleteResult) {
        throw new Exception('Failed to delete routine: ' . pg_last_error($conn));
    }
    
    $deletedRows = pg_affected_rows($deleteResult);
    
    pg_close($conn);
    
    if ($deletedRows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Routine removed successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Routine not found or already deleted'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error removing routine: " . $e->getMessage());
    
    if (isset($conn)) {
        pg_close($conn);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>