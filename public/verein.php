<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$custom = rcb_rows("SELECT title, content FROM pages WHERE slug = 'verein' AND published = 1 LIMIT 1");
rcb_header('club');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Unser Verein</h1><p>Gemeinsam fahren, gemeinsam lernen und die Begeisterung für RC-Modellsport in Zwickau teilen.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if (!empty($custom[0]['content'])): ?><h2><?= rcb_h($custom[0]['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($custom[0]['content'])) ?></div><?php else: ?>
<h2>RC-Modellsport verbindet</h2>
<p>Die RC Bande Zwickau e.V. ist die Anlaufstelle für alle, die ferngesteuerte Modellautos und den Modellrennsport mögen. Ob du bereits Rennen fährst oder den Einstieg suchst – informiere dich über unsere Strecken und Termine und melde dich bei uns.</p>
<div class="rcb-grid">
<article class="rcb-card"><div class="rcb-card-body"><h3>Fahren & trainieren</h3><p>Entdecke die veröffentlichten Streckeninformationen und kommende Trainingsmöglichkeiten.</p><a class="rcb-btn" href="/strecken.php">Strecken ansehen</a></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h3>Gemeinschaft</h3><p>Erlebe den RC-Modellsport zusammen mit anderen Fahrerinnen und Fahrern.</p><a class="rcb-btn" href="/termine.php">Termine ansehen</a></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h3>Mitmachen</h3><p>Du möchtest den Verein kennenlernen oder Mitglied werden? Nimm Kontakt mit uns auf.</p><a class="rcb-btn" href="/mitglied-werden.php">Mitglied werden</a></div></article>
</div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
