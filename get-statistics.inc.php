<?php
function get_user_statistics($conn, $user_id) {
    $query = "SELECT * FROM fitgen.get_user_statistics($1)";
    $result = pg_query_params($conn, $query, [$user_id]);
    if (!$result) {
        return false;
    }
    $stats = pg_fetch_assoc($result);
    if (!$stats) {
        $stats = [
            'total_workouts' => 0,
            'this_month_workouts' => 0,
            'this_week_workouts' => 0,
            'total_exercises' => 0,
            'total_duration_minutes' => 0,
            'avg_workout_duration' => 0,
            'most_popular_muscle_group' => 'N/A',
            'most_used_difficulty' => 'N/A',
            'most_used_equipment' => 'N/A',
            'workout_streak_days' => 0,
            'last_workout_date' => null,
            'monthly_chart_data' => '[]',
            'muscle_group_stats' => '[]',
            'difficulty_stats' => '[]',
            'recent_workouts' => '[]'
        ];
    }
    $stats['monthly_chart_data'] = json_decode($stats['monthly_chart_data'], true) ?? [];
    $stats['muscle_group_stats'] = json_decode($stats['muscle_group_stats'], true) ?? [];
    $stats['difficulty_stats'] = json_decode($stats['difficulty_stats'], true) ?? [];
    $stats['recent_workouts'] = json_decode($stats['recent_workouts'], true) ?? [];

    $stats['total_workouts'] = (int)$stats['total_workouts'];
    $stats['this_month_workouts'] = (int)$stats['this_month_workouts'];
    $stats['this_week_workouts'] = (int)$stats['this_week_workouts'];
    $stats['total_exercises'] = (int)$stats['total_exercises'];
    $stats['total_duration_minutes'] = (float)$stats['total_duration_minutes'];
    $stats['avg_workout_duration'] = (float)$stats['avg_workout_duration'];
    $stats['workout_streak_days'] = (int)$stats['workout_streak_days'];

    if ($stats['last_workout_date']) {
        $date = new DateTime($stats['last_workout_date']);
        $stats['last_workout_formatted'] = $date->format('d M Y');
        $stats['days_since_last_workout'] = (new DateTime())->diff($date)->days;
    } else {
        $stats['last_workout_formatted'] = 'Never';
        $stats['days_since_last_workout'] = 999;
    }

    $total_hours = floor($stats['total_duration_minutes'] / 60);
    $remaining_minutes = $stats['total_duration_minutes'] % 60;
    $stats['total_duration_formatted'] = sprintf('%dh %dm', $total_hours, $remaining_minutes);

    $total_muscle_count = array_sum(array_column($stats['muscle_group_stats'], 'count'));
    if ($total_muscle_count > 0) {
        foreach ($stats['muscle_group_stats'] as &$muscle_stat) {
            if (!isset($muscle_stat['percentage'])) {
                $muscle_stat['percentage'] = round(($muscle_stat['count'] * 100) / $total_muscle_count, 1);
            }
        }
    }

    $total_difficulty_count = array_sum(array_column($stats['difficulty_stats'], 'count'));
    if ($total_difficulty_count > 0) {
        foreach ($stats['difficulty_stats'] as &$diff_stat) {
            if (!isset($diff_stat['percentage'])) {
                $diff_stat['percentage'] = round(($diff_stat['count'] * 100) / $total_difficulty_count, 1);
            }
        }
    }

    return $stats;
}
?>