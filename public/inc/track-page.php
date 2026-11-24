<?php
declare(strict_types=1);

/**
 * Detailseite einer Strecke (Onroad/Offroad): ausführliche Beschreibung, Steckbrief aus der
 * Streckenverwaltung, Galerie, Anfahrt. Startseite und Streckenübersicht zeigen nur Kurzfassungen.
 */
function rcb_track_page(string $kind): void
{
    $defaults = [
        'onroad' => [
            'titel' => 'Onroad-Strecke',
            'zeile' => 'Asphalt · 210 m · Tourenwagen 1:10',
            'inhalt' => '<h2>Über die Strecke</h2><p>Unsere Onroad-Strecke ist eine rund 210 m lange Asphaltstrecke, die wir vom 1. RC-MSC Kirchberg übernommen haben. Am Fahrerstand haben bis zu 10 Fahrerinnen und Fahrer Platz.</p><p>Gefahren werden hier vor allem Tourenwagen im Maßstab 1:10 – vom Einsteiger bis zur Rennklasse.</p>',
            'anfahrt' => '<h2>Anfahrt</h2><p><strong>Onroad-Strecke der RC Bande Zwickau</strong><br>Reichenbacher Straße 125A, 08056 Zwickau<br>Zufahrt über die Neuplanitzer Straße, neben dem Jugendclub „Airport Zwickau“</p><p>Von der Ampel Reichenbacher Straße (B173) / Neuplanitzer Straße aus liegt die Onroad-Strecke <strong>rechts</strong>.</p><p>Die Offroad-Strecke liegt nicht auf demselben Gelände, sondern auf der anderen Straßenseite: <a href="/offroad.php">zur Offroad-Strecke</a>.</p><p><a href="https://www.google.com/maps/search/?api=1&amp;query=50.70669%2C12.45999" target="_blank" rel="noopener">In Google Maps öffnen</a> · <a href="https://www.openstreetmap.org/?mlat=50.70669&amp;mlon=12.45999#map=18/50.70669/12.45999" target="_blank" rel="noopener">OpenStreetMap</a></p>',
        ],
        'offroad' => [
            'titel' => 'Offroad-Strecke',
            'zeile' => 'Kunstrasen · 270 m · Buggys bis 1:8',
            'inhalt' => '<h2>Über die Strecke</h2><p>Unsere Offroad-Strecke ist rund 270 m lang und mit Kunstrasen belegt. Sie ist für Modelle im Maßstab 1:8 und kleiner geeignet. Am Fahrerstand haben bis zu 10 Fahrerinnen und Fahrer Platz.</p><p>Hier sind vor allem Elektro- und Nitro-Buggys unterwegs.</p>',
            'anfahrt' => '<h2>Anfahrt</h2><p><strong>Offroad-Strecke der RC Bande Zwickau</strong><br>Reichenbacher Straße 125A, 08056 Zwickau<br>Zufahrt über die Neuplanitzer Straße, auf der Flugplatz-Seite</p><p>Von der Ampel Reichenbacher Straße (B173) / Neuplanitzer Straße aus liegt die Offroad-Strecke <strong>links</strong>.</p><p>Die Onroad-Strecke liegt nicht auf demselben Gelände, sondern auf der anderen Straßenseite: <a href="/onroad.php">zur Onroad-Strecke</a>.</p><p><a href="https://www.google.com/maps/search/?api=1&amp;query=50.70579%2C12.45868" target="_blank" rel="noopener">In Google Maps öffnen</a> · <a href="https://www.openstreetmap.org/?mlat=50.70579&amp;mlon=12.45868#map=18/50.70579/12.45868" target="_blank" rel="noopener">OpenStreetMap</a></p>',
        ],
    ][$kind];
    $track = rcb_tracks_by_kind()[$kind];

    rcb_header('tracks');
    echo rcb_page_hero($kind, $defaults['titel'], $defaults['zeile'], rcb_track_image($kind));
    ?>
<?php $showFacts = rcb_section_show($kind . '.steckbrief'); ?>
<section class="rcb-section"><div class="rcb-wrap"><div class="rcb-detail"<?= $showFacts ? '' : ' style="grid-template-columns:1fr"' ?>>
<div class="rcb-detail-main">
<?= rcb_rich($kind . '.inhalt', $defaults['inhalt']) ?>
</div>
<?php if ($showFacts): ?>
<aside class="rcb-card rcb-steckbrief<?= rcb_section_mark($kind . '.steckbrief') ?>"><div class="rcb-card-body">
<h2><?= rcb_text($kind . '.steckbrief.titel', 'Steckbrief') ?></h2>
<?php if ($track): ?>
<?php
$equipment = array_map(static fn($k) => rcb_track_equipment_options()[$k], rcb_track_equipment($track));
if ((string)($track['equipment_extra'] ?? '') !== '') { $equipment[] = (string)$track['equipment_extra']; }
$guest = rcb_track_guest_modes()[(string)($track['guest_mode'] ?? '')] ?? '';
$statusInfo = rcb_track_status_labels()[(string)$track['status']] ?? null;
?>
<?php if ((int)$track['show_status'] === 1 && $statusInfo): ?><p class="rcb-track-status rcb-track-status-<?= rcb_h($statusInfo[1]) ?>"><span class="rcb-track-dot"></span><?= rcb_h($statusInfo[0]) ?><?php if ((string)($track['status_note'] ?? '') !== ''): ?><small><?= rcb_h($track['status_note']) ?></small><?php endif; ?></p><?php endif; ?>
<dl>
<?php if ((string)$track['length'] !== ''): ?><dt>Länge</dt><dd><?= rcb_h($track['length']) ?></dd><?php endif; ?>
<?php if ((string)$track['surface'] !== ''): ?><dt>Belag</dt><dd><?= rcb_h($track['surface']) ?></dd><?php endif; ?>
<?php if ((string)$track['classes'] !== ''): ?><dt>Maßstab / Klassen</dt><dd><?= rcb_h($track['classes']) ?></dd><?php endif; ?>
<?php if ((string)($track['driver_spots'] ?? '') !== ''): ?><dt>Fahrerstand</dt><dd><?= rcb_h($track['driver_spots']) ?></dd><?php endif; ?>
<?php if ($equipment): ?><dt>Ausstattung</dt><dd><?= rcb_h(implode(', ', $equipment)) ?></dd><?php endif; ?>
<?php if ((string)($track['driving_times'] ?? '') !== ''): ?><dt>Fahrzeiten</dt><dd><?= nl2br(rcb_h($track['driving_times'])) ?></dd><?php endif; ?>
<?php if ($guest !== ''): ?><dt>Fahren als Gast</dt><dd><?= rcb_h($guest) ?><?php if ((string)($track['guest_note'] ?? '') !== ''): ?><br><span class="rcb-muted"><?= rcb_h($track['guest_note']) ?></span><?php endif; ?></dd><?php endif; ?>
<?php if ((string)$track['description'] !== ''): ?><dt>Hinweis</dt><dd><?= nl2br(rcb_h($track['description'])) ?></dd><?php endif; ?>
</dl>
<?php else: ?><p class="rcb-muted">Die Streckendaten werden im Adminbereich unter „Strecken“ gepflegt.</p><?php endif; ?>
<?php if (rcb_section_show($kind . '.anfahrt')): ?><p><a class="rcb-btn" href="#besuch"><?= rcb_text($kind . '.steckbrief.button', 'Anfahrt & Besuch') ?></a></p><?php endif; ?>
</div></aside>
<?php endif; ?>
</div>
<?php
    // Bis zu 12 Galeriebilder. Im Bearbeiten-Modus erscheint nach dem letzten Bild ein freies Feld zum Hinzufügen.
    $gallery = '';
    $emptyShown = false;
    for ($i = 1; $i <= 12; $i++) {
        $key = $kind . '.galerie.' . $i;
        if (trim(rcb_block($key, '')) === '') {
            if ($emptyShown || !rcb_can_edit($key)) { continue; }
            $emptyShown = true;
        }
        $gallery .= rcb_img_slot($key, $defaults['titel'] . ' – Bild ' . $i);
    }
    // Bis zu 6 kurze Clips (max. 50 MB je Video), gleiche Logik wie bei den Bildern.
    $emptyShown = false;
    for ($i = 1; $i <= 6; $i++) {
        $key = $kind . '.clip.' . $i;
        if (trim(rcb_block($key, '')) === '') {
            if ($emptyShown || !rcb_can_edit($key)) { continue; }
            $emptyShown = true;
        }
        $gallery .= rcb_video_slot($key, $defaults['titel'] . ' – Video ' . $i);
    }
    if ($gallery !== '' && rcb_section_show($kind . '.galerie')): ?>
<div class="<?= trim(rcb_section_mark($kind . '.galerie')) ?>">
<h2 style="margin-top:34px"><?= rcb_text($kind . '.galerie.titel', 'Bilder & Videos') ?></h2>
<div class="rcb-gallery"><?= $gallery ?></div>
<div class="rcb-credit"><?= rcb_rich($kind . '.galerie.quelle', '<p>Fotos und Videos: RC Bande Zwickau e.V. · Luftbild: <a href="https://www.geodaten.sachsen.de/" target="_blank" rel="noopener">GeoSN</a>, <a href="https://www.govdata.de/dl-de/by-2-0" target="_blank" rel="noopener">dl-de/by-2-0</a></p>') ?></div>
</div>
<?php endif; ?>
<?php if (rcb_section_show($kind . '.anfahrt')): ?>
<div class="rcb-grid<?= rcb_section_mark($kind . '.anfahrt') ?>" id="besuch" style="margin-top:34px">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('strecken.hinweis', '<h2>Wichtig vor dem Besuch</h2><ul><li>Trainiert werden kann fast immer – nach Absprache und unter Einhaltung der Fahrzeiten.</li><li>Organisierte Trainings finden bei passendem Wetter statt – bitte vorher anmelden.</li><li>Gastfahrer sind willkommen – bitte meldet euch vorher über <a href="/kontakt.php">Kontakt</a> oder Facebook.</li></ul>') ?></div></article>
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich($kind . '.anfahrt', $defaults['anfahrt']) ?></div></article>
</div>
<?php endif; ?>
<?php $other = $kind === 'onroad' ? 'offroad' : 'onroad'; ?>
<div class="rcb-rule-nav" style="margin-top:28px"><a class="rcb-btn rcb-btn-light" href="/#strecken">← Zur Startseite</a><?php if (rcb_page_published($other) || rcb_admin_viewer()): ?><a class="rcb-btn" href="/<?= $other ?>.php">Zur <?= $other === 'onroad' ? 'Onroad' : 'Offroad' ?>-Strecke →</a><?php endif; ?></div>
</div></section>
<?php
    rcb_footer();
}
