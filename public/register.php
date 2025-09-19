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
    <!-- Nouveau bouton d'aide -->
    <button id="help-btn" title="Aide">Aide</button>
    <h2>Créer un compte</h2>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Identifiant" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <input type="submit" value="S'inscrire">
    </form>

    <p class="register-link">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
</div>

<!-- Modale d'aide -->
<div id="help-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>📌 Règles d'inscription</h3>
        <ul>
            <li>Le login doit être au format <strong>initiale du prénom + nom</strong> (ex. : jharrison), contenir entre <strong>3 et 15 caractères</strong>, uniquement lettres, chiffres ou <code>_</code>.</li>
            <li>Le mot de passe doit comporter au minimum <strong>10 caractères</strong>, avec au moins :
                une majuscule, une minuscule, un chiffre et un caractère spécial (<code>/ . ! + ? = - _ * $</code>).</li>
        </ul>
        <div class="role-warning">
            ⚠️ Attention : le tout premier compte créé sera automatiquement promu <strong>administrateur</strong>.<br>
            Tous les autres comptes seront enregistrés comme <strong>utilisateurs</strong>.
        </div>
    </div>
</div>

<style>
.login-container {
    max-width: 400px;
    margin: 60px auto;
    padding: 25px 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-align: center;
    position: relative;
}

/* Bouton d'aide rectangulaire cohérent avec "S'inscrire" */
#help-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 8px 14px;
    background-color: #2196F3;
    color: #fff;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}
#help-btn:hover {
    background-color: #1976D2;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
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

.role-warning {
    margin: 15px 0;
    padding: 10px;
    font-size: 0.9em;
    color: #856404;
    background-color: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 5px;
    text-align: left;
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

/* Modale */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.6);
}
.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 25px 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    position: relative;
}
.modal-content h3 {
    margin-top: 0;
    color: #333;
}
.modal-content ul {
    text-align: left;
    margin: 15px 0;
    padding-left: 20px;
}
.close {
    position: absolute;
    top: 10px; right: 15px;
    font-size: 24px;
    font-weight: bold;
    color: #999;
    cursor: pointer;
}
.close:hover { color: #000; }

@media screen and (max-width: 480px) {
    .login-container {
        margin: 30px 10px;
        padding: 20px;
    }
}
</style>

<script>
    // Masquer le message d'erreur après 5 secondes
window.addEventListener('DOMContentLoaded', () => {
    const helpBtn = document.getElementById('help-btn');
    const modal = document.getElementById('help-modal');
    const closeBtn = modal.querySelector('.close');

    helpBtn.addEventListener('click', () => {
        modal.style.display = 'block';
    });
    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
	const errorMsg = document.querySelector('.error-msg');
    if (errorMsg) {
        setTimeout(() => {
            errorMsg.style.transition = "opacity 0.5s ease";
            errorMsg.style.opacity = "0";
            setTimeout(() => errorMsg.remove(), 500);
        }, 5000);
	}
});
</script>
</body>
</html>