<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$email = rcb_setting('contact_email', 'rcb.zwickau@gmail.com');
rcb_header('join');
echo rcb_page_hero('mitglied', 'Mitglied werden', 'Du möchtest regelmäßig fahren, den Verein kennenlernen oder dich im Vereinsleben einbringen? Wir freuen uns auf deine Anfrage.');
?>
<section class="rcb-section"><div class="rcb-wrap">
<div class="rcb-grid">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('mitglied.ablauf', '<h2>So geht es weiter</h2><ol><li>Nimm Kontakt mit uns auf – per E-Mail oder über Facebook.</li><li>Komm zu einem Training vorbei und lerne uns und die Strecken kennen.</li><li>Wir besprechen gemeinsam die nächsten Schritte und die Vereinsbedingungen.</li></ol>') ?></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h2><?= rcb_text('mitglied.kontakt.titel', 'Kontakt') ?></h2><p><?= rcb_text('mitglied.kontakt.text', 'Schreib uns, wenn du den Verein kennenlernen möchtest.') ?></p>
<?php if ($email !== ''): ?><p><strong>E-Mail:</strong> <a href="mailto:<?= rcb_h($email) ?>"><?= rcb_h($email) ?></a></p><?php endif; ?>
<a class="rcb-btn" href="/kontakt.php">Kontaktseite öffnen</a></div></article>
</div>
<div style="margin-top:24px"><?= rcb_rich('mitglied.hinweis', '<p class="rcb-muted">Mitgliedsbeiträge, Aufnahmeantrag und Satzung werden hier ergänzt.</p>') ?></div>
</div></section>
<?php rcb_footer(); ?>
