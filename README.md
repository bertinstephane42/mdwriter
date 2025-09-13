# mdwriter

**mdwriter** est une application web légère de gestion et d’édition de documents Markdown. Elle permet aux utilisateurs de créer, éditer, exporter et gérer leurs projets Markdown depuis un navigateur. L’application intègre également un système d’administration pour gérer les utilisateurs et leurs rôles.

---

## Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Sécurité](#sécurité)
- [Licence](#licence)

---

## Fonctionnalités

- **Gestion de comptes utilisateurs**
  - Inscription et connexion
  - Attribution d’un rôle (`user` ou `admin`)
  - Administration centralisée (ajout, suppression, modification de rôle)

- **Éditeur Markdown**
  - Syntaxe complète avec aperçu en temps réel
  - Support des titres, listes, tableaux, images, liens, blockquotes et blocs de code
  - Aide intégrée sous forme de modale

- **Gestion de projets**
  - Création, édition et suppression de projets Markdown
  - Organisation par utilisateur
  - Sauvegarde automatique des documents

- **Export**
  - Export des documents au format Markdown, JSON ou HTML
  - Export PDF généré côté client (via html2canvas et jsPDF)

- **Interface responsive**
  - Adaptée aux écrans mobiles et desktop
  - Navigation simple et intuitive

---

## Architecture

## Architecture

/ mdwriter/

- public/
  - index.php
  - login.php
  - register.php
  - dashboard.php
  - editor.php
  - download.php
  - logout.php
  - api/
    - projects.php
  - assets/
    - css/
      - style.css
      - simplemde.min.css
    - js/
      - app.js
      - simplemde.min.js
      - jspdf.umd.min.js
      - html2canvas.min.js
- inc/                     Fichiers PHP backend
  - .htaccess
  - auth.php
  - projects.php
  - exports.php
  - parsedown.php
- storage/
  - users/
    - .htaccess
    - users.json           
- logs/
  - .htaccess
  - auth.log       

---

## Installation

1. Cloner le dépôt sur votre serveur web :
```bash
git clone https://github.com/votre-utilisateur/mdwriter.git
```

2. Placer le projet dans le dossier accessible par votre serveur web (ex : /var/www/html/mdwriter).

3. Vérifier que PHP 7.4+ est installé et que le serveur peut écrire dans :
 * storage/users/
 * logs/
 * public/uploads/

Configuration
  * .htaccess protège les dossiers sensibles (inc/, storage/).
  * Les utilisateurs sont stockés dans storage/users/users.json.
  * Les logs d’erreurs sont enregistrés dans logs/errors.log.

Utilisation
  1. Ouvrir public/index.php dans votre navigateur.
  2. Créer un compte utilisateur (register.php) ou se connecter (login.php).
  3. Depuis le tableau de bord (dashboard.php) :
     - Créer un nouveau projet
     - Éditer, télécharger ou supprimer vos projets
     - Exporter en PDF, Markdown ou HTML
  4. Pour un administrateur :
     - Gérer les utilisateurs (ajouter, supprimer, modifier le rôle)
     - Supprimer un utilisateur supprime également ses données (storage/users/<username>)

Sécurité
  - Les dossiers sensibles (inc/, storage/) sont protégés par .htaccess.
  - Les mots de passe sont stockés hachés avec password_hash().
  - Les endpoints AJAX sont accessibles uniquement pour les utilisateurs connectés.
  - Les utilisateurs ne peuvent accéder qu’à leurs propres projets.
