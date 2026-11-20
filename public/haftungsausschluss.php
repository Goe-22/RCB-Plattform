<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap" style="max-width:860px">
<h1><?= rcb_text('haftungsausschluss.titel', 'Haftungsausschluss') ?></h1>
<?= rcb_rich('haftungsausschluss.inhalt', <<<'HTML'
<h2>Haftung für Inhalte</h2>
<p>Die Inhalte dieser Website wurden mit Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte, insbesondere von Terminen, Streckeninformationen und Reglements, übernehmen wir jedoch keine Gewähr. Maßgeblich sind die Angaben der Veranstaltungsleitung vor Ort.</p>
<h2>Haftung für Links</h2>
<p>Diese Website enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Für diese fremden Inhalte ist stets der jeweilige Anbieter oder Betreiber verantwortlich. Zum Zeitpunkt der Verlinkung waren keine Rechtsverstöße erkennbar. Bei Bekanntwerden von Rechtsverletzungen entfernen wir derartige Links umgehend.</p>
<h2>Urheberrecht</h2>
<p>Die durch den Verein erstellten Inhalte und Fotos auf dieser Website unterliegen dem deutschen Urheberrecht. Eine Verwendung außerhalb dieser Website bedarf der vorherigen Zustimmung des Vereins.</p>
<h2>Nutzung der Strecken</h2>
<p>Das Befahren der Strecken erfolgt auf eigene Gefahr und nur nach Absprache mit dem Verein. Den Anweisungen der Verantwortlichen vor Ort ist Folge zu leisten. Für Schäden an Modellen und Ausrüstung haftet der Verein nur bei Vorsatz oder grober Fahrlässigkeit, soweit gesetzlich zulässig.</p>
HTML) ?>
</div></section>
<?php rcb_footer(); ?>
