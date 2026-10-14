<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$news = rcb_rows("SELECT * FROM news WHERE published = 1 ORDER BY created_at DESC, id DESC LIMIT 3");
$events = rcb_rows("SELECT * FROM events WHERE published = 1 AND (event_date IS NULL OR event_date = '' OR event_date >= date('now','localtime')) ORDER BY CASE WHEN event_date IS NULL OR event_date = '' THEN 1 ELSE 0 END, event_date ASC, start_time ASC LIMIT 3");
$tracks = rcb_rows("SELECT * FROM tracks WHERE published = 1 ORDER BY name ASC");
$sponsors = rcb_rows("SELECT * FROM sponsors WHERE published = 1 ORDER BY name ASC");
$intro = rcb_rows("SELECT title, content FROM pages WHERE slug = 'startseite' AND published = 1 LIMIT 1");
$siteImages = rcb_rows("SELECT category, filename FROM media WHERE published = 1 AND category IN ('hero','onroad','offroad') ORDER BY uploaded_at DESC, id DESC");
$mediaByCategory = [];
foreach ($siteImages as $siteImage) {
    if (!isset($mediaByCategory[$siteImage['category']])) {
        $mediaByCategory[$siteImage['category']] = (string)$siteImage['filename'];
    }
}
$heroStyle = isset($mediaByCategory['hero'])
    ? "background-image:linear-gradient(125deg,rgba(4,29,58,.82),rgba(13,71,119,.70)),url('/uploads/" . rcb_h(rawurlencode($mediaByCategory['hero'])) . "');background-size:cover;background-position:center"
    : "background-image:linear-gradient(125deg,rgba(4,29,58,.72),rgba(13,71,119,.48)),url('/assets/images/startseite.jpg');background-size:cover;background-position:center";
rcb_header('home');
?>
<section class="rcb-hero"<?= $heroStyle !== '' ? ' style="' . rcb_h($heroStyle) . '"' : '' ?>><div class="rcb-hero-inner">
    <p class="rcb-pill">RC-Modellsport in Zwickau</p>
    <h1><?= rcb_h(rcb_setting('site_name', 'RC Bande Zwickau e.V.')) ?></h1>
    <p><?= rcb_h(rcb_setting('site_description', 'Herzlich willkommen bei der RC Bande Zwickau. Entdecke unsere Strecken, Veranstaltungen und das Vereinsleben.')) ?></p>
    <a class="rcb-btn" href="/strecken.php">Unsere Strecken entdecken</a>
</div></section>

<?php if (!empty($intro[0]['content'])): ?>
<section class="rcb-section"><div class="rcb-wrap">
    <h2><?= rcb_h($intro[0]['title']) ?></h2>
    <div class="rcb-content"><?= nl2br(rcb_h($intro[0]['content'])) ?></div>
</div></section>
<?php endif; ?>

<?php if ($news): ?>
<section class="rcb-section"><div class="rcb-wrap">
    <h2>Neuigkeiten</h2>
    <div class="rcb-grid">
        <?php foreach ($news as $item): ?>
        <article class="rcb-card"><?php if ($item['image_url'] !== ''): ?><img class="rcb-card-image" src="<?= rcb_h($item['image_url']) ?>" alt="<?= rcb_h($item['title']) ?>" loading="lazy"><?php endif; ?><div class="rcb-card-body">
            <span class="rcb-pill"><?= rcb_h(rcb_date(substr((string)$item['created_at'], 0, 10))) ?></span>
            <h3><?= rcb_h($item['title']) ?></h3>
            <?php if ($item['summary'] !== ''): ?><p><?= nl2br(rcb_h($item['summary'])) ?></p><?php endif; ?>
            <?php if ($item['content'] !== ''): ?><div class="rcb-content"><?= nl2br(rcb_h($item['content'])) ?></div><?php endif; ?>
        </div></article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php endif; ?>

