<?php
session_start();
define('USER_FILE', __DIR__ . '/../storage/users/users.json');
define('AUTH_LOG', __DIR__ . '/../logs/auth.log');

// --- Gestion utilisateurs ---
function load_users() {
    if (!file_exists(USER_FILE)) return [];
    $users = json_decode(file_get_contents(USER_FILE), true);
    return is_array($users) ? $users : [];
}

function save_users($users) {
    file_put_contents(USER_FILE, json_encode($users, JSON_PRETTY_PRINT));
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
    return $fails;
}

function record_failed_attempt($username) {
    $failedAttemptsFile = __DIR__ . "/../storage/users/{$username}_fail.json";
    $fails = check_brute_force($username);
    $fails[] = time();
    file_put_contents($failedAttemptsFile, json_encode($fails));
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
    if (isset($users[$username])) return false;
    $users[$username] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role
    ];
    save_users($users);

    $userDir = __DIR__ . "/../storage/users/$username";
    if (!is_dir("$userDir/projects")) mkdir("$userDir/projects", 0755, true);

    return true;
}

function is_admin() {
    if (!isset($_SESSION['user'])) return false;
    $users = load_users();
    $username = $_SESSION['user'];
    return isset($users[$username]) && ($users[$username]['role'] ?? '') === 'admin';
}