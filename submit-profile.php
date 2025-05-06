<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = htmlspecialchars($_POST["first_name"]);
    $lastName = htmlspecialchars($_POST["last_name"]);
    $email = htmlspecialchars($_POST["email"]);
    $gender = $_POST["gender"];
    $age = (int) $_POST["age"];
    $goal = $_POST["goal"];
    $activityLevel = $_POST["activity_level"];
    $injuries = htmlspecialchars($_POST["injuries"]);
    $equipment = $_POST["equipment"];

    $suggestion = "";

    if ($goal === "lose_weight") {
        if ($equipment === "none") {
            $suggestion = "Try HIIT-style bodyweight workouts, 3–5 times per week.";
        } else {
            $suggestion = "Combine cardio and strength circuits using available equipment.";
        }
    } elseif ($goal === "build_muscle") {
        if ($equipment === "full") {
            $suggestion = "Use a gym-based strength program focusing on progressive overload.";
        } else {
            $suggestion = "Use resistance bands or dumbbells for a home muscle-building plan.";
        }
    } elseif ($goal === "flexibility") {
        $suggestion = "Try daily yoga or dynamic stretching routines.";
    } elseif ($goal === "endurance") {
        $suggestion = "Incorporate long-distance cardio (running, cycling) and stamina drills.";
    } elseif ($goal === "rehab") {
        $suggestion = "Follow a light mobility and physical therapy routine. Consult a specialist.";
    } else {
        $suggestion = "Please select a valid goal to receive a recommendation.";
    }

    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Workout Suggestion</title>
        <link rel='stylesheet' href='user_profile.css'>
    </head>
    <body>
        <div class='result'>
            <h2>Hi, $firstName $lastName!</h2>
            <p>Based on your profile, we suggest the following:</p>
            <div class='suggestion'>
                <p>$suggestion</p>
            </div>
            <a href='user_profile.html'>← Back to form</a>
        </div>
    </body>
    </html>";
} else {
    header("Location: user_profile.html");
    exit();
}
?>
