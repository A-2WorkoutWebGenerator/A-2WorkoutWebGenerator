<?php
require_once "db_connection.php";
require_once "jwt_utils.php";
header("Content-Type: application/json");

$headers = getallheaders();
if (!isset($headers["Authorization"])) {
    echo json_encode(["success" => false, "message" => "Missing token"]);
    exit();
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    echo json_encode(["success" => false, "message" => "Invalid token"]);
    exit();
}
$jwt = decode_jwt($matches[1]);
$user_id = $jwt->sub ?? null;

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "Invalid JWT"]);
    exit();
}

$conn = getConnection();
$res = pg_query_params($conn, "SELECT rss_token FROM users WHERE id = $1", [$user_id]);
$row = pg_fetch_assoc($res);

if (!$row || !$row['rss_token']) {
    echo json_encode(["success" => false, "message" => "No RSS token"]);
    exit();
}

echo json_encode([
    "success" => true,
    "rss_link" => "http://localhost:8081/rss.php?token=" . $row['rss_token']
]);
?>