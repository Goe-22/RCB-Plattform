<?php
declare(strict_types=1);

/**
 * Detailseite einer Strecke (Onroad/Offroad): ausführliche Beschreibung, Steckbrief aus der
 * Streckenverwaltung, Klassen, Galerie. Startseite und Streckenübersicht zeigen nur Kurzfassungen.
 */
function rcb_track_page(string $kind): void
{
    $defaults = [
        'onroad' => [
            'titel' => 'Onroad-Strecke',
            'zeile' => 'Asphalt · 210 m · Tourenwagen 1:10',
            'inhalt' => '<h2>Über die Strecke</h2><p>Unsere Onroad-Strecke ist eine rund 210 m lange Asphaltstrecke, die wir vom 1. RC-MSC Kirchberg übernommen haben. Am Fahrerstand haben bis zu 10 Fahrerinnen und Fahrer Platz.</p><p>Gefahren werden hier vor allem Tourenwagen im Maßstab 1:10 – vom Einsteiger bis zur Rennklasse.</p>',
            'klassen' => '<h2>Diese Klassen fahren hier</h2><ul><li><a href="/reglement.php?klasse=tt02">TT02 Cup</a></li><li><a href="/reglement.php?klasse=einsteiger">Einsteiger</a></li><li><a href="/reglement.php?klasse=tw175">TW 17,5t Stock</a></li><li><a href="/reglement.php?klasse=fwd">FWD Cup</a></li><li><a href="/reglement.php?klasse=555">555 Runden Team-Rennen</a></li></ul>',
        ],
        'offroad' => [
            'titel' => 'Offroad-Strecke',
            'zeile' => 'Kunstrasen · 270 m · Buggys bis 1:8',
            'inhalt' => '<h2>Über die Strecke</h2><p>Unsere Offroad-Strecke ist rund 270 m lang und mit Kunstrasen belegt. Sie ist für Modelle im Maßstab 1:8 und kleiner geeignet. Am Fahrerstand haben bis zu 10 Fahrerinnen und Fahrer Platz.</p><p>Hier sind vor allem Elektro- und Nitro-Buggys unterwegs.</p>',
            'klassen' => '<h2>Diese Klassen fahren hier</h2><ul><li><a href="/reglement.php?klasse=ore8">ORE8 Cup (Buggy Elektro)</a></li><li><a href="/reglement.php?klasse=or8">OR8 Cup (Buggy Nitro)</a></li></ul>',
        ],
    ][$kind];
    $track = rcb_tracks_by_kind()[$kind];

    rcb_header('tracks');
    echo rcb_page_hero($kind, $defaults['titel'], $defaults['zeile'], rcb_track_image($kind));
    ?>
<section class="rcb-section"><div class="rcb-wrap"><div class="rcb-detail">
<div class="rcb-detail-main">
<?= rcb_rich($kind . '.inhalt', $defaults['inhalt']) ?>
<?= rcb_rich($kind . '.klassen', $defaults['klassen']) ?>
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
<p><a class="rcb-btn" href="/strecken.php#besuch"><?= rcb_text($kind . '.steckbrief.button', 'Anfahrt & Besuch') ?></a></p>
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
<p style="margin-top:28px"><a class="rcb-btn rcb-btn-light" href="/strecken.php">← Alle Strecken</a></p>
</div></section>
<?php
    rcb_footer();
}
