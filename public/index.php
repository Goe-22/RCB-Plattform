<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

// Anzahl je Bereich (Admin → Einstellungen → Startseite), 0 blendet den Bereich aus.
$newsCount = max(0, min(20, (int)rcb_setting('home_news_count', '6')));
$eventsCount = max(0, min(20, (int)rcb_setting('home_events_count', '3')));
// Neuigkeiten: eigene Meldungen (Admin → Neuigkeiten) und automatische Hinweise auf neue Inhalte, neueste zuerst.
$feed = [];
foreach (rcb_rows("SELECT id, title, summary, content, image_url, created_at FROM news WHERE published = 1 ORDER BY created_at DESC, id DESC LIMIT " . max(1, $newsCount)) as $item) {
    $feed[] = ['type' => 'news', 'date' => (string)$item['created_at'], 'item' => $item];
}
$autoDays = (int)rcb_setting('auto_updates_days', '30');
$autoCutoff = $autoDays > 0 ? date('Y-m-d H:i:s', time() - $autoDays * 86400) : '1970-01-02 00:00:00';
foreach (rcb_rows("SELECT id, title, url, created_at FROM site_updates WHERE created_at >= ? ORDER BY created_at DESC, id DESC LIMIT " . max(1, $newsCount), [$autoCutoff]) as $item) {
    $feed[] = ['type' => 'update', 'date' => (string)$item['created_at'], 'item' => $item];
}
usort($feed, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
$feed = array_slice($feed, 0, $newsCount);
$events = rcb_rows("SELECT * FROM events WHERE published = 1 AND (event_date IS NULL OR CAST(event_date AS CHAR) = '' OR (CASE WHEN end_date IS NOT NULL AND CAST(end_date AS CHAR) <> '' THEN end_date ELSE event_date END) >= ?) ORDER BY CASE WHEN event_date IS NULL OR CAST(event_date AS CHAR) = '' THEN 1 ELSE 0 END, event_date ASC, start_time ASC LIMIT " . max(1, $eventsCount), [rcb_today()]);
$sponsors = rcb_rows("SELECT * FROM sponsors WHERE published = 1 ORDER BY name ASC");
$heroImage = rcb_img('startseite.hero.bild', rcb_media_url('hero', '/assets/images/startseite.jpg'));
rcb_header('home');
?>
<section class="rcb-hero" style="<?= rcb_hero_style($heroImage) ?>"<?= rcb_img_attr('startseite.hero.bild') ?>><div class="rcb-hero-inner">
    <p class="rcb-pill"><?= rcb_text('startseite.hero.zeile', 'RC-Modellsport in Zwickau') ?></p>
    <h1><?= rcb_text('startseite.hero.titel', rcb_setting('site_name', 'RC Bande Zwickau e.V.')) ?></h1>
    <p><?= rcb_text('startseite.hero.text', 'Herzlich willkommen bei der RC Bande Zwickau. Entdecke unsere Strecken, Veranstaltungen und das Vereinsleben.') ?></p>
    <a class="rcb-btn" href="#strecken"><?= rcb_text('startseite.hero.button', 'Unsere Strecken entdecken') ?></a>
</div></section>

<?php if (rcb_filled('startseite.intro', '-')): ?>
<section class="rcb-section"><div class="rcb-wrap">
    <?= rcb_rich('startseite.intro', '<h2>Liebe Besucher,</h2>
<p><strong>willkommen auf der Webseite der RC Bande Zwickau e.V.</strong></p>
<p>Wir sind ein gemeinnütziger Verein zur Förderung des Modellbaus. Unter anderem haben wir das Ziel, Kindern und Jugendlichen eine sinnvolle Freizeitbeschäftigung zu bieten. Dank unserer zahlreichen Mitglieder haben wir ein weites Spektrum an Wissen im Bereich Modellbau, das an euch weitergegeben werden kann.</p>
<p>Zu unserem Verein gehören zwei Bereiche: <strong>Offroad</strong> und <strong>Onroad</strong> mit den jeweiligen Strecken.</p>
<p>Die Anmeldung zum Fahren erfolgt über <a href="/kontakt.php">Kontakt</a> oder <a href="https://de-de.facebook.com/RCBandeZwickau/" target="_blank" rel="noopener noreferrer">Facebook</a>.</p>
<p><strong>Wir freuen uns auf euren Besuch.</strong></p>') ?>
</div></section>
<?php endif; ?>

<?php if ($newsCount > 0): ?>
<section class="rcb-section" id="neuigkeiten" style="background:#eaf0f5"><div class="rcb-wrap">
    <h2><?= rcb_text('startseite.news.titel', 'Neuigkeiten – hier hat sich was getan') ?></h2>
    <?php if ($feed): ?><div class="rcb-news-list">
        <?php foreach ($feed as $entry): $item = $entry['item']; ?>
        <?php if ($entry['type'] === 'news'): ?>
        <article class="rcb-news"><?php if ((string)($item['image_url'] ?? '') !== ''): ?><img src="<?= rcb_h($item['image_url']) ?>" alt="" loading="lazy"><?php endif; ?><div>
            <span class="rcb-pill"><?= rcb_h(rcb_date(substr($entry['date'], 0, 10))) ?></span>
            <h3><?= rcb_h($item['title']) ?></h3>
            <?php if ((string)$item['summary'] !== ''): ?><p><?= nl2br(rcb_h($item['summary'])) ?></p><?php endif; ?>
            <?php if ((string)$item['content'] !== ''): ?><?php if ((string)$item['summary'] !== ''): ?><details><summary>Weiterlesen</summary><p><?= nl2br(rcb_h($item['content'])) ?></p></details><?php else: ?><p><?= nl2br(rcb_h($item['content'])) ?></p><?php endif; ?><?php endif; ?>
        </div></article>
        <?php else: ?>
        <article class="rcb-news rcb-news-update"><div>
            <span class="rcb-pill"><?= rcb_h(rcb_date(substr($entry['date'], 0, 10))) ?> · Neu auf der Seite</span>
            <h3><?= rcb_h($item['title']) ?></h3>
        </div><?php if ((string)$item['url'] !== ''): ?><a class="rcb-btn" href="<?= rcb_h($item['url']) ?>">Ansehen</a><?php endif; ?></article>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php else: ?><div class="rcb-empty"><?= rcb_text('startseite.news.leer', 'Hier erscheinen Neuigkeiten und Hinweise, sobald sich auf der Seite etwas tut.') ?></div><?php endif; ?>
</div></section>
<?php endif; ?>

<section class="rcb-section" id="strecken"><div class="rcb-wrap">
    <h2><?= rcb_text('startseite.strecken.titel', 'Unsere Strecken') ?></h2>
    <p class="rcb-muted"><?= rcb_text('startseite.strecken.text', 'Zwei Strecken, ein Gelände – mitten in Zwickau.') ?></p>
    <div class="rcb-track-tiles">
    <?php foreach (['onroad' => ['Onroad', 'Asphalt · 210 m · Tourenwagen 1:10'], 'offroad' => ['Offroad', 'Kunstrasen · 270 m · Buggys bis 1:8']] as $kind => [$label, $line]): ?>
    <?php if (!rcb_page_published($kind) && !rcb_admin_viewer()) { continue; } ?>
        <a class="rcb-track-tile" href="/<?= $kind ?>.php"<?= rcb_img_attr('strecke.' . $kind . '.bild') ?> style="background-image:linear-gradient(180deg,rgba(4,29,58,0) 35%,rgba(4,29,58,.88)),url('<?= rcb_h(rcb_track_image($kind)) ?>')">
            <span class="rcb-track-tile-text"><strong><?= rcb_text('startseite.' . $kind . '.titel', $label) ?></strong><span><?= rcb_text('startseite.' . $kind . '.text', $line) ?></span></span>
            <span class="rcb-btn"><?= rcb_text('startseite.' . $kind . '.button', 'Strecke & Daten ansehen') ?></span>
        </a>
    <?php endforeach; ?>
    </div>
</div></section>

<?php if ($eventsCount > 0): ?>
<section class="rcb-section" style="background:#eaf0f5"><div class="rcb-wrap">
    <h2><?= rcb_text('startseite.termine.titel', 'Kommende Termine') ?></h2>
    <p class="rcb-muted"><?= rcb_text('startseite.termine.text', 'Rennen, Trainings und Vereinsveranstaltungen.') ?></p>
    <?php if ($events): ?><div class="rcb-events"><?php foreach ($events as $event) { echo rcb_event_card($event, 'compact'); } ?></div>
    <?php else: ?><div class="rcb-empty"><?= rcb_text('startseite.termine.leer', 'Aktuell sind keine kommenden Termine veröffentlicht. Schau später wieder vorbei.') ?></div><?php endif; ?>
    <a class="rcb-btn" href="/termine.php"><?= rcb_text('startseite.termine.button', 'Alle Termine ansehen') ?></a>
</div></section>
<?php endif; ?>

<?php if ($sponsors): ?>
<section class="rcb-section"><div class="rcb-wrap">
    <h2><?= rcb_text('startseite.sponsoren.titel', 'Unsere Unterstützer') ?></h2><div class="rcb-grid">
    <?php foreach ($sponsors as $sponsor): ?>
        <article class="rcb-card"><div class="rcb-card-body">
            <h3><?= rcb_h($sponsor['name']) ?></h3>
            <?php if ((string)$sponsor['description'] !== ''): ?><p><?= nl2br(rcb_h($sponsor['description'])) ?></p><?php endif; ?>
            <?php if (filter_var($sponsor['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url((string)$sponsor['url'], PHP_URL_SCHEME)), ['http','https'], true)): ?><a class="rcb-btn" href="<?= rcb_h($sponsor['url']) ?>" target="_blank" rel="noopener noreferrer">Webseite besuchen</a><?php endif; ?>
        </div></article>
    <?php endforeach; ?>
    </div>
</div></section>
<?php endif; ?>
<?php rcb_footer(); ?>
