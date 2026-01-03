<?php
/**
 * Projects API - CRUD Operations
 * 
 * This API handles all project-related operations:
 * - GET /projects - Retrieve all published projects
 * - GET /projects?action=single&id=X - Get a specific project by ID
 * - POST /projects - Create a new project (requires authentication)
 * - PUT /projects - Update an existing project (requires authentication)
 * - DELETE /projects?id=X - Delete a project (requires authentication)
 * 
 * Data Storage:
 * - Primary: MySQL database using mysqli (recommended)
 * - Fallback: JSON file storage if database is unavailable
 * 
 * Security:
 * - Public endpoints: GET requests (view projects)
 * - Protected endpoints: POST, PUT, DELETE (require authentication token)
 * - Input sanitization to prevent XSS attacks
 * - Prepared statements to prevent SQL injection
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin-config.php';
require_once __DIR__ . '/utils.php';

// Enable CORS for frontend API access
handle_cors();

// Get HTTP method (GET, POST, PUT, DELETE)
$method = $_SERVER['REQUEST_METHOD'];
// Get the action parameter from URL (e.g., ?action=single)
$action = $_GET['action'] ?? '';

// Database connection using mysqli (improved version)
$USE_DB = false;
$mysqli = null;

// Try to connect to MySQL database
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        // Create new mysqli connection
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check for connection errors
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        // Set character encoding to UTF-8 for proper text handling
        $mysqli->set_charset("utf8mb4");
        
        // Connection successful
        $USE_DB = true;
    }
} catch (Exception $e) {
    // If database connection fails, fall back to file-based storage
    $USE_DB = false;
    $mysqli = null;
}

// Public endpoints (no authentication required)
if ($method === 'GET' && empty($action)) {
    get_projects();
    exit;
} elseif ($method === 'GET' && $action === 'single') {
    get_project($_GET['id'] ?? null);
    exit;
}

// All other operations require authentication
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
 * Get all published projects from the database or file
 * Returns a list of all published projects
 */
function get_projects() {
    global $USE_DB, $mysqli;
    
    // If database is available, fetch from there
    if ($USE_DB && $mysqli) {
        // Query to get all published projects, ordered by newest first
        $query = 'SELECT id, title, slug, description, category, image, live_url, github_url, 
                        tech_tags, featured, is_published, created_at, updated_at 
                 FROM projects 
                 WHERE is_published = 1 
                 ORDER BY created_at DESC';
        
        $result = $mysqli->query($query);
        
        if ($result) {
            $rows = [];
            // Fetch all rows as associative arrays
            while ($row = $result->fetch_assoc()) {
                // Convert comma-separated tech_tags to array for easier use
                $row['technologies'] = $row['tech_tags'] ? explode(',', $row['tech_tags']) : [];
                $rows[] = $row;
            }
            
            send_json_response(['success' => true, 'data' => $rows]);
        }
    }

    // If no database, use file-based storage
    $projects = load_projects();
    send_json_response(['success' => true, 'data' => $projects]);
}

/**
 * Get a single project by its ID
 * @param int $id - The project ID to retrieve
 */
