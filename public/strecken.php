<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$tracks = rcb_rows("SELECT * FROM tracks WHERE published = 1 ORDER BY name ASC");
rcb_header('tracks');
echo rcb_page_hero('strecken', 'Unsere Strecken', 'Entdecke die Strecken der RC Bande Zwickau und informiere dich über Ausstattung und Bedingungen.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($tracks): ?><div class="rcb-grid">
<?php foreach ($tracks as $track): ?><?php $kind = rcb_track_kind((string)$track['name']); ?><article class="rcb-card"><?php if ($kind !== ''): ?><div><img class="rcb-card-image" src="<?= rcb_h(rcb_track_image($kind)) ?>" alt="<?= rcb_h($track['name']) ?>" loading="lazy"<?= rcb_img_attr('strecke.' . $kind . '.bild') ?>></div><?php endif; ?><div class="rcb-card-body">
<span class="rcb-pill"><?= rcb_h($track['surface'] ?: 'RC-Strecke') ?></span>
<h2><?= rcb_h($track['name']) ?></h2>
<?php if ((string)$track['description'] !== ''): ?><p class="rcb-content"><?= nl2br(rcb_h($track['description'])) ?></p><?php endif; ?>
<div class="rcb-facts">
<?php if ((string)$track['length'] !== ''): ?><span><strong>Länge:</strong> <?= rcb_h($track['length']) ?></span><?php endif; ?>
<?php if ((string)$track['classes'] !== ''): ?><span><strong>Klassen:</strong> <?= rcb_h($track['classes']) ?></span><?php endif; ?>
<?php if ((int)$track['electricity'] === 1): ?><span>Strom vorhanden</span><?php endif; ?>
<?php if ((int)$track['toilet'] === 1): ?><span>Toilette vorhanden</span><?php endif; ?>
<?php if ((int)$track['paddock'] === 1): ?><span>Fahrerlager vorhanden</span><?php endif; ?>
<?php if ((int)$track['guest_drivers'] === 1): ?><span>Gastfahrer willkommen</span><?php endif; ?>
</div>
<?php if ((int)$track['show_status'] === 1): ?><p><strong>Status:</strong> <?= rcb_h(['open'=>'Geöffnet','closed'=>'Geschlossen','maintenance'=>'Wartung'][(string)$track['status']] ?? 'Bitte beim Verein erfragen') ?></p><?php endif; ?>
<?php if ($kind === 'onroad'): ?><a class="rcb-btn" href="/onroad.php">Details zur Onroad-Strecke</a><?php elseif ($kind === 'offroad'): ?><a class="rcb-btn" href="/offroad.php">Details zur Offroad-Strecke</a><?php endif; ?>
</div></article><?php endforeach; ?>
</div><?php else: ?><div class="rcb-empty">Es wurden noch keine Strecken veröffentlicht. Strecken werden im Adminbereich unter „Strecken“ angelegt.</div><?php endif; ?>
<div style="margin-top:28px"><?= rcb_rich('strecken.hinweis', '<h2>Wichtig vor dem Besuch</h2><p>Trainiert werden kann mit Absprache und unter Einhaltung der Fahrzeiten fast immer. Organisierte Trainings finden bei passendem Wetter statt – bitte vorher anmelden.</p><p><strong>Streckengelände:</strong> Reichenbacher Straße 125A, 08056 Zwickau</p>') ?></div>
</div></section>
<?php rcb_footer(); ?>
