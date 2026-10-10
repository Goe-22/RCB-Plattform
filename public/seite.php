<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    http_response_code(400);
    $page = null;
} else {
    $rows = rcb_rows('SELECT title, content FROM pages WHERE slug = ? AND published = 1 LIMIT 1', [$slug]);
    $page = $rows[0] ?? null;
}
if (!$page) {
    http_response_code(404);
}
rcb_header();
?>
<section class="rcb-section"><div class="rcb-wrap">
<?php if ($page): ?>
    <h1><?= rcb_h($page['title']) ?></h1>
    <div class="rcb-content"><?= nl2br(rcb_h($page['content'])) ?></div>
<?php else: ?>
    <h1>Seite nicht gefunden</h1>
    <p>Diese Seite existiert nicht oder wurde noch nicht veröffentlicht.</p>
    <a class="rcb-btn" href="/">Zur Startseite</a>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
