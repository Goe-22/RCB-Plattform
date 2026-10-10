<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$tracks = rcb_rows("SELECT * FROM tracks WHERE published = 1 AND lower(name) LIKE '%onroad%' ORDER BY name");
$images = rcb_rows("SELECT filename FROM media WHERE published = 1 AND category = 'onroad' ORDER BY uploaded_at DESC, id DESC LIMIT 1");
$trackImage = (string)($images[0]['filename'] ?? '');
rcb_header('tracks');
?>
<section class="rcb-hero"<?= $trackImage !== '' ? ' style="background-image:linear-gradient(125deg,rgba(4,29,58,.82),rgba(13,71,119,.70)),url(/uploads/' . rcb_h(rawurlencode($trackImage)) . ');background-size:cover;background-position:center"' : '' ?>><div class="rcb-hero-inner"><h1>Onroad</h1><p>Glattbahn- und Tourenwagen-RC-Modellsport auf befestigtem Untergrund.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($tracks): ?><div class="rcb-grid"><?php foreach($tracks as $track): ?><article class="rcb-card"><div class="rcb-card-body"><h2><?= rcb_h($track['name']) ?></h2><?php if($track['description']!==''): ?><p><?= nl2br(rcb_h($track['description'])) ?></p><?php endif; ?><div class="rcb-facts"><?php if($track['surface']!==''): ?><span>Belag: <?= rcb_h($track['surface']) ?></span><?php endif; ?><?php if($track['length']!==''): ?><span>Länge: <?= rcb_h($track['length']) ?></span><?php endif; ?><?php if($track['classes']!==''): ?><span>Klassen: <?= rcb_h($track['classes']) ?></span><?php endif; ?><?php if((int)$track['guest_drivers']===1): ?><span>Gastfahrer willkommen</span><?php endif; ?></div></div></article><?php endforeach; ?></div><?php else: ?><div class="rcb-empty">Die konkreten Onroad-Angaben werden hier angezeigt, sobald eine veröffentlichte Strecke mit „Onroad“ im Namen im Adminbereich gepflegt wurde.</div><?php endif; ?>
<a class="rcb-btn" href="/strecken.php">Alle Strecken</a>
</div></section>
<?php rcb_footer(); ?>
