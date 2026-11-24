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
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('kontakt.anfahrt', '<h2>Anfahrt</h2><p><strong>Reichenbacher Straße 125A, 08056 Zwickau</strong></p><p>Unsere beiden Strecken liegen an der Neuplanitzer Straße, gleich an der Ampel Reichenbacher Straße (B173) / Neuplanitzer Straße – auf zwei Seiten der Straße:</p><ul><li><strong>Offroad</strong>: von der Ampel aus links (Flugplatz-Seite) – <a href="https://www.google.com/maps/search/?api=1&amp;query=50.70579%2C12.45868" target="_blank" rel="noopener">Karte</a></li><li><strong>Onroad</strong>: von der Ampel aus rechts, neben dem Jugendclub „Airport Zwickau“ – <a href="https://www.google.com/maps/search/?api=1&amp;query=50.70669%2C12.45999" target="_blank" rel="noopener">Karte</a></li></ul><p>Bitte melde dich vor deinem Besuch an – trainiert wird nach Absprache und bei passendem Wetter.</p>') ?></div></article>
</div>
<div class="rcb-credit" style="margin-top:18px"><?= rcb_rich('kontakt.bildquelle', '<p>Luftbild oben: <a href="https://www.geodaten.sachsen.de/" target="_blank" rel="noopener">Landesamt für Geobasisinformation Sachsen (GeoSN)</a>, <a href="https://www.govdata.de/dl-de/by-2-0" target="_blank" rel="noopener">dl-de/by-2-0</a></p>') ?></div>
</div></section>
<?php rcb_footer(); ?>
