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

// Try to connect to the database; if successful we'll use DB-backed storage
$USE_DB = false;
$pdo = null;
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $USE_DB = true;
    }
} catch (PDOException $e) {
    // Fall back to file storage if DB not available
    $USE_DB = false;
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
 * Get all projects
 */
function get_projects() {
    global $USE_DB, $pdo;
    if ($USE_DB && $pdo) {
        $stmt = $pdo->query('SELECT id, title, slug, description, category, image, live_url, github_url, tech_tags, featured, is_published, created_at, updated_at FROM projects WHERE is_published = 1 ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Convert tech_tags to array for compatibility
        foreach ($rows as &$r) {
            $r['technologies'] = $r['tech_tags'] ? explode(',', $r['tech_tags']) : [];
        }
        send_json_response(['success' => true, 'data' => $rows]);
    }

    $projects = load_projects();
    send_json_response(['success' => true, 'data' => $projects]);
}

/**
 * Get single project
 */
function get_project($id) {
    if (!$id) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    global $USE_DB, $pdo;
    if ($USE_DB && $pdo) {
        $stmt = $pdo->prepare('SELECT id, title, slug, description, category, image, live_url, github_url, tech_tags, featured, is_published, created_at, updated_at FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) {
            send_json_response(['error' => 'Project not found'], 404);
        }
        $project['technologies'] = $project['tech_tags'] ? explode(',', $project['tech_tags']) : [];
        send_json_response(['success' => true, 'data' => $project]);
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

/**
 * Create new project
 */
function create_project() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['title', 'description', 'category'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            send_json_response(['error' => ucfirst($field) . ' is required'], 400);
        }
    }
    
    global $USE_DB, $pdo;
    $title = sanitize_input($input['title']);
    $description = sanitize_input($input['description']);
    $image = $input['image'] ?? '';
    $technologies = $input['technologies'] ?? [];
    $category = sanitize_input($input['category'] ?? '');
    $featured = !empty($input['featured']) ? 1 : 0;
    $github = $input['githubUrl'] ?? '';
    $live = $input['liveUrl'] ?? '';

    if ($USE_DB && $pdo) {
        // generate slug
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($title)));
        $slug = trim($slug, '-');
        if (empty($slug)) {
            $slug = 'proj-' . bin2hex(random_bytes(4));
        }

        $techs = is_array($technologies) ? implode(',', $technologies) : $technologies;

        $stmt = $pdo->prepare('INSERT INTO projects (title, slug, description, category, image, live_url, github_url, tech_tags, featured, is_published, created_at) VALUES (:title, :slug, :description, :category, :image, :live_url, :github_url, :tech_tags, :featured, 1, NOW())');
        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':description' => $description,
            ':category' => $category,
            ':image' => $image,
            ':live_url' => $live,
            ':github_url' => $github,
            ':tech_tags' => $techs,
            ':featured' => $featured
        ]);

        $id = $pdo->lastInsertId();
        $new_project = ['id' => $id, 'title' => $title, 'description' => $description, 'image' => $image, 'technologies' => is_array($technologies) ? $technologies : ($technologies ? explode(',', $technologies) : []), 'category' => $category, 'featured' => $featured, 'githubUrl' => $github, 'liveUrl' => $live, 'created_at' => date('Y-m-d H:i:s')];

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
 * Update existing project
 */
function update_project() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    global $USE_DB, $pdo;
    if (empty($input['id'])) {
        send_json_response(['error' => 'Project ID required'], 400);
    }

    $id = $input['id'];

    if ($USE_DB && $pdo) {
        // Fetch existing
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            send_json_response(['error' => 'Project not found'], 404);
        }

        $title = sanitize_input($input['title'] ?? $existing['title']);
        $description = sanitize_input($input['description'] ?? $existing['description']);
        $image = $input['image'] ?? $existing['image'];
        $technologies = $input['technologies'] ?? ($existing['tech_tags'] ? explode(',', $existing['tech_tags']) : []);
        $category = sanitize_input($input['category'] ?? $existing['category']);
        $featured = isset($input['featured']) ? (!empty($input['featured']) ? 1 : 0) : $existing['featured'];
        $github = $input['githubUrl'] ?? $existing['github_url'] ?? $existing['githubUrl'] ?? '';
        $live = $input['liveUrl'] ?? $existing['live_url'] ?? $existing['liveUrl'] ?? '';

        $techs = is_array($technologies) ? implode(',', $technologies) : $technologies;

        $upd = $pdo->prepare('UPDATE projects SET title = :title, description = :description, category = :category, image = :image, live_url = :live_url, github_url = :github_url, tech_tags = :tech_tags, featured = :featured, updated_at = NOW() WHERE id = :id');
        $upd->execute([
            ':title' => $title,
            ':description' => $description,
            ':category' => $category,
            ':image' => $image,
            ':live_url' => $live,
            ':github_url' => $github,
            ':tech_tags' => $techs,
            ':featured' => $featured,
            ':id' => $id
        ]);

        log_message("Project updated (DB): ID " . $id);
        send_json_response(['success' => true, 'message' => 'Project updated successfully', 'data' => ['id' => $id, 'title' => $title]]);
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
 * Delete project
 */
function delete_project() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        send_json_response(['error' => 'Project ID required'], 400);
    }
    
    global $USE_DB, $pdo;
    if ($USE_DB && $pdo) {
        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
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
