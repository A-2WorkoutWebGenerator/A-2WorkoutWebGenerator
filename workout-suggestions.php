<?php
require_once 'db_connection.php';
require_once 'jwt_utils.php';

header("Content-Type: application/json");

$token = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token!']);
    exit;
}
try {
    $jwt = decode_jwt($token);
    $user_id = $jwt->sub ?? null;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid token!']);
    exit;
}
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'No user id!']);
    exit;
}

$conn = getConnection();
$sql = "SELECT id, generated_at, workout FROM user_workouts WHERE user_id = $1 ORDER BY generated_at DESC";
$result = pg_query_params($conn, $sql, [$user_id]);

$workout_suggestions = [];
if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $workoutArr = json_decode($row['workout'], true); 
        $workout_suggestions[] = [
            "id" => $row["id"],
            "generated_at" => $row["generated_at"],
            "exercises" => $workoutArr
        ];
    }
    echo json_encode(['success' => true, 'suggestions' => $workout_suggestions]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not fetch saved workouts.']);
}
pg_close($conn);
?>