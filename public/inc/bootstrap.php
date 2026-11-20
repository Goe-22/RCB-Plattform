<?php
declare(strict_types=1);

/**
 * Gemeinsame, schreibgeschützte Datenzugriffe für die öffentliche Webseite.
 * Die Tabellen werden ausschließlich vom Adminbereich angelegt.
 */
$rcbRoot = dirname(__DIR__, 2);
$rcbDb = null;

// Gemeinsame Datenbankschicht: MySQL über config/database.php, sonst SQLite in storage/.
require_once $rcbRoot . '/app/Database/Database.php';
if (is_file(Database::configFile()) || is_readable(Database::sqlitePath())) {
    try {
        $rcbDb = Database::connect();
        if (!Database::isMysql()) {
            $rcbDb->exec('PRAGMA query_only = ON');
        }
    } catch (Throwable $e) {
        error_log('RCB public database error: ' . $e->getMessage());
        $rcbDb = null;
    }
}

require_once __DIR__ . '/content.php';

function rcb_h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rcb_rows(string $sql, array $params = []): array
{
    global $rcbDb;
    if (!$rcbDb instanceof PDO) {
        return [];
    }
    try {
        $stmt = $rcbDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        // Die öffentliche Seite bleibt nutzbar, falls eine optionale Tabelle fehlt.
        error_log('RCB public query error: ' . $e->getMessage());
        return [];
    }
}

function rcb_setting(string $key, string $fallback = ''): string
{
    $rows = rcb_rows('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1', [$key]);
    return isset($rows[0]['setting_value']) ? (string)$rows[0]['setting_value'] : $fallback;
}

/** Veröffentlichte Inhaltsseite (Titel/Text) zu einer Seitenkennung, z. B. 'strecken'. */
function rcb_page_copy(string $slug): ?array
{
    $rows = rcb_rows('SELECT title, content FROM pages WHERE slug = ? AND published = 1 LIMIT 1', [$slug]);
    return $rows[0] ?? null;
}

/** Heutiges Datum in deutscher Zeit (Y-m-d) für Terminabfragen in MySQL und SQLite. */
function rcb_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
}

function rcb_date(?string $date, bool $withYear = true): string
{
    if (!$date || $date === '0000-00-00') {
        return '';
    }
    try {
        $format = $withYear ? 'd.m.Y' : 'd.m.';
        return (new DateTimeImmutable($date))->format($format);
    } catch (Throwable $e) {
        return '';
    }
}

