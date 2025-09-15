<?php
session_start();
require_once __DIR__ . '/../inc/projects.php';
require_once __DIR__ . '/../inc/auth.php';

if (!isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}

// Récupération depuis la session (si définie)
$adminMessage = $_SESSION['adminMessage'] ?? '';
$openPasswordModal = $_SESSION['openPasswordModal'] ?? false;

// Réinitialisation côté serveur pour ne pas réafficher après reload
unset($_SESSION['adminMessage'], $_SESSION['openPasswordModal']);

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
			$result = register($_POST['username'], $_POST['password'], $_POST['role'] ?? 'user');
			if ($result['success']) {
				$adminMessage = "Utilisateur créé avec succès.";
				$adminMessageType = 'success'; // succès -> vert
				$users = load_users();
			} else {
				$adminMessage = $result['error']; // affiche le message d'erreur précis
				$adminMessageType = 'error'; // erreur -> rouge
			}
		}

        if ($action === 'delete' && !empty($_POST['username']) && $_POST['username'] !== $_SESSION['user']) {
			$usernameToDelete = $_POST['username'];

			// Supprimer l'utilisateur du fichier users.json
			unset($users[$usernameToDelete]);
			save_users($users);
			
			// Supprimer le répertoire storage/users/<user>
			$userDir = __DIR__ . "/../storage/users/$usernameToDelete";
			if (is_dir($userDir)) {
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

			// Supprimer le répertoire public/images/<user>
			$publicImagesDir = __DIR__ . "/../public/images/$usernameToDelete";
			if (is_dir($publicImagesDir)) {
				$it = new RecursiveDirectoryIterator($publicImagesDir, RecursiveDirectoryIterator::SKIP_DOTS);
				$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
				foreach($files as $file) {
					if ($file->isDir()){
						rmdir($file->getRealPath());
					} else {
						unlink($file->getRealPath());
					}
				}
				rmdir($publicImagesDir);
			}

			$adminMessage = "Utilisateur et ses répertoires supprimés.";
		}

		if ($action === 'change_role' && !empty($_POST['username']) && isset($_POST['role'])) {
			$username = $_POST['username'];
			$newRole = $_POST['role'];

			// Vérifier si on tente de rétrograder un admin
			if ($users[$username]['role'] === 'admin' && $newRole !== 'admin') {
				// Compter le nombre d'admins existants
				$adminCount = 0;
				foreach($users as $u) {
					if ($u['role'] === 'admin') {
						$adminCount++;
					}
				}

				if ($adminCount <= 1) {
					// Dernier admin : refuser
					$adminMessage = "Impossible de rétrograder ce compte, il doit rester au moins un administrateur.";
					$adminMessageType = 'error'; // erreur
				} else {
					// Autoriser le changement
					$users[$username]['role'] = $newRole;
					save_users($users);
					$adminMessage = "Rôle mis à jour.";
					$adminMessageType = 'success'; // succès -> vert
				}
			} else {
				// Aucun risque, mettre à jour
				$users[$username]['role'] = $newRole;
				save_users($users);
				$adminMessage = "Rôle mis à jour.";
				$adminMessageType = 'success'; // succès -> vert
			}
		}
		if ($action === 'change_password' && !empty($_POST['username'])) {
			$usernameToChange = $_POST['username'];

			// Empêche l'admin de changer son propre mot de passe ici
			if ($usernameToChange === $_SESSION['user']) {
				$adminMessage = "Pour modifier votre propre mot de passe, utilisez la modale 'Modifier mon mot de passe'.";
				$adminMessageType = 'error'; // erreur
			} else {
				$newPass = $_POST['password'] ?? '';
				$confirmPass = $_POST['confirm_password'] ?? '';

				if (empty($newPass) || empty($confirmPass)) {
					$adminMessage = "Veuillez remplir tous les champs du mot de passe.";
					$adminMessageType = 'error'; // erreur
				} elseif ($newPass !== $confirmPass) {
					$adminMessage = "Les mots de passe ne correspondent pas.";
					$adminMessageType = 'error'; // erreur
				} else {
					$users[$usernameToChange]['password'] = password_hash($newPass, PASSWORD_DEFAULT);
					save_users($users);
					$adminMessage = "Mot de passe mis à jour pour " . htmlspecialchars($usernameToChange);
					$adminMessageType = 'success'; // succès
				}
			}
		}  
    }
}
$openPasswordModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_POST['self_action']) 
    && $_POST['self_action'] === 'change_password') {
    
    $openPasswordModal = true; // on déclenche l'ouverture de la modale
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $adminMessage = "Veuillez remplir tous les champs.";
    } 
    elseif ($newPassword !== $confirmPassword) {
        $adminMessage = "Les nouveaux mots de passe ne correspondent pas.";
    } 
    elseif (!password_verify($currentPassword, $users[$_SESSION['user']]['password'])) {
        $adminMessage = "Le mot de passe actuel est incorrect.";
    } 
    elseif ($currentPassword === $newPassword) {
        $adminMessage = "Le nouveau mot de passe ne peut pas être identique à l'ancien.";
    } 
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\/.!+?=\-_*$]).{10,}$/', $newPassword)) {
        $adminMessage = "Le mot de passe doit contenir au moins 10 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial (/ . ! + ? = - _ * $).";
    } 
    else {
        $users[$_SESSION['user']]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        save_users($users);
        $adminMessage = "Votre mot de passe a été mis à jour avec succès.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord</title>
    <link rel="stylesheet" href="assets/css/style.css">
	<script src="assets/js/simplemde.min.js"></script>
    <script src="assets/js/html2canvas.min.js"></script>
	<script src="assets/js/html2pdf.bundle.min.js"></script>
</head>
<body>
<div class="dashboard-container">
	<h1 class="app-title">mdWriter</h1>
	<h2 class="welcome-message">Bienvenue <?= htmlspecialchars($_SESSION['user']) ?> (<?= htmlspecialchars($userRole) ?>)</h2>

	<div class="dashboard-actions" style="display:flex; flex-wrap:wrap; justify-content:center; gap:10px;">

		<!-- Ligne 1 : Nouveau rapport, Importer rapport, Gérer les utilisateurs, Voir le journal -->
		<a class="btn" href="editor.php">➕ Nouveau rapport</a>

		<form id="importForm" action="import_project.php" method="post" enctype="multipart/form-data" style="display:none;">	
			<input type="file" id="importFile" name="projectFile" accept="application/json" style="display:none;" onchange="this.form.submit();">
		</form>
		<label for="importFile" class="btn">📂 Importer rapport</label>

		<?php if(is_admin()): ?>
			<a class="btn btn-admin" href="#adminUsersSection" id="toggleAdminUsers">⚙️ Gérer les utilisateurs</a>		
			<a class="btn btn-log" href="#" id="showAuthLog">📜 Voir le journal</a>
		<?php endif; ?>

		<!-- Ligne 2 : Modifier mot de passe + Déconnexion -->
		<button id="openPasswordModal" class="btn btn-password">Modifier mon mot de passe</button>
		<a class="btn btn-logout" href="logout.php">Déconnexion</a>

		<!-- Fenêtre modale -->
		<div id="passwordModal" class="modal">
			<div class="modal-content">
				<h3>Modifier mon mot de passe</h3>
				<form method="post" id="passwordForm">
					<input type="hidden" name="self_action" value="change_password">
					<input type="password" name="current_password" placeholder="Mot de passe actuel" required>
					<input type="password" name="new_password" placeholder="Nouveau mot de passe" required>
					<input type="password" name="confirm_password" placeholder="Confirmation mot de passe" required>
					
					<!-- Affichage du message -->
					<?php if (!empty($adminMessage)): ?>
					<p class="admin-message <?= ($adminMessage === 'Votre mot de passe a été mis à jour avec succès.') ? 'success' : 'error' ?>">
						<?= htmlspecialchars($adminMessage) ?>
					</p>
					<script>
						setTimeout(() => {
							const msg = document.querySelector('.admin-message');
							if (msg) msg.remove();

							// Fermer la modale si ouverte
							const modal = document.getElementById('passwordModal');
							if (modal) modal.style.display = 'none';
						}, 5000); // 5 secondes
					</script>
					<?php endif; ?>

					<div style="text-align:right; margin-top:10px;">
						<button type="button" id="closePasswordModal" class="btn btn-neutral">Annuler</button>
						<button type="submit" class="btn">Mettre à jour</button>
					</div>
				</form>
			</div>
		</div>
		<?php if ($openPasswordModal): ?>
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			const modal = document.getElementById('passwordModal');
			if(modal) modal.style.display = 'flex';
		});
		</script>
		<?php endif; ?>
		<!-- Modale changement mot de passe utilisateur -->
		<div id="adminPasswordModal" class="modal" style="display:none;">
			<div class="modal-content">
				<h3>Changer le mot de passe de l'utilisateur <span id="adminPasswordUsername"></span></h3>
				<form method="post" id="adminPasswordForm">
					<input type="hidden" name="admin_action" value="change_password">
					<input type="hidden" name="username" id="adminPasswordInputUsername" value="">
					<input type="password" name="password" placeholder="Nouveau mot de passe" required>
					<input type="password" name="confirm_password" placeholder="Confirmer le mot de passe" required>
					<div style="text-align:right; margin-top:10px;">
						<button type="button" id="closeAdminPasswordModal" class="btn btn-neutral">Annuler</button>
						<button type="submit" class="btn">Mettre à jour</button>
					</div>
				</form>
			</div>
		</div>
	</div>

    <h3>Vos projets</h3>
    <?php if (empty($projects)): ?>
        <p>Aucun projet pour le moment. Créez-en un nouveau !</p>
    <?php else: ?>
        <ul class="projects-list">
        <?php foreach ($projects as $p): ?>
			<li>
				<strong><?= htmlspecialchars($p['title']) ?></strong> 
				[<a href="editor.php?id=<?= urlencode($p['id']) ?>" class="action-edit">éditer</a>]
				[<a href="delete_project.php?id=<?= urlencode($p['id']) ?>" class="action-delete">supprimer</a>]
				[<a href="download.php?id=<?= urlencode($p['id']) ?>&format=json" class="action-export">json</a>]
				[<a href="download.php?id=<?= urlencode($p['id']) ?>&format=md" class="action-export">md</a>]
				[<a href="download.php?id=<?= urlencode($p['id']) ?>&format=html" class="action-export">html</a>]
				[<a href="#" class="action-export btn-export-pdf" data-id="<?= htmlspecialchars($p['id']) ?>">pdf</a>]
			</li>
		<?php endforeach; ?>

        </ul>
    <?php endif; ?>

    <?php if(is_admin()): ?>
    <div id="adminUsersSection" style="display:none; margin-top:30px;">
        <h3>Administration des utilisateurs</h3>
		<?php if($adminMessage): ?>
			<p id="adminMessage" class="<?= $adminMessageType === 'success' ? 'success-msg' : 'error-msg' ?>">
				<?= htmlspecialchars($adminMessage) ?>
			</p>
		<?php endif; ?>

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
        <table class="admin-table">
            <tr style="background:#eee;"><th>Utilisateur</th><th>Rôle</th><th>Actions</th></tr>
			<?php foreach($users as $u): ?>
			<tr>
				<td><?= htmlspecialchars($u['username']) ?></td>
				<td>
					<form style="display:inline" method="post" onsubmit="return confirmRoleChange(this);">
						<input type="hidden" name="admin_action" value="change_role">
						<input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
						<select name="role">
							<?php
							$isLastAdmin = false;
							if ($u['role'] === 'admin') {
								$adminCount = 0;
								foreach($users as $checkUser) {
									if ($checkUser['role'] === 'admin') $adminCount++;
								}
								if ($adminCount <= 1) {
									$isLastAdmin = true;
								}
							}
							?>
							<option value="user" <?= $u['role']==='user'?'selected':'' ?> <?= $isLastAdmin?'disabled title="Impossible de rétrograder le dernier administrateur"':'' ?>>Utilisateur</option>
							<option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrateur</option>
						</select>
						    <?php if (!$isLastAdmin): // afficher le bouton uniquement si on peut modifier le rôle ?>
								<button type="submit" class="btn" style="margin-left:5px;">Modifier</button>
							<?php endif; ?>
					</form>
				</td>
				<td>
					<?php if($u['username'] !== $_SESSION['user']): ?>
						<form style="display:inline" method="post" onsubmit="return confirm('Supprimer cet utilisateur ?');">
							<input type="hidden" name="admin_action" value="delete">
							<input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
							<button type="submit" class="btn btn-delete">Supprimer</button>
						</form>
					<?php else: ?>
						<!-- Bouton désactivé pour action indisponible -->
						<button class="btn btn-delete" disabled title="Vous ne pouvez pas supprimer votre propre compte" style="opacity:0.6; cursor:not-allowed;">Supprimer</button>
					<?php endif; ?>

					<?php if($u['username'] !== $_SESSION['user']): ?>
						<button class="btn btn-change-user-password" style="background:#2196F3; color:#fff;"
								data-username="<?= htmlspecialchars($u['username']) ?>">
							Changer le mot de passe
						</button>
					<?php else: ?>
						<!-- Bouton désactivé pour action indisponible -->
						<button class="btn btn-change-user-password" disabled title="Vous ne pouvez pas changer votre propre mot de passe"
								style="background:#2196F3; color:#fff; opacity:0.6; cursor:not-allowed;">
							Changer le mot de passe
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
        </table>
    </div>
	<div id="authLogModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
		background: rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;">
		<div style="background:#fff; padding:20px; border-radius:8px; max-width:700px; width:90%; max-height:80%; overflow:auto; position:relative;">
			<h3>Journal des connexions</h3>
			<div id="authLogContent" style="white-space: pre-wrap; font-family: monospace; background:#f4f4f4; padding:10px; border-radius:5px; height:400px; overflow:auto;"></div>
			<button id="closeAuthLog" style="margin-top:10px; padding:8px 12px;">Fermer</button>
		</div>
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
document.getElementById('showAuthLog').addEventListener('click', function(e){
    e.preventDefault();
    fetch('get_auth_log.php')
        .then(response => response.text())
        .then(data => {
            // Séparer le contenu en lignes
            const lines = data.split('\n');
            const coloredLines = lines.map(line => {
                if (line.toLowerCase().includes('success') || line.toLowerCase().includes('réussi')) {
                    return `<span style="color:green;">${line}</span>`;
                } else if (line.toLowerCase().includes('fail') || line.toLowerCase().includes('échec')) {
                    return `<span style="color:red;">${line}</span>`;
                } else {
                    return line;
                }
            });
            document.getElementById('authLogContent').innerHTML = coloredLines.join('<br>');
            document.getElementById('authLogModal').style.display = 'flex';
        });
});

