<?php

require_once 'db_connection.php';

function get_exercises() {
    $conn = getConnection();
    if (!$conn) {
        send_error('Database connection failed');
    }

    $query = "SELECT id, category_id, name, description, instructions, duration_minutes, 
                     difficulty, equipment_needed, video_url, image_url, muscle_groups, 
                     calories_per_minute, created_at, updated_at, location, min_age, 
                     max_age, gender, min_weight, goal, contraindications 
              FROM exercises 
              ORDER BY created_at DESC";

    $result = pg_query($conn, $query);

    if (!$result) {
        send_error('Failed to fetch exercises: ' . pg_last_error($conn));
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

    send_json([
        'success' => true,
        'data' => $exercises
    ]);
}

function add_exercise() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        send_error('Invalid JSON data');
    }
    $required = ['category_id', 'name', 'description', 'instructions', 'difficulty', 'equipment_needed', 'location', 'goal', 'muscle_groups'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            send_error("Field '$field' is required");
        }
    }
    if (empty($input['muscle_groups']) || !is_array($input['muscle_groups'])) {
        send_error('At least one muscle group must be selected');
    }
    $conn = getConnection();
    if (!$conn) {
        send_error('Database connection failed');
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
        send_error('Failed to create exercise: ' . pg_last_error($conn));
    }
    $newExercise = pg_fetch_assoc($result);
    pg_close($conn);

    send_json([
        'success' => true,
        'message' => 'Exercise created successfully',
        'data' => ['id' => (int)$newExercise['id']]
    ]);
}

function get_exercise($id) {
    $conn = getConnection();
    if (!$conn) {
        send_error('Database connection failed');
    }
    $query = "SELECT id, category_id, name, description, instructions, duration_minutes, 
                     difficulty, equipment_needed, video_url, image_url, muscle_groups, 
                     calories_per_minute, created_at, updated_at, location, min_age, 
                     max_age, gender, min_weight, goal, contraindications 
              FROM exercises 
              WHERE id = $1";
    $result = pg_query_params($conn, $query, [(int)$id]);
    if (!$result) {
        send_error('Failed to fetch exercise: ' . pg_last_error($conn));
    }
    $row = pg_fetch_assoc($result);
    pg_close($conn);
    if (!$row) {
        send_error('Exercise not found');
    }
    $muscleGroups = [];
    if ($row['muscle_groups']) {
        $muscleString = trim($row['muscle_groups'], '{}');
        if ($muscleString) {
            $muscleGroups = explode(',', $muscleString);
            $muscleGroups = array_map('trim', $muscleGroups);
        }
    }
    send_json([
        'success' => true,
        'data' => [
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
        ]
    ]);
}

function update_exercise($id) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        send_error('Invalid JSON data');
    }
    $required = ['category_id', 'name', 'description', 'instructions', 'difficulty', 'equipment_needed', 'location', 'goal', 'muscle_groups'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            send_error("Field '$field' is required");
        }
    }
    if (empty($input['muscle_groups']) || !is_array($input['muscle_groups'])) {
        send_error('At least one muscle group must be selected');
    }

    $conn = getConnection();
    if (!$conn) {
        send_error('Database connection failed');
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
        (int)$id
    ];

    $result = pg_query_params($conn, $query, $params);
    if (!$result) {
        send_error('Failed to update exercise: ' . pg_last_error($conn));
    }
    $affectedRows = pg_affected_rows($result);
    pg_close($conn);
    if ($affectedRows === 0) {
        send_error('Exercise not found or no changes made');
    }
    send_json([
        'success' => true,
        'message' => 'Exercise updated successfully'
    ]);
}

function delete_exercise($id) {
    $conn = getConnection();
    if (!$conn) {
        send_error('Database connection failed');
    }
    $query = "DELETE FROM exercises WHERE id = $1";
    $result = pg_query_params($conn, $query, [(int)$id]);
    if (!$result) {
        send_error('Failed to delete exercise: ' . pg_last_error($conn));
    }
    $affectedRows = pg_affected_rows($result);
    pg_close($conn);
    if ($affectedRows === 0) {
        send_error('Exercise not found');
    }
    send_json([
        'success' => true,
        'message' => 'Exercise deleted successfully'
    ]);
}

function send_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function send_error($message) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}
?>