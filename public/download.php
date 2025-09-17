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

		// Créer un dossier tmp sécurisé si inexistant
		$tmpRoot = realpath(__DIR__ . '/../tmp') ?: __DIR__ . '/../tmp';
		if (!is_dir($tmpRoot)) mkdir($tmpRoot, 0777, true);
		$htaccess = $tmpRoot . '/.htaccess';
		if (!file_exists($htaccess)) file_put_contents($htaccess, "Deny from all\n");

		// Analyse HTML pour détecter les images
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$images = $dom->getElementsByTagName('img');
		$imageCount = 0;
		$imagesToCopy = [];

		// Parcourir les images pour voir si elles existent sur le serveur
		foreach ($images as $img) {
			$src = $img->getAttribute('src');
			if (preg_match('~https?://[^/]+/mdwriter/public/images/([^/]+)/([^/?]+)$~', $src, $m)) {
				$absolutePath = __DIR__ . "/../public/images/{$m[1]}/{$m[2]}";
				if (file_exists($absolutePath)) {
					$imagesToCopy[] = ['src' => $src, 'path' => $absolutePath, 'basename' => $m[2]];
					$imageCount++;
				}
			}
		}

		// Si des images existent, créer tmpDir et dossier images
		if ($imageCount > 0) {
			$tmpDir = $tmpRoot . "/export_" . uniqid();
			mkdir($tmpDir, 0777, true);
			$imagesDir = $tmpDir . "/images";
			mkdir($imagesDir, 0777, true);

			// Copier les images et mettre à jour le HTML
			foreach ($imagesToCopy as $imgInfo) {
				copy($imgInfo['path'], $imagesDir . "/" . $imgInfo['basename']);
				foreach ($images as $img) {
					if ($img->getAttribute('src') === $imgInfo['src']) {
						$img->setAttribute('src', 'images/' . $imgInfo['basename']);
					}
				}
			}
		}

		// Sauvegarder le HTML modifié
		$html = $dom->saveHTML();
		$htmlFile = ($imageCount > 0 ? $tmpDir : $tmpRoot) . "/rapport.html";
		file_put_contents($htmlFile, $html);

		if ($imageCount > 0) {
			// Création du ZIP
			$zipPath = $tmpRoot . "/export_" . uniqid() . ".zip";
			$zip = new ZipArchive();
			if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
				$zip->addFile($htmlFile, "rapport.html");

				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($imagesDir, FilesystemIterator::SKIP_DOTS),
					RecursiveIteratorIterator::LEAVES_ONLY
				);

				foreach ($files as $file) {
					$zip->addFile($file->getRealPath(), 'images/' . $file->getBasename());
				}

				$zip->close();

				$filename = preg_replace('/[<>:"\/\\\\|?*]+/', '_', $project['title'] ?? 'rapport') . '.zip';
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header('Content-Length: ' . filesize($zipPath));
				readfile($zipPath);
				unlink($zipPath);
			} else {
				exit("Impossible de créer l’archive ZIP");
			}

			// Nettoyage tmpDir
			array_map('unlink', glob("$imagesDir/*.*"));
			rmdir($imagesDir);
			unlink($htmlFile);
			rmdir($tmpDir);
		} else {
			// Pas d'image → téléchargement direct du HTML
			$filename = preg_replace('/[<>:"\/\\\\|?*]+/', '_', $project['title'] ?? 'rapport') . '.html';
			header('Content-Type: text/html');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Length: ' . filesize($htmlFile));
			readfile($htmlFile);
			unlink($htmlFile);
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