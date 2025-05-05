<?php
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
    
    error_log("Successfully connected to PostgreSQL database");
    return $conn;
}
?>