<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connection.php';

function debugLog($message, $data = null) {
    error_log("CHAMPIONS DEBUG: " . $message . ($data ? " - " . json_encode($data) : ""));
}

function getChampionsSimple($conn, $filters = []) {
    debugLog("Starting getChampionsSimple", $filters);
    
    try {
        $testQuery = "SELECT EXISTS (
            SELECT 1 FROM pg_proc p 
            JOIN pg_namespace n ON p.pronamespace = n.oid 
            WHERE n.nspname = 'fitgen' AND p.proname = 'get_champions_leaderboard'
        ) as function_exists";
        
        $testResult = pg_query($conn, $testQuery);
        $functionExists = pg_fetch_result($testResult, 0, 'function_exists');
        
        debugLog("Function exists check", $functionExists);
        
        if ($functionExists === 'f') {
            throw new Exception('Champions function does not exist in database');
        }

        $age_group = $filters['age_group'] ?? null;
        $gender = $filters['gender'] ?? null;
        $goal = $filters['goal'] ?? null;
        $limit = (int)($filters['limit'] ?? 25);

        debugLog("Calling PL/pgSQL function with params", [$age_group, $gender, $goal, $limit]);

        $query = "SELECT * FROM fitgen.get_champions_leaderboard($1, $2, $3, $4)";
        $params = [$age_group, $gender, $goal, $limit];

        $result = pg_query_params($conn, $query, $params);
        
        if (!$result) {
            $error = pg_last_error($conn);
            debugLog("PL/pgSQL function failed", $error);
            throw new Exception('Database query failed: ' . $error);
        }

        $champions = [];
        $rowCount = 0;
        
        while ($row = pg_fetch_assoc($result)) {
            $rowCount++;
            debugLog("Processing row $rowCount", $row);
            
            $champions[] = [
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?: 'Anonymous',
                'first_name' => $row['first_name'] ?: '',
                'last_name' => $row['last_name'] ?: '',
                'age' => $row['age'] ? (int)$row['age'] : null,
                'gender' => $row['gender'] ?: '',
                'goal' => $row['goal'] ?: '',
                'profile_picture' => $row['profile_picture_path'] ?: '',
                'rank' => (int)$row['rank_position'],
                'stats' => [
                    'total_workouts' => (int)$row['total_workouts'],
                    'active_days' => (int)$row['active_days'],
                    'total_duration' => (float)$row['total_duration'],
                    'activity_score' => (float)$row['activity_score']
                ]
            ];
        }

        debugLog("Successfully processed champions", ['count' => count($champions)]);
        return $champions;

    } catch (Exception $e) {
        debugLog("Exception in getChampionsSimple", $e->getMessage());
        
        return getChampionsFallback($conn, $filters);
    }
}

function getChampionsFallback($conn, $filters = []) {
    debugLog("Using fallback method");
    
    try {
        $whereConditions = ["uw.id IS NOT NULL"];
        $params = [];
        $paramCount = 0;
        if (!empty($filters['age_group'])) {
            switch ($filters['age_group']) {
                case 'youth':
                    $whereConditions[] = "up.age BETWEEN 12 AND 25";
                    break;
                case 'adult':
                    $whereConditions[] = "up.age BETWEEN 26 AND 45";
                    break;
                case 'senior':
                    $whereConditions[] = "up.age > 45";
                    break;
            }
        }

        if (!empty($filters['gender'])) {
            $paramCount++;
            $whereConditions[] = "up.gender::TEXT = $" . $paramCount;
            $params[] = $filters['gender'];
        }

        if (!empty($filters['goal'])) {
            $paramCount++;
            $whereConditions[] = "up.goal::TEXT = $" . $paramCount;
            $params[] = $filters['goal'];
        }

        $whereClause = implode(" AND ", $whereConditions);
        $limit = (int)($filters['limit'] ?? 25);

        $query = "
            SELECT 
                u.id as user_id,
                u.username,
                COALESCE(up.first_name, '') as first_name,
                COALESCE(up.last_name, '') as last_name,
                up.age,
                CASE 
                    WHEN up.gender IS NOT NULL THEN up.gender::TEXT
                    ELSE ''
                END as gender,
                CASE 
                    WHEN up.goal IS NOT NULL THEN up.goal::TEXT
                    ELSE ''
                END as goal,
                COALESCE(up.profile_picture_path, '') as profile_picture_path,
                COUNT(uw.id) as total_workouts,
                COUNT(DISTINCT DATE(uw.generated_at)) as active_days,
                0 as total_duration
            FROM fitgen.users u
            LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
            LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
            WHERE $whereClause
            GROUP BY u.id, u.username, up.first_name, up.last_name, up.age, up.gender, up.goal, up.profile_picture_path
            HAVING COUNT(uw.id) > 0
            ORDER BY COUNT(uw.id) DESC, COUNT(DISTINCT DATE(uw.generated_at)) DESC
            LIMIT $limit
        ";

        debugLog("Fallback query", $query);

        $result = pg_query_params($conn, $query, $params);
        
        if (!$result) {
            throw new Exception('Fallback query failed: ' . pg_last_error($conn));
        }

        $champions = [];
        $rank = 1;
        
        while ($row = pg_fetch_assoc($result)) {
            $activityScore = ($row['total_workouts'] * 10) + ($row['active_days'] * 15);
            
            $champions[] = [
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?: 'Anonymous',
                'first_name' => $row['first_name'] ?: '',
                'last_name' => $row['last_name'] ?: '',
                'age' => $row['age'] ? (int)$row['age'] : null,
                'gender' => $row['gender'] ?: '',
                'goal' => $row['goal'] ?: '',
                'profile_picture' => $row['profile_picture_path'] ?: '',
                'rank' => $rank++,
                'stats' => [
                    'total_workouts' => (int)$row['total_workouts'],
                    'active_days' => (int)$row['active_days'],
                    'total_duration' => (float)$row['total_duration'],
                    'activity_score' => round($activityScore, 2)
                ]
            ];
        }

        debugLog("Fallback successful", ['count' => count($champions)]);
        return $champions;

    } catch (Exception $e) {
        debugLog("Fallback also failed", $e->getMessage());
        throw $e;
    }
}

