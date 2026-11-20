<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$helfer = '<ul><li><strong>Nach jedem Lauf stellt die fertige Gruppe die Helfer für die nächste Gruppe.</strong></li><li><strong>Nach jedem Lauf verbleiben die Fahrzeuge im Parc fermé für die Dauer des nächsten Laufes.</strong></li></ul>';
$lipo = 'Das Laden und Lagern von LiPo-Akkus ist nur im LiPo-Safe-Bag oder Ähnlichem erlaubt.';
$vorbehalt = '<p>Der Veranstalter behält sich vor, bei großer Teilnehmerzahl die Anzahl und die Laufzeiten unter das Minimum anzupassen.</p>';
$punkte = '<li>Der Sieger eines Vorlaufdurchgangs erhält 0 Punkte, der Zweitplatzierte 2 Punkte, der Dritte 3 Punkte, der Vierte 4 Punkte …</li><li>Gewertet wird der beste Vorlauf.</li>';

$sections = [
    'funcup' => ['Fun Cup 2026', '<p><strong>Erstellung der Gesamtwertung</strong></p>
<ul><li><a href="https://storage.e.jimdo.com/file/236d195d-c12b-4518-9f7d-06880dbef797/121er%20Tabelle.jpg">121-Punkte-Tabelle nach DMC</a></li></ul>
<p><strong>Streichergebnisse</strong></p>
<ul><li>bis 4 Läufe: 1</li><li>5 und 6 Läufe: 2</li><li>ab 7 Läufen: 3</li></ul>'],
    '555' => ['555 Runden Team-Rennen', '<p><strong>Allgemein:</strong></p><ul><li>max. 12 Teams</li><li>pro Team 3–6 Fahrer</li></ul>
<p><strong>Fahrzeug:</strong></p><ul><li>1 Auto pro Team, bei Defekt muss repariert werden</li><li>Fahrzeug nach TT02 Fun Cup</li></ul>
<p><strong>Motor:</strong></p><ul><li>1 zugeloster Motor (bei Defekt wechselbar)</li></ul>
<p><strong>Akku:</strong></p><ul><li>max. Fahrzeit pro Akku 20 min, Akkuwechsel erfolgt mit abgenommener Karosserie und muss gemeldet werden</li></ul>
<p><strong>Startgebühr:</strong></p><ul><li>pro Team: 50 €</li></ul>
<p><strong>Qualifikationsmodus:</strong></p><ul><li>3 zusammenhängende schnellste Runden</li></ul>
<p><strong>Zeitplan:</strong></p><ul><li>bis 10 Uhr freies Training</li><li>10:10 Uhr Fahrerbesprechung</li><li>10:30–11:30 Uhr Qualifikation</li><li>12:00 Uhr Start Rennen</li><li>anschließend Siegerehrung</li><li>danach die Saison gemeinsam ausklingen lassen</li></ul>'],
    'tt02' => ['TT02 Cup', '<p><a href="/ergebnisse.php"><strong>Gesamtwertung</strong></a></p>
<p><strong>Fahrzeug:</strong></p><ul><li>erlaubt ist nur der Tamiya TT02 (keine Sondermodelle wie S, RR usw.)</li></ul>
<p><strong>Spurbreite:</strong></p><ul><li>max. 200 mm</li></ul>
<p><strong>Motor:</strong></p><ul><li>Tamiya Torque Tuned Motor (wird vom Veranstalter an jedem Wertungstag ausgegeben)</li></ul>
<p><strong>Regler:</strong></p><ul><li>300045057 TBLE-02S (Baukastenregler)</li><li>300045069 TBLE-04S (Baukastenregler)</li><li>300045070 TBLE-04SR (Baukastenregler)</li><li>300045055 TEU-105BK (Baukastenregler)</li><li>500906134 CARSON Viper Sport 20T wasserfest</li><li>500906264 Dragster Brushed 70A</li><li>Baukastenregler THW-1060 (<em>wird empfohlen</em>)</li></ul>
<p><strong>Untersetzung:</strong></p><ul><li>Es ist nur die Baukasten-Untersetzung <strong>70 : 22</strong> erlaubt.</li></ul>
<p><strong>Karosserien:</strong></p><ul><li>Zugelassen sind alle Tamiya-Baukasten-Karosserien.</li><li><strong>Nicht zugelassen sind <em>300053824 Subaru Legacy</em> und Lightweight-Varianten der Karosserien.</strong></li><li>Die Karosserien müssen lackiert sein.</li></ul>
<p><strong>Spoiler:</strong></p><ul><li>alle zur Karosserie gehörenden Spoiler</li><li>300054729 Racing Spoiler Alpha</li><li>Racing Spoiler Alpha aus dem Raikiri-Set</li></ul>
<p><strong>Akkus:</strong></p><ul><li>Erlaubt sind alle 2S-LiPo- und 7,4-V-NiMH-Akkus. Die Ladeschlussspannung darf 8,4 V nicht überschreiten. ' . $lipo . '</li><li>Das Steckersystem ist freigestellt.</li></ul>
<p><strong>Reifen:</strong></p><ul><li>RCK 210004 – RCK-36A XLT Komplettrad</li><li>Baukasten-Originalreifen (nicht empfohlen, da die Performance schlechter ist als bei den RCK-Reifen)</li><li>Tamiya 300099645 Euro-Cup-Komplettrad-Satz</li></ul>
<p><strong>Reifenheizen:</strong></p><ul><li>Das Aufheizen der Reifen ist nicht erlaubt.</li></ul>
<p><strong>Mindestgewicht:</strong></p><ul><li>1430 g</li></ul>
<p><strong>Differential:</strong></p><ul><li>Die Differentiale dürfen mit verschiedenen Ölen und Fetten sowie mit Diffputty gesperrt werden. Es <strong>muss</strong> aber eine Differentialwirkung gegeben sein! <strong>KEIN SPOOL!</strong></li></ul>
<p><strong>Es sind nur die Original-Baukastenfedern erlaubt! Art.-Nr. 19805699</strong></p>
<ul><li>Die Reparatur der Lenkung mittels langer Schraube ist ohne Kugellager zulässig.</li></ul>
<p><strong>Tuning</strong> (die Montage folgender Teile ist jedem freigestellt):</p>
<ul><li>Kugellager frei</li><li>Schrauben frei</li><li>Öle und Fette frei</li><li><a href="https://tamico.de/Tamiya-CVAOeldruck-Stossdaempfer-Super-Mini-2-Stk-50746">Öldruckdämpferset (300050746)</a> oder <a href="https://tamico.de/Tamiya-54753-CVA-Super-Mini-Daempfer-Set-4-TT-01-TT-02">Öldruckdämpferset (300054753)</a></li><li>Es sind alle Metallritzel mit 22 Zähnen erlaubt.</li><li>300054559 Querlenkerkugeln Low Friction (4)</li><li>300054477 TT-02 Diff.-Abtriebe Kardanwellen (2) <strong>NUR AN DER VORDERACHSE!</strong></li><li>300053792 Kugel-Kardanwellen (2) <strong>NUR AN DER VORDERACHSE!</strong></li><li>TT02-015 Yeah Racing Stahl-Kardanwellen / CVDs (2 Stk.) <strong>NUR AN DER VORDERACHSE!</strong></li><li>300054550 TT-02/B Schaftschraube 3x18 mm (2) Low Friction</li><li>Die Passschrauben MB4 3x14 mm 50582 dürfen durch die metrischen Passschrauben 3x14 mm 54409 zur Verringerung des Lenkspiels ersetzt werden.</li><li>Zur Kühlung des Motors ist die Montage eines Lüfters freigestellt.</li><li>Lenkanschläge dürfen nicht bearbeitet werden.</li><li>Lenkservo und Servosaver / Servohorn sind freigestellt.</li></ul>
<h3>Grundsätzlich gilt: Was nicht ausdrücklich erlaubt ist, ist VERBOTEN!</h3>' . $helfer . '
<p><strong>Durchführung als eigene Veranstaltung:</strong></p><ul><li>2–4 Vorläufe mit 5–7 Minuten</li>' . $punkte . '<li>Jeder fährt 2–3 Hauptfinale A, B, C … mit 10–15 Minuten.</li></ul>
<p><strong>Durchführung als Gastklasse:</strong></p><ul><li>2–5 Vorläufe mit 5 Minuten</li>' . $punkte . '<li>Jeder fährt 2–4 Hauptfinale A, B, C … mit 7 Minuten.</li></ul>' . $vorbehalt],
    'einsteiger' => ['Einsteiger', '<p><strong>Fahrzeug:</strong></p><ul><li>erlaubt sind alle Tourenwagen</li><li>Spurbreite: max. 200 mm</li></ul>
<p><strong>Motor:</strong></p><ul><li>Erlaubt sind Büchsenmotoren oder vergleichbare Brushless-Motoren.</li><li>Die Drehzahl an der Radachse wird mit dem Messgerät bei allen gleich eingestellt.</li><li>max. Drehzahl: folgt</li></ul>
<p><strong>Untersetzung:</strong></p><ul><li>freigestellt</li></ul>
<p><strong>Reifen:</strong></p><ul><li>freigestellt</li><li><em>Empfohlen werden die</em> RCK 210004 – RCK-36A XLT Komplettrad.</li></ul>
<p><strong>Akkus:</strong></p><ul><li>Erlaubt sind alle 2S-LiPo- und 7,4-V-NiMH-Akkus. Die Ladeschlussspannung darf 8,4 V nicht überschreiten. ' . $lipo . '</li></ul>
<p><strong>Durchführung:</strong></p><ul><li>2–5 Vorläufe mit 5 Minuten</li>' . $punkte . '<li>Jeder fährt 2–4 Hauptfinale A, B, C … mit 5 Minuten.</li></ul>' . $vorbehalt],
    'tw175' => ['TW 17,5t Stock', '<p><a href="https://www.myrcm.ch/myrcm/championship/de/12819"><strong>Gesamtwertung</strong></a></p>
<p><strong>Fahrzeuge:</strong></p><ul><li>Erlaubt sind alle 4WD-Chassis.</li><li>Die maximale Breite beträgt 200 mm.</li></ul>
<p><strong>Untersetzung:</strong></p><ul><li>Die maximale Übersetzung darf nicht länger als 1:4,5 sein.</li></ul>
<p><strong>Motor:</strong></p><ul><li>Alle in Europa kaufbaren 17,5t-Brushless-Motoren sind erlaubt.</li><li>Zusätzlich sind alle Brushless-Motoren bis 3000 kV erlaubt.</li></ul>
<p><strong>Regler:</strong></p><ul><li>Erlaubt sind alle elektronischen Fahrregler, die den „EFRA 0-Boost-Blinky-Modus“ erfüllen.</li></ul>
<p><strong>Karosserien:</strong></p><ul><li>Alle handelsüblichen 190- und 200-mm-Tourenwagen-Karosserien sind erlaubt.</li><li>Die Karosserien müssen lackiert sein.</li><li>Heckspoiler müssen an den dafür vorgesehenen Punkten der Karosserie montiert sein und dürfen das Karosseriedach nicht überragen.</li></ul>
<p><strong>Reifen:</strong></p><ul><li>Reifen und Felgen sind freigestellt, Moosgummireifen sind verboten.</li></ul>
<p><strong>Akkus:</strong></p><ul><li>Erlaubt sind alle 2S-LiPo-Akkus.</li><li>Die Ladeschlussspannung darf 8,4 V nicht überschreiten.</li><li>' . $lipo . '</li></ul>' . $helfer . '
<p><strong>Durchführung:</strong></p><ul><li>2–5 Vorläufe mit 5 Minuten</li>' . $punkte . '<li>Jeder fährt 2–4 Hauptfinale A, B, C … mit 7 Minuten.</li></ul>
<p>Der Veranstalter behält sich vor, bei großer Teilnehmerzahl die Anzahl der Läufe unter das Minimum anzupassen.</p>'],
    'fwd' => ['FWD Cup', '<p><a href="https://www.myrcm.ch/myrcm/championship/de/12792"><strong>Gesamtwertung</strong></a></p>
<ul><li>Gefahren wird angelehnt an das aktuell gültige RCK-Challenge-Reglement.</li></ul>
<h3>Zusätzlich:</h3>
<p><strong>Motor:</strong></p><ul><li>Hobbywing Xerun Justock 17,5t G2.1</li><li>RCK 17,5t (Art.-Nr. 190003 oder 230051) mit 19.500 U/min bei 8,4 V</li></ul>
<p><strong>Übersetzung:</strong></p><ul><li><em>Hobbywing 17,5t:</em> 1:4,5</li><li><em>RCK 17,5t:</em> 1:4,4</li></ul>
<p><strong>Karosserie:</strong></p><ul><li>Es sind alle vorbildgetreuen FWD-Karosserien zugelassen, die im Original ebenfalls frontangetriebenen Fahrzeugen entsprechen.</li></ul>' . $helfer . '
<p>Der Veranstalter behält sich vor, bei großer Teilnehmerzahl die Anzahl der Läufe unter das Minimum anzupassen.</p>'],
    'ore8' => ['ORE8 Cup (Buggy Elektro)', '<ul><li>Zugelassen sind <strong>alle 1/8-Buggys mit Elektroantrieb</strong> und einem <strong>Mindestgewicht</strong> von 4WD <strong>3000 g</strong> oder 2WD <strong>2500 g</strong>.</li><li>2-Gang-Getriebe, Lenkhilfen, Bremskontrolle (ABS) und Traktionskontrolle sind nicht erlaubt.</li><li>Hilfsmittel an den Reifen (Spikes, Ketten …), die nicht aus Gummi sind, sind nicht erlaubt. Moosgummireifen sind nicht erlaubt.</li><li>Die Übertragung von <strong>Telemetriedaten</strong> ist <strong>verboten</strong>.</li><li><strong>Zugelassen</strong> sind alle <strong>Motoren</strong> mit einem <strong>Wellendurchmesser</strong> von <strong>5 mm</strong>.</li><li>Als Akku sind <strong>maximal 4 Zellen LiXX</strong> im <strong>Hardcase</strong> mit einer <strong>maximalen Standard-Ladeschlussspannung</strong> von <strong>4,2 V</strong> pro Zelle erlaubt. Sie dürfen <strong>nicht</strong> auf <strong>HV</strong> geladen werden. ' . $lipo . '</li></ul>' . $helfer . '
<p><strong>Durchführung:</strong></p><ul><li>2–4 Vorläufe mit 5–7 Minuten</li><li>Subfinale (1/2- und 1/4-Finale) mit 7–9 Minuten</li><li>Jeder fährt 2–3 Hauptfinale A, B, C … mit 8–10 Minuten.</li></ul>' . $vorbehalt],
    'or8' => ['OR8 Cup (Buggy Nitro)', '<ul><li>Zugelassen sind <strong>alle 1/8-Buggys mit Nitroantrieb</strong> und einem <strong>Mindestgewicht</strong> von 4WD <strong>3000 g</strong> oder 2WD <strong>2500 g</strong>.</li><li>2-Gang-Getriebe, Lenkhilfe, Bremskontrolle (ABS) und Traktionskontrolle sind nicht erlaubt.</li><li>Hilfsmittel an den Reifen (Spikes, Ketten …), die nicht aus Gummi sind, sind nicht erlaubt. Moosgummireifen sind nicht erlaubt.</li><li>Die Übertragung von <strong>Telemetriedaten</strong> ist <strong>verboten</strong>.</li><li>Zugelassen sind alle 2-Takt-<strong>Motoren</strong> mit <strong>maximal 3,5 cm³</strong>.</li><li><strong>Vergaser</strong> dürfen einen maximalen <strong>Durchlass</strong> von <strong>9,0 mm</strong> aufweisen.</li><li>Die <strong>Größe des Tanks</strong> einschließlich Tankstutzen und Anschlussschläuchen darf <strong>125 ml nicht überschreiten</strong>. Lose Teile im Tank, die nicht der Kraftstoffabsaugung dienen, sind nicht erlaubt.</li><li>Der <strong>Nitromethangehalt</strong> im <strong>Sprit</strong> darf <strong>nicht mehr</strong> als <strong>16 % by weight</strong> betragen.</li><li>' . $lipo . '</li></ul>' . $helfer . '
<p><strong>Durchführung:</strong></p><ul><li>2–4 Vorläufe mit 5–7 Minuten</li><li>Subfinale (1/2- und 1/4-Finale) mit 8–10 Minuten</li><li>Jeder fährt 1 Hauptfinale A, B, C … mit 20–45 Minuten.</li></ul>' . $vorbehalt],
];
$teaser = [
    'funcup' => 'Gesamtwertung und Streichergebnisse',
    '555' => 'Teamrennen · TT02 · Zeitplan und Startgebühr',
    'tt02' => 'Onroad · Tamiya TT02 · Technik, Tuning und Ablauf',
    'einsteiger' => 'Onroad · Tourenwagen für Einsteiger',
    'tw175' => 'Onroad · 4WD-Tourenwagen 17,5t Stock',
    'fwd' => 'Onroad · Frontantrieb nach RCK-Challenge',
    'ore8' => 'Offroad · 1/8-Buggy Elektro',
    'or8' => 'Offroad · 1/8-Buggy Nitro',
];
// Weitere Reglements, die früher als Inhaltsseite (Kennung reglement-…) angelegt wurden.
$extra = rcb_rows("SELECT slug, title FROM pages WHERE published = 1 AND slug LIKE 'reglement-%' ORDER BY title");

$klasse = isset($_GET['klasse']) ? (string)$_GET['klasse'] : '';
if ($klasse !== '') {
    // ---------- Unterseite: Regelwerk einer Klasse ----------
    if (!isset($sections[$klasse])) {
        rcb_not_found();
    }
    [$title, $html] = $sections[$klasse];
    rcb_header('rules');
    echo '<section class="rcb-hero rcb-hero-compact"><div class="rcb-hero-inner"><p><a class="rcb-pill" href="/reglement.php">← Alle Klassen</a></p><h1>'
        . rcb_text('reglement.' . $klasse . '.titel', $title) . '</h1><p>'
        . rcb_text('reglement.' . $klasse . '.kurz', $teaser[$klasse] ?? '') . '</p></div></section>';
    ?>
<section class="rcb-section"><div class="rcb-wrap" style="max-width:960px">
<article class="rcb-card rcb-rule"><div class="rcb-card-body">
<?= rcb_rich('reglement.' . $klasse, $html) ?>
</div></article>
<?php $keys = array_keys($sections); $pos = array_search($klasse, $keys, true); ?>
<nav class="rcb-rule-nav" aria-label="Weitere Klassen">
<?php if ($pos > 0): $prev = $keys[$pos - 1]; ?><a class="rcb-btn" href="/reglement.php?klasse=<?= rcb_h($prev) ?>">← <?= rcb_h(rcb_block('reglement.' . $prev . '.titel', $sections[$prev][0])) ?></a><?php endif; ?>
<a class="rcb-btn rcb-btn-light" href="/reglement.php">Alle Klassen</a>
<?php if ($pos < count($keys) - 1): $next = $keys[$pos + 1]; ?><a class="rcb-btn" href="/reglement.php?klasse=<?= rcb_h($next) ?>"><?= rcb_h(rcb_block('reglement.' . $next . '.titel', $sections[$next][0])) ?> →</a><?php endif; ?>
</nav>
</div></section>
<?php
    rcb_footer();
    exit;
}

// ---------- Hauptseite: allgemeiner Teil und Liste der Klassen ----------
rcb_header('rules');
echo rcb_page_hero('reglement', 'Reglement & Ranglisten', 'Regeln dienen der Sicherheit, Fairness und einem guten Miteinander auf der Strecke.');
?>
<section class="rcb-section"><div class="rcb-wrap" style="max-width:960px">
<?= rcb_rich('reglement.allgemein', '<h2>Allgemeine Hinweise</h2><ul><li>Den Anweisungen der Veranstaltungsleitung und Streckenaufsicht ist Folge zu leisten.</li><li>Fahrzeuge dürfen nur auf der freigegebenen Strecke betrieben werden.</li><li>Bei Bergung oder Arbeiten auf der Strecke muss sichergestellt sein, dass kein Fahrzeug fährt.</li><li>Rücksichtnahme, sportliches Verhalten und ein respektvoller Umgang sind für alle selbstverständlich.</li><li>Nach jedem Lauf stellt die fertige Gruppe die Helfer für die nächste Gruppe.</li><li>Das Laden und Lagern von LiPo-Akkus ist nur im LiPo-Safe-Bag oder Ähnlichem erlaubt.</li></ul>') ?>
<?= rcb_rich('reglement.intro', '<p>Ergebnisse vergangener Meisterschaften und Rennen findest du unter <a href="/ergebnisse.php">Ergebnisse</a>.</p>') ?>
<h2 style="margin-top:30px"><?= rcb_text('reglement.klassen.titel', 'Klassen & Regelwerke') ?></h2>
<p class="rcb-muted"><?= rcb_text('reglement.klassen.text', 'Wähle eine Klasse, um das vollständige Regelwerk zu öffnen.') ?></p>
<div class="rcb-class-list">
<?php foreach ($sections as $key => [$title]): ?>
<a class="rcb-class" href="/reglement.php?klasse=<?= rcb_h($key) ?>"><span><strong><?= rcb_h(rcb_block('reglement.' . $key . '.titel', $title)) ?></strong><small><?= rcb_h(rcb_block('reglement.' . $key . '.kurz', $teaser[$key] ?? '')) ?></small></span><span aria-hidden="true">›</span></a>
<?php endforeach; ?>
<?php foreach ($extra as $page): ?>
<a class="rcb-class" href="/seite.php?slug=<?= rcb_h(rawurlencode((string)$page['slug'])) ?>"><span><strong><?= rcb_h($page['title']) ?></strong><small>Weiteres Regelwerk</small></span><span aria-hidden="true">›</span></a>
<?php endforeach; ?>
</div>
</div></section>
<?php rcb_footer(); ?>
