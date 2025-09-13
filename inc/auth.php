<?php
session_start(); // obligatoire pour login et register
define('USER_FILE', __DIR__ . '/../storage/users/users.json');

function load_users() {
    if (!file_exists(USER_FILE)) return [];
    $users = json_decode(file_get_contents(USER_FILE), true);
    return is_array($users) ? $users : [];
}

function save_users($users) {
    file_put_contents(USER_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

function login($username, $password) {
    $users = load_users();
    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        // Stocker le nom d'utilisateur dans la session
        $_SESSION['user'] = $username;
        return true;
    }
    return false;
}

function register($username, $password, $role = 'user') {
    $users = load_users();
    if (isset($users[$username])) return false;
    $users[$username] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role  // nouveau champ
    ];
    save_users($users);

    $userDir = __DIR__ . "/../storage/users/$username";
    if (!is_dir("$userDir/projects")) mkdir("$userDir/projects", 0777, true);
    if (!is_dir("$userDir/exports")) mkdir("$userDir/exports", 0777, true);

    return true;
}

function is_admin() {
    if (!isset($_SESSION['user'])) return false;
    $users = load_users();
    $username = $_SESSION['user'];
    return isset($users[$username]) && ($users[$username]['role'] ?? '') === 'admin';
}