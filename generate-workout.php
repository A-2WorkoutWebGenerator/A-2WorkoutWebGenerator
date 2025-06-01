<?php
require_once 'db_connection.php';
require_once 'jwt_utils.php';

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

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

$muscle_group = $input['muscle_group'] ?? null;
$difficulty   = $input['intensity'] ?? null; 
$equipment    = $input['equipment'] ?? null;
$duration_minutes = $input['duration'] ?? 30; 

$conn = getConnection();
$sql = "SELECT * FROM fitgen.generate_workout_for_user($1, $2, $3, $4, $5)";
$result = pg_query_params($conn, $sql, [
    $user_id, $muscle_group, $difficulty, $equipment, $duration_minutes
]);

$workout = [];
if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        if (isset($row['muscle_groups']) && is_string($row['muscle_groups'])) {
            $row['muscle_groups'] = json_decode($row['muscle_groups']);
        }
        $workout[] = $row;
    }
    if (!empty($workout)) {
        $insertSql = "INSERT INTO user_workouts (user_id, workout) VALUES ($1, $2)";
        pg_query_params($conn, $insertSql, [
            $user_id,
            json_encode($workout)
        ]);
    }
    echo json_encode(['success' => true, 'workout' => $workout]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not generate workout.']);
}
pg_close($conn);
?>