var simplemde = new SimpleMDE({ element: document.getElementById("editor") });

function saveProject() {
    let title = document.getElementById("title").value;
    let markdown = simplemde.value();
    let filename = document.getElementById("filename").value;

    let formData = new FormData();
    formData.append("action", "save");
    formData.append("title", title);
    formData.append("markdown", markdown);
    if (filename) formData.append("filename", filename);

    fetch("../app/projects.php", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById("status").innerText = "✅ Projet sauvegardé";
            document.getElementById("filename").value = data.filename;
        } else {
            document.getElementById("status").innerText = "❌ Erreur de sauvegarde";
        }
    })
    .catch(err => {
        document.getElementById("status").innerText = "⚠️ Erreur réseau";
    });
}
