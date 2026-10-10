<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$upcoming = rcb_rows("SELECT * FROM events WHERE published = 1 AND (event_date IS NULL OR event_date = '' OR event_date >= date('now','localtime')) ORDER BY CASE WHEN event_date IS NULL OR event_date = '' THEN 1 ELSE 0 END, event_date ASC, start_time ASC");
$past = rcb_rows("SELECT * FROM events WHERE published = 1 AND event_date IS NOT NULL AND event_date <> '' AND event_date < date('now','localtime') ORDER BY event_date DESC, start_time DESC LIMIT 20");
rcb_header('events');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Termine & Veranstaltungen</h1><p>Rennen, Trainings und Vereinsaktivitäten der RC Bande Zwickau.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<h2>Kommende Veranstaltungen</h2>
<?php if ($upcoming): ?><div class="rcb-grid">
<?php foreach ($upcoming as $event): ?><article class="rcb-card"><div class="rcb-card-body">
<?php if (!empty($event['event_date'])): ?><span class="rcb-pill"><?= rcb_h(rcb_date($event['event_date'])) ?><?= !empty($event['start_time']) ? ' · ' . rcb_h(substr((string)$event['start_time'], 0, 5)) . ' Uhr' : '' ?></span><?php else: ?><span class="rcb-pill">Termin folgt</span><?php endif; ?>
<h3><?= rcb_h($event['title']) ?></h3>
<?php if ($event['location'] !== ''): ?><p><strong>Ort:</strong> <?= rcb_h($event['location']) ?></p><?php endif; ?>
<?php if ($event['description'] !== ''): ?><p><?= nl2br(rcb_h($event['description'])) ?></p><?php endif; ?>
</div></article><?php endforeach; ?>
</div><?php else: ?><div class="rcb-empty">Aktuell sind keine kommenden Veranstaltungen veröffentlicht.</div><?php endif; ?>
</div></section>
<section class="rcb-section" style="background:#eaf0f5"><div class="rcb-wrap">
<h2>Vergangene Veranstaltungen</h2>
<?php if ($past): ?><div class="rcb-grid">
<?php foreach ($past as $event): ?><article class="rcb-card"><div class="rcb-card-body"><span class="rcb-pill"><?= rcb_h(rcb_date($event['event_date'])) ?></span><h3><?= rcb_h($event['title']) ?></h3>
<?php if ($event['location'] !== ''): ?><p><strong>Ort:</strong> <?= rcb_h($event['location']) ?></p><?php endif; ?>
<?php if ($event['description'] !== ''): ?><p><?= nl2br(rcb_h($event['description'])) ?></p><?php endif; ?>
</div></article><?php endforeach; ?>
</div><?php else: ?><p class="rcb-muted">Im Archiv sind noch keine vergangenen Veranstaltungen vorhanden.</p><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
