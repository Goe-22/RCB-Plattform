<?php
declare(strict_types=1);

// Einstiegspunkt für Installationen, deren Webroot das Repository-Hauptverzeichnis ist.
// Bei Hosting mit public/ als DocumentRoot bitte /admin/ direkt dort aufrufen.
require dirname(__DIR__) . '/public/admin/index.php';
