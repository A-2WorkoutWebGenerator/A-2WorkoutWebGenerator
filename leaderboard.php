<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connection.php';
require_once 'leaderboard_functions.php';

try {
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $filters = [
        'age_group' => $_GET['age_group'] ?? null,
        'gender' => $_GET['gender'] ?? null,
        'goal' => $_GET['goal'] ?? null,
        'limit' => $_GET['limit'] ?? 25
    ];
    $format = $_GET['format'] ?? 'json';

    $champions = getChampionsSimple($conn, $filters);
    $stats = getSimpleStats($conn);

    pg_close($conn);

    if ($format === 'pdf' || $format === 'html') {
        $html = generatePDF($champions, $filters, $stats);
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="fitgen_champions_' . date('Y-m-d_H-i') . '.html"');
        echo $html;
    } else {
        echo json_encode([
            'success' => true,
            'data' => $champions,
            'stats' => $stats,
            'filters_applied' => array_filter($filters),
            'total_count' => count($champions),
            'generated_at' => date('c')
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>