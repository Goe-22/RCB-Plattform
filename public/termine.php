<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
// Mehrtägige Veranstaltungen gelten bis zum letzten Tag als „kommend“.
$lastDay = "CASE WHEN end_date IS NOT NULL AND CAST(end_date AS CHAR) <> '' THEN end_date ELSE event_date END";
$noDate = "(event_date IS NULL OR CAST(event_date AS CHAR) = '')";
$upcoming = rcb_rows("SELECT * FROM events WHERE published = 1 AND ($noDate OR $lastDay >= ?) ORDER BY CASE WHEN $noDate THEN 1 ELSE 0 END, event_date ASC, start_time ASC", [rcb_today()]);
$past = rcb_rows("SELECT * FROM events WHERE published = 1 AND NOT $noDate AND $lastDay < ? ORDER BY event_date DESC, start_time DESC LIMIT 30", [rcb_today()]);
rcb_header('events');
echo rcb_page_hero('termine', 'Termine & Veranstaltungen', 'Rennen, Trainings und Vereinsaktivitäten der RC Bande Zwickau.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<div class="rcb-card" style="margin-bottom:34px"><div class="rcb-card-body"><?= rcb_rich('termine.training', '<h2>Offizielle Trainings</h2><p>Trainiert werden kann fast immer – nach Absprache und unter Einhaltung der Fahrzeiten.</p><p><strong><em>Organisierte Trainings finden bei passendem Wetter statt. Bitte vorher anmelden.</em></strong></p>') ?></div></div>
<h2><?= rcb_text('termine.kommend.titel', 'Kommende Veranstaltungen') ?></h2>
<?php if ($upcoming): ?><div class="rcb-events"><?php foreach ($upcoming as $event) { echo rcb_event_card($event, 'full'); } ?></div>
<?php else: ?><div class="rcb-empty"><?= rcb_text('termine.kommend.leer', 'Aktuell sind keine kommenden Veranstaltungen veröffentlicht.') ?></div><?php endif; ?>
</div></section>
<?php if (rcb_section_show('termine.vergangen')): ?>
<section class="rcb-section<?= rcb_section_mark('termine.vergangen') ?>" style="background:var(--rcb-tint)"><div class="rcb-wrap">
<h2><?= rcb_text('termine.vergangen.titel', 'Vergangene Veranstaltungen') ?></h2>
<?php if ($past): ?><div class="rcb-events"><?php foreach ($past as $event) { echo rcb_event_card($event, 'past'); } ?></div>
<?php else: ?><p class="rcb-muted">Im Archiv sind noch keine vergangenen Veranstaltungen vorhanden.</p><?php endif; ?>
</div></section>
<?php endif; ?>
<?php rcb_footer(); ?>
