<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getConnection() {
    $host = "db";
    $port = "5432";
    $dbname = "fitgen";
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
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        $query = "SELECT 
                    id, 
                    user_name, 
                    achievement, 
                    story_text, 
                    created_at,
                    is_approved,
                    rejection_reason,
                    CASE 
                        WHEN is_approved IS NULL THEN 'pending'
                        WHEN is_approved = true THEN 'approved'
                        WHEN is_approved = false THEN 'rejected'
                    END as status
                  FROM success_stories 
                  ORDER BY 
                    CASE WHEN is_approved IS NULL THEN 0 ELSE 1 END,
                    created_at DESC";
        
        $result = pg_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Failed to fetch stories: ' . pg_last_error($conn));
        }
        
        $stories = [];
        while ($row = pg_fetch_assoc($result)) {
            $stories[] = [
                'id' => (int)$row['id'],
                'user_name' => htmlspecialchars($row['user_name']),
                'achievement' => htmlspecialchars($row['achievement']),
                'story_text' => htmlspecialchars($row['story_text']),
                'created_at' => $row['created_at'],
                'status' => $row['status'],
                'rejection_reason' => $row['rejection_reason'] ? htmlspecialchars($row['rejection_reason']) : null
            ];
        }
        
        pg_close($conn);
        
        echo json_encode([
            'success' => true,
            'data' => $stories
        ]);
        
    } catch (Exception $e) {
        error_log("Get admin stories error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['status'])) {
            throw new Exception('Missing required fields: id and status');
        }
        
        $storyId = (int)$input['id'];
        $status = $input['status'];
        $rejectionReason = isset($input['rejection_reason']) ? $input['rejection_reason'] : null;
        
        if (!in_array($status, ['approved', 'rejected', 'pending'])) {
            throw new Exception('Invalid status. Must be: approved, rejected, or pending');
        }
        
        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        $isApproved = null;
        if ($status === 'approved') {
            $isApproved = 'true';
        } elseif ($status === 'rejected') {
            $isApproved = 'false';
        }
        
        $query = "UPDATE success_stories 
                  SET is_approved = " . ($isApproved === null ? 'NULL' : $isApproved) . ",
                      rejection_reason = $1
                  WHERE id = $2";
        
        $result = pg_query_params($conn, $query, [$rejectionReason, $storyId]);
        
        if (!$result) {
            throw new Exception('Failed to update story: ' . pg_last_error($conn));
        }
        
        $affectedRows = pg_affected_rows($result);
        if ($affectedRows === 0) {
            throw new Exception('Story not found or no changes made');
        }
        
        pg_close($conn);
        error_log("Admin action: Story ID $storyId status changed to $status");
        
        echo json_encode([
            'success' => true,
            'message' => "Story $status successfully"
        ]);
        
    } catch (Exception $e) {
        error_log("Update story status error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Missing required field: id');
        }
        
        $storyId = (int)$input['id'];
        
        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        $query = "DELETE FROM success_stories WHERE id = $1";
        $result = pg_query_params($conn, $query, [$storyId]);
        
        if (!$result) {
            throw new Exception('Failed to delete story: ' . pg_last_error($conn));
        }
        
        $affectedRows = pg_affected_rows($result);
        if ($affectedRows === 0) {
            throw new Exception('Story not found');
        }
        
        pg_close($conn);

        error_log("Admin action: Story ID $storyId deleted");
        
        echo json_encode([
            'success' => true,
            'message' => 'Story deleted successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Delete story error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'PUT', 'DELETE', 'OPTIONS'])) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>