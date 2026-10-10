<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$documents = rcb_rows("SELECT title, content FROM pages WHERE published = 1 AND slug LIKE 'download-%' ORDER BY title");
rcb_header();
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Downloads</h1><p>Dokumente und Informationen für Fahrerinnen, Fahrer und Vereinsmitglieder.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($documents): ?><div class="rcb-grid"><?php foreach($documents as $doc): ?><article class="rcb-card"><div class="rcb-card-body"><h2><?= rcb_h($doc['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($doc['content'])) ?></div></div></article><?php endforeach; ?></div><?php else: ?><div class="rcb-empty"><h2>Downloads werden vorbereitet</h2><p>Hier können später freigegebene Vereinsdokumente, Formulare und ergänzende Informationen gesammelt werden. Der Datei-Upload für Dokumente ist noch nicht aktiviert.</p></div><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
