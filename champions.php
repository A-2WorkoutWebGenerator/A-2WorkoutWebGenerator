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
        $mainQuery = "
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(uw.id) as total_workouts,
                COALESCE(
                    SUM(
                        CASE 
                            WHEN uw.workout IS NOT NULL AND jsonb_typeof(uw.workout) = 'array' THEN 
                                (
                                    SELECT SUM(
                                        CASE 
                                            WHEN exercise ? 'duration_minutes' 
                                            AND exercise->>'duration_minutes' ~ '^[0-9]+(\.[0-9]+)?$'
                                            THEN (exercise->>'duration_minutes')::NUMERIC
                                            ELSE 0
                                        END
                                    )
                                    FROM jsonb_array_elements(uw.workout) as exercise
                                )
                            ELSE 0
                        END
                    ), 0
                ) as total_minutes
            FROM fitgen.users u
            LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
            LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
            WHERE uw.id IS NOT NULL
        ";

        $result = pg_query($conn, $mainQuery);
        
        if (!$result) {
            throw new Exception('Main stats query failed: ' . pg_last_error($conn));
        }

        $row = pg_fetch_assoc($result);
        $ageGroupQuery = "
            SELECT 
                CASE 
                    WHEN up.age BETWEEN 12 AND 25 THEN 'youth'
                    WHEN up.age BETWEEN 26 AND 45 THEN 'adult'
                    WHEN up.age > 45 THEN 'senior'
                    ELSE 'unknown'
                END as age_group,
                COUNT(uw.id) as workout_count
            FROM fitgen.users u
            LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
            LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
            WHERE uw.id IS NOT NULL AND up.age IS NOT NULL
            GROUP BY 
                CASE 
                    WHEN up.age BETWEEN 12 AND 25 THEN 'youth'
                    WHEN up.age BETWEEN 26 AND 45 THEN 'adult'
                    WHEN up.age > 45 THEN 'senior'
                    ELSE 'unknown'
                END
            ORDER BY workout_count DESC
            LIMIT 1
        ";

        $ageResult = pg_query($conn, $ageGroupQuery);
        $mostActiveAgeGroup = 'unknown';
        
        if ($ageResult && pg_num_rows($ageResult) > 0) {
            $ageRow = pg_fetch_assoc($ageResult);
            $mostActiveAgeGroup = $ageRow['age_group'];
        }
        $goalQuery = "
            SELECT 
                up.goal::TEXT as goal,
                COUNT(uw.id) as workout_count
            FROM fitgen.users u
            LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
            LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
            WHERE uw.id IS NOT NULL AND up.goal IS NOT NULL
            GROUP BY up.goal::TEXT
            ORDER BY workout_count DESC
            LIMIT 1
        ";

        $goalResult = pg_query($conn, $goalQuery);
        $mostPopularGoal = 'unknown';
        
        if ($goalResult && pg_num_rows($goalResult) > 0) {
            $goalRow = pg_fetch_assoc($goalResult);
            $mostPopularGoal = $goalRow['goal'];
        }
        $detailedStatsQuery = "
            SELECT 
                SUM(CASE WHEN up.age BETWEEN 12 AND 25 THEN 1 ELSE 0 END) as youth_users,
                SUM(CASE WHEN up.age BETWEEN 26 AND 45 THEN 1 ELSE 0 END) as adult_users,
                SUM(CASE WHEN up.age > 45 THEN 1 ELSE 0 END) as senior_users,
                
                SUM(CASE WHEN up.gender::TEXT = 'male' THEN 1 ELSE 0 END) as male_users,
                SUM(CASE WHEN up.gender::TEXT = 'female' THEN 1 ELSE 0 END) as female_users,
                
                SUM(CASE WHEN up.goal::TEXT = 'lose_weight' THEN 1 ELSE 0 END) as lose_weight_users,
                SUM(CASE WHEN up.goal::TEXT = 'build_muscle' THEN 1 ELSE 0 END) as build_muscle_users,
                SUM(CASE WHEN up.goal::TEXT = 'flexibility' THEN 1 ELSE 0 END) as flexibility_users,
                SUM(CASE WHEN up.goal::TEXT = 'endurance' THEN 1 ELSE 0 END) as endurance_users,
                SUM(CASE WHEN up.goal::TEXT = 'rehab' THEN 1 ELSE 0 END) as rehab_users,
                SUM(CASE WHEN up.goal::TEXT = 'mobility' THEN 1 ELSE 0 END) as mobility_users,
                SUM(CASE WHEN up.goal::TEXT = 'strength' THEN 1 ELSE 0 END) as strength_users,
                SUM(CASE WHEN up.goal::TEXT = 'posture' THEN 1 ELSE 0 END) as posture_users,
                SUM(CASE WHEN up.goal::TEXT = 'cardio' THEN 1 ELSE 0 END) as cardio_users
            FROM fitgen.users u
            LEFT JOIN fitgen.user_profiles up ON u.id = up.user_id
            LEFT JOIN fitgen.user_workouts uw ON u.id = uw.user_id
            WHERE uw.id IS NOT NULL
        ";

        $detailedResult = pg_query($conn, $detailedStatsQuery);
        $detailedStats = [];
        
        if ($detailedResult && pg_num_rows($detailedResult) > 0) {
            $detailedStats = pg_fetch_assoc($detailedResult);
        }

        debugLog("Stats calculation", [
            'main' => $row,
            'most_active_age_group' => $mostActiveAgeGroup,
            'most_popular_goal' => $mostPopularGoal,
            'detailed' => $detailedStats
        ]);
        
        $goalDisplayNames = [
            'lose_weight' => 'Lose Weight',
            'build_muscle' => 'Build Muscle',
            'flexibility' => 'Improve Flexibility',
            'endurance' => 'Increase Endurance',
            'rehab' => 'Rehabilitation',
            'mobility' => 'Increase Mobility',
            'strength' => 'Increase Strength',
            'posture' => 'Greater Posture',
            'cardio' => 'Improve Resistance'
        ];

        $ageGroupDisplayNames = [
            'youth' => 'Youth (12-25)',
            'adult' => 'Adult (26-45)',
            'senior' => 'Senior (45+)',
            'unknown' => 'Unknown'
        ];

        return [
            'total_active_users' => (int)$row['total_users'],
            'total_workouts_generated' => (int)$row['total_workouts'],
            'total_workout_minutes' => (float)$row['total_minutes'],
            'average_workouts_per_user' => $row['total_users'] > 0 ? round($row['total_workouts'] / $row['total_users'], 2) : 0,
            'most_active_age_group' => $mostActiveAgeGroup,
            'most_active_age_group_display' => $ageGroupDisplayNames[$mostActiveAgeGroup] ?? $mostActiveAgeGroup,
            'most_popular_goal' => $mostPopularGoal,
            'most_popular_goal_display' => $goalDisplayNames[$mostPopularGoal] ?? ucfirst(str_replace('_', ' ', $mostPopularGoal)),
            'demographics' => [
                'age_groups' => [
                    'youth' => (int)($detailedStats['youth_users'] ?? 0),
                    'adult' => (int)($detailedStats['adult_users'] ?? 0),
                    'senior' => (int)($detailedStats['senior_users'] ?? 0)
                ],
                'gender' => [
                    'male' => (int)($detailedStats['male_users'] ?? 0),
                    'female' => (int)($detailedStats['female_users'] ?? 0)
                ],
                'goals' => [
                    'lose_weight' => (int)($detailedStats['lose_weight_users'] ?? 0),
                    'build_muscle' => (int)($detailedStats['build_muscle_users'] ?? 0),
                    'flexibility' => (int)($detailedStats['flexibility_users'] ?? 0),
                    'endurance' => (int)($detailedStats['endurance_users'] ?? 0),
                    'rehab' => (int)($detailedStats['rehab_users'] ?? 0),
                    'mobility' => (int)($detailedStats['mobility_users'] ?? 0),
                    'strength' => (int)($detailedStats['strength_users'] ?? 0),
                    'posture' => (int)($detailedStats['posture_users'] ?? 0),
                    'cardio' => (int)($detailedStats['cardio_users'] ?? 0)
                ]
            ]
        ];

    } catch (Exception $e) {
        debugLog("Stats failed", $e->getMessage());
        return [
            'total_active_users' => 0,
            'total_workouts_generated' => 0,
            'total_workout_minutes' => 0,
            'average_workouts_per_user' => 0,
            'most_active_age_group' => 'unknown',
            'most_active_age_group_display' => 'Unknown',
            'most_popular_goal' => 'unknown',
            'most_popular_goal_display' => 'Unknown',
            'demographics' => [
                'age_groups' => ['youth' => 0, 'adult' => 0, 'senior' => 0],
                'gender' => ['male' => 0, 'female' => 0],
                'goals' => []
            ]
        ];
    }
}

