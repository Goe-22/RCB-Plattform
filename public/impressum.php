<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap" style="max-width:860px">
<h1><?= rcb_text('impressum.titel', 'Impressum') ?></h1>
<?= rcb_rich('impressum.inhalt', <<<'HTML'
<h2>Angaben gemäß § 5 DDG</h2>
<p><strong>RC Bande Zwickau e.V.</strong><br>Lassallestraße 51<br>08058 Zwickau</p>
<h3>Vertreten durch den Vorstand</h3>
<p>Stephan Thamm (1. Vorsitzender)</p>
<h3>Kontakt</h3>
<p>E-Mail: <a href="mailto:rcb.zwickau@gmail.com">rcb.zwickau@gmail.com</a></p>
<h3>Registereintrag</h3>
<p>Eingetragen im Vereinsregister<br>Registergericht: Amtsgericht Chemnitz<br>Registernummer: VR 4910</p>
<h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
<p>Stephan Thamm<br>Lassallestraße 51<br>08058 Zwickau</p>
<h3>Streckengelände</h3>
<p>Reichenbacher Straße 125A<br>08056 Zwickau</p>
HTML) ?>
</div></section>
<?php rcb_footer(); ?>
