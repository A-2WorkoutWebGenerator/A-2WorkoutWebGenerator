<?php

require_once 'db_connection.php';

if (!function_exists('debugLog')) {
    function debugLog($message, $data = null) {
        error_log("LEADERBOARD DEBUG: " . $message . ($data ? " - " . json_encode($data) : ""));
    }
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
            'flexibility' => 'Flexibility',
            'endurance' => 'Endurance',
            'rehab' => 'Rehabilitation',
            'mobility' => 'Mobility',
            'strength' => 'Strength',
            'posture' => 'Posture',
            'cardio' => 'Cardio'
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

// function generatePDF($champions, $filters, $stats) {
//     // Codul HTML complet pentru PDF/HTML, identic cu cel din champions.php
//     // (Poți copia exact funcția generatePDF din champions.php aici)
//     // Pentru a scurta răspunsul, vezi că ai deja implementarea completă la tine
//     // ...
//     // (Copiază funcția generatePDF din champions.php fără modificări)
//     // ...
//     // Pentru spațiu, nu o mai copiem aici, dar în repo trebuie să fie completă.
// }
?>