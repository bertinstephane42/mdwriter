<?php
session_start();
require_once __DIR__ . '/../inc/projects.php';
require_once __DIR__ . '/../inc/auth.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    exit("Projet introuvable");
}

$project = loadProject($id);
if (!$project) {
    exit("Projet introuvable");
}

// Si c'est un modèle protégé → suppression interdite
if (!empty($project['isTemplate']) && $project['isTemplate'] === true) {
	header("Location: dashboard.php");
    exit("⚠️ Ce modèle est protégé et ne peut pas être supprimé.");
}

// ✅ Suppression normale
deleteProject($id);
header("Location: dashboard.php");
exit;