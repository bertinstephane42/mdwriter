<?php
session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(["success" => false, "error" => "Non autorisé"]);
    exit;
}

$user = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['user']); // sécurité basique

$targetDir = __DIR__ . "/../images/$user/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Vérifier si un fichier est bien envoyé
if (!isset($_FILES['image'])) {
    echo json_encode(["success" => false, "error" => "Aucun fichier reçu"]);
    exit;
}

$file = $_FILES['image'];

// ✅ Limite de taille : 2 Mo
$maxSize = 2 * 1024 * 1024; // 2 Mo en octets
if ($file['size'] > $maxSize) {
    echo json_encode(["success" => false, "error" => "Image trop volumineuse (max 2 Mo)"]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ["jpg","jpeg","png","gif","webp"];

if (!in_array($ext, $allowed)) {
    echo json_encode(["success" => false, "error" => "Format non supporté"]);
    exit;
}

$filename = uniqid("img_", true) . "." . $ext;
$targetFile = $targetDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    // 🔗 URL absolue
    $baseUrl = "https://cours-reseaux.fr/mdwriter/public/images/$user/";
    $url = $baseUrl . $filename;

    echo json_encode(["success" => true, "url" => $url]);
} else {
    echo json_encode(["success" => false, "error" => "Échec upload"]);
}