function getSimpleStats($conn) {
    try {
        $query = "
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(uw.id) as total_workouts,
                0 as total_minutes
            FROM fitgen.users u
            LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
            LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
            WHERE uw.id IS NOT NULL
        ";

        $result = pg_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Stats query failed: ' . pg_last_error($conn));
        }

        $row = pg_fetch_assoc($result);
        return [
            'total_active_users' => (int)$row['total_users'],
            'total_workouts_generated' => (int)$row['total_workouts'],
            'total_workout_minutes' => (float)$row['total_minutes'],
            'average_workouts_per_user' => $row['total_users'] > 0 ? round($row['total_workouts'] / $row['total_users'], 2) : 0,
            'most_active_age_group' => 'unknown',
            'most_popular_goal' => 'unknown'
        ];

    } catch (Exception $e) {
        debugLog("Stats failed", $e->getMessage());
        return null;
    }
}

try {
    debugLog("Champions API called", $_GET);

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    $testQuery = "SELECT 1 as test";
    $testResult = pg_query($conn, $testQuery);
    if (!$testResult) {
        throw new Exception('Basic database test failed');
    }

    debugLog("Database connection successful");

    $filters = [
        'age_group' => $_GET['age_group'] ?? null,
        'gender' => $_GET['gender'] ?? null,
        'goal' => $_GET['goal'] ?? null,
        'limit' => $_GET['limit'] ?? 25
    ];

    $format = $_GET['format'] ?? 'json';

    debugLog("Processing request", ['filters' => $filters, 'format' => $format]);
    $champions = getChampionsSimple($conn, $filters);
    $stats = getSimpleStats($conn);

    pg_close($conn);

    if ($format === 'pdf') {
        echo "<!DOCTYPE html><html><head><title>Champions</title></head><body>";
        echo "<h1>FitGen Champions</h1>";
        echo "<p>Generated on " . date('Y-m-d H:i:s') . "</p>";
        
        if (!empty($champions)) {
            echo "<table border='1'>";
            echo "<tr><th>Rank</th><th>Name</th><th>Workouts</th><th>Score</th></tr>";
            foreach ($champions as $champion) {
                $name = trim($champion['first_name'] . ' ' . $champion['last_name']) ?: $champion['username'];
                echo "<tr>";
                echo "<td>" . $champion['rank'] . "</td>";
                echo "<td>" . htmlspecialchars($name) . "</td>";
                echo "<td>" . $champion['stats']['total_workouts'] . "</td>";
                echo "<td>" . $champion['stats']['activity_score'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No champions data available</p>";
        }
        
        echo "</body></html>";
        
    } else {
        echo json_encode([
            'success' => true,
            'data' => $champions,
            'stats' => $stats,
            'filters_applied' => array_filter($filters),
            'total_count' => count($champions),
            'generated_at' => date('c'),
            'debug_info' => [
                'method' => 'simplified',
                'has_data' => !empty($champions)
            ]
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    debugLog("Fatal error", $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c'),
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ]);
}
?>