<?php
/**
 * Projects API
 * Handles CRUD operations for projects
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin-config.php';
require_once __DIR__ . '/utils.php';

handle_cors();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';


if ($method === 'GET' && empty($action)) {
    get_projects();
    exit;
} elseif ($method === 'GET' && $action === 'single') {
    get_project($_GET['id'] ?? null);
    exit;
}


require_once __DIR__ . '/auth.php';
require_auth();

switch ($method) {
    case 'POST':
        create_project();
        break;
    case 'PUT':
        update_project();
        break;
    case 'DELETE':
        delete_project();
        break;
    default:
        send_json_response(['error' => 'Method not allowed'], 405);
}

/**
 * Get all projects
 */
function get_projects() {
    $projects = load_projects();
    send_json_response([
        'success' => true,
        'data' => $projects
    ]);
}

/**
 * Get single project
 */
function get_project($id) {
    if (!$id) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    $projects = load_projects();
    $project = array_filter($projects, function($p) use ($id) {
        return $p['id'] == $id;
    });
    
    if (empty($project)) {
        send_json_response(['error' => 'Project not found'], 404);
    }
    
    send_json_response([
        'success' => true,
        'data' => array_values($project)[0]
    ]);
}


function create_project() {
    $input = json_decode(file_get_contents('php://input'), true);
    
   
    $required = ['title', 'description', 'category'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            send_json_response(['error' => ucfirst($field) . ' is required'], 400);
        }
    }
    
    $projects = load_projects();
    
    
    $max_id = 0;
    foreach ($projects as $project) {
        if ($project['id'] > $max_id) {
            $max_id = $project['id'];
        }
    }
    
    $new_project = [
        'id' => $max_id + 1,
        'title' => sanitize_input($input['title']),
        'description' => sanitize_input($input['description']),
        'image' => $input['image'] ?? '',
        'technologies' => $input['technologies'] ?? [],
        'category' => sanitize_input($input['category']),
        'featured' => $input['featured'] ?? false,
        'githubUrl' => $input['githubUrl'] ?? '',
        'liveUrl' => $input['liveUrl'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $projects[] = $new_project;
    save_projects($projects);
    
    log_message("Project created: " . $new_project['title']);
    
    send_json_response([
        'success' => true,
        'message' => 'Project created successfully',
        'data' => $new_project
    ], 201);
}


function update_project() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    $projects = load_projects();
    $found = false;
    
    foreach ($projects as $key => $project) {
        if ($project['id'] == $input['id']) {
            $projects[$key] = array_merge($project, [
                'title' => sanitize_input($input['title'] ?? $project['title']),
                'description' => sanitize_input($input['description'] ?? $project['description']),
                'image' => $input['image'] ?? $project['image'],
                'technologies' => $input['technologies'] ?? $project['technologies'],
                'category' => sanitize_input($input['category'] ?? $project['category']),
                'featured' => $input['featured'] ?? $project['featured'],
                'githubUrl' => $input['githubUrl'] ?? $project['githubUrl'],
                'liveUrl' => $input['liveUrl'] ?? $project['liveUrl'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        send_json_response(['error' => 'Project not found'], 404);
    }
    
    save_projects($projects);
    
    log_message("Project updated: ID " . $input['id']);
    
    send_json_response([
        'success' => true,
        'message' => 'Project updated successfully',
        'data' => $projects[$key]
    ]);
}


function delete_project() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    $projects = load_projects();
    $initial_count = count($projects);
    
    $projects = array_filter($projects, function($project) use ($id) {
        return $project['id'] != $id;
    });
    
    if (count($projects) === $initial_count) {
        send_json_response(['error' => 'Project not found'], 404);
    }
    
    save_projects(array_values($projects));
    
    log_message("Project deleted: ID $id");
    
    send_json_response([
        'success' => true,
        'message' => 'Project deleted successfully'
    ]);
}


function load_projects() {
    if (!file_exists(PROJECTS_FILE)) {
        return [];
    }
    
    $content = file_get_contents(PROJECTS_FILE);
    return json_decode($content, true) ?? [];
}


function save_projects($projects) {
    file_put_contents(PROJECTS_FILE, json_encode($projects, JSON_PRETTY_PRINT));
}
