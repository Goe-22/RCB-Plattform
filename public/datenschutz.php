<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$custom = rcb_rows("SELECT title, content FROM pages WHERE slug = 'datenschutz' AND published = 1 LIMIT 1");
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap">
<h1>Datenschutzhinweise</h1>
<?php if (!empty($custom[0]['content'])): ?><h2><?= rcb_h($custom[0]['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($custom[0]['content'])) ?></div><?php else: ?>
<div class="rcb-empty"><strong>Datenschutzerklärung vor dem Livegang prüfen und vervollständigen.</strong><p>Die endgültigen Angaben hängen unter anderem vom Webhosting, den Server-Logs, verwendeten Cookies, eingebundenen Diensten und den Kontaktwegen ab.</p><p>Lege im Adminbereich eine veröffentlichte Inhaltsseite mit der Seitenkennung <code>datenschutz</code> an, um den abgestimmten Text hier auszugeben.</p><p>Diese Vorlage ersetzt keine rechtliche Prüfung.</p></div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
