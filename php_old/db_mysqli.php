<?php
/**
 * MySQLi connection wrapper for php/ folder
 */

// Ensure configuration constants are available; prefer project root config.php
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_NAME')) {
    require_once __DIR__ . '/../config.php';
}

function get_mysqli_connection($with_db = true) {
    $db = $with_db ? DB_NAME : null;

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, $db);
    if ($mysqli->connect_errno) {
        throw new Exception('MySQLi connection failed: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}
