<?php

$host = getenv("MEPHYS_DB_HOST") ?: "localhost";
$dbname = getenv("MEPHYS_DB_NAME") ?: "contact";
$username = getenv("MEPHYS_DB_USER") ?: "root";
$password = getenv("MEPHYS_DB_PASSWORD") ?: "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $e) {

    // Record the detailed error privately.
    error_log("Database connection failed: " . $e->getMessage());

    // Show a generic message to visitors.
    die("Unable to connect to the database. Please try again later.");
}
