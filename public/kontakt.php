<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$email = rcb_setting('contact_email');
$phone = rcb_setting('contact_phone');
rcb_header();
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Kontakt</h1><p>Fragen zum Verein, zu unseren Strecken oder zu Veranstaltungen? Melde dich bei uns.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap"><div class="rcb-grid">
<article class="rcb-card"><div class="rcb-card-body"><h2>So erreichst du uns</h2>
<?php if ($email !== ''): ?><p><strong>E-Mail:</strong> <a href="mailto:<?= rcb_h($email) ?>"><?= rcb_h($email) ?></a></p><?php else: ?><p>Die Kontakt-E-Mail wird noch in den Webseiteneinstellungen hinterlegt.</p><?php endif; ?>
<?php if ($phone !== ''): ?><p><strong>Telefon:</strong> <?= rcb_h($phone) ?></p><?php endif; ?>
<p>Die Anschrift und weitere Vereinsangaben werden ergänzt, sobald die offiziellen Angaben vorliegen.</p>
<?php if ($email !== ''): ?><a class="rcb-btn" href="mailto:<?= rcb_h($email) ?>">E-Mail schreiben</a><?php endif; ?></div></article>
<article class="rcb-card"><div class="rcb-card-body"><h2>Du möchtest vorbeikommen?</h2><p>Bitte informiere dich vor deiner Anreise über die veröffentlichten Termine und die Streckeninformationen.</p><a class="rcb-btn" href="/termine.php">Termine ansehen</a> <a class="rcb-btn" href="/strecken.php">Strecken ansehen</a></div></article>
</div></div></section>
<?php rcb_footer(); ?>
