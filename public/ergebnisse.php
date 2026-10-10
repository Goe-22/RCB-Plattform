<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$results = rcb_rows("SELECT r.id, r.title, r.content, r.updated_at, e.title AS event_title, e.event_date FROM results r LEFT JOIN events e ON e.id=r.event_id WHERE r.published = 1 ORDER BY COALESCE(e.event_date, r.updated_at) DESC, r.id DESC");
rcb_header('results');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Rennergebnisse</h1><p>Ergebnisse und Rennberichte der RC Bande Zwickau.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<h2>Ergebnisse & Rennberichte</h2>
<?php if ($results): ?>
<div class="rcb-grid">
<?php foreach ($results as $item): ?>
<article class="rcb-card"><div class="rcb-card-body">
<?php if (!empty($item['event_date'])): ?><span class="rcb-pill"><?= rcb_h(rcb_date($item['event_date'])) ?></span><?php endif; ?>
<h3><?= rcb_h($item['title']) ?></h3>
<?php if (!empty($item['event_title'])): ?><p class="rcb-muted">Veranstaltung: <?= rcb_h($item['event_title']) ?></p><?php endif; ?>
<?php if ($item['content'] !== ''): ?><div class="rcb-content"><?= nl2br(rcb_h($item['content'])) ?></div><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="rcb-empty"><strong>Noch keine Ergebnisse veröffentlicht.</strong><p>Die Ergebnislisten erscheinen hier, sobald sie im Adminbereich unter „Ergebnisse“ angelegt und veröffentlicht wurden.</p></div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
