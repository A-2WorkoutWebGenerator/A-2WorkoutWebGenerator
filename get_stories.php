<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getConnection() {
    $host = "database-1.cpak6uiam1q1.eu-north-1.rds.amazonaws.com";
    $port = "5432";
    $dbname = "postgres";
    $username = "postgres";
    $password = "postgres";
    
    $connection_string = "host=$host port=$port dbname=$dbname user=$username password=$password";
    
    error_log("Attempting to connect to PostgreSQL: $host:$port/$dbname");
    
    $conn = @pg_connect($connection_string);
    
    if (!$conn) {
        error_log("Database connection failed: " . pg_last_error());
        return false;
    }
    
    $schemaResult = pg_query($conn, "SET search_path TO fitgen, public");
    if (!$schemaResult) {
        error_log("Failed to set search_path: " . pg_last_error($conn));
        return false;
    }
    
    error_log("Successfully connected to PostgreSQL database");
    return $conn;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    if ($limit > 100) $limit = 100;
    if ($limit < 1) $limit = 10;

    $query = "SELECT id, user_name, achievement, story_text, created_at 
              FROM success_stories 
              WHERE is_approved = true 
              ORDER BY created_at DESC 
              LIMIT $1 OFFSET $2";
    
    $result = pg_query_params($conn, $query, [$limit, $offset]);
    
    if (!$result) {
        throw new Exception('Failed to fetch stories: ' . pg_last_error($conn));
    }

    $stories = [];
    while ($row = pg_fetch_assoc($result)) {
        $createdAt = new DateTime($row['created_at']);
        $formattedDate = $createdAt->format('F Y');
        
        $stories[] = [
            'id' => (int)$row['id'],
            'userName' => htmlspecialchars($row['user_name']),
            'achievement' => htmlspecialchars($row['achievement']),
            'storyText' => htmlspecialchars($row['story_text']),
            'createdAt' => $row['created_at'],
            'formattedDate' => $formattedDate
        ];
    }

    $countQuery = "SELECT COUNT(*) as total FROM success_stories WHERE is_approved = true";
    $countResult = pg_query($conn, $countQuery);
    $totalCount = 0;
    
    if ($countResult) {
        $countRow = pg_fetch_assoc($countResult);
        $totalCount = (int)$countRow['total'];
    }

    pg_close($conn);

    echo json_encode([
        'success' => true,
        'data' => $stories,
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => ($offset + $limit) < $totalCount
        ]
    ]);

} catch (Exception $e) {
    error_log("Get stories error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>