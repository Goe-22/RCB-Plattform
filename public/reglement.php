<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$pages = rcb_rows("SELECT title, content FROM pages WHERE published = 1 AND slug IN ('reglement','reglement-onroad','reglement-offroad','reglement-veranstaltung') ORDER BY CASE slug WHEN 'reglement' THEN 0 WHEN 'reglement-onroad' THEN 1 WHEN 'reglement-offroad' THEN 2 ELSE 3 END");
rcb_header();
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Reglement & Hinweise</h1><p>Regeln dienen der Sicherheit, Fairness und einem guten Miteinander auf der Strecke.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($pages): ?>
<?php foreach ($pages as $page): ?><article class="rcb-card" style="margin-bottom:18px"><div class="rcb-card-body"><h2><?= rcb_h($page['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($page['content'])) ?></div></div></article><?php endforeach; ?>
<?php else: ?>
<div class="rcb-card"><div class="rcb-card-body"><h2>Allgemeine Hinweise</h2><ul><li>Den Anweisungen der Veranstaltungsleitung und Streckenaufsicht ist Folge zu leisten.</li><li>Fahrzeuge dürfen nur auf der freigegebenen Strecke betrieben werden.</li><li>Bei Bergung oder Arbeiten auf der Strecke muss sichergestellt sein, dass kein Fahrzeug fährt.</li><li>Rücksichtnahme, sportliches Verhalten und ein respektvoller Umgang sind für alle selbstverständlich.</li></ul><p style="margin-top:16px">Die verbindlichen Klassen-, technischen und Veranstaltungsregeln werden vom Verein ergänzt und im Adminbereich veröffentlicht.</p></div></div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
