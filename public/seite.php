<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once dirname(__DIR__) . '/app/Helpers/RichText.php';

// Eigene Seiten (Admin → Seiten → Eigene Seiten). Unveröffentlichte Seiten sehen nur angemeldete Admins.
$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$page = null;
if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    $page = rcb_custom_pages()[$slug] ?? null;
}
if (!$page || ((int)$page['published'] !== 1 && !rcb_admin_viewer())) {
    rcb_not_found();
}
$legacy = rcb_rows('SELECT content FROM pages WHERE slug = ? LIMIT 1', [$slug]);
$defaultHtml = RichText::fromPlain((string)($legacy[0]['content'] ?? ''));
if ($defaultHtml === '') {
    $defaultHtml = '<p>Hier steht der Inhalt dieser Seite.</p>';
}
rcb_header('seite:' . $slug);
echo rcb_page_hero('seite.' . $slug, (string)$page['title'], '');
?>
<?php if ((int)$page['published'] !== 1): ?><div class="rcb-wrap"><div class="rcb-empty" style="margin-top:20px;border-color:#f6c76b;background:#fff8e6"><strong>Nicht veröffentlicht:</strong> Diese Seite sehen nur angemeldete Admins. Veröffentlichen unter Admin → Seiten.</div></div><?php endif; ?>
<section class="rcb-section"><div class="rcb-wrap" style="max-width:960px">
<?= rcb_rich('seite.' . $slug . '.inhalt', $defaultHtml) ?>
</div></section>
<?php rcb_footer(); ?>
