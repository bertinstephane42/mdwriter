<?php
session_start();
require_once __DIR__ . '/../inc/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (login($username, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Identifiants incorrects.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-container">
    <h2>Connexion</h2>
    
    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="post">
        <input type="text" name="username" placeholder="Nom d'utilisateur" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <input type="submit" value="Se connecter">
    </form>

    <p class="register-link">Pas de compte ? <a href="register.php">Créer un compte</a></p>
</div>

<style>
/* Centrer le formulaire */
.login-container {
    max-width: 400px;
    margin: 60px auto;
    padding: 25px 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-align: center;
}

/* Titres */
.login-container h2 {
    margin-bottom: 20px;
    color: #333;
}

/* Champs du formulaire */
.login-container input[type="text"],
.login-container input[type="password"] {
    width: 100%;
    padding: 10px 12px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 5px;
}

/* Bouton submit */
.login-container input[type="submit"] {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.login-container input[type="submit"]:hover {
    background-color: #45a049;
}

/* Message d'erreur */
.error-msg {
    color: red;
    font-weight: bold;
    margin-bottom: 10px;
}

/* Lien de création de compte */
.register-link {
    margin-top: 15px;
}
.register-link a {
    color: #2196F3;
    text-decoration: none;
}
.register-link a:hover {
    text-decoration: underline;
}

/* Responsive */
@media screen and (max-width: 480px) {
    .login-container {
        margin: 30px 10px;
        padding: 20px;
    }
}
</style>
</body>
</html>