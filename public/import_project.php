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
		
		// ❌ Bloquer si isTemplate === true
        if (!empty($data['isTemplate'])) {
            die("Impossible d'importer un fichier JSON de type template.");
        }
		
		// ✅ Vérification des attributs obligatoires
        $requiredAttrs = ['title', 'markdown', 'date'];
        $missingAttrs = array_filter($requiredAttrs, fn($attr) => !isset($data[$attr]));
        if (!empty($missingAttrs)) {
            die("Impossible d'importer : attributs manquants dans le JSON -> " . implode(', ', $missingAttrs));
        }

        // Créer un nouvel ID pour le projet importé
        $newId = "proj_" . substr(md5(uniqid('', true)), 0, 9);

        // Optionnel : modifier le titre pour indiquer que c'est un clone
        $title = trim($data['title'] ?? 'Sans titre');
        $data['title'] = $title . " (clone)";

        // Sauvegarder le projet importé avec nouvel ID
        importProject($data);

        // Redirection vers le dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        die("Erreur lors de l'upload ou type de fichier invalide.");
    }
}
?>