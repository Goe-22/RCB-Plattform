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
<section class="rcb-section"><div class="rcb-wrap"><div class="rcb-detail">
<div class="rcb-detail-main">
<?= rcb_rich($kind . '.inhalt', $defaults['inhalt']) ?>
</div>
<aside class="rcb-card rcb-steckbrief"><div class="rcb-card-body">
<h2><?= rcb_text($kind . '.steckbrief.titel', 'Steckbrief') ?></h2>
<?php if ($track): ?>
<dl>
<?php if ((string)$track['length'] !== ''): ?><dt>Länge</dt><dd><?= rcb_h($track['length']) ?></dd><?php endif; ?>
<?php if ((string)$track['surface'] !== ''): ?><dt>Belag</dt><dd><?= rcb_h($track['surface']) ?></dd><?php endif; ?>
<?php if ((string)$track['classes'] !== ''): ?><dt>Maßstab / Klassen</dt><dd><?= rcb_h($track['classes']) ?></dd><?php endif; ?>
<?php if ((string)$track['description'] !== ''): ?><dt>Hinweis</dt><dd><?= nl2br(rcb_h($track['description'])) ?></dd><?php endif; ?>
<dt>Ausstattung</dt><dd><?= rcb_h(implode(', ', array_filter([(int)$track['electricity'] === 1 ? 'Strom' : '', (int)$track['toilet'] === 1 ? 'Toilette' : '', (int)$track['paddock'] === 1 ? 'Fahrerlager' : ''])) ?: '–') ?></dd>
<dt>Gastfahrer</dt><dd><?= (int)$track['guest_drivers'] === 1 ? 'willkommen (bitte vorher anmelden)' : 'nach Absprache' ?></dd>
<?php if ((int)$track['show_status'] === 1): ?><dt>Status</dt><dd><strong><?= rcb_h(['open' => 'Geöffnet', 'closed' => 'Geschlossen', 'maintenance' => 'Wartung'][(string)$track['status']] ?? 'Bitte beim Verein erfragen') ?></strong></dd><?php endif; ?>
</dl>
<?php else: ?><p class="rcb-muted">Die Streckendaten werden im Adminbereich unter „Strecken“ gepflegt.</p><?php endif; ?>
<p><a class="rcb-btn" href="#besuch"><?= rcb_text($kind . '.steckbrief.button', 'Anfahrt & Besuch') ?></a></p>
</div></aside>
</div>
<?php
    $gallery = '';
    for ($i = 1; $i <= 6; $i++) {
        $gallery .= rcb_img_slot($kind . '.galerie.' . $i, $defaults['titel'] . ' – Bild ' . $i);
    }
    if ($gallery !== ''): ?>
<h2 style="margin-top:34px"><?= rcb_text($kind . '.galerie.titel', 'Bilder') ?></h2>
<div class="rcb-gallery"><?= $gallery ?></div>
<?php endif; ?>
<div class="rcb-grid" id="besuch" style="margin-top:34px">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('strecken.hinweis', '<h2>Wichtig vor dem Besuch</h2><ul><li>Trainiert werden kann fast immer – nach Absprache und unter Einhaltung der Fahrzeiten.</li><li>Organisierte Trainings finden bei passendem Wetter statt – bitte vorher anmelden.</li><li>Gastfahrer sind willkommen – bitte meldet euch vorher über <a href="/kontakt.php">Kontakt</a> oder Facebook.</li></ul>') ?></div></article>
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich($kind . '.anfahrt', $defaults['anfahrt']) ?></div></article>
</div>
<?php $other = $kind === 'onroad' ? 'offroad' : 'onroad'; ?>
<div class="rcb-rule-nav" style="margin-top:28px"><a class="rcb-btn rcb-btn-light" href="/#strecken">← Zur Startseite</a><?php if (rcb_page_published($other) || rcb_admin_viewer()): ?><a class="rcb-btn" href="/<?= $other ?>.php">Zur <?= $other === 'onroad' ? 'Onroad' : 'Offroad' ?>-Strecke →</a><?php endif; ?></div>
</div></section>
<?php
    rcb_footer();
}
