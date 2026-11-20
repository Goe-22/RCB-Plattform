<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$upcoming = rcb_rows("SELECT * FROM events WHERE published = 1 AND (event_date IS NULL OR CAST(event_date AS CHAR) = '' OR event_date >= ?) ORDER BY CASE WHEN event_date IS NULL OR CAST(event_date AS CHAR) = '' THEN 1 ELSE 0 END, event_date ASC, start_time ASC", [rcb_today()]);
$past = rcb_rows("SELECT * FROM events WHERE published = 1 AND event_date IS NOT NULL AND CAST(event_date AS CHAR) <> '' AND event_date < ? ORDER BY event_date DESC, start_time DESC LIMIT 20", [rcb_today()]);
$card = static function (array $event, bool $upcoming): string {
    $date = !empty($event['event_date']) ? rcb_h(rcb_date((string)$event['event_date'])) . (!empty($event['start_time']) ? ' · ' . rcb_h(substr((string)$event['start_time'], 0, 5)) . ' Uhr' : '') : ($upcoming ? 'Termin folgt' : '');
    return '<article class="rcb-card"><div class="rcb-card-body">' . ($date !== '' ? '<span class="rcb-pill">' . $date . '</span>' : '')
        . '<h3>' . rcb_h($event['title']) . '</h3>'
        . ((string)$event['location'] !== '' ? '<p><strong>Ort:</strong> ' . rcb_h($event['location']) . '</p>' : '')
        . ((string)$event['description'] !== '' ? '<p>' . nl2br(rcb_h($event['description'])) . '</p>' : '')
        . '</div></article>';
};
rcb_header('events');
echo rcb_page_hero('termine', 'Termine & Veranstaltungen', 'Rennen, Trainings und Vereinsaktivitäten der RC Bande Zwickau.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<?= rcb_rich('termine.training', '<h2>Offizielle Trainings</h2><p>Trainiert werden kann mit Absprache und unter Einhaltung der Fahrzeiten fast immer.</p><p><strong><em>Organisierte Trainings finden bei passendem Wetter statt. Bitte vorher anmelden.</em></strong></p>') ?>
<h2 style="margin-top:28px"><?= rcb_text('termine.kommend.titel', 'Kommende Veranstaltungen') ?></h2>
<?php if ($upcoming): ?><div class="rcb-grid"><?php foreach ($upcoming as $event) { echo $card($event, true); } ?></div>
<?php else: ?><div class="rcb-empty"><?= rcb_text('termine.kommend.leer', 'Aktuell sind keine kommenden Veranstaltungen veröffentlicht.') ?></div><?php endif; ?>
<div style="margin-top:28px"><?= rcb_rich('termine.serien', '<h2>Rennserien zu Gast</h2><p><strong>Onroad:</strong> TT-02 Cup / Einsteiger / TW 17,5t Stock / FWD</p><p><strong>Offroad:</strong> Buggy Elektro ORE8 Cup / Nitro OR8 Cup</p><p>Die Reglements findest du unter <a href="/reglement.php">Reglement</a>.</p>') ?></div>
</div></section>
<section class="rcb-section" style="background:#eaf0f5"><div class="rcb-wrap">
<h2><?= rcb_text('termine.vergangen.titel', 'Vergangene Veranstaltungen') ?></h2>
<?php if ($past): ?><div class="rcb-grid"><?php foreach ($past as $event) { echo $card($event, false); } ?></div>
<?php else: ?><p class="rcb-muted">Im Archiv sind noch keine vergangenen Veranstaltungen vorhanden.</p><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
