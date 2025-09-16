<?php
session_start();
define('USER_FILE', __DIR__ . '/../storage/users/users.json');
define('AUTH_LOG', __DIR__ . '/../logs/auth.log');

// --- Gestion utilisateurs ---
function load_users() {
    if (!file_exists(USER_FILE)) return [];
    $content = @file_get_contents(USER_FILE);
    if ($content === false) {
        log_auth('system', "Impossible de lire le fichier utilisateur : " . USER_FILE);
        return [];
    }
    $users = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_auth('system', "Erreur JSON dans " . USER_FILE . " : " . json_last_error_msg());
        return [];
    }
    return is_array($users) ? $users : [];
}

function save_users($users) {
    $json = json_encode($users, JSON_PRETTY_PRINT);
    if ($json === false) {
        log_auth('system', "Erreur d'encodage JSON lors de save_users()");
        return false;
    }

    $dir = dirname(USER_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        log_auth('system', "Impossible de créer le répertoire pour USER_FILE : $dir");
        return false;
    }

    $fp = @fopen(USER_FILE, 'c+');
    if (!$fp) {
        log_auth('system', "Impossible d'ouvrir le fichier utilisateur pour écriture : " . USER_FILE);
        return false;
    }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        $written = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = ($written !== false);
    } else {
        log_auth('system', "Impossible d'obtenir le verrou sur " . USER_FILE);
    }
    fclose($fp);

    if ($ok) {
        @chmod(USER_FILE, 0640);
        return true;
    } else {
        log_auth('system', "Échec écriture dans " . USER_FILE);
        return false;
    }
}

// --- Fonction de journalisation ---
function log_auth($username, $action) {
    $date = date('Y-m-d H:i:s');
	$ip = $_SERVER['REMOTE_ADDR'] ?? 'IP inconnue';
    $line = "[$date] $username ($ip): $action\n";
    file_put_contents(AUTH_LOG, $line, FILE_APPEND);
}

// --- Anti-brute force --- 
function check_brute_force($username) {
    $users = load_users();
    $failedAttemptsFile = __DIR__ . "/../storage/users/{$username}_fail.json";
    $fails = [];

    if (file_exists($failedAttemptsFile)) {
        $fails = json_decode(file_get_contents($failedAttemptsFile), true);
        $fails = is_array($fails) ? $fails : [];
    }

    // Supprime les tentatives anciennes (>1h)
    $fails = array_filter($fails, fn($t) => $t > time() - 3600);

    // 🔒 Journalisation brute force si seuil critique
    if (count($fails) >= 5) {
        log_auth($username, "Suspicion brute force : " . count($fails) . " échecs en 1h");
    }

    return $fails;
}

function record_failed_attempt($username) {
    $safeUsername = preg_replace('/[^A-Za-z0-9_]/', '', (string)$username);
    if ($safeUsername === '') $safeUsername = 'unknown';

    $failedAttemptsFile = __DIR__ . "/../storage/users/{$safeUsername}_fail.json";
    $fails = check_brute_force($safeUsername);
    $fails[] = time();

    $json = json_encode($fails);
    if ($json === false) {
        log_auth($safeUsername, "Erreur d'encodage JSON lors de l'enregistrement d'une tentative échouée");
        return false;
    }

    $res = @file_put_contents($failedAttemptsFile, $json, LOCK_EX);
    if ($res === false) {
        log_auth($safeUsername, "Impossible d'enregistrer la tentative échouée dans $failedAttemptsFile");
        return false;
    }

    return true;
}

// --- Login avec anti-brute force ---
function login($username, $password) {
    $users = load_users();
    $fails = check_brute_force($username);

    if (count($fails) >= 5) {
        $delay = 30 * (count($fails) - 4); // 30s supplémentaires par échec après 5
        sleep($delay);
    }

    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        $_SESSION['user'] = $username;
        log_auth($username, "Connexion réussie");
        
        // Réinitialiser le compteur d'échecs
        $failedAttemptsFile = __DIR__ . "/../storage/users/{$username}_fail.json";
        if (file_exists($failedAttemptsFile)) unlink($failedAttemptsFile);

        return true;
    }

    log_auth($username, "Échec de connexion");
    record_failed_attempt($username);
    return false;
}

function register($username, $password, $role = 'user') { 
    $users = load_users();
	// --- Vérification de la longueur du login ---
	if (strlen($username) < 3 || strlen($username) > 15) {
        log_auth($username, "Échec création compte : login invalide");
        return ['success' => false, 'error' => "Le nom d'utilisateur doit contenir entre 3 et 15 caractères."];
    }

    // --- Vérification des caractères autorisés (lettres, chiffres, underscore) ---
    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        log_auth($username, "Échec création compte : caractères non autorisés");
        return ['success' => false, 'error' => "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et underscores (_)."];
    }
	
	// --- Vérification si le login existe déjà ---
    if (isset($users[$username])) {
        log_auth($username, "Échec création compte : login déjà utilisé");
        return ['success' => false, 'error' => "Nom d'utilisateur déjà pris."];
    }

    // --- Vérification du mot de passe ---
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\/.!+?=\-_*$]).{10,}$/', $password)) {
        log_auth($username, "Échec création compte : mot de passe faible");
        return ['success' => false, 'error' => "Le mot de passe doit contenir au moins 10 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial (/ . ! + ? = - _ * $)."];
    }

    // --- Création de l'utilisateur ---
    $users[$username] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role
    ];
    save_users($users);

    // --- Création du répertoire projets ---
    $userDir = __DIR__ . "/../storage/users/$username";
    $projectsDir = "$userDir/projects";
    if (!is_dir($projectsDir) && !mkdir($projectsDir, 0755, true)) {
        log_auth($username, "Erreur création répertoire utilisateur");
        return ['success' => false, 'error' => "Impossible de créer le répertoire utilisateur."];
    }

    // --- Copie du template JSON ---
    $templateFile = __DIR__ . "/../public/templates/template1.json";
    if (file_exists($templateFile)) {
        $randomStr = bin2hex(random_bytes(8)); // 16 caractères hex
        $newFile = "$projectsDir/proj_$randomStr.json";

        if (!copy($templateFile, $newFile)) {
            log_auth($username, "Erreur copie du template JSON");
            error_log("Erreur lors de la copie du template pour l'utilisateur $username");
        }
    } else {
        log_auth($username, "Template JSON manquant");
        error_log("Template non trouvé : $templateFile");
    }

    // ✅ Journalisation création de compte réussie
    log_auth($username, "Compte créé avec succès");

    return ['success' => true];
}

function is_admin() {
    if (empty($_SESSION['user'])) return false;
    $username = preg_replace('/[^A-Za-z0-9_]/', '', $_SESSION['user']);
    if ($username === '') return false;
    $users = load_users();
    return isset($users[$username]) && (($users[$username]['role'] ?? '') === 'admin');
}