<?php
/**
 * Migrate projects from JSON file to MySQL projects table
 * Run once after creating the database: http://localhost:8000/migrate-projects.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_mysqli.php';

function slugify($text) {
    $text = preg_replace('~[^
\pL0-9_]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9_]+~', '', $text);
    if (empty($text)) {
        return 'proj-' . bin2hex(random_bytes(4));
    }
    return $text;
}

try {
    $mysqli = get_mysqli_connection(true);

    $jsonFile = __DIR__ . '/data/projects.json';
    if (!file_exists($jsonFile)) {
        echo json_encode(['success' => false, 'error' => 'projects.json not found']);
        exit;
    }

    $content = file_get_contents($jsonFile);
    $projects = json_decode($content, true) ?? [];

    $inserted = 0;
    $skipped = 0;

    $checkStmt = $mysqli->prepare('SELECT COUNT(*) as cnt FROM projects WHERE slug = ? OR title = ?');
    $insStmt = $mysqli->prepare('INSERT INTO projects (title, slug, description, category, image, live_url, github_url, tech_tags, featured, is_published, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($projects as $p) {
        $title = $p['title'] ?? 'Untitled';
        $slug = $p['slug'] ?? slugify($title);
        $description = $p['description'] ?? '';
        $category = $p['category'] ?? null;
        $image = $p['image'] ?? null;
        $live = $p['liveUrl'] ?? $p['live_url'] ?? null;
        $github = $p['githubUrl'] ?? $p['repo_url'] ?? $p['github_url'] ?? null;
        $techs = isset($p['technologies']) && is_array($p['technologies']) ? implode(',', $p['technologies']) : ($p['tech_tags'] ?? null);
        $featured = !empty($p['featured']) ? 1 : 0;
        $is_published = 1;
        $created_at = $p['created_at'] ?? date('Y-m-d H:i:s');

        $checkStmt->bind_param('ss', $slug, $title);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row && ((int)$row['cnt'] > 0)) {
            $skipped++;
            continue;
        }

        $insStmt->bind_param(
            'ssssssssiis',
            $title,
            $slug,
            $description,
            $category,
            $image,
            $live,
            $github,
            $techs,
            $featured,
            $is_published,
            $created_at
        );
        $insStmt->execute();

        $inserted++;
    }

    echo json_encode(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
