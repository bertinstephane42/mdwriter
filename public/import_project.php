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

        // ✅ Vérification qu'aucun attribut non autorisé n'existe
        $allowedAttrs = array_merge($requiredAttrs, ['isTemplate', 'id']);
        $extraAttrs = array_diff(array_keys($data), $allowedAttrs);
        if (!empty($extraAttrs)) {
            die("Impossible d'importer : attributs non autorisés trouvés -> " . implode(', ', $extraAttrs));
        }

        $title = trim($data['title'] ?? 'Sans titre');

        // Vérifier si un projet avec ce titre existe déjà
        $existingTitles = array_column(listProjects($_SESSION['user']), 'title');
        if (in_array($title, $existingTitles)) {
            $suffix = " (clone)";
            $maxLen = 25;

            // Si le titre + suffixe dépasse la limite, on tronque le titre
            if (mb_strlen($title . $suffix) > $maxLen) {
                $title = mb_substr($title, 0, $maxLen - mb_strlen($suffix));
            }

            $title .= $suffix;
        }

        // Toujours s'assurer que le titre final ne dépasse pas 25
        if (mb_strlen($title) > 25) {
            $title = mb_substr($title, 0, 25);
        }

        $data['title'] = $title;

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