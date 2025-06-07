<?php
require_once 'db_connection.php';
require_once 'jwt_utils.php';
require_once 'get-statistics.inc.php';

header("Content-Type: application/json");
$token = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

try {
    $jwt = decode_jwt($token);
    $user_id = $jwt->sub ?? null;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'No user ID found']);
    exit;
}

$conn = getConnection();

try {
    $stats = get_user_statistics($conn, $user_id);
    if (!$stats) {
        throw new Exception('No statistics found');
    }
    echo json_encode([
        'success' => true,
        'statistics' => $stats
    ]);
} catch (Exception $e) {
    error_log("Statistics error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching statistics: ' . $e->getMessage()
    ]);
} finally {
    if ($conn) {
        pg_close($conn);
    }
}
?>