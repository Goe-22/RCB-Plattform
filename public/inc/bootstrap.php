<?php
declare(strict_types=1);

/**
 * Gemeinsame, schreibgeschützte Datenzugriffe für die öffentliche Webseite.
 * Die SQLite-Datei wird ausschließlich vom Adminbereich angelegt.
 */
$rcbRoot = dirname(__DIR__, 2);
$rcbDbFile = $rcbRoot . '/storage/database.sqlite';
$rcbDb = null;

if (is_file($rcbDbFile) && is_readable($rcbDbFile)) {
    try {
        $rcbDb = new PDO('sqlite:' . $rcbDbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $rcbDb->exec('PRAGMA query_only = ON');
        $rcbDb->exec('PRAGMA busy_timeout = 3000');
    } catch (Throwable $e) {
        error_log('RCB public database error: ' . $e->getMessage());
        $rcbDb = null;
    }
}

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
    if (rcb_setting('site_enabled', '1') === '0') {
        http_response_code(503);
        header('Retry-After: 3600');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wartungsarbeiten · ' . rcb_h($siteName) . '</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f3f6f9;color:#1d2733;display:grid;min-height:100vh;place-items:center;margin:0;padding:20px}.box{max-width:620px;background:white;border-radius:14px;padding:clamp(24px,6vw,48px);box-shadow:0 8px 35px #062b5518}h1{color:#062b55}</style></head><body><main class="box"><h1>Wir sind gleich zurück.</h1><p>Die Webseite wird gerade gewartet. Bitte versuche es später erneut.</p><p><strong>' . rcb_h($siteName) . '</strong></p></main></body></html>';
        exit;
    }
    $logoRows = rcb_rows("SELECT filename FROM media WHERE published = 1 AND category = 'logo' ORDER BY id DESC LIMIT 1");
    $logoMarkup = !empty($logoRows[0]['filename'])
        ? '<img src="/uploads/' . rcb_h(rawurlencode((string)$logoRows[0]['filename'])) . '" alt="Vereinslogo" style="width:48px;height:48px;object-fit:contain">'
        : 'RCB';
    $links = [
        'home' => ['Startseite', '/'],
        'club' => ['Verein', '/verein.php'],
        'tracks' => ['Strecken', '/strecken.php'],
        'events' => ['Termine', '/termine.php'],
        'gallery' => ['Galerie', '/galerie.php'],
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
    .rcb-footer{padding:26px 16px;background:#041d3a;color:#dce5ee;text-align:center}
    .rcb-footer a{color:#fff}.rcb-nav{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}
    @media(max-width:650px){.rcb-nav{align-items:flex-start}.rcb-nav nav ul{gap:8px;flex-wrap:wrap;justify-content:flex-end}.rcb-nav nav a{padding:6px 8px}}
    </style></head><body>';
    echo '<header class="site-header"><div class="header-inner rcb-nav">';
    echo '<a class="logo-area" href="/" aria-label="Zur Startseite"><span class="brand-mark">' . $logoMarkup . '</span><span class="club-name">' . rcb_h($siteName) . '<small>RC-Modellsport in Zwickau</small></span></a>';
    echo '<nav aria-label="Hauptnavigation"><ul>';
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
    echo '<p><a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></p>';
    echo '</footer></body></html>';
}