<section class="rcb-section"><div class="rcb-wrap">
    <h2>Unsere Strecken</h2>
    <p class="rcb-muted">Alle veröffentlichten Streckeninformationen auf einen Blick.</p>
    <?php if ($tracks): ?><div class="rcb-grid">
        <?php foreach ($tracks as $track): ?>
        <?php $trackCategory = stripos((string)$track['name'], 'onroad') !== false ? 'onroad' : (stripos((string)$track['name'], 'offroad') !== false ? 'offroad' : ''); $trackImage = $trackCategory !== '' ? ($mediaByCategory[$trackCategory] ?? '') : ''; $trackImageUrl = $trackImage !== '' ? '/uploads/' . rawurlencode($trackImage) : ($trackCategory !== '' ? '/assets/images/' . $trackCategory . '.jpg' : ''); ?>
        <article class="rcb-card"><?php if ($trackImageUrl !== ''): ?><img class="rcb-card-image" src="<?= rcb_h($trackImageUrl) ?>" alt="<?= rcb_h($track['name']) ?>" loading="lazy"><?php endif; ?><div class="rcb-card-body">
            <span class="rcb-pill"><?= rcb_h($track['surface'] ?: 'RC-Strecke') ?></span>
            <h3><?= rcb_h($track['name']) ?></h3>
            <?php if ($track['description'] !== ''): ?><p><?= nl2br(rcb_h($track['description'])) ?></p><?php endif; ?>
            <div class="rcb-facts">
                <?php if ($track['length'] !== ''): ?><span>Länge: <?= rcb_h($track['length']) ?></span><?php endif; ?>
                <?php if ($track['classes'] !== ''): ?><span><?= rcb_h($track['classes']) ?></span><?php endif; ?>
                <?php if ((int)$track['guest_drivers'] === 1): ?><span>Gastfahrer willkommen</span><?php endif; ?>
            </div>
        </div></article>
        <?php endforeach; ?>
    </div><?php else: ?><div class="rcb-empty">Streckeninformationen werden hier angezeigt, sobald sie im Adminbereich veröffentlicht wurden.</div><?php endif; ?>
    <a class="rcb-btn" href="/strecken.php">Alle Strecken</a>
</div></section>

<section class="rcb-section" style="background:#eaf0f5"><div class="rcb-wrap">
    <h2>Kommende Termine</h2>
    <p class="rcb-muted">Rennen, Trainings und Vereinsveranstaltungen.</p>
    <?php if ($events): ?><div class="rcb-grid">
        <?php foreach ($events as $event): ?>
        <article class="rcb-card"><div class="rcb-card-body">
            <?php if (!empty($event['event_date'])): ?><span class="rcb-pill"><?= rcb_h(rcb_date($event['event_date'])) ?><?= !empty($event['start_time']) ? ' · ' . rcb_h(substr((string)$event['start_time'], 0, 5)) . ' Uhr' : '' ?></span><?php endif; ?>
            <h3><?= rcb_h($event['title']) ?></h3>
            <?php if ($event['location'] !== ''): ?><p><strong>Ort:</strong> <?= rcb_h($event['location']) ?></p><?php endif; ?>
            <?php if ($event['description'] !== ''): ?><p><?= nl2br(rcb_h($event['description'])) ?></p><?php endif; ?>
        </div></article>
        <?php endforeach; ?>
    </div><?php else: ?><div class="rcb-empty">Aktuell sind keine kommenden Termine veröffentlicht. Schau später wieder vorbei.</div><?php endif; ?>
    <a class="rcb-btn" href="/termine.php">Alle Termine ansehen</a>
</div></section>

<?php if ($sponsors): ?>
<section class="rcb-section"><div class="rcb-wrap">
    <h2>Unsere Unterstützer</h2><div class="rcb-grid">
    <?php foreach ($sponsors as $sponsor): ?>
        <article class="rcb-card"><div class="rcb-card-body">
            <h3><?= rcb_h($sponsor['name']) ?></h3>
            <?php if ($sponsor['description'] !== ''): ?><p><?= nl2br(rcb_h($sponsor['description'])) ?></p><?php endif; ?>
            <?php if (filter_var($sponsor['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($sponsor['url'], PHP_URL_SCHEME)), ['http','https'], true)): ?><a class="rcb-btn" href="<?= rcb_h($sponsor['url']) ?>" target="_blank" rel="noopener noreferrer">Webseite besuchen</a><?php endif; ?>
        </div></article>
    <?php endforeach; ?>
    </div>
</div></section>
<?php endif; ?>
<?php rcb_footer(); ?>