function generatePDF($champions, $filters, $stats) {
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>FitGen Champions Leaderboard - Advanced Report</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                margin: 0; 
                padding: 20px; 
                background: #f8f9fa;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header { 
                text-align: center; 
                margin-bottom: 40px; 
                border-bottom: 3px solid #18D259;
                padding-bottom: 20px;
            }
            .logo { 
                color: #18D259; 
                font-size: 32px; 
                font-weight: bold; 
                margin-bottom: 10px;
            }
            .subtitle {
                color: #666;
                font-size: 18px;
                margin-bottom: 10px;
            }
            .generated-info {
                color: #888;
                font-size: 14px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin: 30px 0;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }
            .stat-card {
                text-align: center;
                padding: 15px;
                background: white;
                border-radius: 6px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            .stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #18D259;
                margin-bottom: 5px;
            }
            .stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .insights-section {
                background: #e6f9ed;
                padding: 20px;
                margin: 30px 0;
                border-radius: 8px;
                border-left: 4px solid #18D259;
            }
            .insights-title {
                color: #18D259;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 15px;
            }
            .insight-item {
                background: white;
                padding: 12px 15px;
                margin: 10px 0;
                border-radius: 6px;
                border-left: 3px solid #18D259;
                font-size: 14px;
            }
            .insight-highlight {
                font-weight: bold;
                color: #18D259;
            }
            
            .filters { 
                background: #e6f9ed; 
                padding: 20px; 
                margin-bottom: 30px; 
                border-radius: 8px;
                border-left: 4px solid #18D259;
            }
            .filters h3 {
                margin: 0 0 15px 0;
                color: #18D259;
            }
            .filter-item {
                display: inline-block;
                background: white;
                padding: 8px 15px;
                margin: 5px;
                border-radius: 20px;
                font-size: 14px;
                border: 1px solid #18D259;
            }
            
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 30px; 
                background: white;
            }
            th, td { 
                padding: 12px 8px; 
                text-align: center; 
                border-bottom: 1px solid #eee; 
                font-size: 14px;
            }
            th { 
                background: linear-gradient(135deg, #18D259 0%, #3fcb70 100%); 
                color: white; 
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            tr:nth-child(even) {
                background: #f8f9fa;
            }
            tr:hover {
                background: #e6f9ed;
            }
            
            .rank { 
                font-weight: bold; 
                font-size: 16px;
            }
            .rank.top-3 {
                background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
                color: #333;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto;
            }
            .rank.rank-1 { background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); }
            .rank.rank-2 { background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%); }
            .rank.rank-3 { background: linear-gradient(135deg, #cd7f32 0%, #daa520 100%); }
            
            .user-name {
                font-weight: 600;
                color: #333;
            }
            .activity-score {
                font-weight: bold;
                color: #18D259;
            }
            
            .footer { 
                margin-top: 40px; 
                text-align: center; 
                color: #666;
                font-size: 14px;
                border-top: 1px solid #eee;
                padding-top: 20px;
            }
            .footer-logo {
                color: #18D259;
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            @media print {
                body { background: white; }
                .container { 
                    box-shadow: none; 
                    margin: 0;
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>💪 FitGen Champions</div>
                <div class='subtitle'>Most Active Users Leaderboard</div>
                <div class='generated-info'>Generated on " . date('F j, Y \a\t g:i A') . "</div>
            </div>";

    if ($stats) {
        $html .= "
            <div class='stats-grid'>
                <div class='stat-card'>
                    <div class='stat-value'>{$stats['total_active_users']}</div>
                    <div class='stat-label'>Active Users</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-value'>{$stats['total_workouts_generated']}</div>
                    <div class='stat-label'>Total Workouts</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-value'>" . number_format($stats['total_workout_minutes']) . "</div>
                    <div class='stat-label'>Total Minutes</div>
                </div>
            </div>";

        $html .= "
            <div class='insights-section'>
                <div class='insights-title'>📊 Community Insights</div>
                <div class='insight-item'>
                    Most Active Age Group: <span class='insight-highlight'>{$stats['most_active_age_group_display']}</span>
                </div>
                <div class='insight-item'>
                    Most Popular Goal: <span class='insight-highlight'>{$stats['most_popular_goal_display']}</span>
                </div>
                <div class='insight-item'>
                    Average Workouts per User: <span class='insight-highlight'>{$stats['average_workouts_per_user']}</span>
                </div>";
        
        if (isset($stats['demographics'])) {
            $demographics = $stats['demographics'];
            if (!empty($demographics['age_groups'])) {
                $ageBreakdown = [];
                foreach ($demographics['age_groups'] as $group => $count) {
                    if ($count > 0) {
                        $groupNames = [
                            'youth' => 'Youth (12-25)',
                            'adult' => 'Adult (26-45)', 
                            'senior' => 'Senior (45+)'
                        ];
                        $ageBreakdown[] = ($groupNames[$group] ?? $group) . ": {$count}";
                    }
                }
                if (!empty($ageBreakdown)) {
                    $html .= "
                        <div class='insight-item'>
                            Age Distribution: <span class='insight-highlight'>" . implode(', ', $ageBreakdown) . "</span>
                        </div>";
                }
            }
            if (!empty($demographics['gender'])) {
                $genderBreakdown = [];
                foreach ($demographics['gender'] as $gender => $count) {
                    if ($count > 0) {
                        $genderBreakdown[] = ucfirst($gender) . ": {$count}";
                    }
                }
                if (!empty($genderBreakdown)) {
                    $html .= "
                        <div class='insight-item'>
                            Gender Distribution: <span class='insight-highlight'>" . implode(', ', $genderBreakdown) . "</span>
                        </div>";
                }
            }
        }
        
        $html .= "</div>";
    }

    if (!empty(array_filter($filters))) {
        $html .= "<div class='filters'><h3>Applied Filters</h3>";
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $html .= "<span class='filter-item'>{$label}: " . htmlspecialchars($value) . "</span>";
            }
        }
        $html .= "</div>";
    }
    $html .= "
        <table>
            <thead>
                <tr>
                    <th style='width: 60px;'>Rank</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Goal</th>
                    <th>Workouts</th>
                    <th>Active Days</th>
                    <th>Duration (min)</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($champions as $champion) {
        $rank = $champion['rank'];
        $name = trim($champion['first_name'] . ' ' . $champion['last_name']) ?: $champion['username'];
        $rankClass = $rank <= 3 ? "top-3 rank-{$rank}" : '';
        $goalDisplayNames = [
            'lose_weight' => 'Weight',
            'build_muscle' => 'Muscle',
            'flexibility' => 'Flexibility',
            'endurance' => 'Endurance',
            'rehab' => 'Rehab',
            'mobility' => 'Mobility',
            'strength' => 'Strength',
            'posture' => 'Posture',
            'cardio' => 'Resistance'
        ];
        $html .= "
            <tr>
                <td><div class='rank {$rankClass}'>{$rank}</div></td>
                <td class='user-name'>" . htmlspecialchars($name) . "</td>
                <td>" . ($champion['age'] ?: 'N/A') . "</td>
                <td>" . ($champion['gender'] ? ucfirst($champion['gender']) : 'N/A') . "</td>
                <td>" . ($champion['goal'] ? ($goalDisplayNames[$champion['goal']] ?? ucwords(str_replace('_', ' ', $champion['goal']))) : 'N/A') . "</td>
                <td>{$champion['stats']['total_workouts']}</td>
                <td>{$champion['stats']['active_days']}</td>
                <td>{$champion['stats']['total_duration']}</td>
                <td class='activity-score'>{$champion['stats']['activity_score']}</td>
            </tr>";
    }

    $html .= "
            </tbody>
        </table>
        
        <div class='footer'>
            <div class='footer-logo'>FitGen</div>
            <p>Transform Your Workout Experience</p>
            <p>© 2025 FitGen. All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>";

    return $html;
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