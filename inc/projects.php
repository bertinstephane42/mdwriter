<?php
session_start();

// Vérification utilisateur connecté
if (!isset($_SESSION['user'])) {
    if (ob_get_length()) ob_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Utilisateur non connecté."]);
    exit;
}

// Répertoires
function user_dir(): string {
    return __DIR__ . '/../storage/users/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['user']);
}

function projects_dir(): string {
    $dir = user_dir() . '/projects';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Liste tous les projets de l'utilisateur
 */
function listProjects(): array {
    $dir = projects_dir();
    $projects = [];

    if (!is_dir($dir)) return $projects;

    $files = glob($dir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) continue;
        $projects[] = [
            'id' => pathinfo($file, PATHINFO_FILENAME),
            'title' => htmlspecialchars($data['title'] ?? pathinfo($file, PATHINFO_FILENAME), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'date' => $data['date'] ?? date("Y-m-d H:i", filemtime($file)),
            'markdown' => $data['markdown'] ?? '',
			'isTemplate' => !empty($data['isTemplate'])
        ];
    }

    // Tri : d'abord les templates, ensuite le reste
    usort($projects, function($a, $b) {
        if ($a['isTemplate'] === $b['isTemplate']) {
            // Si les deux sont du même type, trier par date décroissante
            return strcmp($b['date'], $a['date']);
        }
        return $a['isTemplate'] ? -1 : 1; // templates en premier
    });

    return $projects;
}

/**
 * Sauvegarde un nouveau projet
 */
function saveProject(string $title, string $markdown): string {
    $title = trim($title);

    // Vérification du titre
    if ($title === '') {
        throw new InvalidArgumentException("Le titre est obligatoire.");
    }
    if (mb_strlen($title) > 25) {
        throw new InvalidArgumentException("Le titre ne doit pas dépasser 25 caractères.");
    }

    // Vérification de la taille du Markdown
    $markdown = is_string($markdown) ? $markdown : '';
    $maxLength = 500000; // 500 000 caractères (~0,5 Mo)
    if (mb_strlen($markdown) > $maxLength) {
        throw new InvalidArgumentException("Le contenu du projet est trop long. Limite : {$maxLength} caractères.");
    }

    $dir = projects_dir();

    // 🔒 Générer un ID unique garanti
    do {
        $id = "proj_" . bin2hex(random_bytes(8)); // 16 caractères aléatoires
        $file = "$dir/$id.json";
    } while (file_exists($file));

    $data = [
        'title' => $title,
        'markdown' => $markdown,
        'date' => date("Y-m-d H:i")
    ];

    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException("Impossible de sauvegarder le projet.");
    }

    return $id;
}

/**
 * Met à jour un projet existant
 */
function updateProject(string $id, string $title, string $markdown): void {
    $id = basename($id);
    $file = projects_dir() . "/$id.json";

    if (!file_exists($file)) {
        throw new RuntimeException("Le projet $id n'existe pas.");
    }

    $title = trim($title);

    // Vérification du titre
    if ($title === '') {
        throw new InvalidArgumentException("Le titre est obligatoire.");
    }
    if (mb_strlen($title) > 25) {
        throw new InvalidArgumentException("Le titre ne doit pas dépasser 25 caractères.");
    }

    // Vérification de la taille du Markdown
    $markdown = is_string($markdown) ? $markdown : '';
    $maxLength = 500000;
    if (mb_strlen($markdown) > $maxLength) {
        throw new InvalidArgumentException("Le contenu du projet est trop long. Limite : {$maxLength} caractères.");
    }

    $data = [
        'title' => $title,
        'markdown' => $markdown,
        'date' => date("Y-m-d H:i")
    ];

    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException("Impossible de mettre à jour le projet.");
    }
}

/**
 * Charge un projet spécifique de l'utilisateur courant
 */
function loadProject(string $id): ?array {
    $file = projects_dir() . '/' . basename($id) . '.json';
    if (!file_exists($file)) return null;

    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException("Impossible de lire le projet : $id");
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        throw new RuntimeException("Projet invalide ou corrompu : $id");
    }

    // Échapper le titre pour affichage
    if (isset($data['title'])) {
        $data['title'] = htmlspecialchars($data['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return $data;
}

/**
 * Supprime un projet
 */
function deleteProject(string $id): void {
    $file = projects_dir() . '/' . basename($id) . '.json';
    if (!file_exists($file)) {
        throw new RuntimeException("Le projet $id n'existe pas.");
    }
    unlink($file);
}

/**
 * Importe un projet (sécurisé) avec prise en charge de isTemplate
 */
function importProject(array $data): string {
    $dir = projects_dir();

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Impossible de créer le répertoire projets.");
    }

    // Extraction des champs autorisés
    $allowedKeys = ['title', 'markdown', 'date', 'isTemplate'];
    $projectData = array_intersect_key($data, array_flip($allowedKeys));

	// --- Validation et adaptation du titre pour clone ---
	$title = trim($projectData['title'] ?? 'Sans titre');

	// Ajouter "(clone)" uniquement si ce n'est pas déjà présent
	if (strpos($title, '(clone)') === false) {
		$title .= " (clone)";
	}

	// Tronquer le titre pour ne pas dépasser 25 caractères
	if (mb_strlen($title) > 25) {
		$title = mb_substr($title, 0, 25);
	}

	$projectData['title'] = $title;

    // --- Validation du Markdown ---
    $markdown = $projectData['markdown'] ?? '';
    $projectData['markdown'] = is_string($markdown) ? $markdown : '';
    $maxLength = 500000;
    if (mb_strlen($projectData['markdown']) > $maxLength) {
        throw new InvalidArgumentException("Le contenu du projet est trop long. Limite : {$maxLength} caractères.");
    }

    // --- Validation de la date ---
    $date = $projectData['date'] ?? date("Y-m-d H:i");
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $date)) {
        $date = date("Y-m-d H:i");
    }
    $projectData['date'] = $date;

    // --- Validation de isTemplate ---
    $projectData['isTemplate'] = !empty($projectData['isTemplate']);

    // --- Génération ID et sauvegarde ---
    $id = uniqid("proj_");
    $file = "$dir/$id.json";

    if (file_put_contents($file, json_encode($projectData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException("Impossible de sauvegarder le projet importé.");
    }

    return $id;
}

/**
 * API AJAX pour sauvegarder un projet
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header("Content-Type: application/json; charset=UTF-8");

    $title = trim($_POST['title'] ?? '');
    $markdown = $_POST['markdown'] ?? '';
    $id = $_POST['id'] ?? null;

    try {
        if ($title === '') throw new InvalidArgumentException("Le titre est obligatoire.");

        if ($id && file_exists(projects_dir() . '/' . basename($id) . '.json')) {
            updateProject($id, $title, $markdown);
        } else {
            $id = saveProject($title, $markdown);
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => true, "id" => $id]);
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}