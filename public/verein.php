<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
rcb_header('club');
echo rcb_page_hero('verein', 'Unser Verein', 'Gemeinsam fahren, gemeinsam lernen und die Begeisterung für RC-Modellsport in Zwickau teilen.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<?= rcb_rich('verein.inhalt', '<h2>RC-Modellsport verbindet</h2>
<p>Die RC Bande Zwickau e.V. ist ein gemeinnütziger Verein zur Förderung des Modellbaus. Unter anderem haben wir das Ziel, Kindern und Jugendlichen eine sinnvolle Freizeitbeschäftigung zu bieten.</p>
<p>Dank unserer zahlreichen Mitglieder haben wir ein weites Spektrum an Wissen im Bereich Modellbau – von Technik und Fahrzeugaufbau über Abstimmung bis zum Rennbetrieb –, das an euch weitergegeben werden kann.</p>
<p>Zu unserem Verein gehören zwei Bereiche: <strong>Onroad</strong> auf Asphalt und <strong>Offroad</strong> auf Kunstrasen, jeweils mit eigener Strecke.</p>') ?>
<div class="rcb-grid">
<article class="rcb-card"><div class="rcb-card-body"><h3><?= rcb_text('verein.karte1.titel', 'Fahren & trainieren') ?></h3><p><?= rcb_text('verein.karte1.text', 'Entdecke unsere Strecken und die kommenden Trainingsmöglichkeiten.') ?></p><a class="rcb-btn" href="/strecken.php">Strecken ansehen</a></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h3><?= rcb_text('verein.karte2.titel', 'Gemeinschaft') ?></h3><p><?= rcb_text('verein.karte2.text', 'Erlebe den RC-Modellsport zusammen mit anderen Fahrerinnen und Fahrern.') ?></p><a class="rcb-btn" href="/termine.php">Termine ansehen</a></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h3><?= rcb_text('verein.karte3.titel', 'Mitmachen') ?></h3><p><?= rcb_text('verein.karte3.text', 'Du möchtest den Verein kennenlernen oder Mitglied werden? Nimm Kontakt mit uns auf.') ?></p><a class="rcb-btn" href="/mitglied-werden.php">Mitglied werden</a></div></article>
</div>
</div></section>
<?php rcb_footer(); ?>