document.getElementById('closeAuthLog').addEventListener('click', function(){
    document.getElementById('authLogModal').style.display = 'none';
});
<?php endif; ?>
const adminMsg = document.getElementById('adminMessage');
if (adminMsg) {
    setTimeout(() => {
        adminMsg.style.transition = "opacity 1s ease";
        adminMsg.style.opacity = "0";
        setTimeout(() => {
            adminMsg.textContent = '';
			adminMsg.style.display = 'none';
        }, 1000); // suppression après fondu
    }, 8000); // 8 secondes
}

async function exportProjectPDF(projectId) {
    try {
        // 1️⃣ Récupérer le HTML complet du projet
        const res = await fetch(`download.php?id=${encodeURIComponent(projectId)}&format=htmlraw`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`Erreur réseau : ${res.status}`);
        const htmlContent = await res.text();

        // 2️⃣ Parser la réponse
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlContent, "text/html");

        // 3️⃣ Extraire le titre (h1 en général dans ton rapport HTML)
        let title = doc.querySelector("h1")?.textContent?.trim() || "rapport";
        // Nettoyage du titre pour en faire un nom de fichier valide
        title = title.replace(/[<>:"/\\|?*]+/g, "_");

        // 4️⃣ Ajouter des styles PDF-friendly
        const pdfStyles = `
            <style>
                /* Amélioration des citations */
                blockquote {
                    border-left: 4px solid #ccc;
                    background: #f9f9f9;
                    padding: 10px 15px;
                    margin: 12px 0;
                    font-style: italic;
                    page-break-inside: avoid;
                }

                /* Code et préformaté */
                pre, code {
                    background: #f4f4f4;
                    padding: 8px 10px;
                    border-radius: 4px;
                    display: block;
                    font-family: monospace;
                    overflow-x: auto;
                    page-break-inside: avoid;
                }

                /* Tableaux */
                table {
                    border-collapse: collapse;
                    width: 100%;
                    margin: 12px 0;
                    page-break-inside: avoid;
                }
                th, td {
                    border: 1px solid #ccc;
                    padding: 6px;
                    text-align: left;
                }

                /* Images */
                img {
                    max-width: 100%;
                    height: auto;
                    page-break-inside: avoid;
                }

                /* Titres */
                h1, h2, h3, h4 {
                    page-break-after: avoid;
                }

                /* Sauts de page forcés */
                .page-break {
                    page-break-before: always;
                }
            </style>
        `;

        // 5️⃣ Construire le HTML complet
        const fullHTML = `
            <html>
                <head>
                    ${document.head.innerHTML}
                    ${pdfStyles}
                </head>
                <body>
                    ${doc.body.innerHTML}
                </body>
            </html>
        `;

        // Créer un DOM temporaire pour forcer le chargement des images
        const tempContainer = document.createElement("div");
        tempContainer.style.display = "none";
        tempContainer.innerHTML = fullHTML;
        document.body.appendChild(tempContainer);

        // ⚡ Attendre que toutes les images soient bien chargées
        await Promise.all(
            Array.from(tempContainer.querySelectorAll("img")).map(img => {
                if (img.complete) return Promise.resolve();
                return new Promise(resolve => {
                    img.onload = img.onerror = resolve;
                });
            })
        );

        // 6️⃣ Générer le PDF avec pagination
        const opt = {
            margin: 10,
            filename: `rapport_${title}.pdf`,
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        const worker = html2pdf().set(opt).from(fullHTML);

        // Générer le PDF sans l’enregistrer directement
        const pdf = await worker.toPdf().get('pdf');

        // Récupérer le nombre total de pages
        const totalPages = pdf.internal.getNumberOfPages();

        // Ajouter un footer (ou header) à chaque page
        for (let i = 1; i <= totalPages; i++) {
            pdf.setPage(i);
            pdf.setFontSize(10);
            pdf.setTextColor(100);
            // En pied de page, centré
            pdf.text(
                `Page ${i} / ${totalPages}`,
                pdf.internal.pageSize.getWidth() / 2,
                pdf.internal.pageSize.getHeight() - 10,
                { align: 'center' }
            );
        }

        // Sauvegarder le fichier
        pdf.save(`rapport_${title}.pdf`);

    } catch (err) {
        console.error('Erreur export PDF', err);
        alert("Erreur lors de la génération du PDF. Vérifie la console.");
    }
}

// Écoute du clic pour le bouton d'export
document.body.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-export-pdf');
    if (!btn) return;
    e.preventDefault();
    exportProjectPDF(btn.dataset.id);
});

