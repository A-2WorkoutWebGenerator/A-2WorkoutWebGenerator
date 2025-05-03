<?php
function getConnection() {
    $username = "student";
    $password = "STUDENT";
    $connection_string = "host.docker.internal:1521/XE";
    
    $conn = oci_connect($username, $password, $connection_string);
    
    if (!$conn) {
        $e = oci_error();
        error_log("Database connection error: " . $e['message']);
        return ["error" => "Database connection failed: " . $e['message']];
    }
    
    return $conn;
}
?>