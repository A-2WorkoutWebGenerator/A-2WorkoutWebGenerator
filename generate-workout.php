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
$location = $input['location'] ?? 'gym';

$conn = getConnection();

$profile = null;
$profileRes = pg_query_params($conn, "SELECT * FROM user_profiles WHERE user_id = $1", [$user_id]);
if ($profileRes) {
    $profile = pg_fetch_assoc($profileRes);
}
$age = $profile['age'] ?? null;
$gender = $profile['gender'] ?? null;
$weight = $profile['weight'] ?? null;
$goal = $profile['goal'] ?? null;
$activity_level = $profile['activity_level'] ?? null;
$injuries = $profile['injuries'] ?? null;

$params = [
    $user_id, $muscle_group, $difficulty, $equipment, $duration_minutes, $location,
    $age, $weight, $goal, $activity_level, $injuries
];

$sql = "SELECT * FROM fitgen.generate_workout_for_user($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)";
$result = pg_query_params($conn, $sql, $params);

$workout = [];
if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        if (isset($row['muscle_groups'])) {
            if (is_string($row['muscle_groups'])) {
                $row['muscle_groups'] = json_decode($row['muscle_groups'], true);
            }
        }
        $workout[] = $row;
    }

    foreach ($workout as &$w) {
        if (isset($w['muscle_groups']) && !is_array($w['muscle_groups']) && !is_null($w['muscle_groups'])) {
            $w['muscle_groups'] = json_decode($w['muscle_groups'], true);
        }
    }
    unset($w);

    if (!empty($workout)) {
        $insertSql = "INSERT INTO user_workouts (user_id, workout) VALUES ($1, $2)";
        pg_query_params($conn, $insertSql, [
            $user_id,
            json_encode($workout, JSON_UNESCAPED_UNICODE)
        ]);
    }
    echo json_encode(['success' => true, 'workout' => $workout]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not generate workout.']);
}
pg_close($conn);
?>