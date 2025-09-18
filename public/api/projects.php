<?php
session_start();
require_once __DIR__ . '/../../inc/projects.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $projectId = $_POST['id'] ?? null;
    $title = $_POST['title'] ?? '';
    $markdown = $_POST['markdown'] ?? '';

    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Titre vide']);
        exit;
    }

    try {
        $savedProject = saveOrUpdateProject($projectId, $title, $markdown);
        echo json_encode(['success' => true, 'id' => $savedProject['id']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Ajouter d'autres actions si besoin
echo json_encode(['success' => false, 'error' => 'Action inconnue']);