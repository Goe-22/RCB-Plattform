<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
// Übersicht: je Strecke Bild, Eckdaten und Kurzbeschreibung. Ausführliches steht auf den Detailseiten.
$tracks = rcb_tracks_by_kind();
$teaser = [
    'onroad' => 'Rund 210 m Asphalt für Tourenwagen im Maßstab 1:10 – vom Einsteiger bis zum TT02 Cup.',
    'offroad' => 'Rund 270 m Kunstrasen für Elektro- und Nitro-Buggys bis Maßstab 1:8.',
];
rcb_header('tracks');
echo rcb_page_hero('strecken', 'Unsere Strecken', 'Zwei Strecken auf einem Gelände: Onroad auf Asphalt und Offroad auf Kunstrasen.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<div class="rcb-grid rcb-track-overview">
<?php foreach (['onroad' => 'Onroad', 'offroad' => 'Offroad'] as $kind => $label): ?>
<?php if (!rcb_page_published($kind) && !rcb_admin_viewer()) { continue; } $track = $tracks[$kind]; ?>
<article class="rcb-card"><div><img class="rcb-card-image" src="<?= rcb_h(rcb_track_image($kind)) ?>" alt="<?= rcb_h($label) ?>-Strecke" loading="lazy"<?= rcb_img_attr('strecke.' . $kind . '.bild') ?>></div><div class="rcb-card-body">
<h2><?= rcb_text('strecken.' . $kind . '.titel', $label) ?></h2>
<?php if ($track): ?><div class="rcb-facts"><?php if ((string)$track['length'] !== ''): ?><span><strong>Länge:</strong> <?= rcb_h($track['length']) ?></span><?php endif; ?><?php if ((string)$track['surface'] !== ''): ?><span><strong>Belag:</strong> <?= rcb_h($track['surface']) ?></span><?php endif; ?><?php if ((int)$track['show_status'] === 1): ?><span><strong>Status:</strong> <?= rcb_h(['open' => 'Geöffnet', 'closed' => 'Geschlossen', 'maintenance' => 'Wartung'][(string)$track['status']] ?? '–') ?></span><?php endif; ?></div><?php endif; ?>
<p><?= rcb_text('strecken.' . $kind . '.text', $teaser[$kind]) ?></p>
<a class="rcb-btn" href="/<?= $kind ?>.php"><?= rcb_text('strecken.' . $kind . '.button', 'Mehr zur ' . $label . '-Strecke') ?></a>
</div></article>
<?php endforeach; ?>
<?php foreach ($tracks['andere'] as $track): ?>
<article class="rcb-card"><div class="rcb-card-body"><h2><?= rcb_h($track['name']) ?></h2>
<div class="rcb-facts"><?php if ((string)$track['length'] !== ''): ?><span><strong>Länge:</strong> <?= rcb_h($track['length']) ?></span><?php endif; ?><?php if ((string)$track['surface'] !== ''): ?><span><strong>Belag:</strong> <?= rcb_h($track['surface']) ?></span><?php endif; ?></div>
<?php if ((string)$track['description'] !== ''): ?><p><?= nl2br(rcb_h($track['description'])) ?></p><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<div class="rcb-grid" id="besuch" style="margin-top:34px">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('strecken.hinweis', '<h2>Wichtig vor dem Besuch</h2><ul><li>Trainiert werden kann nach Absprache und unter Einhaltung der Fahrzeiten fast immer.</li><li>Organisierte Trainings finden bei passendem Wetter statt – bitte vorher anmelden.</li><li>Gastfahrer sind willkommen, bitte meldet euch vorher über <a href="/kontakt.php">Kontakt</a> oder Facebook.</li></ul>') ?></div></article>
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('strecken.anfahrt', '<h2>Anfahrt</h2><p><strong>Streckengelände der RC Bande Zwickau</strong><br>Reichenbacher Straße 125A<br>08056 Zwickau</p><p><a href="https://www.openstreetmap.org/search?query=Reichenbacher%20Stra%C3%9Fe%20125A%2C%2008056%20Zwickau">Auf der Karte anzeigen</a></p>') ?></div></article>
</div>
</div></section>
<?php rcb_footer(); ?>
