<?php
session_start();
require_once __DIR__ . '/../inc/projects.php';
require_once __DIR__ . '/../inc/auth.php';

if (!isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}

$projects = listProjects($_SESSION['user']);

// Récupérer le rôle de l'utilisateur connecté
$users = load_users();
$userRole = $users[$_SESSION['user']]['role'] ?? 'user';

// Section admin : gestion utilisateurs
$adminMessage = '';
if (is_admin()) {
    $users = load_users();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
        $action = $_POST['admin_action'];

        if ($action === 'add' && !empty($_POST['username']) && !empty($_POST['password'])) {
            if (register($_POST['username'], $_POST['password'], $_POST['role'] ?? 'user')) {
                $adminMessage = "Utilisateur créé avec succès.";
                $users = load_users();
            } else {
                $adminMessage = "Le nom d'utilisateur existe déjà.";
            }
        }

        if ($action === 'delete' && !empty($_POST['username']) && $_POST['username'] !== $_SESSION['user']) {
			$usernameToDelete = $_POST['username'];

			// Supprimer l'utilisateur du fichier users.json
			unset($users[$usernameToDelete]);
			save_users($users);
			
			// Supprimer le répertoire utilisateur si présent
			$userDir = __DIR__ . "/../storage/users/$usernameToDelete";
			if (is_dir($userDir)) {
				// Fonction récursive pour supprimer tout le contenu
				$it = new RecursiveDirectoryIterator($userDir, RecursiveDirectoryIterator::SKIP_DOTS);
				$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
				foreach($files as $file) {
					if ($file->isDir()){
						rmdir($file->getRealPath());
					} else {
						unlink($file->getRealPath());
					}
				}
				rmdir($userDir);
			}

			$adminMessage = "Utilisateur et son répertoire supprimés.";
		}

        if ($action === 'change_role' && !empty($_POST['username']) && isset($_POST['role'])) {
            $users[$_POST['username']]['role'] = $_POST['role'];
            save_users($users);
            $adminMessage = "Rôle mis à jour.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
    <h2>Bienvenue <?= htmlspecialchars($_SESSION['user']) ?> (<?= htmlspecialchars($userRole) ?>)</h2>

    <div class="dashboard-actions">
        <a class="btn" href="editor.php">➕ Nouveau rapport</a>
        <a class="btn" href="logout.php">Déconnexion</a>
        <?php if(is_admin()): ?>
            <a class="btn" href="#adminUsersSection" id="toggleAdminUsers">⚙️ Gérer les utilisateurs</a>
        <?php endif; ?>
    </div>

    <h3>Vos projets</h3>
    <?php if (empty($projects)): ?>
        <p>Aucun projet pour le moment. Créez-en un nouveau !</p>
    <?php else: ?>
        <ul class="projects-list">
        <?php foreach ($projects as $p): ?>
            <li>
                <strong><?= htmlspecialchars($p['title']) ?></strong> 
                [<a href="editor.php?id=<?= urlencode($p['id']) ?>">éditer</a>]
                [<a href="delete_project.php?id=<?= urlencode($p['id']) ?>" class="deleteLink">supprimer</a>]
                [<a href="download.php?id=<?= urlencode($p['id']) ?>&format=json">json</a>]
                [<a href="download.php?id=<?= urlencode($p['id']) ?>&format=md">md</a>]
                [<a href="download.php?id=<?= urlencode($p['id']) ?>&format=html">html</a>]
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if(is_admin()): ?>
    <div id="adminUsersSection" style="display:none; margin-top:30px;">
        <h3>Administration des utilisateurs</h3>
        <?php if($adminMessage) echo "<p style='color:green;'>$adminMessage</p>"; ?>

        <!-- Formulaire ajout utilisateur -->
        <form method="post" style="margin-bottom:15px;">
            <input type="hidden" name="admin_action" value="add">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <select name="role">
                <option value="user">Utilisateur</option>
                <option value="admin">Administrateur</option>
            </select>
            <input type="submit" value="Créer" class="btn">
        </form>

        <!-- Liste des utilisateurs -->
        <table border="1" cellpadding="5" style="width:100%; border-collapse: collapse;">
            <tr style="background:#eee;"><th>Utilisateur</th><th>Rôle</th><th>Actions</th></tr>
            <?php foreach($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                    <form style="display:inline" method="post">
                        <input type="hidden" name="admin_action" value="change_role">
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
                        <input type="hidden" name="admin_action" value="delete">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                        <button type="submit" class="btn" style="background:#f44336; color:#fff;">Supprimer</button>
                    </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

</div>

<style>
.dashboard-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 25px 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.dashboard-container h2 {
    margin-bottom: 20px;
    color: #333;
    text-align: center;
}

.dashboard-actions {
    text-align: center;
    margin-bottom: 25px;
}

.dashboard-actions .btn {
    display: inline-block;
    padding: 10px 15px;
    margin: 0 5px;
    background-color: #4CAF50;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.dashboard-actions .btn:hover {
    background-color: #45a049;
}

.projects-list {
    list-style-type: none;
    padding: 0;
}

.projects-list li {
    padding: 10px 12px;
    margin-bottom: 8px;
    background: #f9f9f9;
    border-radius: 5px;
}

.projects-list li a {
    margin-left: 5px;
    color: #2196F3;
    text-decoration: none;
}

.projects-list li a:hover {
    text-decoration: underline;
}

table th, table td {
    padding:8px;
    border:1px solid #ccc;
    text-align:left;
}

@media screen and (max-width: 480px) {
    .dashboard-container {
        margin: 20px 10px;
        padding: 15px;
    }
    .dashboard-actions .btn {
        display: block;
        margin: 10px 0;
    }
}
</style>

<script>
document.querySelectorAll('.deleteLink').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm("Voulez-vous vraiment supprimer ce projet ? Cette action est irréversible.")) {
            e.preventDefault();
        }
    });
});

<?php if(is_admin()): ?>
// Toggle section admin
document.getElementById('toggleAdminUsers').addEventListener('click', function(e){
    e.preventDefault();
    let section = document.getElementById('adminUsersSection');
    section.style.display = (section.style.display === 'none') ? 'block' : 'none';
    section.scrollIntoView({behavior: 'smooth'});
});
<?php endif; ?>
</script>

</body>
</html>