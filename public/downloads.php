<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$documents = rcb_rows("SELECT title, content FROM pages WHERE published = 1 AND slug LIKE 'download-%' ORDER BY title");
rcb_header();
echo rcb_page_hero('downloads', 'Downloads', 'Dokumente und Informationen für Fahrerinnen, Fahrer und Vereinsmitglieder.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<?= rcb_rich('downloads.inhalt', '<p>Hier stellen wir Ausschreibungen, Formulare und Vereinsdokumente bereit. Dokumente kannst du in diesem Text als Link einfügen.</p>') ?>
<?php if ($documents): ?><div class="rcb-grid" style="margin-top:20px"><?php foreach ($documents as $doc): ?><article class="rcb-card"><div class="rcb-card-body"><h2><?= rcb_h($doc['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($doc['content'])) ?></div></div></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
