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

switch ($format) {
    case 'md':
        $content = $project['markdown'] ?? '';
        $filename = ($project['title'] ?? 'rapport') . '.md';
        $mime = 'text/markdown';

        // Headers + sortie directe
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;

	case 'html':
		$Parsedown = new Parsedown();
		$htmlContent = $Parsedown->text($project['markdown'] ?? '');

		// HTML complet initial
		$html = "<!DOCTYPE html>
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

		// Création d’un dossier ../tmp protégé par .htaccess
		$tmpRoot = realpath(__DIR__ . '/../tmp');
		if (!$tmpRoot) {
			$tmpRoot = __DIR__ . '/../tmp';
			mkdir($tmpRoot, 0777, true);
		}

		// Ajouter un .htaccess pour interdire l'accès web
		$htaccess = $tmpRoot . '/.htaccess';
		if (!file_exists($htaccess)) {
			file_put_contents($htaccess, "Deny from all\n");
		}

		// Créer un sous-dossier unique pour cet export
		$tmpDir = $tmpRoot . "/export_" . uniqid();
		mkdir($tmpDir, 0777, true);
		$imagesDir = $tmpDir . "/images";
		mkdir($imagesDir, 0777, true);

		// Utilisation de DOMDocument pour gérer les images
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$images = $dom->getElementsByTagName('img');
		foreach ($images as $img) {
			$src = $img->getAttribute('src');

			// Debug (à retirer ensuite)
			// error_log("SRC détecté : " . $src);

			// Reconnaître les URL absolues vers /public/images/<user>/<fichier>
			if (preg_match('~https?://[^/]+/mdwriter/public/images/([^/]+)/([^/?]+)$~', $src, $m)) {
				$user = $m[1];
				$basename = $m[2];

				$absolutePath = __DIR__ . "/../public/images/$user/$basename";

				if (file_exists($absolutePath)) {
					// Copier l'image dans le dossier temporaire
					copy($absolutePath, $imagesDir . "/" . $basename);

					// Modifier le src pour pointer vers le dossier images/ du zip
					$img->setAttribute('src', 'images/' . $basename);
				}
			}
		}

		// Sauvegarder le HTML modifié
		$html = $dom->saveHTML();
		$htmlFile = "$tmpDir/rapport.html";
		file_put_contents($htmlFile, $html);

		// Création du ZIP
		$zipPath = $tmpRoot . "/export_" . uniqid() . ".zip";
		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
			// Ajouter le HTML
			$zip->addFile($htmlFile, "rapport.html");

			// Ajouter les images
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($imagesDir, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $file) {
				if ($file->isFile()) {
					$filePath = $file->getRealPath();
					$relPath = 'images/' . $file->getBasename();
					$zip->addFile($filePath, $relPath);
				}
			}

			$zip->close();

			// Téléchargement du ZIP
			$filename = preg_replace('/[<>:"\/\\\\|?*]+/', '_', $project['title'] ?? 'rapport') . '.zip';
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Length: ' . filesize($zipPath));
			readfile($zipPath);

			// Nettoyage du zip
			unlink($zipPath);

			// Nettoyage du sous-dossier export
			array_map('unlink', glob("$imagesDir/*.*"));
			rmdir($imagesDir);
			unlink($htmlFile);
			rmdir($tmpDir);

			// Nettoyage de tous les autres anciens sous-dossiers de ../tmp (sécu)
			foreach (glob($tmpRoot . "/export_*") as $old) {
				if (is_dir($old)) {
					array_map('unlink', glob("$old/*.*"));
					@rmdir($old);
				}
			}

			exit;
		} else {
			exit("Impossible de créer l’archive ZIP");
		}
	break;

	case 'htmlraw':
		$Parsedown = new Parsedown();
		$htmlContent = $Parsedown->text($project['markdown'] ?? '');

		// Charger HTML dans DOMDocument pour gérer les images
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		// Parcourir toutes les images et les convertir en base64
		$images = $dom->getElementsByTagName('img');
		foreach ($images as $img) {
			$src = $img->getAttribute('src');

			// Si déjà en base64 -> ignorer
			if (strpos($src, 'data:image') === 0) {
				continue;
			}

			$absolutePath = null;
			$mime = null;
			$data = null;

			// Cas 1 : image hébergée sur ton domaine
			if (preg_match('~^https?://cours-reseaux\.fr/mdwriter/public/images/([^/]+)/([^/?#]+)$~', $src, $m)) {
				$absolutePath = __DIR__ . "/../public/images/{$m[1]}/{$m[2]}";
			}
			// Cas 2 : chemin relatif interne (/mdwriter/public/images/...)
			elseif (preg_match('~^/mdwriter/public/images/([^/]+)/([^/?#]+)$~', $src, $m)) {
				$absolutePath = __DIR__ . "/../public/images/{$m[1]}/{$m[2]}";
			}
			// Cas 3 : URL externe complète
			elseif (preg_match('~^https?://~', $src)) {
				// Essayer de télécharger l’image distante
				if (ini_get('allow_url_fopen')) {
					$data = @file_get_contents($src);
				} else {
					// Fallback avec cURL si allow_url_fopen désactivé
					$ch = curl_init($src);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
					$data = curl_exec($ch);
					curl_close($ch);
				}

				// Détecter le type MIME de l'image distante
				if ($data !== false) {
					$imageInfo = @getimagesizefromstring($data);
					if ($imageInfo && isset($imageInfo['mime'])) {
						$mime = $imageInfo['mime'];
					} else {
						$mime = 'application/octet-stream';
					}
				}
			}

			// Charger si fichier local
			if ($absolutePath && file_exists($absolutePath)) {
				$ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
				switch ($ext) {
					case 'jpg':
					case 'jpeg':
						$mime = 'image/jpeg';
						break;
					case 'png':
						$mime = 'image/png';
						break;
					case 'gif':
						$mime = 'image/gif';
						break;
					case 'svg':
						// Convertir le SVG en PNG avec Imagick si dispo
						if (extension_loaded('imagick')) {
							$imagick = new Imagick();
							$imagick->readImage($absolutePath);
							$imagick->setImageFormat("png");
							$mime = 'image/png';
							$data = $imagick->getImageBlob();
						} else {
							// fallback: ignorer l'image si pas de support SVG
							$data = null;
						}
						break;
					default:
						$mime = 'application/octet-stream';
				}
				$data = file_get_contents($absolutePath);
			}

			// Si on a bien des données image, remplacer par base64
			if ($data !== null && $data !== false) {
				$base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
				$img->setAttribute('src', $base64);
			}
		}

		// Recomposer le HTML complet
		$htmlContent = $dom->saveHTML();

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
	img { max-width: 100%; height: auto; }
	</style>
	</head>
	<body>
	$htmlContent
	</body>
	</html>";

		$filename = preg_replace('/[<>:"\/\\\\|?*]+/', '_', $project['title'] ?? 'rapport') . '.html';
		$mime = 'text/html';

		header('Content-Description: File Transfer');
		header("Content-Type: $mime; charset=utf-8");
		header('Content-Disposition: inline; filename="' . $filename . '"');
		echo $content;
		exit;

    case 'json':
        $content = json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = ($project['title'] ?? 'rapport') . '.json';
        $mime = 'application/json';

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;

    default:
        http_response_code(400);
        exit("Format inconnu");
}