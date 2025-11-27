<?php
/**
 * API endpoint to fetch portfolio data
 * Can be used to retrieve projects, skills, or other dynamic content
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

handle_cors();

// Get the requested resource type
$resource = $_GET['resource'] ?? 'projects';

switch ($resource) {
    case 'projects':
        $data = get_projects();
        break;
    case 'skills':
        $data = get_skills();
        break;
    case 'testimonials':
        $data = get_testimonials();
        break;
    default:
        send_json_response([
            'success' => false,
            'error' => 'Invalid resource type'
        ], 400);
}

send_json_response([
    'success' => true,
    'data' => $data
]);

/**
 * Get projects data
 */
function get_projects() {
    return [
        [
            'id' => 1,
            'title' => 'E-Commerce Platform',
            'description' => 'A full-featured e-commerce platform built with Laravel and Vue.js',
            'image' => 'assets/projects/ecommerce.jpg',
            'category' => 'Web Application',
            'technologies' => ['Laravel', 'Vue.js', 'MySQL', 'Stripe'],
            'githubUrl' => 'https://github.com/yourusername/ecommerce',
            'liveUrl' => 'https://demo.example.com'
        ],
        [
            'id' => 2,
            'title' => 'Task Management App',
            'description' => 'A collaborative task management application with real-time updates',
            'image' => 'assets/projects/taskapp.jpg',
            'category' => 'Mobile App',
            'technologies' => ['Angular', 'Firebase', 'TypeScript'],
            'githubUrl' => 'https://github.com/yourusername/taskapp',
            'liveUrl' => null
        ],
        [
            'id' => 3,
            'title' => 'Portfolio Website',
            'description' => 'A modern, responsive portfolio website with dark mode',
            'image' => 'assets/projects/portfolio.jpg',
            'category' => 'Website',
            'technologies' => ['Angular', 'Bootstrap', 'PHP'],
            'githubUrl' => 'https://github.com/yourusername/portfolio',
            'liveUrl' => 'https://yourdomain.com'
        ]
    ];
}

/**
 * Get skills data
 */
function get_skills() {
    return [
        [
            'category' => 'Frontend',
            'skills' => [
                ['name' => 'HTML/CSS', 'level' => 'Expert'],
                ['name' => 'JavaScript', 'level' => 'Expert'],
                ['name' => 'Angular', 'level' => 'Advanced'],
                ['name' => 'Vue.js', 'level' => 'Advanced'],
                ['name' => 'React', 'level' => 'Intermediate']
            ]
        ],
        [
            'category' => 'Backend',
            'skills' => [
                ['name' => 'PHP', 'level' => 'Expert'],
                ['name' => 'Laravel', 'level' => 'Advanced'],
                ['name' => 'Node.js', 'level' => 'Intermediate'],
                ['name' => 'MySQL', 'level' => 'Advanced'],
                ['name' => 'PostgreSQL', 'level' => 'Intermediate']
            ]
        ],
        [
            'category' => 'DevOps',
            'skills' => [
                ['name' => 'Git', 'level' => 'Advanced'],
                ['name' => 'Docker', 'level' => 'Intermediate'],
                ['name' => 'Linux', 'level' => 'Advanced'],
                ['name' => 'CI/CD', 'level' => 'Intermediate']
            ]
        ]
    ];
}

/**
 * Get testimonials data
 */
function get_testimonials() {
    return [
        [
            'id' => 1,
            'name' => 'John Doe',
            'role' => 'CEO, Tech Company',
            'image' => 'assets/testimonials/john.jpg',
            'rating' => 5,
            'text' => 'Excellent work! Delivered the project on time and exceeded expectations.',
            'date' => '2024-10-15'
        ],
        [
            'id' => 2,
            'name' => 'Jane Smith',
            'role' => 'Product Manager',
            'image' => 'assets/testimonials/jane.jpg',
            'rating' => 5,
            'text' => 'Great attention to detail and very responsive to feedback.',
            'date' => '2024-09-20'
        ]
    ];
}
