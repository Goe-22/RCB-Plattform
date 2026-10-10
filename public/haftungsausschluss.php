<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$custom = rcb_rows("SELECT title, content FROM pages WHERE slug = 'haftungsausschluss' AND published = 1 LIMIT 1);
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap"><h1>Haftungsausschluss</h1>
<?php if (!empty($custom[0]['content'])): ?><h2><?= rcb_h($custom[0]['title']) ?></h2><div class="rcb-content"><?= nl2br(rcb_h($custom[0]['content'])) ?></div><?php else: ?>
<div class="rcb-empty"><strong>Vereinsbezogene Regelung ergänzen.</strong><p>Bitte hinterlege den vom Verein abgestimmten Haftungsausschluss als veröffentlichte Inhaltsseite mit der Seitenkennung <code>haftungsausschluss</code>. Vor der Veröffentlichung sollte geprüft werden, welche Regelungen für eure Anlage und Veranstaltungen tatsächlich gelten.</p><p>Diese Seite ist nur eine technische Vorlage und keine fertige rechtliche Erklärung.</p></div>
<?php endif; ?></div></section>
<?php rcb_footer(); ?>
