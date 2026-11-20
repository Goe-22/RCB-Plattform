<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$email = rcb_setting('contact_email', 'rcb.zwickau@gmail.com');
$phone = rcb_setting('contact_phone');
rcb_header('contact');
echo rcb_page_hero('kontakt', 'Kontakt & Anfahrt', 'Fragen zum Verein, zu unseren Strecken oder zu Veranstaltungen? Melde dich bei uns.');
?>
<section class="rcb-section"><div class="rcb-wrap"><div class="rcb-grid">
<article class="rcb-card"><div class="rcb-card-body"><h2><?= rcb_text('kontakt.erreichen.titel', 'So erreichst du uns') ?></h2>
<?php if ($email !== ''): ?><p><strong>E-Mail:</strong> <a href="mailto:<?= rcb_h($email) ?>"><?= rcb_h($email) ?></a></p><?php endif; ?>
<?php if ($phone !== ''): ?><p><strong>Telefon:</strong> <?= rcb_h($phone) ?></p><?php endif; ?>
<?= rcb_rich('kontakt.erreichen', '<p><strong>Facebook:</strong> <a href="https://de-de.facebook.com/RCBandeZwickau/">RC Bande Zwickau</a></p><p>Die Anmeldung zum Fahren erfolgt per E-Mail oder über Facebook.</p>') ?>
<?php if ($email !== ''): ?><a class="rcb-btn" href="mailto:<?= rcb_h($email) ?>">E-Mail schreiben</a><?php endif; ?></div></article>
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('kontakt.anfahrt', '<h2>Anfahrt</h2><p><strong>Streckengelände der RC Bande Zwickau</strong><br>Reichenbacher Straße 125A<br>08056 Zwickau</p><p><a href="https://www.openstreetmap.org/search?query=Reichenbacher%20Stra%C3%9Fe%20125A%2C%2008056%20Zwickau">Auf der Karte anzeigen</a></p><p>Bitte melde dich vor deinem Besuch an – trainiert wird nach Absprache und bei passendem Wetter.</p>') ?></div></article>
</div></div></section>
<?php rcb_footer(); ?>
