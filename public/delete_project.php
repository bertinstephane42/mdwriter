<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
require_once __DIR__ . '/../inc/projects.php';

if (isset($_GET['id'])) {
    $projectId = $_GET['id'];
    deleteProject($projectId, $_SESSION['user']); // il faut que ta fonction supprime bien le projet pour cet utilisateur
}

header("Location: dashboard.php");
exit;