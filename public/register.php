<?php
session_start();
require_once __DIR__ . '/../inc/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = register($_POST['username'], $_POST['password']);
    if ($result['success']) {
        header("Location: login.php");
        exit;
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-container">
    <h2>Créer un compte</h2>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <!-- Champ login -->
        <input type="text" name="username" placeholder="Identifiant" required>
        <small>
            Le login doit être au format <strong>initiale du prénom + nom</strong> (ex. : jharrison),  
            contenir entre <strong>3 et 15 caractères</strong>, uniquement lettres, chiffres ou <code>_</code>.
        </small>

        <!-- Champ mot de passe -->
        <input type="password" name="password" placeholder="Mot de passe" required>
        <small>
            Le mot de passe doit comporter au minimum <strong>10 caractères</strong>, avec au moins :  
            une majuscule, une minuscule, un chiffre et un caractère spécial (<code>/ . ! + ? = - _ * $</code>).
        </small>

        <input type="submit" value="S'inscrire">
    </form>

    <p class="register-link">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
</div>

<style>
/* Réutilisation et cohérence avec login.php */
.login-container {
    max-width: 400px;
    margin: 60px auto;
    padding: 25px 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-align: center;
}

.login-container h2 {
    margin-bottom: 20px;
    color: #333;
}

.login-container input[type="text"],
.login-container input[type="password"] {
    width: 100%;
    padding: 10px 12px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.login-container input[type="submit"] {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    background-color: #2196F3;
    color: white;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.login-container input[type="submit"]:hover {
    background-color: #1976D2;
}

.error-msg {
    color: red;
    font-weight: bold;
    margin-bottom: 10px;
}

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
<script>
    // Masquer le message d'erreur après 5 secondes
    window.addEventListener('DOMContentLoaded', (event) => {
        const errorMsg = document.querySelector('.error-msg');
        if (errorMsg) {
            setTimeout(() => {
                errorMsg.style.transition = "opacity 0.5s ease";
                errorMsg.style.opacity = "0";
                setTimeout(() => errorMsg.remove(), 500); // supprime complètement l'élément
            }, 5000);
        }
    });
</script>
</body>
</html>