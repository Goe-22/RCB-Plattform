<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
// Unveröffentlicht: für Besucher nicht erreichbar, Admins können sie im Editor vorbereiten.
if (!rcb_live_published() && !rcb_admin_viewer()) {
    rcb_not_found();
}
rcb_header('live');
echo rcb_page_hero('live', 'Live-Timing', 'Rundenzeiten und Rennstände – direkt von der Zeitnahme.');
?>
<?php if (!rcb_live_published()): ?><div class="rcb-wrap"><div class="rcb-empty" style="margin-top:20px;border-color:#f6c76b;background:#fff8e6"><strong>Nicht veröffentlicht:</strong> Diese Seite sehen nur angemeldete Admins. Sie wird für Besucher sichtbar, sobald unter Einstellungen „Live-Timing veröffentlichen“ angehakt ist.</div></div><?php endif; ?>
<section class="rcb-section"><div class="rcb-wrap">
<?= rcb_rich('live.inhalt', '<h2>Live-Zeitnahme</h2><p>Hier erscheinen während unserer Rennen die Rundenzeiten und Rennstände.</p><p>Ergebnisse vergangener Rennen findest du unter <a href="/ergebnisse.php">Ergebnisse</a>.</p>') ?>
</div></section>
<?php rcb_footer(); ?>
