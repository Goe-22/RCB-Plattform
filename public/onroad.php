<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$tracks = rcb_rows("SELECT * FROM tracks WHERE published = 1 AND lower(name) LIKE '%onroad%' ORDER BY name");
rcb_header('tracks');
echo rcb_page_hero('onroad', 'Onroad', 'Glattbahn- und Tourenwagen-RC-Modellsport auf Asphalt.', rcb_track_image('onroad'));
?>
<section class="rcb-section"><div class="rcb-wrap">
<?= rcb_rich('onroad.inhalt', '<h2>Unsere Onroad-Strecke</h2><ul><li>Streckenlänge: 210 m</li><li>Fahrerstand für 10 Personen</li><li>Asphaltbelag</li><li>übernommen vom 1. RC-MSC Kirchberg</li></ul>') ?>
<?php if ($tracks): ?><div class="rcb-grid" style="margin-top:24px"><?php foreach ($tracks as $track): ?><article class="rcb-card"><div class="rcb-card-body"><h3><?= rcb_h($track['name']) ?></h3><?php if ((string)$track['description'] !== ''): ?><p><?= nl2br(rcb_h($track['description'])) ?></p><?php endif; ?><div class="rcb-facts"><?php if ((string)$track['surface'] !== ''): ?><span>Belag: <?= rcb_h($track['surface']) ?></span><?php endif; ?><?php if ((string)$track['length'] !== ''): ?><span>Länge: <?= rcb_h($track['length']) ?></span><?php endif; ?><?php if ((string)$track['classes'] !== ''): ?><span>Klassen: <?= rcb_h($track['classes']) ?></span><?php endif; ?><?php if ((int)$track['guest_drivers'] === 1): ?><span>Gastfahrer willkommen</span><?php endif; ?></div></div></article><?php endforeach; ?></div><?php endif; ?>
<a class="rcb-btn" href="/strecken.php">Alle Strecken</a>
</div></section>
<?php rcb_footer(); ?>
