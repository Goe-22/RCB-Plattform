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

/** Führt einen Schreibvorgang aus (z. B. Formular-Eingang); die öffentliche Seite ist sonst nur lesend verbunden. */
function rcb_db_write(callable $write): bool
{
    global $rcbDb;
    if (!$rcbDb instanceof PDO) {
        return false;
    }
    $sqlite = !Database::isMysql();
    try {
        if ($sqlite) { $rcbDb->exec('PRAGMA query_only = OFF'); }
        $write($rcbDb);
        return true;
    } catch (Throwable $e) {
        error_log('RCB public write error: ' . $e->getMessage());
        return false;
    } finally {
        if ($sqlite) { $rcbDb->exec('PRAGMA query_only = ON'); }
    }
}

require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/tracks.php';
require_once __DIR__ . '/design.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/events.php';

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

/** Kennungen der verborgenen (nicht veröffentlichten) Seiten. */
function rcb_hidden_slugs(): array
{
    static $hidden = null;
    if ($hidden === null) {
        $hidden = rcb_hidden_pages(rcb_setting('pages_hidden', '[]'), rcb_setting('live_enabled', '0'));
    }
    return $hidden;
}

function rcb_page_published(string $slug): bool
{
    return !in_array($slug, rcb_hidden_slugs(), true);
}

/** Eigene Seiten (Admin → Seiten → Eigene Seiten): Adresse => Zeile (id, slug, title, published). */
function rcb_custom_pages(): array
{
    static $pages = null;
    if ($pages === null) {
        $pages = [];
        foreach (rcb_rows('SELECT id, slug, title, published FROM pages ORDER BY title') as $row) {
            $pages[(string)$row['slug']] = $row;
        }
    }
    return $pages;
}

/** Adressen eigener Seiten, die im Menü stehen. */
function rcb_custom_menu(): array
{
    $list = json_decode(rcb_setting('nav_custom', '[]'), true);
    return array_values(array_filter(is_array($list) ? $list : [], static fn($slug) => is_string($slug) && isset(rcb_custom_pages()[$slug])));
}

/** Ist die Live-Timing-Seite veröffentlicht? */
function rcb_live_published(): bool
{
    return rcb_page_published('live');
}

/** Gibt eine einfache 404-Seite aus und beendet die Ausgabe. */
function rcb_not_found(): never
{
    http_response_code(404);
    rcb_header();
    echo '<section class="rcb-section"><div class="rcb-wrap"><h1>Seite nicht gefunden</h1><p>Diese Seite ist nicht (mehr) verfügbar.</p><a class="rcb-btn" href="/">Zur Startseite</a></div></section>';
    rcb_footer();
    exit;
}

/** Veröffentlichte Inhaltsseite (Titel/Text) zu einer Seitenkennung, z. B. 'verein'. */
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

/** Kennung der aufgerufenen Seite für zugeordnete Dateien, z. B. 'mitglied', 'reglement:tt02', 'seite:jugend'. */
function rcb_page_key_for_request(string $active): string
{
    if (str_starts_with($active, 'seite:')) {
        return $active;
    }
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $slug = rcb_page_slug_for_path($script === '/index.php' ? '/' : $script);
    $klasse = (string)($_GET['klasse'] ?? '');
    if ($slug === 'reglement' && preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $klasse)) {
        return 'reglement:' . $klasse;
    }
    return $slug;
}

/** Veröffentlichte Dateien einer Seite. */
function rcb_page_files(string $key): array
{
    return $key === '' ? [] : rcb_rows('SELECT id, title, description, path, size FROM files WHERE published = 1 AND page_key = ? ORDER BY sort_order, title', [$key]);
}

/** Download-Kasten einer Seite (leer, wenn keine Dateien zugeordnet sind). */
function rcb_page_downloads(string $key, string $heading = 'Downloads'): string
{
    $GLOBALS['rcb_downloads_done'][$key] = true;
    $files = rcb_page_files($key);
    if (!$files) {
        return '';
    }
    $html = '<div class="rcb-downloads"><h2>' . rcb_h($heading) . '</h2><div class="rcb-download-list">';
    foreach ($files as $file) {
        $ext = strtolower(pathinfo((string)$file['path'], PATHINFO_EXTENSION));
        $type = ['pdf' => 'PDF', 'doc' => 'Word', 'docx' => 'Word', 'odt' => 'Text', 'xls' => 'Excel', 'xlsx' => 'Excel', 'ods' => 'Tabelle'][$ext] ?? strtoupper($ext);
        $size = (int)$file['size'] >= 1048576 ? number_format((int)$file['size'] / 1048576, 1, ',', '.') . ' MB' : max(1, (int)round((int)$file['size'] / 1024)) . ' KB';
        $download = $ext === 'pdf' ? ' target="_blank" rel="noopener"' : ' download="' . rcb_h(preg_replace('/[^\p{L}\p{N} ._-]+/u', '', (string)$file['title']) . '.' . $ext) . '"';
        $html .= '<a class="rcb-download" href="' . rcb_h($file['path']) . '"' . $download . '>'
            . '<span class="rcb-download-icon" aria-hidden="true">' . ($ext === 'pdf' ? 'PDF' : '📄') . '</span>'
            . '<span class="rcb-download-text"><strong>' . rcb_h($file['title']) . '</strong>'
            . ((string)$file['description'] !== '' ? '<span>' . rcb_h($file['description']) . '</span>' : '')
            . '<small>' . rcb_h($type . ' · ' . $size) . '</small></span>'
            . '<span class="rcb-download-arrow" aria-hidden="true">⬇</span></a>';
    }
    return $html . '</div></div>';
}

