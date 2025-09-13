<?php
session_start();
require_once __DIR__ . '/parsedown.php';

function exportHTML($markdown, $title) {
    $parsedown = new Parsedown();
    $html = "<html><head><title>" . htmlspecialchars($title) . "</title></head><body>";
    $html .= $parsedown->text($markdown);
    $html .= "</body></html>";
    return $html;
}

function exportMarkdown($markdown) {
    return $markdown;
}

function exportPDF($markdown, $title) {
    // Génération côté client via jsPDF, cette fonction peut rester vide ou écrire un log
    return;
}
