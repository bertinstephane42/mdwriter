<?php
error_reporting(E_ALL & ~E_NOTICE); // désactive notices
ob_start(); // démarre le buffer pour capter toute sortie accidentelle
session_start();

// Vérification utilisateur connecté
if (!isset($_SESSION['user'])) {
    if (ob_get_length()) ob_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Utilisateur non connecté."]);
    exit;
}

// Répertoires
function user_dir() {
    return __DIR__ . '/../storage/users/' . $_SESSION['user'];
}

function projects_dir() {
    return user_dir() . '/projects';
}

/**
 * Liste tous les projets de l'utilisateur
 */
function listProjects($username = null) {
    $dir = projects_dir();
    $projects = [];

    if (!is_dir($dir)) return $projects;

    $files = glob($dir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        $projects[] = [
            'id' => pathinfo($file, PATHINFO_FILENAME),
            'title' => $data['title'] ?? pathinfo($file, PATHINFO_FILENAME),
            'date' => $data['date'] ?? date("Y-m-d H:i", filemtime($file)),
            'markdown' => $data['markdown'] ?? ''
        ];
    }
    return $projects;
}

/**
 * Sauvegarde un nouveau projet
 */
function saveProject($title, $markdown) {
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException("Le titre est obligatoire.");
    }

    $dir = projects_dir();
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $id = uniqid("proj_");
    $data = [
        'title' => $title,
        'markdown' => $markdown,
        'date' => date("Y-m-d H:i")
    ];
    file_put_contents("$dir/$id.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $id;
}

/**
 * Met à jour un projet existant
 */
function updateProject($id, $title, $markdown) {
    $dir = projects_dir();
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $id = basename($id);
    $file = "$dir/$id.json";

    $data = [
        'title' => $title,
        'markdown' => $markdown,
        'date' => date("Y-m-d H:i")
    ];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Charge un projet spécifique
 */
function loadProject($id) {
    $file = projects_dir() . '/' . basename($id) . '.json';
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

/**
 * Supprime un projet
 */
function deleteProject($id) {
    $file = projects_dir() . '/' . basename($id) . '.json';
    if (file_exists($file)) unlink($file);
}

/**
 * API AJAX pour sauvegarder un projet
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header("Content-Type: application/json; charset=UTF-8");

    $title = trim($_POST['title'] ?? '');
    $markdown = $_POST['markdown'] ?? '';
    $id = $_POST['id'] ?? null;

    try {
        if ($title === '') {
            throw new InvalidArgumentException("Le titre est obligatoire.");
        }

        if ($id && file_exists(projects_dir() . '/' . basename($id) . '.json')) {
            updateProject($id, $title, $markdown);
        } else {
            $id = saveProject($title, $markdown);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => true, "id" => $id]);
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}