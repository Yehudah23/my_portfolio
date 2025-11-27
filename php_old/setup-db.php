<?php
/**
 * Database Setup Script
 * Run this file once to create the necessary database tables
 * Access via: http://localhost:8000/setup-db.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db_mysqli.php';

try {
    $mysqli = get_mysqli_connection(false);

    // Create database if it doesn't exist
    $dbNameEscaped = $mysqli->real_escape_string(DB_NAME);
    $createDbSql = "CREATE DATABASE IF NOT EXISTS `" . $dbNameEscaped . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$mysqli->query($createDbSql)) {
        throw new Exception('Create database failed: ' . $mysqli->error);
    }

    if (!$mysqli->select_db(DB_NAME)) {
        throw new Exception('Select database failed: ' . $mysqli->error);
    }

    $sql = "
    CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    if (!$mysqli->query($sql)) {
        throw new Exception('Create table failed: ' . $mysqli->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database and tables created successfully!',
        'database' => DB_NAME
    ]);

    $mysqli->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database setup failed: ' . $e->getMessage()
    ]);
}