/** Aktuelle Gestaltung (Admin → Design). */
function rcb_design(): array
{
    static $design = null;
    return $design ??= rcb_design_from_json(rcb_setting('design', ''));
}

/** Abschnitt anzeigen? Ausgeblendete Abschnitte sieht nur, wer die Seite gerade bearbeitet. */
function rcb_section_show(string $id): bool
{
    return !in_array($id, rcb_design()['hidden'], true) || rcb_editor() !== null;
}

/** Zusätzliche CSS-Klasse für ausgeblendete Abschnitte im Bearbeiten-Modus. */
function rcb_section_mark(string $id): string
{
    return in_array($id, rcb_design()['hidden'], true) ? ' rcb-section-off' : '';
}

function rcb_header(string $active = 'home'): void
{
    $GLOBALS['rcb_page_key'] = rcb_page_key_for_request($active);
    $siteName = rcb_setting('site_name', 'RC Bande Zwickau e.V.');
    $editor = rcb_admin_viewer();
    if (rcb_setting('site_enabled', '1') === '0' && !$editor) {
        http_response_code(503);
        header('Retry-After: 3600');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wartungsarbeiten · ' . rcb_h($siteName) . '</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f3f6f9;color:#1d2733;display:grid;min-height:100vh;place-items:center;margin:0;padding:20px}.box{max-width:620px;background:white;border-radius:14px;padding:clamp(24px,6vw,48px);box-shadow:0 8px 35px #062b5518}h1{color:#062b55}</style></head><body><main class="box"><h1>Wir sind gleich zurück.</h1><p>Die Webseite wird gerade gewartet. Bitte versuche es später erneut.</p><p><strong>' . rcb_h($siteName) . '</strong></p></main></body></html>';
        exit;
    }
    $logoUrl = rcb_img('global.logo', rcb_media_url('logo', '/assets/images/rcb-logo.png'));
    $logoMarkup = '<img src="' . rcb_h($logoUrl) . '" alt="' . rcb_h($siteName) . '"' . rcb_img_attr('global.logo') . ' class="rcb-logo-img">';
    // Verborgene Seiten sind für Besucher nicht erreichbar; angemeldete Admins sehen sie im Editor/in der Vorschau.
    $currentSlug = rcb_page_slug_for_path((string)parse_url((string)($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
    if ($currentSlug !== '' && !rcb_page_published($currentSlug) && !$editor && http_response_code() !== 404) {
        rcb_not_found();
    }
    $activeSlug = ['club' => 'verein', 'tracks' => $currentSlug, 'events' => 'termine', 'rules' => 'reglement', 'results' => 'ergebnisse', 'join' => 'mitglied', 'contact' => 'kontakt'][$active] ?? $active;
    // Menüeinträge: [Bezeichnung, Adresse, verborgen?, Unterpunkte]
    $pages = rcb_site_pages();
    $groups = rcb_nav_groups();
    $custom = rcb_custom_pages();
    $links = [];
    foreach (rcb_nav_order(rcb_setting('nav_order', '[]'), rcb_custom_menu()) as $slug) {
        if (isset($groups[$slug])) {
            $children = [];
            foreach ($groups[$slug][1] as $child) {
                $childHidden = !rcb_page_published($child);
                if (!$childHidden || $editor) {
                    $children[$child] = [$pages[$child][0], $pages[$child][1], $childHidden];
                }
            }
            if ($children) {
                $links[$slug] = [$groups[$slug][0], '', false, $children];
            }
            continue;
        }
        if (str_starts_with($slug, 'seite:')) {
            $row = $custom[substr($slug, 6)] ?? null;
            $isHidden = !$row || (int)$row['published'] !== 1;
            if ($row && (!$isHidden || $editor)) {
                $links[$slug] = [(string)$row['title'], '/seite.php?slug=' . rawurlencode((string)$row['slug']), $isHidden, []];
            }
            continue;
        }
        $isHidden = !rcb_page_published($slug);
        if ($isHidden && !$editor) {
            continue;
        }
        $links[$slug] = [$pages[$slug][0], $pages[$slug][1], $isHidden, []];
    }
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#062b55">';
    echo '<title>' . rcb_h($siteName) . '</title>';
    echo '<meta name="description" content="' . rcb_h(rcb_setting('site_description', 'RC-Modellsport in Zwickau')) . '">';
    echo '<link rel="stylesheet" href="/assets/css/style.css">';
    echo '<style id="rcb-design">' . rcb_design_css(rcb_design(), (string)$GLOBALS['rcb_page_key']) . '</style>';
    echo '<script src="/assets/js/site.js" defer></script>';
    if ($editor) {
        echo '<meta name="rcb-csrf" content="' . rcb_h($editor['csrf']) . '">';
        $editorPages = [];
        $allPages = rcb_site_pages();
        foreach (array_merge(rcb_nav_order(rcb_setting('nav_order', '[]')), array_keys(array_filter($allPages, static fn($p) => !$p[2]))) as $slug) {
            if (isset($allPages[$slug])) {
                $editorPages[] = [$allPages[$slug][1], $allPages[$slug][0] . (rcb_page_published($slug) ? '' : ' (verborgen)')];
            }
        }
        foreach (rcb_custom_pages() as $row) {
            $editorPages[] = ['/seite.php?slug=' . rawurlencode((string)$row['slug']), (string)$row['title'] . ((int)$row['published'] === 1 ? '' : ' (verborgen)')];
        }
        if ($editor['scope'] === 'reglement') {
            // Nur-Reglement-Admins: Auswahl auf das Reglement und seine Klassen beschränken.
            $editorPages = [['/reglement.php', 'Reglement – allgemeiner Teil']];
            foreach (rcb_rule_classes(rcb_setting('reglement_klassen', '')) as $class) {
                $editorPages[] = ['/reglement.php?klasse=' . rawurlencode((string)$class['key']), 'Reglement – ' . (string)($class['title'] ?? $class['key']) . (empty($class['published']) ? ' (verborgen)' : '')];
            }
        }
        echo '<script type="application/json" id="rcb-pages">' . json_encode($editorPages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
        echo '<link rel="stylesheet" href="/assets/css/editor.css">';
        echo '<script src="/assets/js/editor.js" data-mode="' . rcb_h($editor['mode']) . '" data-scope="' . rcb_h($editor['scope']) . '" defer></script>';
    }
    echo '<style>
    main{padding:0}.rcb-wrap{width:min(100% - 32px,1200px);margin:0 auto}
    .rcb-hero{padding:clamp(64px,10vw,130px) 20px;background:linear-gradient(125deg,var(--rcb-blue-dark),var(--rcb-blue-mid));color:#fff;text-align:center}
    .rcb-hero-inner{max-width:850px;margin:auto}.rcb-hero h1{font-size:clamp(2.1rem,5vw,4rem);line-height:1.12;margin:0 0 20px}
    .rcb-hero p{font-size:clamp(1rem,2vw,1.25rem);line-height:1.7}
    .rcb-section{padding:54px 0}.rcb-section h2{color:var(--rcb-blue);margin:0 0 12px;font-size:clamp(1.6rem,3vw,2.2rem)}
    .rcb-muted{color:#657383}.rcb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(245px,1fr));gap:22px;margin-top:26px}
    .rcb-card{background:#fff;border:1px solid #dce3ea;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(4,29,58,.06)}
    .rcb-card-body{padding:22px}.rcb-card h3{margin:0 0 8px;color:var(--rcb-blue)}.rcb-card p{margin:8px 0}
    .rcb-pill{display:inline-block;border-radius:99px;padding:4px 10px;background:var(--rcb-tint);color:var(--rcb-blue);font-size:.82rem;font-weight:bold}
    .rcb-btn{display:inline-block;margin-top:14px;padding:11px 18px;border-radius:7px;background:var(--rcb-red);color:white!important;text-decoration:none;font-weight:bold}
    .rcb-btn:hover{background:var(--rcb-red-dark)}.rcb-empty{padding:22px;background:#fff;border:1px dashed #c7d2de;border-radius:10px}
    .rcb-facts{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.rcb-facts span{padding:6px 10px;background:#f1f5f8;border-radius:6px;font-size:.9rem}
    .rcb-content{white-space:normal;overflow-wrap:anywhere;line-height:1.8}
    .rcb-content h2{margin:26px 0 10px}.rcb-content h2:first-child,.rcb-content h3:first-child{margin-top:0}.rcb-content h3{color:var(--rcb-blue);margin:20px 0 8px}.rcb-content ul,.rcb-content ol{padding-left:1.3em;margin:6px 0 14px}.rcb-content li{margin:3px 0}.rcb-content p{margin:8px 0}.rcb-content img{max-width:100%;height:auto;border-radius:8px}.rcb-content a{color:var(--rcb-blue-mid)}.rcb-content table{border-collapse:collapse;width:100%}.rcb-content td,.rcb-content th{border:1px solid #dce3ea;padding:6px 8px}
    .rcb-toc{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0 26px}.rcb-toc a{padding:7px 12px;border-radius:99px;background:var(--rcb-tint);color:var(--rcb-blue);font-weight:bold;text-decoration:none;font-size:.92rem}.rcb-toc a:hover{background:var(--rcb-blue);color:#fff}
    .rcb-rule{margin-bottom:20px;scroll-margin-top:110px}.rcb-rule h2{color:var(--rcb-blue)}
    .rcb-class-list{display:flex;flex-direction:column;gap:10px;margin-top:16px}.rcb-class{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;background:#fff;border:1px solid #dce3ea;border-radius:12px;color:#1d2733;text-decoration:none;box-shadow:0 4px 14px rgba(4,29,58,.05);transition:border-color .15s,transform .15s}.rcb-class:hover{border-color:var(--rcb-blue);transform:translateX(3px)}.rcb-class strong{display:block;color:var(--rcb-blue);font-size:1.12rem}.rcb-class small{display:block;color:#657383;margin-top:3px}.rcb-class>span:last-child{font-size:1.8rem;color:var(--rcb-red);line-height:1}
    .rcb-rule-nav{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px}.rcb-btn-light{background:var(--rcb-tint);color:var(--rcb-blue)!important}.rcb-btn-light:hover{background:#d5e0eb}.rcb-hero .rcb-pill{text-decoration:none}.rcb-has-sub{position:relative}.rcb-sub-toggle{display:block;padding:9px 10px;border:0;border-radius:5px;background:transparent;color:#fff;font-family:inherit;font-size:13px;font-weight:700;line-height:1.2;cursor:pointer;white-space:nowrap}.rcb-sub-toggle:hover,.rcb-sub-toggle[aria-expanded="true"]{background:rgba(255,255,255,.14)}.rcb-has-sub.is-active>.rcb-sub-toggle{background:rgba(255,255,255,.2);box-shadow:inset 0 -2px 0 #f2c94c}.rcb-sub-toggle:focus-visible{outline:3px solid #f2c94c;outline-offset:2px}
    .site-header nav ul.rcb-sub{display:none;justify-content:flex-start;position:absolute;top:calc(100% + 6px);left:0;z-index:1100;min-width:190px;flex-direction:column;align-items:stretch;gap:2px;padding:6px;border-radius:10px;background:var(--rcb-blue);box-shadow:0 10px 26px rgba(0,0,0,.28)}.site-header nav .rcb-has-sub.open>ul.rcb-sub{display:flex}.site-header nav ul.rcb-sub a{padding:10px 12px;font-size:14px}
    @media(max-width:900px){.rcb-sub-toggle{width:100%;text-align:left;padding:12px 14px;font-size:15px;opacity:.75;cursor:default;pointer-events:none}.rcb-sub-toggle span{display:none}.site-header nav ul.rcb-sub{display:flex;position:static;min-width:0;padding:0 0 0 14px;background:transparent;box-shadow:none}}
    .rcb-events{display:flex;flex-direction:column;gap:14px;margin-top:18px}.rcb-event{display:grid;grid-template-columns:118px minmax(0,1fr) auto;gap:20px;align-items:start;padding:18px 20px;background:#fff;border:1px solid #dce3ea;border-radius:12px;box-shadow:0 4px 14px rgba(4,29,58,.05);scroll-margin-top:110px}.rcb-event:target{border-color:var(--rcb-red);box-shadow:0 0 0 3px rgba(201,39,44,.15)}.rcb-event-date{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:86px;padding:10px 6px;border-radius:10px;background:var(--rcb-blue);color:#fff;text-align:center}.rcb-event-day{font-size:1.35rem;font-weight:800;line-height:1.15}.rcb-event-month{font-size:.82rem;opacity:.85;margin-top:4px}.rcb-event-body h3{margin:0 0 4px;color:var(--rcb-blue);font-size:1.2rem}.rcb-event-meta{margin:0 0 8px;color:#657383}.rcb-event-classes{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0;padding:0;list-style:none}.rcb-event-classes li{flex-wrap:wrap;display:flex;align-items:center;gap:6px;padding:5px 10px;border-radius:8px;background:#f1f5f8;font-size:.92rem}.rcb-event-class{font-weight:bold;color:var(--rcb-blue)}.rcb-event-classes a{color:var(--rcb-blue-mid);font-size:.85rem}.rcb-tag{padding:2px 7px;border-radius:99px;background:#fff1d6;color:#8a5a00;font-size:.75rem;font-weight:bold}.rcb-event-actions{display:flex;flex-direction:column;gap:8px;min-width:190px}.rcb-event-actions .rcb-btn{margin:0;text-align:center}.rcb-event-announcement{margin-top:10px}.rcb-event-announcement summary{white-space:nowrap;display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:9px 16px;border:2px solid var(--rcb-blue);border-radius:8px;background:#fff;color:var(--rcb-blue);font-weight:800;list-style:none}.rcb-event-announcement summary::-webkit-details-marker{display:none}.rcb-event-announcement summary:hover{background:var(--rcb-blue);color:#fff}.rcb-event-announcement[open] summary{background:var(--rcb-blue);color:#fff;margin-bottom:10px}.rcb-event-announcement .rcb-content{padding:14px 16px;border-left:4px solid var(--rcb-blue);background:#f6f8fb;border-radius:0 8px 8px 0}.rcb-btn-nennung,.rcb-btn-doc,.rcb-btn-result{display:flex!important;align-items:center;gap:12px;padding:12px 18px!important;text-align:left!important;font-size:1.05rem;line-height:1.2;transition:transform .12s,box-shadow .12s}.rcb-btn-nennung small,.rcb-btn-doc small,.rcb-btn-result small{display:block;font-size:.78rem;font-weight:600;opacity:.85;margin-top:2px}.rcb-btn-icon{font-size:1.35rem;line-height:1}.rcb-btn-nennung{background:var(--rcb-red);box-shadow:0 6px 16px rgba(201,39,44,.35)}.rcb-btn-nennung:hover,.rcb-btn-doc:hover,.rcb-btn-result:hover{transform:translateY(-1px)}.rcb-btn-nennung:hover{background:var(--rcb-red-dark);box-shadow:0 8px 20px rgba(201,39,44,.45)}.rcb-btn-doc{background:var(--rcb-blue);color:#fff!important;box-shadow:0 6px 16px rgba(6,43,85,.25)}.rcb-btn-doc:hover{background:var(--rcb-blue-mid)}.rcb-event-classes a.rcb-class-nennung{white-space:nowrap;padding:3px 9px;border-radius:6px;background:var(--rcb-red);color:#fff;font-weight:bold;text-decoration:none}.rcb-event-classes a.rcb-class-nennung:hover{background:var(--rcb-red-dark)}.rcb-event-past{opacity:.92}.rcb-event-past .rcb-event-date{background:#5b6b7c}@media(max-width:760px){.rcb-event{grid-template-columns:84px minmax(0,1fr);gap:14px}.rcb-event-actions{grid-column:1/-1;flex-direction:row;flex-wrap:wrap;min-width:0}.rcb-event-day{font-size:1.05rem}}
    .rcb-track-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:22px 0}.rcb-track-tile{position:relative;display:flex;align-items:flex-end;justify-content:space-between;gap:14px;min-height:300px;padding:22px;border-radius:14px;background-size:cover;background-position:center;color:#fff;text-decoration:none;box-shadow:0 8px 24px rgba(4,29,58,.18);transition:transform .15s}.rcb-track-tile:hover{transform:translateY(-3px)}.rcb-track-tile-text strong{display:block;font-size:1.7rem}.rcb-track-tile-text span{display:block;opacity:.92}.rcb-track-tile .rcb-btn{margin:0;flex:none}
    .rcb-detail{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:28px;align-items:start}.rcb-detail-main>div+div{margin-top:22px}.rcb-steckbrief dl{display:grid;grid-template-columns:auto 1fr;gap:8px 14px;margin:12px 0 18px}.rcb-steckbrief dt{font-weight:bold;color:var(--rcb-blue)}.rcb-steckbrief dd{margin:0}@media(max-width:820px){.rcb-detail{grid-template-columns:1fr}}
    .rcb-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}.rcb-gallery-item{position:relative;min-height:160px;border-radius:10px;overflow:hidden;background:#dce3ea center/cover}.rcb-gallery-img{display:block;width:100%;height:200px;object-fit:cover}.rcb-track-status{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 14px;padding:10px 14px;border-radius:10px;font-weight:800}.rcb-track-status small{flex-basis:100%;font-weight:600;opacity:.85}.rcb-track-dot{width:11px;height:11px;border-radius:50%;background:currentColor}.rcb-track-status-open{background:#e3f6e8;color:#17703a}.rcb-track-status-closed{background:#fde8e8;color:var(--rcb-red-dark)}.rcb-track-status-maintenance{background:#fff1d6;color:#8a5a00}.rcb-gallery-video{background:#000;cursor:pointer}.rcb-gallery-vid{display:block;width:100%;height:200px;object-fit:cover}.rcb-play{position:absolute;left:50%;top:50%;width:58px;height:58px;margin:-29px 0 0 -29px;border-radius:50%;background:rgba(201,39,44,.9);box-shadow:0 4px 18px rgba(0,0,0,.45);pointer-events:none}.rcb-play::after{content:"";position:absolute;left:23px;top:17px;border-style:solid;border-width:12px 0 12px 19px;border-color:transparent transparent transparent #fff}.rcb-lightbox video{max-width:100%;max-height:100%;border-radius:8px;background:#000;cursor:auto}body.rcb-editing .rcb-play{display:none}.rcb-pay{display:flex;flex-direction:column;gap:8px;margin:4px 0 16px}.rcb-btn.rcb-btn-pay{margin-top:0;background:#ffc439;color:#111!important;border-color:#ffc439;justify-content:center;text-align:center}.rcb-btn.rcb-btn-pay:hover{background:#f2b622}.rcb-pay p{margin:0;font-size:.85rem}.rcb-pay-info{color:#657383}.rcb-results-list{display:grid;gap:12px}.rcb-result-row{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center;padding:14px 18px;border:1px solid #dbe3ec;border-radius:14px;background:#fff}.rcb-result-row h3{margin:0 0 2px;font-size:1.1rem}.rcb-result-row p{margin:0}.rcb-result-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}.rcb-btn-result{background:#17703a;border-color:#17703a;color:#fff!important;box-shadow:0 6px 16px rgba(23,112,58,.25)}.rcb-btn-result:hover{background:#125a2e}@media(max-width:640px){.rcb-result-row{grid-template-columns:auto 1fr}.rcb-result-actions{grid-column:1/-1;justify-content:flex-start}}.rcb-credit{font-size:.82rem;color:#657383}.rcb-credit p{margin:8px 0 0}.rcb-credit a{color:#3d5a78}body:not(.rcb-editing) .rcb-gallery-img{cursor:zoom-in}.rcb-lightbox{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:20px;background:rgba(3,16,32,.92);cursor:zoom-out}.rcb-lightbox img{max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 10px 40px #000}.rcb-gallery-empty{display:grid;place-items:center;border:2px dashed #9fb0c2;color:#657383;font-weight:bold}
    .rcb-news-list{display:flex;flex-direction:column;gap:12px}.rcb-news{display:flex;align-items:center;gap:18px;justify-content:space-between;padding:18px 20px;background:#fff;border:1px solid #dce3ea;border-radius:12px}.rcb-news>div{flex:1;min-width:0}.rcb-news h3{margin:6px 0 4px;color:var(--rcb-blue)}.rcb-news p{margin:6px 0}.rcb-news img{width:140px;height:96px;object-fit:cover;border-radius:8px;flex:none}.rcb-news .rcb-btn{margin-top:0;flex:none}.rcb-news details summary{cursor:pointer;color:var(--rcb-blue-mid);font-weight:bold}.rcb-news-update{border-left:4px solid var(--rcb-red)}@media(max-width:600px){.rcb-news{flex-direction:column;align-items:flex-start}.rcb-news img{width:100%;height:170px}}
    .rcb-nav-hidden{opacity:.55;text-decoration:line-through dotted!important}.rcb-hero.rcb-hero-compact{min-height:0;padding:clamp(36px,6vw,64px) 20px}.rcb-hero-compact h1{margin-bottom:10px}
    .rcb-footer{padding:26px 16px;background:var(--rcb-blue-dark);color:#dce5ee;text-align:center}
    .rcb-footer a{color:#fff}.rcb-nav{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}.rcb-nav nav ul{flex-wrap:wrap;justify-content:flex-end}
    .logo-area{display:flex;align-items:center;gap:0;min-width:0;flex:0 1 auto}.logo-area .brand-mark{display:flex;align-items:center;justify-content:flex-start;width:auto;height:auto;min-width:0;margin:0;padding:0;border:0;border-radius:0;background:transparent}.logo-area .club-name{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}.logo-area .brand-mark img{display:block;max-width:min(250px,34vw);max-height:96px}
    .rcb-card-image{display:block;width:100%;height:220px;object-fit:cover}
    .rcb-menu-button{display:none;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:transparent;color:#fff;font-size:25px;line-height:1;padding:8px 11px;cursor:pointer}
    @media(max-width:900px){.rcb-nav{align-items:center}.rcb-menu-button{display:block;margin-left:auto}.rcb-nav nav{display:none;flex-basis:100%;width:100%}.rcb-nav nav.open{display:block}.rcb-nav nav ul{display:flex;flex-direction:column;align-items:stretch;gap:2px}.rcb-nav nav a{padding:10px 12px}.rcb-nav nav a[aria-current="page"]{background:rgba(255,255,255,.14)}}
    .rcb-downloads h2{margin:0 0 14px;color:var(--rcb-blue)}.rcb-download-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
    .rcb-download{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid #dce3ea;border-radius:12px;background:#fff;color:var(--rcb-blue);text-decoration:none;box-shadow:0 4px 14px rgba(4,29,58,.05);transition:transform .12s,box-shadow .12s,border-color .12s}
    .rcb-download:hover{transform:translateY(-1px);border-color:var(--rcb-red);box-shadow:0 8px 20px rgba(4,29,58,.1)}
    .rcb-download-icon{display:grid;place-items:center;flex:none;width:48px;height:56px;border-radius:6px;background:var(--rcb-red);color:#fff;font-size:.8rem;font-weight:800;letter-spacing:.04em}
    .rcb-download-text{flex:1;min-width:0}.rcb-download-text strong{display:block;font-size:1.02rem}.rcb-download-text span{display:block;color:#3b4b5c;font-size:.9rem;margin-top:2px}.rcb-download-text small{display:block;color:#657383;margin-top:4px}
    .rcb-download-arrow{flex:none;font-size:1.3rem;color:var(--rcb-red)}
    .rcb-downloads-section{padding-top:0}
    .rcb-join-grid{align-items:stretch}.rcb-join-grid .rcb-download-list{grid-template-columns:1fr}.rcb-join-form{margin-top:24px;scroll-margin-top:120px}.rcb-join-form .rcb-card-body{max-width:820px}
    .rcb-form{display:flex;flex-direction:column;gap:6px;margin-top:10px}.rcb-form label{font-weight:700;color:var(--rcb-blue);margin-top:8px}
    .rcb-form input:not([type=checkbox]):not([type=radio]),.rcb-form select,.rcb-form textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #b9c6d3;border-radius:8px;background:#fff;color:#1d2733;font:inherit}
    .rcb-form input:focus,.rcb-form select:focus,.rcb-form textarea:focus{outline:3px solid rgba(201,39,44,.25);border-color:var(--rcb-red)}
    .rcb-form-row{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}.rcb-form-row>div{display:flex;flex-direction:column;gap:6px}@media(max-width:640px){.rcb-form-row{grid-template-columns:1fr}}
    .rcb-form .rcb-check{display:flex;gap:10px;align-items:flex-start;font-weight:400;color:#1d2733;margin:14px 0 6px}.rcb-check input{margin-top:4px;width:18px;height:18px;flex:none;accent-color:var(--rcb-red)}
    .rcb-form .rcb-btn{align-self:flex-start;margin-top:8px;border:0;cursor:pointer;font-size:1.05rem}.rcb-form-note{color:#657383;font-size:.88rem;margin:8px 0 0}
    .rcb-hp{position:absolute!important;left:-10000px;width:1px;height:1px;overflow:hidden}
    .rcb-form-errors{margin:12px 0;padding:12px 16px;border-left:4px solid var(--rcb-red);border-radius:0 8px 8px 0;background:#fdecec;color:#7a1518}.rcb-form-errors ul{margin:0;padding-left:18px}
    .rcb-form-success{padding:8px 0}.rcb-form-success h2{color:#1f7a4d}
    .rcb-form fieldset{margin:18px 0 4px;padding:16px 18px 14px;border:1px solid #dce3ea;border-radius:12px;display:flex;flex-direction:column;gap:6px}
    .rcb-form legend{padding:0 8px;font-weight:800;color:var(--rcb-blue);font-size:1.05rem}.rcb-form label small{font-weight:400;color:#657383}
    .rcb-form .rcb-check{margin:4px 0}.rcb-inline{display:flex;gap:24px}.rcb-q{margin:0;color:#1d2733}
    .rcb-fee-list{display:flex;flex-direction:column;gap:4px}.rcb-form .rcb-fee{display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border:1px solid #e3e9ef;border-radius:8px;margin:0}
    .rcb-fee span{flex:1}.rcb-fee strong{white-space:nowrap;color:var(--rcb-blue)}.rcb-fee:has(input:checked){border-color:var(--rcb-red);background:#fff7f7}
    .rcb-yesno{display:flex;flex-wrap:wrap;align-items:center;gap:6px 22px;padding:6px 0;border-bottom:1px solid #eef2f5}.rcb-yesno>span{flex:1 1 260px;color:#1d2733}.rcb-yesno .rcb-check{margin:0}
    .rcb-form-row-zip{grid-template-columns:160px 1fr}@media(max-width:640px){.rcb-form-row-zip{grid-template-columns:1fr}}
    .rcb-antrag-doc{background:#fff;border:1px solid #dce3ea;border-radius:12px;padding:32px 36px;color:#111}
    .rcb-antrag-doc header{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid var(--rcb-blue);padding-bottom:12px;margin-bottom:16px}
    .rcb-antrag-doc header h1{margin:0;font-size:1.7rem;color:var(--rcb-blue)}.rcb-antrag-doc header p{margin:4px 0 0;color:#555}.rcb-antrag-doc header img{height:80px;width:auto}
    .rcb-antrag-doc table{width:100%;border-collapse:collapse;margin-bottom:16px}.rcb-antrag-doc th,.rcb-antrag-doc td{text-align:left;vertical-align:top;padding:6px 8px;border-bottom:1px solid #e3e9ef}.rcb-antrag-doc th{width:38%;font-weight:600;color:#444}
    .rcb-sign-row{display:grid;grid-template-columns:1fr 1fr;gap:36px;margin:30px 0 6px}.rcb-sign-row>div{font-size:.85rem;color:#444}.rcb-sign-row span{display:block;border-bottom:1.5px solid #111;height:34px;margin-bottom:4px}
    .rcb-bank{margin-top:22px;padding:10px 12px;background:#f4f6f8;border-radius:8px;font-size:.92rem}.rcb-board{margin-top:22px;padding:12px 14px;border:1.5px dashed #888;border-radius:8px}.rcb-board .rcb-sign-row{margin-top:22px}.rcb-small{font-size:.8rem;color:#666;margin-top:16px}
    @media print{.site-header,.rcb-footer,.rcb-no-print,.rcb-downloads-section{display:none!important}body{background:#fff}.rcb-print-page{padding:0}.rcb-print-page .rcb-wrap{max-width:none!important;width:100%}.rcb-antrag-doc{border:0;padding:0}.rcb-antrag-doc table{font-size:10.5pt}@page{margin:14mm}}
    .rcb-admin-hint{padding:10px 12px;border:1px dashed var(--rcb-red);border-radius:8px;background:#fff7f7;font-size:.9rem}
    /* Kopfleiste: Logo mit Vereinsname, größere Menüpunkte, „Mitglied werden“ als Knopf */
    .site-header .header-inner.rcb-nav{width:min(100% - 40px,1320px);min-height:96px;padding:10px 0;flex-wrap:nowrap;gap:24px}
    .site-header a.logo-area{display:flex;align-items:center;gap:18px;flex:0 0 auto;text-decoration:none}
    .site-header .logo-area .brand-mark{display:block;width:auto;height:auto;flex:none;border:0;border-radius:0;background:transparent}
    .site-header .logo-area .rcb-logo-img{display:block;height:78px;width:auto;max-width:170px;object-fit:contain}
    .site-header .logo-area .rcb-logo-img{filter:brightness(0) invert(1) drop-shadow(0 1px 2px rgba(0,0,0,.25))}
    .site-header .logo-area .club-name{position:static;width:auto;height:auto;overflow:visible;clip:auto;color:#fff;font-size:18px;font-weight:800;line-height:1.15;letter-spacing:.005em;white-space:nowrap}
    .site-header .logo-area .club-name small{display:block;margin-top:5px;color:rgba(255,255,255,.78);font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase}
    .site-header nav ul{gap:6px;flex-wrap:nowrap}
    .site-header nav a,.site-header .rcb-sub-toggle{padding:11px 14px;border-radius:9px;font-size:16px;font-weight:700;line-height:1.2}
    .site-header nav a[aria-current="page"],.rcb-has-sub.is-active>.rcb-sub-toggle{background:rgba(255,255,255,.16);box-shadow:none}
    .site-header nav a.rcb-nav-cta{margin-left:8px;padding:11px 18px;background:var(--rcb-red);color:#fff;box-shadow:0 4px 14px rgba(201,39,44,.35)}
    .site-header nav a.rcb-nav-cta:hover,.site-header nav a.rcb-nav-cta[aria-current="page"]{background:var(--rcb-red-dark);box-shadow:0 4px 14px rgba(201,39,44,.45)}
    body.rcb-editing .site-header .logo-area .rcb-logo-img{outline-offset:2px}
    @media(max-width:1280px){.site-header nav a,.site-header .rcb-sub-toggle{padding:10px 10px;font-size:15px}.site-header .logo-area .club-name{font-size:16px}.site-header .logo-area .rcb-logo-img{height:68px}}
    @media(max-width:1180px){
      .site-header .header-inner.rcb-nav{min-height:84px;flex-wrap:wrap}
      .rcb-menu-button{display:block;margin-left:auto}
      .site-header nav,.rcb-nav nav{display:none;position:absolute;top:100%;left:0;right:0;width:100%;padding:8px 20px 18px;background:var(--rcb-blue);box-shadow:0 8px 12px rgba(0,0,0,.18)}
      .site-header nav.open,.rcb-nav nav.open{display:block}
      .site-header nav ul{flex-direction:column;align-items:stretch;gap:2px}
      .site-header nav a,.site-header .rcb-sub-toggle{width:100%;text-align:left;padding:12px 14px;font-size:16px;white-space:normal}
      .rcb-sub-toggle{opacity:.75;cursor:default;pointer-events:none}.rcb-sub-toggle span{display:none}
      .site-header nav ul.rcb-sub{display:flex;position:static;min-width:0;padding:0 0 0 14px;background:transparent;box-shadow:none}
      .site-header nav a.rcb-nav-cta{margin:8px 0 0;text-align:center}
    }
    @media(max-width:560px){.site-header .header-inner.rcb-nav{width:calc(100% - 24px);min-height:72px;gap:10px}.site-header a.logo-area{gap:10px}.site-header .logo-area .rcb-logo-img{height:52px;max-width:90px}.site-header .logo-area .club-name{font-size:14px}.site-header .logo-area .club-name small{font-size:9.5px;letter-spacing:.12em;margin-top:3px}}
    </style></head><body>';
    echo '<header class="site-header"><div class="header-inner rcb-nav">';
    echo '<a class="logo-area" href="/" aria-label="Zur Startseite"><span class="brand-mark">' . $logoMarkup . '</span><span class="club-name">' . rcb_h($siteName) . '<small>Modellsport in Zwickau</small></span></a>';
    echo '<button class="rcb-menu-button" type="button" aria-controls="rcb-main-nav" aria-expanded="false" aria-label="Menü öffnen">☰</button>';
    echo '<nav id="rcb-main-nav" aria-label="Hauptnavigation"><ul>';
    $hiddenMark = ' class="rcb-nav-hidden" title="Nicht veröffentlicht – nur für Admins sichtbar"';
    $cta = '';
    foreach ($links as $key => [$label, $url, $isHidden, $children]) {
        if ($key === 'mitglied' && !$children) {
            // „Mitglied werden“ als hervorgehobener Knopf ganz rechts.
            $cta = '<li class="rcb-nav-cta-item"><a class="rcb-nav-cta" href="' . rcb_h($url) . '"' . ($activeSlug === $key ? ' aria-current="page"' : '') . ($isHidden ? $hiddenMark : '') . '>' . rcb_h($label) . '</a></li>';
            continue;
        }
        if ($children) {
            $groupActive = isset($children[$activeSlug]);
            echo '<li class="rcb-has-sub' . ($groupActive ? ' is-active' : '') . '"><button type="button" class="rcb-sub-toggle" aria-expanded="false" aria-controls="rcb-sub-' . rcb_h($key) . '"' . ($groupActive ? ' aria-current="true"' : '') . '>' . rcb_h($label) . ' <span aria-hidden="true">▾</span></button><ul class="rcb-sub" id="rcb-sub-' . rcb_h($key) . '">';
            foreach ($children as $childKey => [$childLabel, $childUrl, $childHidden]) {
                echo '<li><a href="' . rcb_h($childUrl) . '"' . ($activeSlug === $childKey ? ' aria-current="page"' : '') . ($childHidden ? $hiddenMark : '') . '>' . rcb_h($childLabel) . '</a></li>';
            }
            echo '</ul></li>';
            continue;
        }
        $aria = $activeSlug === $key ? ' aria-current="page"' : '';
        echo '<li><a href="' . rcb_h($url) . '"' . $aria . ($isHidden ? $hiddenMark : '') . '>' . rcb_h($label) . '</a></li>';
    }
    echo $cta . '</ul></nav></div></header><main>';
}

function rcb_footer(): void
{
    $email = rcb_setting('contact_email');
    $phone = rcb_setting('contact_phone');
    // Dateien, die dieser Seite zugeordnet sind, erscheinen automatisch unten auf der Seite.
    $pageKey = (string)($GLOBALS['rcb_page_key'] ?? '');
    if ($pageKey !== '' && empty($GLOBALS['rcb_downloads_done'][$pageKey])) {
        $downloads = rcb_page_downloads($pageKey);
        if ($downloads !== '') {
            echo '<section class="rcb-section rcb-downloads-section"><div class="rcb-wrap">' . $downloads . '</div></section>';
        }
    }
    echo '</main><footer class="rcb-footer"><p><strong>' . rcb_h(rcb_setting('site_name', 'RC Bande Zwickau e.V.')) . '</strong></p>';
    if ($email !== '') {
        echo '<p><a href="mailto:' . rcb_h($email) . '">' . rcb_h($email) . '</a></p>';
    }
    if ($phone !== '') {
        echo '<p>Telefon: ' . rcb_h($phone) . '</p>';
    }
    $footerLinks = [];
    foreach (['impressum', 'datenschutz', 'haftungsausschluss'] as $slug) {
        if (rcb_page_published($slug)) {
            [$label, $url] = rcb_site_pages()[$slug];
            $footerLinks[] = '<a href="' . rcb_h($url) . '">' . rcb_h($label) . '</a>';
        }
    }
    echo '<p>' . implode(' · ', $footerLinks) . '</p>';
    echo '</footer></body></html>';
}
