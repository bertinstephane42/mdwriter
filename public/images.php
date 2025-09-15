<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Sécuriser le nom du dossier utilisateur
$user = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['user']);

$dir = __DIR__ . "/images/$user/";
$baseUrl = "https://cours-reseaux.fr/mdwriter/public/images/$user/";

// S'assurer que le dossier existe
if (!file_exists($dir)) {
    mkdir($dir, 0755, true);
}

// Extensions autorisées
$allowed = ["jpg","jpeg","png","gif","webp"];

// Lister les fichiers
$files = array_diff(scandir($dir), [".", ".."]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Galerie d'images</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f9f9f9;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }
        .item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .item img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .item button {
            margin-top: 8px;
            padding: 5px 10px;
            font-size: 0.9em;
            border: none;
            border-radius: 4px;
            background: #007bff;
            color: white;
            cursor: pointer;
        }
        .item button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Galerie d'images de <?php echo htmlspecialchars($user); ?></h1>
    <div class="gallery">
        <?php foreach ($files as $file): ?>
            <?php 
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;
                $url = $baseUrl . rawurlencode($file);
            ?>
            <div class="item">
                <img src="images/<?php echo htmlspecialchars($user) . '/' . htmlspecialchars($file); ?>" alt="">
                <button onclick="copyUrl('<?php echo $url; ?>')">Copier l’URL</button>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function copyUrl(url) {
            navigator.clipboard.writeText(url).then(() => {
                alert("URL copiée : " + url);
            }).catch(err => {
                alert("Erreur lors de la copie : " + err);
            });
        }
    </script>
</body>
</html>