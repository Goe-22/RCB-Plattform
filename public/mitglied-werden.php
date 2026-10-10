<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$email = rcb_setting('contact_email');
$phone = rcb_setting('contact_phone');
rcb_header('join');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Mitglied werden</h1><p>Du möchtest regelmäßig fahren, den Verein kennenlernen oder dich im Vereinsleben einbringen? Wir freuen uns auf deine Anfrage.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<div class="rcb-grid">
<article class="rcb-card"><div class="rcb-card-body"><h2>So geht es weiter</h2><ol><li>Nimm Kontakt mit uns auf.</li><li>Stelle deine Fragen zum Verein, zu Trainingszeiten und zur Mitgliedschaft.</li><li>Wir besprechen gemeinsam die nächsten Schritte und die geltenden Vereinsbedingungen.</li></ol></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h2>Kontakt</h2><p>Schreibe uns oder ruf an, wenn du den Verein kennenlernen möchtest.</p>
<?php if ($email !== ''): ?><p><strong>E-Mail:</strong> <a href="mailto:<?= rcb_h($email) ?>"><?= rcb_h($email) ?></a></p><?php else: ?><p>Die Kontakt-E-Mail wird noch in den Webseiteneinstellungen hinterlegt.</p><?php endif; ?>
<?php if ($phone !== ''): ?><p><strong>Telefon:</strong> <?= rcb_h($phone) ?></p><?php endif; ?>
<a class="rcb-btn" href="/kontakt.php">Kontaktseite öffnen</a></div></article>
</div>
<p class="rcb-muted" style="margin-top:20px">Mitgliedsbeiträge, Aufnahmebedingungen und Antragsunterlagen werden ergänzt, sobald die verbindlichen Vereinsangaben hinterlegt sind.</p>
</div></section>
<?php rcb_footer(); ?>