function rcb_header(string $active = 'home'): void
{
    $siteName = rcb_setting('site_name', 'RC Bande Zwickau e.V.');
    $editor = rcb_editor();
    if (rcb_setting('site_enabled', '1') === '0' && !$editor) {
        http_response_code(503);
        header('Retry-After: 3600');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wartungsarbeiten · ' . rcb_h($siteName) . '</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f3f6f9;color:#1d2733;display:grid;min-height:100vh;place-items:center;margin:0;padding:20px}.box{max-width:620px;background:white;border-radius:14px;padding:clamp(24px,6vw,48px);box-shadow:0 8px 35px #062b5518}h1{color:#062b55}</style></head><body><main class="box"><h1>Wir sind gleich zurück.</h1><p>Die Webseite wird gerade gewartet. Bitte versuche es später erneut.</p><p><strong>' . rcb_h($siteName) . '</strong></p></main></body></html>';
        exit;
    }
    $logoUrl = rcb_img('global.logo', rcb_media_url('logo', '/assets/images/rcb-logo.png'));
    $logoMarkup = '<img src="' . rcb_h($logoUrl) . '" alt="' . rcb_h($siteName) . '"' . rcb_img_attr('global.logo') . ' style="width:min(250px,34vw);height:96px;object-fit:contain;object-position:left center">';
    $links = [
        'home' => ['Startseite', '/'],
        'club' => ['Verein', '/verein.php'],
        'tracks' => ['Strecken', '/strecken.php'],
        'events' => ['Termine', '/termine.php'],
        'news' => ['Neuigkeiten', '/neuigkeiten.php'],
        'rules' => ['Reglement', '/reglement.php'],
        'results' => ['Ergebnisse', '/ergebnisse.php'],
        'live' => ['Live-Timing', '/live.php'],
        'join' => ['Mitglied werden', '/mitglied-werden.php'],
        'contact' => ['Kontakt', '/kontakt.php'],
    ];
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#062b55">';
    echo '<title>' . rcb_h($siteName) . '</title>';
    echo '<meta name="description" content="' . rcb_h(rcb_setting('site_description', 'RC-Modellsport in Zwickau')) . '">';
    echo '<link rel="stylesheet" href="/assets/css/style.css">';
    echo '<script src="/assets/js/site.js" defer></script>';
    if ($editor) {
        echo '<meta name="rcb-csrf" content="' . rcb_h($editor['csrf']) . '">';
        echo '<link rel="stylesheet" href="/assets/css/editor.css">';
        echo '<script src="/assets/js/editor.js" defer></script>';
    }
    echo '<style>
    main{padding:0}.rcb-wrap{width:min(100% - 32px,1200px);margin:0 auto}
    .rcb-hero{padding:clamp(64px,10vw,130px) 20px;background:linear-gradient(125deg,#041d3a,#0d4777);color:#fff;text-align:center}
    .rcb-hero-inner{max-width:850px;margin:auto}.rcb-hero h1{font-size:clamp(2.1rem,5vw,4rem);line-height:1.12;margin:0 0 20px}
    .rcb-hero p{font-size:clamp(1rem,2vw,1.25rem);line-height:1.7}
    .rcb-section{padding:54px 0}.rcb-section h2{color:#062b55;margin:0 0 12px;font-size:clamp(1.6rem,3vw,2.2rem)}
    .rcb-muted{color:#657383}.rcb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(245px,1fr));gap:22px;margin-top:26px}
    .rcb-card{background:#fff;border:1px solid #dce3ea;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(4,29,58,.06)}
    .rcb-card-body{padding:22px}.rcb-card h3{margin:0 0 8px;color:#062b55}.rcb-card p{margin:8px 0}
    .rcb-pill{display:inline-block;border-radius:99px;padding:4px 10px;background:#e8eef5;color:#062b55;font-size:.82rem;font-weight:bold}
    .rcb-btn{display:inline-block;margin-top:14px;padding:11px 18px;border-radius:7px;background:#c9272c;color:white!important;text-decoration:none;font-weight:bold}
    .rcb-btn:hover{background:#a81d22}.rcb-empty{padding:22px;background:#fff;border:1px dashed #c7d2de;border-radius:10px}
    .rcb-facts{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.rcb-facts span{padding:6px 10px;background:#f1f5f8;border-radius:6px;font-size:.9rem}
    .rcb-content{white-space:normal;overflow-wrap:anywhere;line-height:1.8}
    .rcb-content h2{margin:26px 0 10px}.rcb-content h2:first-child,.rcb-content h3:first-child{margin-top:0}.rcb-content h3{color:#062b55;margin:20px 0 8px}.rcb-content ul,.rcb-content ol{padding-left:1.3em;margin:6px 0 14px}.rcb-content li{margin:3px 0}.rcb-content p{margin:8px 0}.rcb-content img{max-width:100%;height:auto;border-radius:8px}.rcb-content a{color:#0d4777}.rcb-content table{border-collapse:collapse;width:100%}.rcb-content td,.rcb-content th{border:1px solid #dce3ea;padding:6px 8px}
    .rcb-toc{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0 26px}.rcb-toc a{padding:7px 12px;border-radius:99px;background:#e8eef5;color:#062b55;font-weight:bold;text-decoration:none;font-size:.92rem}.rcb-toc a:hover{background:#062b55;color:#fff}
    .rcb-rule{margin-bottom:20px;scroll-margin-top:110px}.rcb-rule h2{color:#062b55}
    .rcb-footer{padding:26px 16px;background:#041d3a;color:#dce5ee;text-align:center}
    .rcb-footer a{color:#fff}.rcb-nav{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}.rcb-nav nav ul{flex-wrap:wrap;justify-content:flex-end}
    .logo-area{display:flex;align-items:center;gap:0;min-width:0;flex:0 1 auto}.logo-area .brand-mark{display:flex;align-items:center;justify-content:flex-start;width:auto;height:auto;min-width:0;margin:0;padding:0;border:0;border-radius:0;background:transparent}.logo-area .club-name{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}.logo-area .brand-mark img{display:block;max-width:min(250px,34vw);max-height:96px}
    .rcb-card-image{display:block;width:100%;height:220px;object-fit:cover}
    .rcb-menu-button{display:none;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:transparent;color:#fff;font-size:25px;line-height:1;padding:8px 11px;cursor:pointer}
    @media(max-width:900px){.rcb-nav{align-items:center}.rcb-menu-button{display:block;margin-left:auto}.rcb-nav nav{display:none;flex-basis:100%;width:100%}.rcb-nav nav.open{display:block}.rcb-nav nav ul{display:flex;flex-direction:column;align-items:stretch;gap:2px}.rcb-nav nav a{padding:10px 12px}.rcb-nav nav a[aria-current="page"]{background:rgba(255,255,255,.14)}}
    </style></head><body>';
    echo '<header class="site-header"><div class="header-inner rcb-nav">';
    echo '<a class="logo-area" href="/" aria-label="Zur Startseite"><span class="brand-mark">' . $logoMarkup . '</span><span class="club-name">' . rcb_h($siteName) . '<small>RC-Modellsport in Zwickau</small></span></a>';
    echo '<button class="rcb-menu-button" type="button" aria-controls="rcb-main-nav" aria-expanded="false" aria-label="Menü öffnen">☰</button>';
    echo '<nav id="rcb-main-nav" aria-label="Hauptnavigation"><ul>';
    foreach ($links as $key => [$label, $url]) {
        $aria = $active === $key ? ' aria-current="page"' : '';
        echo '<li><a href="' . rcb_h($url) . '"' . $aria . '>' . rcb_h($label) . '</a></li>';
    }
    echo '</ul></nav></div></header><main>';
}

function rcb_footer(): void
{
    $email = rcb_setting('contact_email');
    $phone = rcb_setting('contact_phone');
    echo '</main><footer class="rcb-footer"><p><strong>' . rcb_h(rcb_setting('site_name', 'RC Bande Zwickau e.V.')) . '</strong></p>';
    if ($email !== '') {
        echo '<p><a href="mailto:' . rcb_h($email) . '">' . rcb_h($email) . '</a></p>';
    }
    if ($phone !== '') {
        echo '<p>Telefon: ' . rcb_h($phone) . '</p>';
    }
    echo '<p><a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a> · <a href="/haftungsausschluss.php">Haftungsausschluss</a> · <a href="/downloads.php">Downloads</a></p>';
    echo '</footer></body></html>';
}
