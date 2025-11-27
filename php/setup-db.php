<?php
/**
 * Database Setup Script
 * Run this file once to create the necessary database tables
 * Access via: http://localhost:8000/setup-db.php
 */

require_once __DIR__ . '/config.php';

try {
    // Connect to MySQL without database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Use the database
    $pdo->exec("USE " . DB_NAME);
    
    // Create contacts table
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
    
    $pdo->exec($sql);
    
    // Create projects table
    $sql = "
    CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        category VARCHAR(100),
        image VARCHAR(255),
        live_url VARCHAR(255),
        github_url VARCHAR(255),
        tech_tags VARCHAR(255),
        featured TINYINT(1) DEFAULT 0,
        is_published TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_published (is_published)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $pdo->exec($sql);

    // Create admin_users table
    $sql = "
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(255),
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $pdo->exec($sql);

    // Create newsletter_subscriptions table
    $sql = "
    CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $pdo->exec($sql);

    // Create testimonials table
    $sql = "
    CREATE TABLE IF NOT EXISTS testimonials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        role VARCHAR(255),
        company VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $pdo->exec($sql);

    // Ensure there is a default admin user (for development only)
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM admin_users WHERE username = :username");
    $defaultAdmin = 'admin';
    $stmt->execute([':username' => $defaultAdmin]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['cnt'] === 0) {
        $password = 'admin123'; // development default - change in production
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, email) VALUES (:username, :hash, :display_name, :email)");
        $ins->execute([
            ':username' => $defaultAdmin,
            ':hash' => $hash,
            ':display_name' => 'Administrator',
            ':email' => 'admin@localhost'
        ]);
    }
    echo json_encode([
        'success' => true,
        'message' => 'Database and tables created successfully!',
        'database' => DB_NAME
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database setup failed: ' . $e->getMessage()
    ]);
}
