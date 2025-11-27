<?php
/**
 * Admin Configuration
 * IMPORTANT: Change these credentials before deploying to production!
 */

// Admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', password_hash('admin123', PASSWORD_DEFAULT)); // Change this password!

// Session configuration
define('SESSION_NAME', 'portfolio_admin_session');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Projects storage
define('PROJECTS_FILE', __DIR__ . '/data/projects.json');

// Ensure data directory exists
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Initialize projects file if it doesn't exist
if (!file_exists(PROJECTS_FILE)) {
    $default_projects = [
        [
            'id' => 1,
            'title' => 'E-Commerce Platform',
            'description' => 'A full-featured online store with product management, cart functionality, and secure checkout.',
            'image' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'technologies' => ['Angular', 'TypeScript', 'Node.js', 'MongoDB'],
            'category' => 'Web App',
            'featured' => true,
            'githubUrl' => 'https://github.com/kingjudah/ecommerce',
            'liveUrl' => 'https://ecommerce-demo.kingjudah.com',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'title' => 'Task Management System',
            'description' => 'A collaborative task management application with real-time updates and team collaboration features.',
            'image' => 'https://images.unsplash.com/photo-1540350394557-8d14678e7f91?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'technologies' => ['Vue.js', 'Firebase', 'Tailwind CSS'],
            'category' => 'Web App',
            'featured' => true,
            'githubUrl' => 'https://github.com/kingjudah/taskmanager',
            'liveUrl' => 'https://tasks.kingjudah.com',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 3,
            'title' => 'Portfolio Website',
            'description' => 'A responsive portfolio website built with Angular and Bootstrap to showcase projects and skills.',
            'image' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'technologies' => ['Angular', 'Bootstrap', 'TypeScript'],
            'category' => 'Website',
            'featured' => true,
            'githubUrl' => 'https://github.com/kingjudah/portfolio',
            'liveUrl' => 'https://kingjudah.com',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    file_put_contents(PROJECTS_FILE, json_encode($default_projects, JSON_PRETTY_PRINT));
}