// Modal mot de passe
const openPasswordModal = document.getElementById('openPasswordModal');
const closePasswordModal = document.getElementById('closePasswordModal');
const passwordModal = document.getElementById('passwordModal');
const passwordForm = document.getElementById('passwordForm');

if (openPasswordModal && closePasswordModal && passwordModal) {
    openPasswordModal.addEventListener('click', () => {
        passwordModal.style.display = 'flex';
    });
    closePasswordModal.addEventListener('click', () => {
        passwordModal.style.display = 'none';
    });
    // Validation côté client : confirmation du mot de passe
    passwordForm.addEventListener('submit', (e) => {
        const newPass = passwordForm.querySelector('input[name="new_password"]').value;
        const confirmPass = passwordForm.querySelector('input[name="confirm_password"]').value;
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert("Les mots de passe ne correspondent pas.");
        }
    });
}
// Vérification côté client pour les formulaires admin
document.querySelectorAll('.admin-change-password').forEach(form => {
    form.addEventListener('submit', (e) => {
        const newPass = form.querySelector('input[name="password"]').value;
        const confirmPass = form.querySelector('input[name="confirm_password"]').value;

        if (newPass !== confirmPass) {
            e.preventDefault();
            alert("Les mots de passe ne correspondent pas. Veuillez vérifier.");
        }
    });
});
// Modal admin pour changer mot de passe utilisateur
const adminPasswordModal = document.getElementById('adminPasswordModal');
const closeAdminPasswordModal = document.getElementById('closeAdminPasswordModal');
const adminPasswordUsernameSpan = document.getElementById('adminPasswordUsername');
const adminPasswordInputUsername = document.getElementById('adminPasswordInputUsername');

document.querySelectorAll('.btn-change-user-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const username = btn.dataset.username;
        adminPasswordUsernameSpan.textContent = username;
        adminPasswordInputUsername.value = username;
        adminPasswordModal.style.display = 'flex';
    });
});

if(closeAdminPasswordModal){
    closeAdminPasswordModal.addEventListener('click', () => {
        adminPasswordModal.style.display = 'none';
    });
}

// Validation côté client : confirmation mot de passe
const adminPasswordForm = document.getElementById('adminPasswordForm');
if(adminPasswordForm){
    adminPasswordForm.addEventListener('submit', (e) => {
        const newPass = adminPasswordForm.querySelector('input[name="password"]').value;
        const confirmPass = adminPasswordForm.querySelector('input[name="confirm_password"]').value;
        if(newPass !== confirmPass){
            e.preventDefault();
            alert("Les mots de passe ne correspondent pas. Veuillez vérifier.");
        }
    });
}
function confirmRoleChange(form) {
    const select = form.querySelector('select[name="role"]');
    const username = form.querySelector('input[name="username"]').value;
    const newRole = select.value;
    return confirm(`Êtes-vous sûr de vouloir modifier le rôle de "${username}" en "${newRole}" ?`);
}
</script>

</body>
</html>