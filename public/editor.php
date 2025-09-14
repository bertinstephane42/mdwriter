<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
require_once __DIR__ . '/../inc/projects.php';

$project = null;
$projectId = null;
if (isset($_GET['id'])) {
    $projectId = $_GET['id'];
    $project = loadProject($projectId);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title class="editor-title">Éditeur Markdown</title>
    <link rel="stylesheet" href="assets/css/simplemde.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/simplemde.min.js"></script>
    <script src="assets/js/html2canvas.min.js"></script>
</head>
<body>
<h2 class="editor-title">Éditeur Markdown</h1>

<form id="editorForm">
    <input type="hidden" name="id" value="<?= htmlspecialchars($projectId ?? '') ?>">
    <input type="text" name="title" placeholder="Titre" value="<?= htmlspecialchars($project['title'] ?? '') ?>"><br>
    <textarea id="editor" name="markdown"><?= htmlspecialchars($project['markdown'] ?? '') ?></textarea><br>
    <button type="submit" name="action" value="save">💾 Sauvegarder</button>
    <p>
        <button type="button" id="backDashboardBtn">🏠 Retour au dashboard</button>
    </p>
</form>
<div id="saveMessage" style="color:green; margin-top:5px;"></div>

<!-- Modale d'aide Markdown -->
<div id="helpModal" class="modal">
  <div class="modal-content" style="max-height:80vh; overflow-y:auto;">
    <span class="close" style="cursor:pointer;float:right;font-size:1.5em;">&times;</span>
    <h1 style="text-align:center;">Guide Markdown</h1>

    <h3>Mise en forme</h3>
    <pre><code>**gras**
*italique*
~~barré~~</code></pre>

    <h3>Titres</h3>
    <pre><code># Gros titre
## Titre moyen
### Petit titre
#### Très petit titre</code></pre>

    <h3>Listes</h3>
    <pre><code>* Élément de liste
* Élément de liste
* Élément de liste

1. Élément numéroté
2. Élément numéroté
3. Élément numéroté</code></pre>

    <h3>Liens</h3>
    <pre><code>[Texte du lien](http://www.exemple.com)</code></pre>

    <h3>Blockquotes (citations)</h3>
    <pre><code>> Ceci est une citation.
> Elle peut s'étendre sur plusieurs lignes !</code></pre>

    <h3>Images &nbsp; <small>Besoin de télécharger une image ? <a href="http://imgur.com/" target="_blank">Imgur</a> propose une interface simple.</small></h3>
    <pre><code>![](http://www.exemple.com/image.jpg)</code></pre>

    <h3>Tableaux</h3>
    <pre><code>| Colonne 1 | Colonne 2 | Colonne 3 |
| -------- | -------- | -------- |
| John     | Doe      | Homme    |
| Mary     | Smith    | Femme    |

<em>Ou sans aligner les colonnes :</em>

| Colonne 1 | Colonne 2 | Colonne 3 |
| -------- | -------- | -------- |
| John | Doe | Homme |
| Mary | Smith | Femme |</code></pre>

    <h3>Afficher du code</h3>
    <pre><code>`var exemple = "bonjour !";`</code></pre>

    <p><em>Ou sur plusieurs lignes :</em></p>
    <pre><code>
&#96;&#96;&#96;bash
exemple="bonjour !"
echo "$exemple"
&#96;&#96;&#96;bash
</code></pre>

    <h3>Bonnes pratiques</h3>
    <ul>
      <li>Structurer le document avec titres et sous-titres.</li>
      <li>Numéroter les étapes d’une procédure.</li>
      <li>Inclure des blocs de code pour les commandes ou scripts.</li>
      <li>Ne pas abuser de la mise en forme ; privilégier la clarté.</li>
      <li>Exporter régulièrement pour sauvegarder votre travail.</li>
    </ul>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const projectId = "<?= $projectId ?? '' ?>";
	let simplemde = new SimpleMDE({
		element: document.getElementById("editor"),
		spellChecker: false,
		autosave: { enabled: true, uniqueId: "mdwriter_autosave", delay: 1000 },
		status: false,
		toolbar: [
			{ name: "bold", action: SimpleMDE.toggleBold, className: "fa fa-bold", title: "Gras" },
			{ name: "italic", action: SimpleMDE.toggleItalic, className: "fa fa-italic", title: "Italique" },
			{ name: "strikethrough", action: SimpleMDE.toggleStrikethrough, className: "fa fa-strikethrough", title: "Barré" },
			{ name: "heading", action: SimpleMDE.toggleHeadingSmaller, className: "fa fa-header", title: "Titre" },
			"|",
			{ name: "code", action: SimpleMDE.toggleCodeBlock, className: "fa fa-code", title: "Bloc de code" },
			{ name: "quote", action: SimpleMDE.toggleBlockquote, className: "fa fa-quote-left", title: "Citation" },
			{ name: "unordered-list", action: SimpleMDE.toggleUnorderedList, className: "fa fa-list-ul", title: "Liste à puces" },
			{ name: "ordered-list", action: SimpleMDE.toggleOrderedList, className: "fa fa-list-ol", title: "Liste numérotée" },
			{ name: "link", action: SimpleMDE.drawLink, className: "fa fa-link", title: "Lien" },
			{ name: "image", action: SimpleMDE.drawImage, className: "fa fa-picture-o", title: "Image" },
			{ name: "table", action: SimpleMDE.drawTable, className: "fa fa-table", title: "Tableau" },
			{ name: "horizontal-rule", action: SimpleMDE.drawHorizontalRule, className: "fa fa-minus", title: "Ligne horizontale" },
			{ name: "preview", action: SimpleMDE.togglePreview, className: "fa fa-eye no-disable", title: "Aperçu" },
			{ name: "side-by-side", action: SimpleMDE.toggleSideBySide, className: "fa fa-columns no-disable no-mobile", title: "Côte à côte" },
			{ name: "fullscreen", action: SimpleMDE.toggleFullScreen, className: "fa fa-arrows-alt no-disable no-mobile", title: "Plein écran" },
			"|",
			{
				name: "guide",
				action: function() {
					const modal = document.getElementById("helpModal");
					modal.style.display = "block";
				},
				className: "fa fa-question-circle",
				title: "Aide Markdown"
			}
		]
	});


    // Si c'est un nouveau projet, vider explicitement le contenu
    if (!projectId) {
        simplemde.value("");
    }

    const form = document.getElementById("editorForm");
    const saveMessage = document.getElementById("saveMessage");
    const backBtn = document.getElementById("backDashboardBtn");
    let isSaved = true;

    const markDirty = () => { isSaved = false; };
    const markSaved = () => { isSaved = true; };

    form.querySelector('input[name="title"]').addEventListener("input", markDirty);
    simplemde.codemirror.on("change", markDirty);

	form.addEventListener("submit", function(e) {
		e.preventDefault();

		const titleInput = form.querySelector('input[name="title"]');
		if (!titleInput.value.trim()) {
			alert("Veuillez renseigner un titre avant de sauvegarder !");
			titleInput.focus();
			return false;
		}

		// Mettre à jour le textarea avant l'envoi
		form.querySelector('textarea[name="markdown"]').value = simplemde.value();

		// Construire formData avec le champ action=save
		const formData = new FormData(form);
		formData.append("action", "save");

		fetch('api/projects.php', {  // <-- ici on pointe sur le nouvel endpoint public
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				saveMessage.textContent = "Sauvegarde réussie ✅";
				markSaved();
				setTimeout(() => saveMessage.textContent = "", 3000);

				// Mettre à jour l'ID si nouveau projet
				form.querySelector('input[name="id"]').value = data.id;
			} else {
				saveMessage.textContent = "Erreur lors de la sauvegarde ❌";
				console.error("Erreur côté serveur :", data.error);
			}
		})
		.catch(err => {
			saveMessage.textContent = "Erreur lors de la sauvegarde ❌";
			console.error("Erreur réseau :", err);
		});
	});
	
    // --- Retour au dashboard ---
    backBtn.addEventListener("click", function() {
        if (!confirm("Voulez-vous vraiment retourner au dashboard ?")) return;

        if (!isSaved) {
            if (!confirm(
                "Vous n'avez pas sauvegardé votre projet ! Si vous quittez maintenant, vous perdrez toutes les modifications.\n\nVoulez-vous vraiment quitter sans sauvegarder ?"
            )) return;
        }

        window.location.href = "dashboard.php";
    });

    // Modale d'aide
    const modal = document.getElementById("helpModal");
    const span = modal.querySelector(".close");

    span.onclick = () => modal.style.display = "none";
    window.onclick = (event) => { if (event.target === modal) modal.style.display = "none"; }
});
</script>
</body>
</html>