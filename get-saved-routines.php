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
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $query = "
        SELECT 
            id, 
            name, 
            difficulty, 
            description, 
            duration, 
            frequency, 
            icon, 
            exercises, 
            video_url, 
            category, 
            saved_at,
            -- Adaugă un nume frumos pentru categorie
            CASE 
                WHEN category = 'kinetotherapy' THEN 'Kinetotherapy'
                WHEN category = 'physiotherapy' THEN 'Physiotherapy'
                WHEN category = 'football' THEN 'Football'
                WHEN category = 'basketball' THEN 'Basketball'
                WHEN category = 'tennis' THEN 'Tennis'
                WHEN category = 'swimming' THEN 'Swimming'
                ELSE 'General Workout'
            END as category_display
        FROM saved_routines 
        WHERE user_id = $1 
        ORDER BY saved_at DESC
    ";
    
    $result = pg_query_params($conn, $query, [$userId]);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . pg_last_error($conn));
    }
    
    $routines = [];
    $categoryCounts = [];
    
    while ($row = pg_fetch_assoc($result)) {
        $row['exercises'] = json_decode($row['exercises'], true) ?: [];
        $category = $row['category'];
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = 0;
        }
        $categoryCounts[$category]++;
        
        $routines[] = $row;
    }
    
    $totalRoutines = count($routines);
    $uniqueCategories = count($categoryCounts);
    
    pg_close($conn);
    
    echo json_encode([
        'success' => true,
        'routines' => $routines,
        'count' => $totalRoutines,
        'categories' => array_keys($categoryCounts),
        'category_counts' => $categoryCounts,
        'stats' => [
            'total_routines' => $totalRoutines,
            'unique_categories' => $uniqueCategories,
            'most_saved_category' => $totalRoutines > 0 ? array_keys($categoryCounts, max($categoryCounts))[0] : null
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching saved routines: " . $e->getMessage());
    
    if (isset($conn)) {
        pg_close($conn);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>