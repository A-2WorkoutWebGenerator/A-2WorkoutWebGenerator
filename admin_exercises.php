<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
    
    return $conn;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        $query = "SELECT id, category_id, name, description, instructions, duration_minutes, 
                         difficulty, equipment_needed, video_url, image_url, muscle_groups, 
                         calories_per_minute, created_at, updated_at, location, min_age, 
                         max_age, gender, min_weight, goal, contraindications 
                  FROM exercises 
                  ORDER BY created_at DESC";
        
        $result = pg_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Failed to fetch exercises: ' . pg_last_error($conn));
        }

        $exercises = [];
        while ($row = pg_fetch_assoc($result)) {
            $muscleGroups = [];
            if ($row['muscle_groups']) {
                $muscleString = trim($row['muscle_groups'], '{}');
                if ($muscleString) {
                    $muscleGroups = explode(',', $muscleString);
                    $muscleGroups = array_map('trim', $muscleGroups);
                }
            }
            
            $exercises[] = [
                'id' => (int)$row['id'],
                'category_id' => (int)$row['category_id'],
                'name' => htmlspecialchars($row['name']),
                'description' => htmlspecialchars($row['description']),
                'instructions' => htmlspecialchars($row['instructions']),
                'duration_minutes' => $row['duration_minutes'] ? (int)$row['duration_minutes'] : null,
                'difficulty' => htmlspecialchars($row['difficulty']),
                'equipment_needed' => htmlspecialchars($row['equipment_needed']),
                'video_url' => htmlspecialchars($row['video_url'] ?? ''),
                'image_url' => htmlspecialchars($row['image_url'] ?? ''),
                'muscle_groups' => $muscleGroups,
                'calories_per_minute' => $row['calories_per_minute'] ? (float)$row['calories_per_minute'] : null,
                'location' => htmlspecialchars($row['location']),
                'min_age' => $row['min_age'] ? (int)$row['min_age'] : null,
                'max_age' => $row['max_age'] ? (int)$row['max_age'] : null,
                'gender' => htmlspecialchars($row['gender'] ?? ''),
                'min_weight' => $row['min_weight'] ? (float)$row['min_weight'] : null,
                'goal' => htmlspecialchars($row['goal']),
                'contraindications' => htmlspecialchars($row['contraindications'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        pg_close($conn);

        echo json_encode([
            'success' => true,
            'data' => $exercises
        ]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }

        $required = ['category_id', 'name', 'description', 'instructions', 'difficulty', 'equipment_needed', 'location', 'goal', 'muscle_groups'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        if (empty($input['muscle_groups']) || !is_array($input['muscle_groups'])) {
            throw new Exception('At least one muscle group must be selected');
        }

        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        $muscleGroupsArray = '{' . implode(',', $input['muscle_groups']) . '}';

        $query = "INSERT INTO exercises (
                    category_id, name, description, instructions, duration_minutes, 
                    difficulty, equipment_needed, video_url, image_url, muscle_groups, 
                    calories_per_minute, location, min_age, max_age, gender, min_weight, 
                    goal, contraindications, created_at, updated_at
                  ) VALUES (
                    $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, 
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                  ) RETURNING id";

        $params = [
            (int)$input['category_id'],
            $input['name'],
            $input['description'],
            $input['instructions'],
            $input['duration_minutes'] ? (int)$input['duration_minutes'] : null,
            $input['difficulty'],
            $input['equipment_needed'],
            $input['video_url'] ?: null,
            $input['image_url'] ?: null,
            $muscleGroupsArray,
            $input['calories_per_minute'] ? (float)$input['calories_per_minute'] : null,
            $input['location'],
            $input['min_age'] ? (int)$input['min_age'] : null,
            $input['max_age'] ? (int)$input['max_age'] : null,
            $input['gender'] ?: null,
            $input['min_weight'] ? (float)$input['min_weight'] : null,
            $input['goal'],
            $input['contraindications'] ?: null
        ];

        $result = pg_query_params($conn, $query, $params);
        
        if (!$result) {
            throw new Exception('Failed to create exercise: ' . pg_last_error($conn));
        }

        $newExercise = pg_fetch_assoc($result);
        pg_close($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Exercise created successfully',
            'data' => ['id' => (int)$newExercise['id']]
        ]);

    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !$input['id']) {
            throw new Exception('Exercise ID is required');
        }

        $required = ['category_id', 'name', 'description', 'instructions', 'difficulty', 'equipment_needed', 'location', 'goal', 'muscle_groups'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        if (empty($input['muscle_groups']) || !is_array($input['muscle_groups'])) {
            throw new Exception('At least one muscle group must be selected');
        }

        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        $muscleGroupsArray = '{' . implode(',', $input['muscle_groups']) . '}';

        $query = "UPDATE exercises SET 
                    category_id = $1, name = $2, description = $3, instructions = $4, 
                    duration_minutes = $5, difficulty = $6, equipment_needed = $7, 
                    video_url = $8, image_url = $9, muscle_groups = $10, 
                    calories_per_minute = $11, location = $12, min_age = $13, max_age = $14, 
                    gender = $15, min_weight = $16, goal = $17, contraindications = $18, 
                    updated_at = CURRENT_TIMESTAMP
                  WHERE id = $19";

        $params = [
            (int)$input['category_id'],
            $input['name'],
            $input['description'],
            $input['instructions'],
            $input['duration_minutes'] ? (int)$input['duration_minutes'] : null,
            $input['difficulty'],
            $input['equipment_needed'],
            $input['video_url'] ?: null,
            $input['image_url'] ?: null,
            $muscleGroupsArray,
            $input['calories_per_minute'] ? (float)$input['calories_per_minute'] : null,
            $input['location'],
            $input['min_age'] ? (int)$input['min_age'] : null,
            $input['max_age'] ? (int)$input['max_age'] : null,
            $input['gender'] ?: null,
            $input['min_weight'] ? (float)$input['min_weight'] : null,
            $input['goal'],
            $input['contraindications'] ?: null,
            (int)$input['id']
        ];

        $result = pg_query_params($conn, $query, $params);
        
        if (!$result) {
            throw new Exception('Failed to update exercise: ' . pg_last_error($conn));
        }

        $affectedRows = pg_affected_rows($result);
        if ($affectedRows === 0) {
            throw new Exception('Exercise not found or no changes made');
        }

        pg_close($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Exercise updated successfully'
        ]);

    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !$input['id']) {
            throw new Exception('Exercise ID is required');
        }

        $conn = getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        $query = "DELETE FROM exercises WHERE id = $1";
        $result = pg_query_params($conn, $query, [(int)$input['id']]);
        
        if (!$result) {
            throw new Exception('Failed to delete exercise: ' . pg_last_error($conn));
        }

        $affectedRows = pg_affected_rows($result);
        if ($affectedRows === 0) {
            throw new Exception('Exercise not found');
        }

        pg_close($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Exercise deleted successfully'
        ]);

    } else {
        throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    error_log("Admin exercises error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>