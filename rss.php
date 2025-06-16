<?php
header('Content-Type: application/rss+xml; charset=utf-8');
require_once 'db_connection.php';
require_once 'get-statistics.inc.php';
$conn = getConnection();

$user_id = null;
$username = null;

$token = $_GET['token'] ?? '';

if ($token) {
    $sql = "SELECT id, username FROM users WHERE rss_token = $1";
    $res = pg_query_params($conn, $sql, [$token]);
    if ($row = pg_fetch_assoc($res)) {
        $user_id = $row['id'];
        $username = $row['username'];
    }
}

function friendly_action($operation, $table) {
    $tables = ['exercises' => 'exercise'];
    $actions = [
        'INSERT' => 'created a new',
        'UPDATE' => 'modified an existing',
        'DELETE' => 'deleted an'
    ];
    $table_friendly = $tables[$table] ?? $table;
    $action_friendly = $actions[$operation] ?? strtolower($operation);
    return ucfirst("$action_friendly $table_friendly");
}

function friendly_description($row) {
    $user = $row['user_id'] ? htmlspecialchars($row['user_id']) : '-';
    $ip = $row['ip_address'] ? htmlspecialchars($row['ip_address']) : '-';
    $when = date('Y-m-d H:i', strtotime($row['created_at']));
    $old_values = $row['old_values'] ? htmlspecialchars($row['old_values']) : '-';
    $new_values = $row['new_values'] ? htmlspecialchars($row['new_values']) : '-';

    $desc = "<b>User ID:</b> $user<br/>";
    $desc .= "<b>IP:</b> $ip<br/>";
    $desc .= "<b>Date:</b> $when<br/>";
    $desc .= "<b>Old values:</b><pre style=\"white-space:pre-wrap;\">$old_values</pre><br/>";
    $desc .= "<b>New values:</b><pre style=\"white-space:pre-wrap;\">$new_values</pre><br/>";

    return $desc;
}

function statistics_description($stats) {
    $desc  = "<b>Total workouts:</b> {$stats['total_workouts']}<br/>";
    $desc .= "<b>Workouts this month:</b> {$stats['this_month_workouts']}<br/>";
    $desc .= "<b>Workouts this week:</b> {$stats['this_week_workouts']}<br/>";
    $desc .= "<b>Total exercises:</b> {$stats['total_exercises']}<br/>";
    $desc .= "<b>Total time:</b> {$stats['total_duration_formatted']}<br/>";
    $desc .= "<b>Workout average time:</b> " . number_format($stats['avg_workout_duration'], 1) . " min<br/>";
    $desc .= "<b>Streak (days):</b> {$stats['workout_streak_days']}<br/>";
    $desc .= "<b>Last workout:</b> {$stats['last_workout_formatted']}<br/>";
    $desc .= "<b>Days from the last workout:</b> {$stats['days_since_last_workout']}<br/>";
    $desc .= "<b>Popular muscle group:</b> " . htmlspecialchars($stats['most_popular_muscle_group']) . "<br/>";
    $desc .= "<b>Preferred difficulty:</b> " . htmlspecialchars($stats['most_used_difficulty']) . "<br/>";
    $desc .= "<b>Preferred equipment:</b> " . htmlspecialchars($stats['most_used_equipment']) . "<br/>";
    return $desc;
}

$query = "SELECT id, table_name, operation, user_id, old_values, new_values, created_at, ip_address
          FROM audit_log
          WHERE table_name = 'exercises'
          ORDER BY created_at DESC LIMIT 20";
$result = pg_query($conn, $query);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0">
  <channel>
    <title>Summary for exercises updates and personal statistics</title>
    <link>http://localhost:8081/</link>
    <?php
    echo "<description>Latest updates in the FitGen app on the exercises and personal statistics";
    if ($username) {
        echo " for @$username";
    }
    echo ".</description>";
    ?>
    <language>en-us</language>
<?php

if ($result) {
  while ($row = pg_fetch_assoc($result)) {
    $title = htmlspecialchars(friendly_action($row['operation'], $row['table_name']));
    $description = friendly_description($row);
    $pubDate = date(DATE_RSS, strtotime($row['created_at']));
    $link = "http://localhost:8081/audit/{$row['id']}";
    $guid = $link;
    echo "    <item>
    <title>{$title}</title>
    <link>" . htmlspecialchars($link) . "</link>
    <description><![CDATA[{$description}]]></description>
    <pubDate>{$pubDate}</pubDate>
    <guid>" . htmlspecialchars($guid) . "</guid>
    </item>\n";
  }
}

if ($user_id) {
    $stats = get_user_statistics($conn, $user_id);
    error_log("RSS: user_id = $user_id, stats = " . print_r($stats, true));
    $desc = $stats ? statistics_description($stats) : "There are no statistics for this user.";
    $title = "Personal statistics for #{$user_id}";
    $pubDate = date(DATE_RSS); 
    $link = "http://localhost:8081/user/{$user_id}/statistics";
    $guid = "statistics-{$user_id}-" . date('YmdHis');
    echo "    <item>
    <title>" . htmlspecialchars($title) . "</title>
    <link>" . htmlspecialchars($link) . "</link>
    <description><![CDATA[{$desc}]]></description>
    <pubDate>{$pubDate}</pubDate>
    <guid>" . htmlspecialchars($guid) . "</guid>
    </item>\n";
}

pg_close($conn);
?>
  </channel>
</rss>