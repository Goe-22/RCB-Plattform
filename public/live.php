<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$enabled = rcb_setting('live_enabled', '0') === '1';
rcb_header('live');
?>
<section class="rcb-hero"><div class="rcb-hero-inner"><h1>Live-Timing</h1><p>Rundenzeiten und Rennstände – direkt von der Zeitnahme, sobald die Schnittstelle eingerichtet ist.</p></div></section>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($enabled): ?>
<div class="rcb-empty"><h2>Live-Timing ist aktiviert</h2><p>Die Anzeige der Zeitnahmedaten wird ergänzt, sobald Quelle und Übertragungsformat eingerichtet sind.</p></div>
<?php else: ?>
<div class="rcb-empty"><h2>Derzeit keine Live-Übertragung</h2><p>Aktuell ist keine Live-Zeitnahme für die öffentliche Webseite freigeschaltet. Die technische Anbindung an RCM Ultimate wird separat eingerichtet.</p></div>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
