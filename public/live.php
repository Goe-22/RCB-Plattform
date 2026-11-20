<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$enabled = rcb_setting('live_enabled', '0') === '1';
rcb_header('live');
echo rcb_page_hero('live', 'Live-Timing', 'Rundenzeiten und Rennstände – direkt von der Zeitnahme.');
?>
<section class="rcb-section"><div class="rcb-wrap"><div class="rcb-empty">
<?= $enabled
    ? rcb_rich('live.aktiv', '<h2>Live-Timing ist aktiviert</h2><p>Die Anzeige der Zeitnahmedaten wird ergänzt, sobald Quelle und Übertragungsformat eingerichtet sind.</p>')
    : rcb_rich('live.inaktiv', '<h2>Derzeit keine Live-Übertragung</h2><p>Aktuell läuft keine Live-Zeitnahme. Ergebnisse vergangener Rennen findest du unter <a href="/ergebnisse.php">Ergebnisse</a>.</p>') ?>
</div></div></section>
<?php rcb_footer(); ?>
