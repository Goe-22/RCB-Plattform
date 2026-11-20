<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap" style="max-width:860px">
<h1><?= rcb_text('datenschutz.titel', 'Datenschutzerklärung') ?></h1>
<?= rcb_rich('datenschutz.inhalt', <<<'HTML'
<h2>1. Verantwortlicher</h2>
<p>RC Bande Zwickau e.V., vertreten durch den 1. Vorsitzenden Stephan Thamm<br>Lassallestraße 51, 08058 Zwickau<br>E-Mail: <a href="mailto:rcb.zwickau@gmail.com">rcb.zwickau@gmail.com</a></p>
<h2>2. Allgemeines</h2>
<p>Wir verarbeiten personenbezogene Daten nur, soweit dies zur Bereitstellung dieser Website und unserer Vereinsangebote erforderlich ist. Rechtsgrundlagen sind insbesondere Art. 6 Abs. 1 lit. b DSGVO (Vertrag/Mitgliedschaft), lit. c (rechtliche Pflichten) und lit. f (berechtigtes Interesse).</p>
<h2>3. Hosting und Server-Logfiles</h2>
<p>Diese Website wird bei einem externen Webhoster (LXI, lxi.be) betrieben. Beim Aufruf der Website verarbeitet der Server automatisch Informationen, die Ihr Browser übermittelt: IP-Adresse, Datum und Uhrzeit des Zugriffs, aufgerufene Seite, Referrer-URL, Browsertyp und Betriebssystem. Diese Daten sind technisch erforderlich, um die Website sicher und stabil auszuliefern (Art. 6 Abs. 1 lit. f DSGVO). Sie werden nicht mit anderen Daten zusammengeführt und nach Ablauf der beim Hoster üblichen Speicherfrist gelöscht. Mit dem Hoster besteht bzw. wird ein Vertrag zur Auftragsverarbeitung geschlossen (Art. 28 DSGVO).</p>
<h2>4. Cookies</h2>
<p>Für Besucherinnen und Besucher setzt diese Website keine Cookies und verwendet keine Analyse- oder Trackingdienste. Nur bei der Anmeldung im passwortgeschützten Verwaltungsbereich wird ein technisch notwendiges Sitzungs-Cookie gesetzt, das beim Schließen des Browsers gelöscht wird.</p>
<h2>5. Kontakt per E-Mail</h2>
<p>Wenn Sie uns per E-Mail kontaktieren, verarbeiten wir Ihre Angaben (z. B. Name, E-Mail-Adresse, Inhalt der Nachricht), um Ihre Anfrage zu beantworten (Art. 6 Abs. 1 lit. b bzw. f DSGVO). Die Daten werden gelöscht, sobald sie für diesen Zweck nicht mehr benötigt werden und keine gesetzlichen Aufbewahrungspflichten bestehen.</p>
<h2>6. Veranstaltungen, Ergebnisse und Fotos</h2>
<p>Bei Rennen veröffentlichen wir Ergebnislisten mit Namen der Teilnehmenden, in der Regel über den Zeitnahmedienst myRCM. Fotos von Veranstaltungen veröffentlichen wir im Rahmen unseres berechtigten Interesses an der Berichterstattung über das Vereinsleben bzw. mit Einwilligung. Wenn Sie nicht abgebildet oder genannt werden möchten, genügt eine kurze Nachricht an uns.</p>
<h2>7. Externe Links</h2>
<p>Diese Website enthält Links zu Angeboten anderer Anbieter (z. B. Facebook, myRCM, OpenStreetMap). Diese Inhalte werden nicht eingebettet; Daten werden erst übertragen, wenn Sie einen Link anklicken. Für die Verarbeitung auf diesen Seiten ist der jeweilige Anbieter verantwortlich.</p>
<h2>8. Ihre Rechte</h2>
<p>Sie haben das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) sowie Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21). Eine erteilte Einwilligung können Sie jederzeit mit Wirkung für die Zukunft widerrufen. Wenden Sie sich dazu an die oben genannte E-Mail-Adresse.</p>
<p>Sie haben außerdem das Recht, sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren, z. B. bei der Sächsischen Datenschutz- und Transparenzbeauftragten.</p>
<p><em>Stand: November 2026</em></p>
HTML) ?>
</div></section>
<?php rcb_footer(); ?>
