<?php
session_start();
require_once __DIR__ . '/../inc/projects.php';
require_once __DIR__ . '/../inc/auth.php';

if (!isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['projectFile'])) {
    $file = $_FILES['projectFile'];

    if ($file['error'] === UPLOAD_ERR_OK && mime_content_type($file['tmp_name']) === 'application/json') {
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

        if ($data === null) {
            die("Fichier JSON invalide.");
        }

        // Nom projet imposé : proj_XXXXXXXXXXXXX (13 caractères)
        $newId = "proj_" . substr(md5(uniqid('', true)), 0, 9);

        $user = $_SESSION['user'];
        $projects = listProjects($user);

        // Vérifier si un projet du même titre existe déjà
        $title = $data['title'] ?? 'Sans titre';
        $exists = false;
        foreach ($projects as $p) {
            if ($p['title'] === $title) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            // Demander confirmation avant suppression
            echo "<script>
                if (confirm('Un projet portant ce titre existe déjà. Voulez-vous le remplacer ?')) {
                    window.location.href = 'import_project.php?confirm=1&title=" . urlencode($title) . "&tmp=" . urlencode($file['tmp_name']) . "';
                } else {
                    window.location.href = 'dashboard.php';
                }
            </script>";
            exit;
        }

        // Sauvegarder le projet importé
        importProject($data);
        header("Location: dashboard.php");
        exit;
    }
}

// Confirmation après le prompt JS
if (isset($_GET['confirm']) && $_GET['confirm'] == 1 && isset($_GET['title']) && isset($_GET['tmp'])) {
    $user = $_SESSION['user'];
    $content = file_get_contents($_GET['tmp']);
    $data = json_decode($content, true);
    if ($data) {
        $newId = "proj_" . substr(md5(uniqid('', true)), 0, 9);
        saveProject($user, $newId, $data);
    }
    header("Location: dashboard.php");
    exit;
}
?>
