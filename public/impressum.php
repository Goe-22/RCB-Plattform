<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$custom = rcb_rows("SELECT title, content FROM pages WHERE slug = 'impressum' AND published = 1 LIMIT 1);
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap">
<h1>Impressum</h1>
<?php if (!empty($custom[0]['content'])): ?><h2><?= rcb_h($custom[0]['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($custom[0]['content'])) ?></div><?php else: ?>
<div class="rcb-empty"><strong>Vor Veröffentlichung vervollständigen.</strong><p>Bitte hinterlege die rechtlich erforderlichen Angaben in einer veröffentlichten Inhaltsseite mit der Seitenkennung <code>impressum</code>.</p><p>Erforderlich sind insbesondere der vollständige Vereinsname, die ladungsfähige Anschrift, vertretungsberechtigte Personen sowie Kontaktangaben und gegebenenfalls Register- und Umsatzsteuerangaben.</p><p>Diese Vorlage ist nicht als vollständige Rechtsberatung oder fertiges Impressum zu verstehen.</p></div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