function get_project($id) {
    // Validate that ID is provided
    if (!$id) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    global $USE_DB, $mysqli;
    
    // If database is available, fetch from there
    if ($USE_DB && $mysqli) {
        // Prepare statement to prevent SQL injection
        $stmt = $mysqli->prepare('SELECT id, title, slug, description, category, image, live_url, 
                                         github_url, tech_tags, featured, is_published, created_at, updated_at 
                                  FROM projects 
                                  WHERE id = ?');
        
        // Bind the ID parameter
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // Get result
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        
        // Check if project exists
        if (!$project) {
            send_json_response(['error' => 'Project not found'], 404);
        }
        
        // Convert tech_tags to array
        $project['technologies'] = $project['tech_tags'] ? explode(',', $project['tech_tags']) : [];
        
        send_json_response(['success' => true, 'data' => $project]);
    }

    // Fallback to file-based storage
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

/**
 * Create a new project
 * Accepts JSON input with project details
 */
function create_project() {
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate that all required fields are present
    $required = ['title', 'description', 'category'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            send_json_response(['error' => ucfirst($field) . ' is required'], 400);
        }
    }
    
    global $USE_DB, $mysqli;
    
    // Sanitize inputs to prevent XSS attacks
    $title = sanitize_input($input['title']);
    $description = sanitize_input($input['description']);
    $image = $input['image'] ?? '';
    $technologies = $input['technologies'] ?? [];
    $category = sanitize_input($input['category'] ?? '');
    $featured = !empty($input['featured']) ? 1 : 0;
    $github = $input['githubUrl'] ?? '';
    $live = $input['liveUrl'] ?? '';

    // If database is available, insert there
    if ($USE_DB && $mysqli) {
        // Generate URL-friendly slug from title
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($title)));
        $slug = trim($slug, '-');
        // If slug is empty, generate a random one
        if (empty($slug)) {
            $slug = 'proj-' . bin2hex(random_bytes(4));
        }

        // Convert technologies array to comma-separated string for database
        $techs = is_array($technologies) ? implode(',', $technologies) : $technologies;

        // Prepare INSERT statement to prevent SQL injection
        $stmt = $mysqli->prepare('INSERT INTO projects (title, slug, description, category, image, 
                                                        live_url, github_url, tech_tags, featured, 
                                                        is_published, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
        
        // Bind parameters: s = string, i = integer
        $stmt->bind_param('ssssssssi', $title, $slug, $description, $category, $image, 
                         $live, $github, $techs, $featured);
        
        // Execute the insert
        $stmt->execute();

        // Get the auto-generated ID
        $id = $mysqli->insert_id;
        
        // Prepare response data
        $new_project = [
            'id' => $id, 
            'title' => $title, 
            'description' => $description, 
            'image' => $image, 
            'technologies' => is_array($technologies) ? $technologies : ($technologies ? explode(',', $technologies) : []), 
            'category' => $category, 
            'featured' => $featured, 
            'githubUrl' => $github, 
            'liveUrl' => $live, 
            'created_at' => date('Y-m-d H:i:s')
        ];

        log_message("Project created (DB): " . $title);
        send_json_response(['success' => true, 'message' => 'Project created successfully', 'data' => $new_project], 201);
    }

    // Fallback to file storage
    $projects = load_projects();
    // Generate new ID
    $max_id = 0;
    foreach ($projects as $project) {
        if ($project['id'] > $max_id) {
            $max_id = $project['id'];
        }
    }

    $new_project = [
        'id' => $max_id + 1,
        'title' => $title,
        'description' => $description,
        'image' => $image,
        'technologies' => $technologies,
        'category' => $category,
        'featured' => (bool)$featured,
        'githubUrl' => $github,
        'liveUrl' => $live,
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

/**
 * Update an existing project
 * Accepts JSON input with updated project details
 */
function update_project() {
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate that project ID is provided
    if (empty($input['id'])) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    $id = $input['id'];
    global $USE_DB, $mysqli;

    // If database is available, update there
    if ($USE_DB && $mysqli) {
        // First, fetch the existing project to merge with new data
        $stmt = $mysqli->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        
        // Check if project exists
        if (!$existing) {
            send_json_response(['error' => 'Project not found'], 404);
        }

        // Use new values if provided, otherwise keep existing values
        $title = sanitize_input($input['title'] ?? $existing['title']);
        $description = sanitize_input($input['description'] ?? $existing['description']);
        $image = $input['image'] ?? $existing['image'];
        $technologies = $input['technologies'] ?? ($existing['tech_tags'] ? explode(',', $existing['tech_tags']) : []);
        $category = sanitize_input($input['category'] ?? $existing['category']);
        $featured = isset($input['featured']) ? (!empty($input['featured']) ? 1 : 0) : $existing['featured'];
        $github = $input['githubUrl'] ?? $existing['github_url'] ?? $existing['githubUrl'] ?? '';
        $live = $input['liveUrl'] ?? $existing['live_url'] ?? $existing['liveUrl'] ?? '';

        // Convert technologies array to comma-separated string
        $techs = is_array($technologies) ? implode(',', $technologies) : $technologies;

        // Prepare UPDATE statement
        $upd = $mysqli->prepare('UPDATE projects 
                                SET title = ?, description = ?, category = ?, image = ?, 
                                    live_url = ?, github_url = ?, tech_tags = ?, featured = ?, 
                                    updated_at = NOW() 
                                WHERE id = ?');
        
        // Bind parameters
        $upd->bind_param('sssssssii', $title, $description, $category, $image, 
                        $live, $github, $techs, $featured, $id);
        
        // Execute update
        $upd->execute();

        log_message("Project updated (DB): ID " . $id);
        send_json_response(['success' => true, 'message' => 'Project updated successfully', 
                           'data' => ['id' => $id, 'title' => $title]]);
    }

    // Fallback to JSON file update
    $projects = load_projects();
    $found = false;
    
    foreach ($projects as $key => $project) {
        if ($project['id'] == $id) {
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
    log_message("Project updated: ID " . $id);
    send_json_response(['success' => true, 'message' => 'Project updated successfully', 'data' => $projects[$key]]);
}

/**
 * Delete a project by ID
 * Permanently removes project from database or file
 */
function delete_project() {
    // Get project ID from URL parameter
    $id = $_GET['id'] ?? null;
    
    // Validate that ID is provided
    if (!$id) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    global $USE_DB, $mysqli;
    
    // If database is available, delete from there
    if ($USE_DB && $mysqli) {
        // Prepare DELETE statement to prevent SQL injection
        $stmt = $mysqli->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // Check if any row was actually deleted
        if ($stmt->affected_rows === 0) {
            send_json_response(['error' => 'Project not found'], 404);
        }
        
        log_message("Project deleted (DB): ID $id");
        send_json_response(['success' => true, 'message' => 'Project deleted successfully']);
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

/**
 * Load projects from file
 */
function load_projects() {
    if (!file_exists(PROJECTS_FILE)) {
        return [];
    }
    
    $content = file_get_contents(PROJECTS_FILE);
    return json_decode($content, true) ?? [];
}

/**
 * Save projects to file
 */
function save_projects($projects) {
    file_put_contents(PROJECTS_FILE, json_encode($projects, JSON_PRETTY_PRINT));
}
