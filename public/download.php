<?php
session_start();
require_once __DIR__ . '/../inc/projects.php';
require_once __DIR__ . '/../inc/parsedown.php'; // Parser Markdown

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit("Accès refusé");
}

$id = $_GET['id'] ?? '';
$format = $_GET['format'] ?? 'md';

// Charger le projet
$project = loadProject($id);
if (!$project) {
    http_response_code(404);
    exit("Projet introuvable");
}

// Générer le contenu selon le format
switch ($format) {
    case 'md':
        $content = $project['markdown'] ?? '';
        $filename = ($project['title'] ?? 'rapport') . '.md';
        $mime = 'text/markdown';
        break;

    case 'html':
        $Parsedown = new Parsedown();
        $htmlContent = $Parsedown->text($project['markdown'] ?? '');

        // HTML complet avec style léger
        $content = "<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>
<title>" . htmlspecialchars($project['title'] ?? 'Rapport') . "</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1,h2,h3,h4,h5,h6 { color: #333; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
code { background: #eee; padding: 2px 4px; border-radius: 3px; }
ul, ol { margin-left: 20px; }
blockquote { border-left: 4px solid #ccc; padding-left: 10px; color: #666; margin: 10px 0; }
</style>
</head>
<body>
$htmlContent
</body>
</html>";

        $filename = ($project['title'] ?? 'rapport') . '.html';
        $mime = 'text/html';
        break;

    case 'json':
        $content = json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = ($project['title'] ?? 'rapport') . '.json';
        $mime = 'application/json';
        break;

    default:
        http_response_code(400);
        exit("Format inconnu");
}

// Headers pour téléchargement
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($content));

echo $content;
exit;