<?php
session_start();
require_once __DIR__ . '/../inc/auth.php';

if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

$users = load_users();
$message = '';

// Gestion création utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' && !empty($_POST['username']) && !empty($_POST['password'])) {
        if (register($_POST['username'], $_POST['password'], $_POST['role'] ?? 'user')) {
            $message = "Utilisateur créé avec succès.";
            $users = load_users();
        } else {
            $message = "Le nom d'utilisateur existe déjà.";
        }
    }

    if ($action === 'delete' && !empty($_POST['username']) && $_POST['username'] !== $_SESSION['user']) {
        unset($users[$_POST['username']]);
        save_users($users);
        $message = "Utilisateur supprimé.";
    }

    if ($action === 'change_role' && !empty($_POST['username']) && isset($_POST['role'])) {
        $users[$_POST['username']]['role'] = $_POST['role'];
        save_users($users);
        $message = "Rôle mis à jour.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Admin utilisateurs</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<h2>Gestion des utilisateurs</h2>
<?php if($message) echo "<p>$message</p>"; ?>

<h3>Ajouter un utilisateur</h3>
<form method="post">
    <input type="hidden" name="action" value="add">
    <input type="text" name="username" placeholder="Nom d'utilisateur" required>
    <input type="password" name="password" placeholder="Mot de passe" required>
    <select name="role">
        <option value="user">Utilisateur</option>
        <option value="admin">Administrateur</option>
    </select>
    <input type="submit" value="Créer">
</form>

<h3>Liste des utilisateurs</h3>
<table border="1" cellpadding="5">
    <tr><th>Utilisateur</th><th>Rôle</th><th>Actions</th></tr>
    <?php foreach($users as $u): ?>
    <tr>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td>
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                <select name="role" onchange="this.form.submit()">
                    <option value="user" <?= $u['role']==='user'?'selected':'' ?>>Utilisateur</option>
                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrateur</option>
                </select>
            </form>
        </td>
        <td>
            <?php if($u['username'] !== $_SESSION['user']): ?>
            <form style="display:inline" method="post" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                <button type="submit">Supprimer</button>
            </form>
            <?php else: ?>
            -
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>