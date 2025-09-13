<?php
session_start();
require_once __DIR__ . '/../inc/auth.php';

if (!is_admin()) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$logFile = __DIR__ . '/../logs/auth.log';
if (!file_exists($logFile)) {
    echo "Aucun journal disponible.";
    exit;
}

// Lire le contenu du log en toute sécurité
$content = file_get_contents($logFile);
echo nl2br(htmlspecialchars($content));