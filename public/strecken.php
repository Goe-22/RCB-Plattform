<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$tracks = rcb_rows("SELECT * FROM tracks WHERE published = 1 ORDER BY name ASC");
$trackImages = rcb_rows("SELECT category, filename FROM media WHERE published = 1 AND category IN ('onroad','offroad') ORDER BY uploaded_at DESC, id DESC");
$imageMap = [];
foreach ($trackImages as $image) { if (!isset($imageMap[$image['category']])) { $imageMap[$image['category']] = (string)$image['filename']; } }
rcb_header('tracks');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Unsere Strecken</h1><p>Entdecke die veröffentlichten Strecken der RC Bande Zwickau und informiere dich über Ausstattung und Bedingungen.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($tracks): ?><div class="rcb-grid">
<?php foreach ($tracks as $track): ?><?php $kind = stripos((string)$track['name'], 'onroad') !== false ? 'onroad' : (stripos((string)$track['name'], 'offroad') !== false ? 'offroad' : ''); $trackImage = $kind !== '' ? ($imageMap[$kind] ?? '') : ''; ?><article class="rcb-card"><?php if ($trackImage !== ''): ?><img src="/uploads/<?= rcb_h(rawurlencode($trackImage)) ?>" alt="<?= rcb_h($track['name']) ?>" loading="lazy" style="display:block;width:100%;height:220px;object-fit:cover"><?php endif; ?><div class="rcb-card-body">
<span class="rcb-pill"><?= rcb_h($track['surface'] ?: 'RC-Strecke') ?></span>
<h2><?= rcb_h($track['name']) ?></h2>
<?php if ($track['description'] !== ''): ?><p class="rcb-content"><?= nl2br(rcb_h($track['description'])) ?></p><?php endif; ?>
<div class="rcb-facts">
<?php if ($track['length'] !== ''): ?><span><strong>Länge:</strong> <?= rcb_h($track['length']) ?></span><?php endif; ?>
<?php if ($track['classes'] !== ''): ?><span><strong>Klassen:</strong> <?= rcb_h($track['classes']) ?></span><?php endif; ?>
<?php if ((int)$track['electricity'] === 1): ?><span>Strom vorhanden</span><?php endif; ?>
<?php if ((int)$track['toilet'] === 1): ?><span>Toilette vorhanden</span><?php endif; ?>
<?php if ((int)$track['paddock'] === 1): ?><span>Fahrerlager vorhanden</span><?php endif; ?>
<?php if ((int)$track['guest_drivers'] === 1): ?><span>Gastfahrer willkommen</span><?php endif; ?>
</div>
<?php if ((int)$track['show_status'] === 1): ?><p><strong>Status:</strong> <?= rcb_h(['open'=>'Geöffnet','closed'=>'Geschlossen','maintenance'=>'Wartung'][$track['status']] ?? 'Bitte beim Verein erfragen') ?></p><?php endif; ?>
<?php if ($kind === 'onroad'): ?><a class="rcb-btn" href="/onroad.php">Details zur Onroad-Strecke</a><?php elseif ($kind === 'offroad'): ?><a class="rcb-btn" href="/offroad.php">Details zur Offroad-Strecke</a><?php endif; ?>
</div></article><?php endforeach; ?>
</div><?php else: ?><div class="rcb-empty">Es wurden noch keine Strecken zur Veröffentlichung freigegeben. Die Vereinsverwaltung kann Strecken im Adminbereich anlegen und veröffentlichen.</div><?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
