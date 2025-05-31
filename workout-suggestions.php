<?php
require_once 'db_connection.php';
require_once 'jwt_utils.php';

header("Content-Type: application/json");

function getBearerToken() {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                    return $matches[1];
                }
            }
        }
    }
    if (isset($_POST['auth_token'])) return $_POST['auth_token'];
    return null;
}

$token = getBearerToken();
if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token missing.']);
    exit();
}
$jwt = decode_jwt($token);
$user_id = $jwt->sub ?? null;

$conn = getConnection();
if (!$user_id || !$conn) {
    echo json_encode(['success' => false, 'message' => 'DB or user error.']);
    exit();
}

$query = "SELECT generated_at, suggestion FROM workout_suggestions WHERE user_id = $1 ORDER BY generated_at DESC";
$result = pg_query_params($conn, $query, [$user_id]);
$suggestions = [];
while ($row = pg_fetch_assoc($result)) {
    $suggestions[] = [
        'generated_at' => $row['generated_at'],
        'suggestion' => json_decode($row['suggestion'], true)
    ];
}
echo json_encode(['success' => true, 'suggestions' => $suggestions]);
?>