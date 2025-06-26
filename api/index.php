<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$apiPath = preg_replace('#^/api#', '', $path);

require_once __DIR__ . '/../exercise_functions.php';

switch (true) {

    case $apiPath === '/auth/login' && $method === 'POST':
        require __DIR__ . '/../login.php';
        break;
    case $apiPath === '/auth/register' && $method === 'POST':
        require __DIR__ . '/../register.php';
        break;
    case $apiPath === '/auth/logout' && $method === 'POST':
        require __DIR__ . '/../logout.php';
        break;

    case $apiPath === '/profile' && $method === 'GET':
        require __DIR__ . '/../get-profile.php';
        break;
    case $apiPath === '/profile' && $method === 'PUT':
        require __DIR__ . '/../submit-profile.php';
        break;
    case $apiPath === '/profile/picture' && $method === 'POST':
        require __DIR__ . '/../upload-profile-pic.php';
        break;

    case $apiPath === '/exercises' && $method === 'GET':
        get_exercises();
        break;
    case $apiPath === '/exercises' && $method === 'POST':
        add_exercise();
        break;
    case preg_match('#^/exercises/(\d+)$#', $apiPath, $m) && $method === 'GET':
        get_exercise($m[1]);
        break;
    case preg_match('#^/exercises/(\d+)$#', $apiPath, $m) && $method === 'PUT':
        update_exercise($m[1]);
        break;
    case preg_match('#^/exercises/(\d+)$#', $apiPath, $m) && $method === 'DELETE':
        delete_exercise($m[1]);
        break;

    case $apiPath === '/workouts/generate' && $method === 'POST':
        require __DIR__ . '/../generate-workout.php';
        break;
    case $apiPath === '/workouts/history' && $method === 'GET':
        require __DIR__ . '/../workout-suggestions.php';
        break;

    case $apiPath === '/statistics' && $method === 'GET':
        require __DIR__ . '/../get-statistics.php';
        break;

    case $apiPath === '/leaderboard' && $method === 'GET':
        require __DIR__ . '/../leaderboard.php';
        break;

    default:
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}