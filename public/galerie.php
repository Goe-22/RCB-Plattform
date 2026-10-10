<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$images = rcb_rows("SELECT filename,title,description,uploaded_at FROM media WHERE published = 1 ORDER BY uploaded_at DESC, id DESC");
rcb_header('gallery');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Bildergalerie</h1><p>Einblicke in Strecken, Fahrzeuge und das Vereinsleben.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($images): ?>
<div class="rcb-grid">
<?php foreach ($images as $item): ?>
<figure class="rcb-card"><a href="/uploads/<?= rcb_h(rawurlencode($item['filename'])) ?>" target="_blank" rel="noopener"><img src="/uploads/<?= rcb_h(rawurlencode($item['filename'])) ?>" alt="<?= rcb_h($item['description'] ?: $item['title']) ?>" loading="lazy" style="display:block;width:100%;height:240px;object-fit:cover"></a>
<?php if ($item['title'] !== '' || $item['description'] !== ''): ?><figcaption class="rcb-card-body"><?php if ($item['title'] !== ''): ?><h3><?= rcb_h($item['title']) ?></h3><?php endif; ?><?php if ($item['description'] !== ''): ?><p><?= rcb_h($item['description']) ?></p><?php endif; ?></figcaption><?php endif; ?>
</figure>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="rcb-empty"><h2>Die Galerie wird vorbereitet</h2><p>Hier erscheinen Bilder, sobald sie im Adminbereich hochgeladen und zur Veröffentlichung freigegeben wurden. Vereinslogo und Streckenbilder können später ergänzt werden.</p></div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
