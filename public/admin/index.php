<?php
declare(strict_types=1);

/**
 * RCB Plattform Admin
 * PHP 8.3+, PDO SQLite. Database stays outside the public directory.
 * The first visitor can create the initial administrator only while users is empty.
 */
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('rcb_admin_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, private');
header('Content-Security-Policy: default-src \'self\'; style-src \'unsafe-inline\'; form-action \'self\'; frame-ancestors \'none\'; base-uri \'self\'');

$root = dirname(__DIR__, 2);
// Öffentliches Verzeichnis: bei Hosting mit public_html als Webroot ist es der Elternordner von admin/.
$publicRoot = dirname(__DIR__);
$storage = $root . '/storage';
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
    http_response_code(500);
    exit('Speicherverzeichnis konnte nicht angelegt werden. Bitte Hosting prüfen.');
}
require_once $root . '/app/Database/Database.php';
require_once $root . '/app/Database/DatabaseSchema.php';
require_once $publicRoot . '/inc/pages.php';
require_once $publicRoot . '/inc/membership.php';
require_once $publicRoot . '/inc/tracks.php';
require_once $publicRoot . '/inc/design.php';
require_once $publicRoot . '/inc/notify.php';
try {
    $db = Database::connect();
    DatabaseSchema::ensure($db);
    DatabaseSchema::registerBundledImages($db, $publicRoot);
    // Promote the first existing account once, preserving access to the main administrator.
    $superadminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
    if ($superadminCount === 0) {
        $firstAdminId = $db->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
        if ($firstAdminId !== false) {
            $stmt = $db->prepare("UPDATE users SET role='superadmin', permissions='[]' WHERE id=?");
            $stmt->execute([(int)$firstAdminId]);
            $db->exec("UPDATE users SET role='subadmin', permissions='[\"events\",\"tracks\",\"pages\",\"sponsors\",\"media\",\"results\",\"news\"]' WHERE id <> " . (int)$firstAdminId . " AND role NOT IN ('superadmin','subadmin')");
        }
    }
    // Fällige Änderungs-Benachrichtigungen verschicken (höchstens einmal pro Minute geprüft).
    try { rcb_notify_dispatch($db); } catch (Throwable $e) { error_log('RCB notify: ' . $e->getMessage()); }
    // Klarnamen aus angenommenen Einladungen nachtragen (Einladungen vor Einführung des Namensfelds).
    foreach ($db->query("SELECT id, username FROM users WHERE display_name = '' OR display_name IS NULL")->fetchAll() as $u) {
        $stmt = $db->prepare("SELECT details FROM logs WHERE action = 'invite_accepted' AND user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int)$u['id']]);
        if (preg_match('/über Einladungslink \((.+)\)$/u', (string)$stmt->fetchColumn(), $m)) {
            $db->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([mb_substr(trim(preg_replace('/\s+[–-]\s+.*$/u', '', $m[1])), 0, 190), (int)$u['id']]);
        }
    }
    if (!Database::isMysql()) {
        @chmod(Database::sqlitePath(), 0640);
    }
} catch (Throwable $e) {
    error_log('RCB admin database error: ' . $e->getMessage());
    http_response_code(500);
    exit(Database::isMysql()
        ? 'Die Admin-Datenbank konnte nicht geöffnet werden. Bitte die Zugangsdaten in config/database.php prüfen.'
        : 'Die Admin-Datenbank konnte nicht geöffnet werden. Bitte PHP mit PDO_SQLite aktivieren und Schreibrechte für storage/ setzen.');
}
$initialSetupKey = trim((string)(Database::config()['initial_setup_key'] ?? ''));
// Sobald eine Konfigurationsdatei existiert (Webhosting), ist die Ersteinrichtung nur mit Schlüssel möglich.
$setupKeyRequired = is_file(Database::configFile()) || $initialSetupKey !== '';

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $sent)) {
        http_response_code(419);
        exit('Sitzung abgelaufen oder ungültige Anfrage. Bitte zurückgehen und erneut versuchen.');
    }
}
function admin_setting(PDO $db, string $key, string $default = ''): string {
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? $default : (string)$value;
}
function admin_set_setting(PDO $db, string $key, string $value): void {
    $db->prepare(Database::isMysql()
        ? 'INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value, updated_at=CURRENT_TIMESTAMP')->execute([$key, $value]);
}
/** Automatischer Hinweis „Neu auf der Seite“ für die Startseite (gleicher Hinweis am selben Tag wird nicht doppelt angelegt). */
function add_site_update(PDO $db, string $title, string $url): void {
    if (admin_setting($db, 'auto_updates_enabled', '1') !== '1') {
        return;
    }
    $title = function_exists('mb_substr') ? mb_substr($title, 0, 250) : substr($title, 0, 250);
    $stmt = $db->prepare('SELECT COUNT(*) FROM site_updates WHERE title = ? AND url = ? AND created_at >= ?');
    $stmt->execute([$title, $url, date('Y-m-d 00:00:00')]);
    if ((int)$stmt->fetchColumn() === 0) {
        $db->prepare('INSERT INTO site_updates (title, url, created_at) VALUES (?, ?, ?)')->execute([$title, $url, date('Y-m-d H:i:s')]);
    }
}
/** Adressen eigener Seiten, die im Menü stehen (nur existierende). */
function admin_custom_menu(PDO $db): array {
    $list = json_decode(admin_setting($db, 'nav_custom', '[]'), true);
    $existing = $db->query('SELECT slug FROM pages')->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(is_array($list) ? $list : [], static fn($slug) => is_string($slug) && in_array($slug, $existing, true)));
}
function admin_nav_order(PDO $db): array {
    return rcb_nav_order(admin_setting($db, 'nav_order', '[]'), admin_custom_menu($db));
}
function admin_rule_classes(PDO $db): array {
    return rcb_rule_classes(admin_setting($db, 'reglement_klassen', ''));
}
function admin_save_rule_classes(PDO $db, array $classes): void {
    admin_set_setting($db, 'reglement_klassen', json_encode(array_values($classes), JSON_UNESCAPED_UNICODE));
}
function admin_rule_class_title(PDO $db, array $class): string {
    $stmt = $db->prepare('SELECT content FROM content_blocks WHERE block_key = ? LIMIT 1');
    $stmt->execute(['reglement.' . $class['key'] . '.titel']);
    $title = $stmt->fetchColumn();
    return is_string($title) && trim($title) !== '' ? $title : $class['title'];
}
/** Welche Seite betrifft ein Seitenbereich? [Bezeichnung, Adresse] oder null (nicht ankündigen). */
function describe_block_key(PDO $db, string $key, array $hidden): ?array {
    $parts = explode('.', $key);
    $map = ['startseite' => 'home', 'verein' => 'verein', 'strecken' => 'home', 'strecke' => 'home', 'onroad' => 'onroad', 'offroad' => 'offroad', 'termine' => 'termine', 'ergebnisse' => 'ergebnisse', 'live' => 'live', 'mitglied' => 'mitglied', 'kontakt' => 'kontakt', 'downloads' => 'downloads', 'reglement' => 'reglement'];
    if ($parts[0] === 'seite' && isset($parts[1])) {
        $stmt = $db->prepare('SELECT title, published FROM pages WHERE slug = ? LIMIT 1');
        $stmt->execute([$parts[1]]);
        $row = $stmt->fetch();
        return $row && (int)$row['published'] === 1 ? [(string)$row['title'], '/seite.php?slug=' . rawurlencode($parts[1])] : null;
    }
    $slug = $map[$parts[0]] ?? '';
    if ($slug === '' || in_array($slug, $hidden, true)) {
        return null;
    }
    if ($slug === 'reglement' && isset($parts[1])) {
        foreach (admin_rule_classes($db) as $class) {
            if ($class['key'] === $parts[1]) {
                return $class['published'] ? ['Reglement: ' . admin_rule_class_title($db, $class), '/reglement.php?klasse=' . rawurlencode($class['key'])] : null;
            }
        }
    }
    [$label, $url] = rcb_site_pages()[$slug];
    return [$label, $url];
}
function log_action(PDO $db, ?int $userId, string $action, string $details = ''): void {
    $stmt = $db->prepare('INSERT INTO logs (user_id, action, details, logged_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $action, (function_exists('mb_substr') ? mb_substr($details, 0, 500) : substr($details, 0, 500)), date('Y-m-d H:i:s')]);
}
function redirect_to(string $page = 'dashboard', string $notice = 'Gespeichert'): never {
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
    header('Location: ' . $script . '?page=' . rawurlencode($page) . '&notice=' . rawurlencode($notice));
    exit;
}
function post_string(string $key, int $max = 5000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
function checked(int|string|bool $value): string {
    return (int)$value === 1 ? ' checked' : '';
}
function store_uploaded_image(mixed $file, string $publicRoot): array {
    if (!is_array($file)) {
        throw new RuntimeException('Bitte eine Bilddatei auswählen.');
    }
    
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Der Upload ist fehlgeschlagen. Bitte Datei und maximale Uploadgröße prüfen.');
    }
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Das Bild darf maximal 8 MB groß sein.');
    }
    if (!is_uploaded_file((string)$file['tmp_name'])) {
        throw new RuntimeException('Die hochgeladene Datei wurde nicht akzeptiert.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('Für sichere Bild-Uploads muss die PHP-Erweiterung fileinfo aktiviert sein.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Erlaubt sind nur JPG-, PNG- und WebP-Bilder.');
    }
    $imageInfo = @getimagesize((string)$file['tmp_name']);
    if (!$imageInfo || (int)$imageInfo[0] < 1 || (int)$imageInfo[1] < 1) {
        throw new RuntimeException('Die Datei enthält kein gültiges Bild.');
    }
    $uploads = $publicRoot . '/uploads';
    if (!is_dir($uploads) && !mkdir($uploads, 0755, true) && !is_dir($uploads)) {
        throw new RuntimeException('Der Upload-Ordner konnte nicht angelegt werden.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string)$file['tmp_name'], $uploads . '/' . $filename)) {
        throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
    }
    return [$filename, $mime];
}
/** Kurzes Video (MP4/WebM/MOV, max. 50 MB) speichern; gibt [Dateiname, MIME] zurück. */
function store_uploaded_video(mixed $file, string $publicRoot): array {
    if (!is_array($file)) { throw new RuntimeException('Bitte eine Videodatei auswählen.'); }
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { throw new RuntimeException('Das Video ist zu groß für den Server (erlaubt: ' . admin_upload_limit_mb() . ' MB).'); }
    if ($err !== UPLOAD_ERR_OK) { throw new RuntimeException('Der Upload ist fehlgeschlagen. Bitte erneut versuchen.'); }
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 50 * 1024 * 1024) { throw new RuntimeException('Das Video darf höchstens 50 MB groß sein. Bitte kürzen oder kleiner exportieren.'); }
    if (!is_uploaded_file((string)$file['tmp_name'])) { throw new RuntimeException('Die hochgeladene Datei wurde nicht akzeptiert.'); }
    if (!class_exists('finfo')) { throw new RuntimeException('Für Uploads muss die PHP-Erweiterung fileinfo aktiviert sein.'); }
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    if (!isset($extensions[$mime])) { throw new RuntimeException('Erlaubt sind nur MP4-, WebM- und MOV-Videos.'); }
    $uploads = $publicRoot . '/uploads';
    if (!is_dir($uploads) && !mkdir($uploads, 0755, true) && !is_dir($uploads)) { throw new RuntimeException('Der Upload-Ordner konnte nicht angelegt werden.'); }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string)$file['tmp_name'], $uploads . '/' . $filename)) { throw new RuntimeException('Das Video konnte nicht gespeichert werden.'); }
    return [$filename, $mime];
}
/** Kleinste Upload-Grenze des Servers in MB (upload_max_filesize / post_max_size). */
function admin_upload_limit_mb(): int {
    $toBytes = static function (string $v): int {
        $v = trim($v); if ($v === '') { return 0; }
        $n = (int)$v; $u = strtolower(substr($v, -1));
        return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
    };
    $limits = array_filter([$toBytes((string)ini_get('upload_max_filesize')), $toBytes((string)ini_get('post_max_size'))]);
    return $limits ? (int)floor(min($limits) / 1048576) : 0;
}
/** Sichere http(s)-Adresse? Leer ist erlaubt. */
function admin_url_or_empty(string $url, string $field): string {
    $url = trim($url);
    if ($url === '') { return ''; }
    if (!preg_match('#^https?://#i', $url)) { $url = 'https://' . $url; }
    if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        throw new RuntimeException('Bitte bei „' . $field . '“ eine gültige Internetadresse eingeben.');
    }
    return $url;
}
/** PDF-Ausschreibung speichern, gibt den Pfad (/uploads/ausschreibungen/…pdf) zurück. */
function store_uploaded_pdf(array $file, string $publicRoot): string {
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { throw new RuntimeException('Der PDF-Upload ist fehlgeschlagen. Bitte Datei und maximale Uploadgröße prüfen.'); }
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 10 * 1024 * 1024) { throw new RuntimeException('Die PDF-Datei darf maximal 10 MB groß sein.'); }
    if (!is_uploaded_file((string)$file['tmp_name'])) { throw new RuntimeException('Die hochgeladene Datei wurde nicht akzeptiert.'); }
    $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) : '';
    $head = (string)file_get_contents((string)$file['tmp_name'], false, null, 0, 5);
    if ($head !== '%PDF-' || ($mime !== '' && $mime !== 'application/pdf')) { throw new RuntimeException('Als Ausschreibung sind nur PDF-Dateien erlaubt.'); }
    $dir = $publicRoot . '/uploads/ausschreibungen';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { throw new RuntimeException('Der Ordner für Ausschreibungen konnte nicht angelegt werden.'); }
    $name = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) { throw new RuntimeException('Die PDF-Datei konnte nicht gespeichert werden.'); }
    @chmod($dir . '/' . $name, 0644);
    return '/uploads/ausschreibungen/' . $name;
}
function delete_uploaded_pdf(string $path, string $publicRoot): void {
    if (preg_match('#^/uploads/ausschreibungen/[a-f0-9]{32}\.pdf$#', $path)) { @unlink($publicRoot . $path); }
}
/** Speichert ein hochgeladenes Dokument (PDF, Word, Excel, OpenDocument) und gibt [Pfad, Größe] zurück. */
function store_uploaded_document(array $file, string $publicRoot): array {
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { throw new RuntimeException('Der Upload ist fehlgeschlagen. Bitte Datei und maximale Uploadgröße prüfen.'); }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 20 * 1024 * 1024) { throw new RuntimeException('Die Datei darf maximal 20 MB groß sein.'); }
    if (!is_uploaded_file((string)$file['tmp_name'])) { throw new RuntimeException('Die hochgeladene Datei wurde nicht akzeptiert.'); }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $head = (string)file_get_contents((string)$file['tmp_name'], false, null, 0, 8);
    $ok = match ($ext) {
        'pdf' => str_starts_with($head, '%PDF-'),
        'docx', 'xlsx', 'odt', 'ods' => str_starts_with($head, "PK\x03\x04"),
        'doc', 'xls' => str_starts_with($head, "\xD0\xCF\x11\xE0"),
        default => false,
    };
    if (!$ok) { throw new RuntimeException('Erlaubt sind PDF-, Word-, Excel- und OpenDocument-Dateien.'); }
    $dir = $publicRoot . '/uploads/dateien';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { throw new RuntimeException('Der Ordner für Dateien konnte nicht angelegt werden.'); }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) { throw new RuntimeException('Die Datei konnte nicht gespeichert werden.'); }
    @chmod($dir . '/' . $name, 0644);
    return ['/uploads/dateien/' . $name, $size];
}
/** Seiten, denen Dateien zugeordnet werden können: Kennung => Bezeichnung. */
function admin_file_targets(PDO $db, bool $reglementOnly): array {
    $targets = [];
    if (!$reglementOnly) {
        foreach (rcb_site_pages() as $slug => [$label]) {
            if ($slug !== 'downloads') { $targets[$slug] = $label; }
        }
    }
    $targets['reglement'] = 'Reglement – allgemeiner Teil';
    foreach (admin_rule_classes($db) as $class) {
        $targets['reglement:' . $class['key']] = 'Reglement – ' . admin_rule_class_title($db, $class);
    }
    if (!$reglementOnly) {
        foreach ($db->query('SELECT slug, title FROM pages ORDER BY title')->fetchAll() as $row) {
            $targets['seite:' . $row['slug']] = 'Eigene Seite – ' . $row['title'];
        }
    }
    return $targets;
}
function admin_permission_labels(): array {
    return ['events'=>'Termine','tracks'=>'Strecken','reglement'=>'Reglement','pages'=>'Seiten','sponsors'=>'Sponsoren','media'=>'Bilder','results'=>'Ergebnisse','news'=>'Neuigkeiten','requests'=>'Mitgliedschaft'];
}
function admin_permissions(array $user): array {
    $decoded = json_decode((string)($user['permissions'] ?? '[]'), true);
    return is_array($decoded) ? array_values(array_intersect($decoded, array_keys(admin_permission_labels()))) : [];
}
function admin_can(array $user, string $area): bool {
    if (($user['role'] ?? '') === 'superadmin') { return true; }
    if (in_array($area, ['dashboard'], true)) { return true; }
    $permissions = admin_permissions($user);
    // „Seiten“ schließt das Reglement ein; „Reglement“ allein erlaubt nur das Reglement.
    if ($area === 'membership') { $area = 'requests'; }
    if ($area === 'design') { $area = 'pages'; }
    if ($area === 'reglement' || $area === 'files') { return in_array('reglement', $permissions, true) || in_array('pages', $permissions, true); }
    return in_array($area, $permissions, true);
}
/** Welche Seitenbereiche darf der Benutzer im Editor ändern: 'all', 'reglement' oder null. */
function admin_editor_scope(array $user): ?string {
    if (admin_can($user, 'pages')) { return 'all'; }
    return admin_can($user, 'reglement') ? 'reglement' : null;
}
/** Adresse dieser Installation (für Links), aus der aktuellen Anfrage. */
function admin_origin(): string {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (!preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/', $host)) { $host = 'localhost'; }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https://' : 'http://') . $host;
}
/** Erstellt einen Einmal-Link-Code (Einladung oder Passwort) und speichert nur dessen Hash. */
function admin_create_token(PDO $db, string $kind, ?int $userId, array $permissions, string $note, int $validSeconds, ?int $createdBy): string {
    $token = bin2hex(random_bytes(24));
    $db->prepare('INSERT INTO admin_tokens (token_hash, kind, user_id, permissions, note, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
       ->execute([hash('sha256', $token), $kind, $userId, json_encode(array_values($permissions), JSON_THROW_ON_ERROR), $note, $createdBy, time() + $validSeconds]);
    return $token;
}
/** Gültiger, noch nicht benutzter Code einer Art, sonst null. */
function admin_find_token(PDO $db, string $token, string $kind): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) { return null; }
    $stmt = $db->prepare('SELECT * FROM admin_tokens WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at >= ? LIMIT 1');
    $stmt->execute([hash('sha256', $token), $kind, time()]);
    return $stmt->fetch() ?: null;
}
/** Markiert einen Code als benutzt; false, wenn er inzwischen schon verbraucht wurde. */
function admin_use_token(PDO $db, int $id): bool {
    $stmt = $db->prepare('UPDATE admin_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL');
    $stmt->execute([time(), $id]);
    return $stmt->rowCount() === 1;
}
function admin_token_url(string $script, string $kind, string $token): string {
    return admin_origin() . $script . '?' . ($kind === 'invite' ? 'einladung' : 'passwort') . '=' . $token;
}
/** „Klarname (benutzername)“ bzw. nur der Benutzername, wenn kein Name hinterlegt ist. */
function admin_user_label(array $user): string {
    $name = trim((string)($user['display_name'] ?? ''));
    return $name !== '' ? $name . ' (' . $user['username'] . ')' : (string)$user['username'];
}
function admin_email_taken(PDO $db, string $email, int $exceptId): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?) AND id <> ?');
    $stmt->execute([$email, $exceptId]);
    return (int)$stmt->fetchColumn() > 0;
}
function admin_check_new_password(string $password, string $confirm): void {
    if (strlen($password) < 12) { throw new RuntimeException('Das Passwort muss mindestens 12 Zeichen lang sein.'); }
    if (!hash_equals($password, $confirm)) { throw new RuntimeException('Die Passwörter stimmen nicht überein.'); }
}
function admin_start_session(array $user): void {
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = (string)$user['username'];
    $_SESSION['admin_role'] = (string)$user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
/** Zählt einen Versuch (Anmeldung, Passwort vergessen, Notfall-Zugang); true, wenn zu viele. */
function admin_too_many_attempts(PDO $db, bool $record): bool {
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown') . (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $cutoff = time() - 900;
    $db->prepare('DELETE FROM admin_login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
    $stmt = $db->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash = ? AND attempted_at >= ?');
    $stmt->execute([$ipHash, $cutoff]);
    if ((int)$stmt->fetchColumn() >= 8) { return true; }
    if ($record) { $db->prepare('INSERT INTO admin_login_attempts (ip_hash, attempted_at) VALUES (?, ?)')->execute([$ipHash, time()]); }
    return false;
}
function admin_area_for_page(string $page): string {
    return $page === 'design' ? 'pages' : $page;
}
function admin_draft_banner(PDO $db, string $returnPage, string $previewUrl, bool $reglementOnly = false): void {
    $stmt = $db->query('SELECT block_key FROM content_drafts' . ($reglementOnly ? " WHERE block_key LIKE 'reglement.%'" : '') . ' ORDER BY block_key');
    $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $count = count($keys);
    if ($returnPage === 'reglement' && !$reglementOnly) {
        $own = count(array_filter($keys, static fn($k) => str_starts_with((string)$k, 'reglement.')));
    }
    $hidden = '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="return" value="' . h($returnPage) . '">';
    if ($count === 0) {
        echo '<p class="muted">Alle gespeicherten Änderungen sind online.</p>';
        return;
    }
    echo '<section class="card draft-banner"><div><h2>Unveröffentlichter Entwurf</h2><p><strong>' . $count . '</strong> gespeicherte ' . ($count === 1 ? 'Änderung ist' : 'Änderungen sind') . ' noch nicht online'
        . (isset($own) ? ' (davon <strong>' . $own . '</strong> im Reglement)' : '') . '. Besucher sehen weiterhin den bisherigen Stand.'
        . (isset($own) && $own < $count ? ' Veröffentlichen stellt alle Änderungen online, auch die auf anderen Seiten.' : '') . '</p></div>'
        . '<div class="form-actions"><form method="post">' . $hidden . '<input type="hidden" name="action" value="publish_drafts"><button type="submit">⬆ Jetzt veröffentlichen</button></form>'
        . '<a class="button secondary" href="' . h($previewUrl) . '" target="_blank" rel="noopener">Entwurf ansehen</a>'
        . '<form method="post" data-confirm="Alle unveröffentlichten Änderungen verwerfen?">' . $hidden . '<input type="hidden" name="action" value="discard_drafts"><button class="danger" type="submit">Entwurf verwerfen</button></form></div></section>';
}
function admin_area_for_action(string $action): string {
    $map = ['save_event'=>'events','save_event_settings'=>'events','delete_event'=>'events','save_track'=>'tracks','delete_track'=>'tracks','save_page'=>'pages','delete_page'=>'pages','save_sponsor'=>'sponsors','delete_sponsor'=>'sponsors','upload_media'=>'media','delete_media'=>'media','save_result'=>'results','delete_result'=>'results','save_news'=>'news','delete_news'=>'news','save_settings'=>'settings','toggle_user'=>'users','delete_user'=>'users','backup_export'=>'backup','page_visibility'=>'pages','page_move'=>'pages','rule_class_add'=>'reglement','rule_class_move'=>'reglement','rule_class_toggle'=>'reglement','rule_class_delete'=>'reglement','delete_site_update'=>'news','custom_page_add'=>'pages','custom_page_menu'=>'pages','clear_site_updates'=>'news','toggle_auto_updates'=>'news','create_invite'=>'users','save_membership'=>'membership','save_design'=>'design','save_notify'=>'dashboard','reset_design'=>'design','request_status'=>'requests','request_delete'=>'requests','file_upload'=>'files','file_delete'=>'files','file_toggle'=>'files','file_move'=>'files','update_user'=>'users','create_reset_link'=>'users','revoke_token'=>'users'];
    return $map[$action] ?? 'dashboard';
}

$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$setupMode = $userCount === 0;
$error = '';
$notice = trim((string)($_GET['notice'] ?? ''));
$page = preg_replace('/[^a-z]/', '', (string)($_GET['page'] ?? 'dashboard')) ?: 'dashboard';
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');

// ---------- Schnittstellen für den Seiten-Editor (JSON) ----------
$editorAction = (string)($_POST['action'] ?? '');
// Bei zu großen Uploads verwirft PHP alle Formularfelder; der Editor schickt die Aktion deshalb zusätzlich in der Adresse mit.
if ($editorAction === '' && ($_GET['editor'] ?? '') === 'upload_block_video') { $editorAction = 'upload_block_video'; }
$isEditorRequest = ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($editorAction, ['save_blocks', 'upload_block_image', 'upload_block_video', 'publish_drafts', 'discard_drafts'], true))
    || ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($page, ['mediajson', 'draftstatus'], true));
$isEditorForm = $editorAction !== '' && empty($_SERVER['HTTP_X_REQUESTED_WITH']);
if ($isEditorRequest) {
    header('Content-Type: application/json; charset=UTF-8');
    $respond = static function (int $code, array $data) use ($isEditorForm): never {
        if ($isEditorForm && ($data['ok'] ?? false)) {
            redirect_to(($_POST['return'] ?? '') === 'reglement' ? 'reglement' : 'pages', (string)($data['message'] ?? 'Gespeichert'));
        }
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $respond(413, ['ok' => false, 'error' => 'Die Datei ist zu groß für den Server (erlaubt: ' . admin_upload_limit_mb() . ' MB).']);
    }
    $editorUser = null;
    if (!empty($_SESSION['admin_user_id'])) {
        $stmt = $db->prepare('SELECT id, username, role, permissions, active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['admin_user_id']]);
        $editorUser = $stmt->fetch() ?: null;
    }
    if (!$editorUser || (int)$editorUser['active'] !== 1) { $respond(401, ['ok' => false, 'error' => 'Nicht angemeldet. Bitte im Admin neu anmelden.']); }
    $editorScope = admin_editor_scope($editorUser);
    if ($editorScope === null && !admin_can($editorUser, 'media')) { $respond(403, ['ok' => false, 'error' => 'Keine Berechtigung zum Bearbeiten der Seiten.']); }
    // Nur-Reglement-Admins sehen und ändern ausschließlich Reglement-Entwürfe.
    $draftWhere = $editorScope === 'reglement' ? " WHERE block_key LIKE 'reglement.%'" : '';
    if ($page === 'draftstatus' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $keys = $db->query('SELECT block_key FROM content_drafts' . $draftWhere . ' ORDER BY block_key')->fetchAll(PDO::FETCH_COLUMN);
        $respond(200, ['ok' => true, 'count' => count($keys), 'keys' => $keys]);
    }
    if ($page === 'mediajson' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $wantVideo = ($_GET['type'] ?? '') === 'video';
        $items = $db->query('SELECT filename, title FROM media WHERE published = 1 AND category ' . ($wantVideo ? "= 'video'" : "<> 'video'") . ' ORDER BY id DESC')->fetchAll();
        $respond(200, array_map(static fn($m) => ['url' => '/uploads/' . rawurlencode((string)$m['filename']), 'title' => (string)$m['title']], $items));
    }
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $sent)) { $respond(419, ['ok' => false, 'error' => 'Sitzung abgelaufen. Bitte die Seite neu laden oder im Admin neu anmelden.']); }
    if ($editorScope === null) { $respond(403, ['ok' => false, 'error' => 'Keine Berechtigung zum Bearbeiten der Seiten.']); }
    try {
        if ($editorAction === 'upload_block_image') {
            [$filename, $mime] = store_uploaded_image($_FILES['media_file'] ?? null, $publicRoot);
            $originalName = function_exists('mb_substr') ? mb_substr((string)($_FILES['media_file']['name'] ?? ''), 0, 180) : substr((string)($_FILES['media_file']['name'] ?? ''), 0, 180);
            $db->prepare('INSERT INTO media (filename,title,description,category,mime_type,published) VALUES (?,?,?,?,?,1)')
               ->execute([$filename, $originalName, 'Über den Seiten-Editor hochgeladen', 'seite', $mime]);
            log_action($db, (int)$editorUser['id'], 'media_uploaded', $filename . ' (Seiten-Editor)');
            $respond(200, ['ok' => true, 'url' => '/uploads/' . rawurlencode($filename)]);
        }
        if ($editorAction === 'upload_block_video') {
            [$filename, $mime] = store_uploaded_video($_FILES['media_file'] ?? null, $publicRoot);
            $originalName = function_exists('mb_substr') ? mb_substr((string)($_FILES['media_file']['name'] ?? ''), 0, 180) : substr((string)($_FILES['media_file']['name'] ?? ''), 0, 180);
            $db->prepare('INSERT INTO media (filename,title,description,category,mime_type,published) VALUES (?,?,?,?,?,1)')
               ->execute([$filename, $originalName, 'Video über den Seiten-Editor hochgeladen', 'video', $mime]);
            log_action($db, (int)$editorUser['id'], 'media_uploaded', $filename . ' (Video)');
            $respond(200, ['ok' => true, 'url' => '/uploads/' . rawurlencode($filename)]);
        }
        if ($editorAction === 'publish_drafts') {
            $drafts = $db->query('SELECT block_key, content FROM content_drafts' . $draftWhere)->fetchAll();
            $publish = $db->prepare(Database::isMysql()
                ? 'INSERT INTO content_blocks (block_key, content, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
                : 'INSERT INTO content_blocks (block_key, content, updated_by) VALUES (?, ?, ?) ON CONFLICT(block_key) DO UPDATE SET content = excluded.content, updated_by = excluded.updated_by, updated_at = CURRENT_TIMESTAMP');
            $unpublish = $db->prepare('DELETE FROM content_blocks WHERE block_key = ?');
            $db->beginTransaction();
            foreach ($drafts as $draft) {
                if ($draft['content'] === null) {
                    $unpublish->execute([$draft['block_key']]);
                } else {
                    $publish->execute([$draft['block_key'], $draft['content'], (int)$editorUser['id']]);
                }
            }
            $db->exec('DELETE FROM content_drafts' . $draftWhere);
            $db->commit();
            if (($_POST['announce'] ?? '1') !== '0') {
                $hidden = rcb_hidden_pages(admin_setting($db, 'pages_hidden', '[]'), admin_setting($db, 'live_enabled', '0'));
                $announced = [];
                foreach ($drafts as $draft) {
                    $target = describe_block_key($db, (string)$draft['block_key'], $hidden);
                    if ($target && !isset($announced[$target[1]])) {
                        $announced[$target[1]] = true;
                        add_site_update($db, $target[0] . (in_array($target[0], ['Ergebnisse', 'Termine', 'Downloads'], true) ? ' wurden' : ' wurde') . ' aktualisiert', $target[1]);
                    }
                }
            }
            log_action($db, (int)$editorUser['id'], 'content_published', count($drafts) . ' Änderungen veröffentlicht');
            $respond(200, ['ok' => true, 'published' => count($drafts), 'message' => count($drafts) === 1 ? '1 Änderung veröffentlicht' : count($drafts) . ' Änderungen veröffentlicht']);
        }
        if ($editorAction === 'discard_drafts') {
            $count = (int)$db->query('SELECT COUNT(*) FROM content_drafts' . $draftWhere)->fetchColumn();
            $db->exec('DELETE FROM content_drafts' . $draftWhere);
            log_action($db, (int)$editorUser['id'], 'content_drafts_discarded', $count . ' Entwurfsänderungen verworfen');
            $respond(200, ['ok' => true, 'discarded' => $count, 'message' => 'Entwurf verworfen']);
        }
        $blocks = json_decode((string)($_POST['blocks'] ?? ''), true);
        if (!is_array($blocks) || !$blocks || count($blocks) > 200) { throw new RuntimeException('Keine gültigen Änderungen übermittelt.'); }
        require_once $root . '/app/Helpers/RichText.php';
        $upsert = $db->prepare(Database::isMysql()
            ? 'INSERT INTO content_drafts (block_key, content, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO content_drafts (block_key, content, updated_by) VALUES (?, ?, ?) ON CONFLICT(block_key) DO UPDATE SET content = excluded.content, updated_by = excluded.updated_by, updated_at = CURRENT_TIMESTAMP');
        $db->beginTransaction();
        foreach ($blocks as $key => $block) {
            $key = (string)$key;
            if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,189}$/', $key) || !is_array($block)) { throw new RuntimeException('Ungültiger Seitenbereich.'); }
            if ($editorScope === 'reglement' && !str_starts_with($key, 'reglement.')) { throw new RuntimeException('Du darfst nur das Reglement bearbeiten.'); }
            $type = (string)($block['type'] ?? '');
            $value = (string)($block['value'] ?? '');
            if ($type === 'text') {
                $value = str_replace("\r\n", "\n", trim(strip_tags($value)));
                $value = function_exists('mb_substr') ? mb_substr($value, 0, 5000) : substr($value, 0, 5000);
            } elseif ($type === 'rich') {
                if (strlen($value) > 300000) { throw new RuntimeException('Der Text ist zu lang.'); }
                $value = RichText::sanitize($value);
            } elseif ($type === 'image') {
                $value = trim($value);
                if ($value === '') { $upsert->execute([$key, null, (int)$editorUser['id']]); continue; }
                if (!preg_match('#^/(uploads|assets/images)/[A-Za-z0-9._%-]+(\#p=\d{1,3},\d{1,3})?$#', $value)) { throw new RuntimeException('Ungültige Bildadresse.'); }
            } elseif ($type === 'video') {
                $value = trim($value);
                if ($value === '') { $upsert->execute([$key, null, (int)$editorUser['id']]); continue; }
                if (!preg_match('#^/uploads/[A-Za-z0-9._%-]+\.(mp4|webm|mov)$#', $value)) { throw new RuntimeException('Ungültige Videoadresse.'); }
            } else {
                throw new RuntimeException('Unbekannter Bereichstyp.');
            }
            $upsert->execute([$key, $value, (int)$editorUser['id']]);
        }
        $db->commit();
        log_action($db, (int)$editorUser['id'], 'content_draft_saved', implode(', ', array_slice(array_keys($blocks), 0, 20)));
        $pending = (int)$db->query('SELECT COUNT(*) FROM content_drafts' . $draftWhere)->fetchColumn();
        $respond(200, ['ok' => true, 'saved' => count($blocks), 'pending' => $pending]);
    } catch (RuntimeException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $respond(422, ['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log('RCB editor error: ' . $e->getMessage());
        $respond(500, ['ok' => false, 'error' => 'Speichern fehlgeschlagen. Bitte erneut versuchen.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($setupMode && $action === 'initial_setup') {
        $username = post_string('username', 60);
        $email = post_string('email', 190);
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        $providedSetupKey = trim((string)($_POST['initial_setup_key'] ?? ''));
        $setupIpHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown') . (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $setupCutoff = time() - 900;
        $db->prepare('DELETE FROM admin_login_attempts WHERE attempted_at < ?')->execute([$setupCutoff]);
        $attemptQuery = $db->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash = ? AND attempted_at >= ?');
        $attemptQuery->execute([$setupIpHash, $setupCutoff]);
        if ($setupKeyRequired && (int)$attemptQuery->fetchColumn() >= 8) {
            $error = 'Zu viele Versuche mit dem Einrichtungsschlüssel. Bitte 15 Minuten warten.';
        } elseif ($setupKeyRequired && (strlen($initialSetupKey) < 10 || str_starts_with($initialSetupKey, 'REPLACE_WITH_'))) {
            $error = 'Der Einrichtungsschlüssel fehlt oder ist zu kurz. In config/database.php muss bei initial_setup_key ein Schlüssel mit mindestens 10 Zeichen eingetragen sein.';
        } elseif ($setupKeyRequired && !hash_equals($initialSetupKey, $providedSetupKey)) {
            $db->prepare('INSERT INTO admin_login_attempts (ip_hash, attempted_at) VALUES (?, ?)')->execute([$setupIpHash, time()]);
            $error = 'Der eingegebene Einrichtungsschlüssel stimmt nicht mit config/database.php überein. Das ist nicht das Datenbank-Passwort.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
            $error = 'Der Benutzername muss 3–60 Zeichen haben (Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Bitte eine gültige E-Mail-Adresse eingeben. Sie wird für „Passwort vergessen“ gebraucht.';
        } elseif (strlen($password) < 12) {
            $error = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            $stmt = $db->prepare('INSERT INTO users (username, email, display_name, password_hash, role, permissions, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
            try {
                $stmt->execute([$username, $email, post_string('display_name', 190), password_hash($password, PASSWORD_DEFAULT), 'superadmin', '[]']);
                $newId = (int)$db->lastInsertId();
                log_action($db, $newId, 'initial_setup', 'Erstes Administratorkonto erstellt');
                session_regenerate_id(true);
                $_SESSION['admin_user_id'] = $newId;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_role'] = 'superadmin';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                redirect_to('dashboard', 'Administratorkonto eingerichtet');
            } catch (PDOException $e) {
                $error = 'Das Konto konnte nicht erstellt werden. Der Benutzername ist möglicherweise bereits vergeben.';
            }
        }
    } elseif (!$setupMode && $action === 'login' && empty($_SESSION['admin_user_id'])) {
        $username = post_string('username', 190);
        $password = (string)($_POST['password'] ?? '');
        $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown') . (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $cutoff = time() - 900;
        $stmt = $db->prepare('DELETE FROM admin_login_attempts WHERE attempted_at < ?');
        $stmt->execute([$cutoff]);
        $stmt = $db->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash = ? AND attempted_at >= ?');
        $stmt->execute([$ipHash, $cutoff]);
        $attempts = (int)$stmt->fetchColumn();
        if ($attempts >= 8) {
            $error = 'Zu viele Anmeldeversuche. Bitte 15 Minuten warten.';
        } else {
            $stmt = $db->prepare('SELECT id, username, password_hash, role, active FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && (int)$user['active'] === 1 && password_verify($password, $user['password_hash'])) {
                $db->prepare('DELETE FROM admin_login_attempts WHERE ip_hash = ?')->execute([$ipHash]);
                session_regenerate_id(true);
                $_SESSION['admin_user_id'] = (int)$user['id'];
                $_SESSION['admin_username'] = (string)$user['username'];
                $_SESSION['admin_role'] = (string)$user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                if ((string)$user['role'] === 'superadmin') { admin_set_setting($db, 'site_url', admin_origin()); }
                log_action($db, (int)$user['id'], 'login', 'Anmeldung erfolgreich');
                redirect_to('dashboard', 'Willkommen zurück');
            }
            $db->prepare('INSERT INTO admin_login_attempts (ip_hash, attempted_at) VALUES (?, ?)')->execute([$ipHash, time()]);
            $error = 'Anmeldung fehlgeschlagen. Bitte Zugangsdaten prüfen.';
        }
    } elseif (!$setupMode && in_array($action, ['accept_invite', 'reset_password', 'forgot_password', 'recover_superadmin'], true)) {
        try {
            if ($action === 'accept_invite') {
                $row = admin_find_token($db, (string)($_GET['einladung'] ?? ''), 'invite');
                if (!$row) { throw new RuntimeException('Diese Einladung ist ungültig, abgelaufen oder wurde schon benutzt.'); }
                $username = post_string('username', 60);
                $email = post_string('email', 190);
                $displayName = post_string('display_name', 190);
                if ($displayName === '') { throw new RuntimeException('Bitte deinen Namen eintragen.'); }
                if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) { throw new RuntimeException('Der Benutzername muss 3–60 Zeichen haben (Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich).'); }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben. Sie wird für „Passwort vergessen“ gebraucht.'); }
                if (admin_email_taken($db, $email, 0)) { throw new RuntimeException('Diese E-Mail-Adresse gehört schon zu einem anderen Zugang.'); }
                admin_check_new_password((string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''));
                $exists = $db->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?)');
                $exists->execute([$username]);
                if ((int)$exists->fetchColumn() > 0) { throw new RuntimeException('Dieser Benutzername ist schon vergeben. Bitte einen anderen wählen.'); }
                $permissions = array_values(array_intersect((array)json_decode((string)$row['permissions'], true), array_keys(admin_permission_labels())));
                $db->beginTransaction();
                if (!admin_use_token($db, (int)$row['id'])) { $db->rollBack(); throw new RuntimeException('Diese Einladung wurde gerade schon benutzt.'); }
                $db->prepare('INSERT INTO users (username,email,display_name,password_hash,role,permissions,active) VALUES (?,?,?,?,?,?,1)')
                   ->execute([$username, $email, $displayName, password_hash((string)$_POST['password'], PASSWORD_DEFAULT), 'subadmin', json_encode($permissions, JSON_THROW_ON_ERROR)]);
                $newId = (int)$db->lastInsertId();
                $db->prepare('UPDATE admin_tokens SET user_id = ? WHERE id = ?')->execute([$newId, (int)$row['id']]);
                $db->commit();
                log_action($db, $newId, 'invite_accepted', $username . ' über Einladungslink' . ($row['note'] !== '' ? ' (' . $row['note'] . ')' : ''));
                admin_start_session(['id' => $newId, 'username' => $username, 'role' => 'subadmin']);
                redirect_to('dashboard', 'Willkommen! Dein Zugang ist eingerichtet.');
            }
            if ($action === 'reset_password') {
                $row = admin_find_token($db, (string)($_GET['passwort'] ?? ''), 'reset');
                $stmt = $db->prepare('SELECT id, username, role, active FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([(int)($row['user_id'] ?? 0)]);
                $user = $row ? $stmt->fetch() : false;
                if (!$row || !$user || (int)$user['active'] !== 1) { throw new RuntimeException('Dieser Link ist ungültig, abgelaufen oder wurde schon benutzt.'); }
                admin_check_new_password((string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''));
                if (!admin_use_token($db, (int)$row['id'])) { throw new RuntimeException('Dieser Link wurde gerade schon benutzt.'); }
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), (int)$user['id']]);
                $db->prepare("UPDATE admin_tokens SET used_at = ? WHERE kind = 'reset' AND user_id = ? AND used_at IS NULL")->execute([time(), (int)$user['id']]);
                log_action($db, (int)$user['id'], 'password_reset', 'Passwort über Link neu gesetzt');
                admin_start_session($user);
                redirect_to('dashboard', 'Dein neues Passwort ist gespeichert.');
            }
            if ($action === 'forgot_password') {
                if (admin_too_many_attempts($db, true)) { throw new RuntimeException('Zu viele Versuche. Bitte 15 Minuten warten.'); }
                $identifier = post_string('identifier', 190);
                $stmt = $db->prepare("SELECT id, username, email FROM users WHERE active = 1 AND email <> '' AND (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) LIMIT 1");
                $stmt->execute([$identifier, $identifier]);
                $user = $identifier !== '' ? $stmt->fetch() : false;
                $base = admin_setting($db, 'site_url', '');
                if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL) && $base !== '') {
                    $token = admin_create_token($db, 'reset', (int)$user['id'], [], 'Passwort vergessen (E-Mail)', 3600, null);
                    $link = $base . $script . '?passwort=' . $token;
                    $siteName = admin_setting($db, 'site_name', 'RC Bande Zwickau e.V.');
                    $body = "Hallo " . $user['username'] . ",\n\nfür deinen Admin-Zugang bei " . $siteName . " wurde ein neues Passwort angefordert.\n"
                        . "Über diesen Link kannst du innerhalb der nächsten Stunde ein neues Passwort festlegen:\n\n" . $link
                        . "\n\nDer Link funktioniert nur einmal. Wenn du das nicht angefordert hast, kannst du diese E-Mail einfach ignorieren.\n";
                    $host = (string)(parse_url($base, PHP_URL_HOST) ?: 'localhost');
                    $headers = "From: " . mb_encode_mimeheader($siteName, 'UTF-8') . " <noreply@" . preg_replace('/^www\./', '', $host) . ">\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
                    if (!@mail((string)$user['email'], mb_encode_mimeheader('Neues Passwort für den Admin-Zugang', 'UTF-8'), $body, $headers)) {
                        error_log('RCB: Passwort-E-Mail konnte nicht gesendet werden an Benutzer ' . $user['id']);
                    }
                    log_action($db, (int)$user['id'], 'password_reset_requested', 'Link per E-Mail angefordert');
                }
                // Immer dieselbe Antwort, damit niemand herausfinden kann, welche Konten es gibt.
                header('Location: ' . $script . '?vergessen=1&notice=' . rawurlencode('Wenn zu diesem Namen bzw. dieser E-Mail ein Konto mit hinterlegter E-Mail-Adresse existiert, ist jetzt ein Link unterwegs (bitte auch im Spam-Ordner schauen). Er gilt eine Stunde.'));
                exit;
            }
            if ($action === 'recover_superadmin') {
                if (admin_too_many_attempts($db, false)) { throw new RuntimeException('Zu viele Versuche. Bitte 15 Minuten warten.'); }
                $username = post_string('username', 190);
                $key = trim((string)($_POST['setup_key'] ?? ''));
                $stmt = $db->prepare("SELECT id, username, role, active FROM users WHERE LOWER(username) = LOWER(?) AND role = 'superadmin' AND active = 1 LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                $keyUsable = strlen($initialSetupKey) >= 10 && !str_starts_with($initialSetupKey, 'REPLACE_WITH_');
                if (!$keyUsable || !$user || $key === '' || !hash_equals($initialSetupKey, $key)) {
                    admin_too_many_attempts($db, true);
                    throw new RuntimeException('Hauptadmin-Name oder Einrichtungsschlüssel stimmen nicht.');
                }
                admin_check_new_password((string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''));
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), (int)$user['id']]);
                log_action($db, (int)$user['id'], 'password_recovered', 'Hauptadmin-Passwort mit Einrichtungsschlüssel neu gesetzt');
                admin_start_session($user);
                admin_set_setting($db, 'site_url', admin_origin());
                redirect_to('dashboard', 'Dein neues Passwort ist gespeichert.');
            }
        } catch (RuntimeException $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $error = $e->getMessage();
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('RCB token action error: ' . $e->getMessage());
            $error = 'Es ist ein Fehler aufgetreten. Bitte erneut versuchen.';
        }
    } elseif (!empty($_SESSION['admin_user_id'])) {
        $uid = (int)$_SESSION['admin_user_id'];
        $authStmt = $db->prepare('SELECT id, username, email, display_name, role, permissions, active, notify_mode FROM users WHERE id = ? LIMIT 1');
        $authStmt->execute([$uid]);
        $currentUser = $authStmt->fetch();
        if (!$currentUser || (int)$currentUser['active'] !== 1) { http_response_code(403); exit('Benutzerkonto ist nicht aktiv.'); }
        $actionArea = admin_area_for_action($action);
        if ($action !== 'logout' && $actionArea !== 'dashboard' && !admin_can($currentUser, $actionArea)) { http_response_code(403); exit('Für diese Aktion hast du keine Berechtigung.'); }
        if ($action === 'backup_export') {
            // Authenticated, CSRF-protected SQL export. Restore only into a fresh database.
            log_action($db, $uid, 'database_backup_downloaded', 'SQL-Datenbankexport heruntergeladen');
            if (Database::isMysql()) {
                $dump = "-- RC Bande Zwickau Datenbank-Backup (MySQL/MariaDB)\n-- Erstellt: " . date(DATE_ATOM) . "\n-- Import nur in eine frische/leere Datenbank.\n";
                $dump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
                foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
                    $quotedTable = '`' . str_replace('`', '``', (string)$tableName) . '`';
                    $create = $db->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
                    $dump .= "\n" . (string)($create[1] ?? '') . ";\n";
                    $rows = $db->query('SELECT * FROM ' . $quotedTable);
                    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                        $columnsSql = implode(', ', array_map(static fn($name) => '`' . str_replace('`', '``', (string)$name) . '`', array_keys($row)));
                        $values = array_map(static fn($value) => $value === null ? 'NULL' : $db->quote((string)$value), array_values($row));
                        $dump .= 'INSERT INTO ' . $quotedTable . ' (' . $columnsSql . ') VALUES (' . implode(', ', $values) . ");\n";
                    }
                }
                $dump .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
                header('Content-Type: application/sql; charset=UTF-8');
                header('Content-Disposition: attachment; filename="rcb-backup-' . date('Ymd-His') . '.sql"');
                header('Content-Length: ' . strlen($dump));
                echo $dump;
                exit;
            }
            $tables = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND sql IS NOT NULL ORDER BY name")->fetchAll();
            $dump = "-- RC Bande Zwickau Datenbank-Backup\n-- Erstellt: " . date(DATE_ATOM) . "\n-- Import nur in eine frische/leere SQLite-Datenbank.\n";
            $dump .= "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n";
            foreach ($tables as $table) {
                $tableName = (string)$table['name'];
                $quotedTable = '"' . str_replace('"', '""', $tableName) . '"';
                $dump .= "\n" . rtrim((string)$table['sql'], ';') . ";\n";
                $columns = $db->query('PRAGMA table_info(' . $quotedTable . ')')->fetchAll();
                $columnNames = array_column($columns, 'name');
                if (!$columnNames) { continue; }
                $quotedColumns = array_map(static fn($name) => '"' . str_replace('"', '""', (string)$name) . '"', $columnNames);
                $rows = $db->query('SELECT * FROM ' . $quotedTable);
                while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                    $values = array_map(static fn($value) => $value === null ? 'NULL' : $db->quote((string)$value), $row);
                    $dump .= 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $values) . ");\n";
                }
            }
            $dump .= "\nCOMMIT;\nPRAGMA foreign_keys=ON;\n";
            header('Content-Type: application/sql; charset=UTF-8');
            header('Content-Disposition: attachment; filename="rcb-backup-' . date('Ymd-His') . '.sql"');
            header('Content-Length: ' . strlen($dump));
            echo $dump;
            exit;
        }
        if ($action === 'logout') {
            log_action($db, $uid, 'logout', 'Abmeldung');
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: ' . $script);
            exit;
        }

        try {
            if ($action === 'save_event') {
                $id = (int)($_POST['id'] ?? 0);
                $title = post_string('title', 180);
                $eventDate = post_string('event_date', 10);
                $endDate = post_string('end_date', 10);
                $startTime = post_string('start_time', 5);
                if ($title === '') { throw new RuntimeException('Bitte einen Veranstaltungstitel eingeben.'); }
                foreach ([$eventDate, $endDate] as $date) {
                    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { throw new RuntimeException('Bitte ein gültiges Datum eingeben.'); }
                }
                if ($endDate !== '' && ($eventDate === '' || $endDate < $eventDate)) { throw new RuntimeException('Das Enddatum muss nach dem ersten Tag liegen.'); }
                if ($endDate === $eventDate) { $endDate = ''; }
                if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) { throw new RuntimeException('Bitte eine gültige Uhrzeit eingeben.'); }
                // Ort: eine unserer Strecken (Reiter „Unsere Strecken“) oder frei eingetragen („Anderer Ort“).
                $trackId = null;
                if (($_POST['location_mode'] ?? 'club') === 'other') {
                    $location = post_string('location_text', 180);
                } else {
                    $selected = (int)($_POST['location_track'] ?? 0);
                    $location = 'Strecken der RC Bande Zwickau';
                    if ($selected > 0) {
                        $stmt = $db->prepare('SELECT name FROM tracks WHERE id = ?');
                        $stmt->execute([$selected]);
                        $trackName = $stmt->fetchColumn();
                        if (is_string($trackName)) {
                            $trackId = $selected;
                            $label = stripos($trackName, 'strecke') !== false ? $trackName : $trackName . '-Strecke';
                            $location = $label . ' der RC Bande Zwickau';
                        }
                    }
                }
                $registration = admin_url_or_empty(post_string('registration_url', 1000), 'Link zur Nennung');
                $announcementUrl = admin_url_or_empty(post_string('announcement_url', 1000), 'Link zur Ausschreibung');
                $resultsUrl = admin_url_or_empty(post_string('results_url', 1000), 'Link zu den Ergebnissen');
                $old = null;
                if ($id > 0) { $stmt = $db->prepare('SELECT announcement_url FROM events WHERE id = ?'); $stmt->execute([$id]); $old = $stmt->fetchColumn(); }
                $oldPdf = is_string($old) && str_starts_with($old, '/uploads/ausschreibungen/') ? $old : '';
                if (!empty($_FILES['announcement_file']['name'])) {
                    $announcementUrl = store_uploaded_pdf($_FILES['announcement_file'], $publicRoot);
                } elseif ($oldPdf !== '' && !isset($_POST['remove_announcement']) && trim((string)($_POST['announcement_url'] ?? '')) === '') {
                    $announcementUrl = $oldPdf; // hochgeladene PDF behalten
                }
                if ($oldPdf !== '' && $announcementUrl !== $oldPdf) { delete_uploaded_pdf($oldPdf, $publicRoot); }
                $classes = [];
                foreach ((array)($_POST['class'] ?? []) as $row) {
                    if (!is_array($row) || trim((string)($row['name'] ?? '')) === '') { continue; }
                    $name = trim((string)$row['name']);
                    $classes[] = ['name' => function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120), 'guest' => !empty($row['guest']), 'url' => admin_url_or_empty((string)($row['url'] ?? ''), 'Nennung für ' . $name)];
                    if (count($classes) >= 20) { break; }
                }
                $values = [$title, post_string('description', 5000), $eventDate !== '' ? $eventDate : null, $endDate !== '' ? $endDate : null, $startTime !== '' ? $startTime : null, $location, $registration, $announcementUrl, post_string('announcement_text', 30000), json_encode($classes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), isset($_POST['published']) ? 1 : 0];
                if ($id > 0) {
                    $db->prepare('UPDATE events SET title=?, description=?, event_date=?, end_date=?, start_time=?, location=?, registration_url=?, announcement_url=?, announcement_text=?, classes_json=?, published=?, track_id=?, results_url=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...array_slice($values, 0, 11), $trackId, $resultsUrl, $id]);
                } else {
                    $db->prepare('INSERT INTO events (title, description, event_date, end_date, start_time, location, registration_url, announcement_url, announcement_text, classes_json, published, track_id, results_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$values, $trackId, $resultsUrl]);
                    $newId = (int)$db->lastInsertId();
                    if ($values[10] === 1 && !in_array('termine', rcb_hidden_pages(admin_setting($db, 'pages_hidden', '[]'), admin_setting($db, 'live_enabled', '0')), true)) {
                        add_site_update($db, 'Neuer Termin: ' . $title . ($eventDate !== '' ? ' am ' . date('d.m.Y', strtotime($eventDate)) : ''), '/termine.php#termin-' . $newId);
                    }
                }
                log_action($db, $uid, 'event_saved', $title);
                redirect_to('events', 'Termin gespeichert');
            }
            if ($action === 'delete_event') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('SELECT announcement_url FROM events WHERE id = ?');
                $stmt->execute([$id]);
                delete_uploaded_pdf((string)$stmt->fetchColumn(), $publicRoot);
                $db->prepare('DELETE FROM events WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'event_deleted', 'Termin-ID ' . $id);
                redirect_to('events', 'Termin gelöscht');
            }
            if ($action === 'save_design') {
                $old = rcb_design_from_json((string)($db->query("SELECT setting_value FROM settings WHERE setting_key='design'")->fetchColumn() ?: ''));
                $new = $old;
                foreach (['red', 'blue'] as $c) {
                    $v = strtolower(post_string($c, 7));
                    if (!preg_match('/^#[0-9a-f]{6}$/', $v)) { throw new RuntimeException('Bitte eine gültige Farbe wählen.'); }
                    $new[$c] = $v;
                }
                $new['font'] = array_key_exists(post_string('font', 20), rcb_design_font_sizes()) ? post_string('font', 20) : 'normal';
                $new['backgrounds'] = [];
                foreach ((array)($_POST['bg'] ?? []) as $pageKey => $type) {
                    $pageKey = (string)$pageKey; $type = (string)$type;
                    if (!preg_match('/^[a-z0-9:_-]{1,80}$/', $pageKey) || !array_key_exists($type, rcb_design_background_types()) || $type === 'standard') { continue; }
                    $image = (string)(($_POST['bgimg'] ?? [])[$pageKey] ?? '');
                    if ($type === 'bild' && !preg_match('#^/uploads/[A-Za-z0-9._%-]+$#', $image)) { continue; }
                    $new['backgrounds'][$pageKey] = ['type' => $type, 'image' => $type === 'bild' ? $image : ''];
                }
                $show = array_map('strval', (array)($_POST['show'] ?? []));
                $new['hidden'] = array_values(array_diff(rcb_design_all_section_ids(), $show));
                admin_set_setting($db, 'design', json_encode($new, JSON_UNESCAPED_SLASHES));
                log_action($db, $uid, 'design_saved', 'Farben ' . $new['red'] . '/' . $new['blue'] . ', Schrift ' . $new['font']);
                redirect_to('design', 'Design gespeichert – die Webseite ist sofort aktualisiert');
            }
            if ($action === 'reset_design') {
                $keep = rcb_design_from_json((string)($db->query("SELECT setting_value FROM settings WHERE setting_key='design'")->fetchColumn() ?: ''));
                $reset = rcb_design_defaults();
                if (post_string('what', 20) !== 'all') { $reset['backgrounds'] = $keep['backgrounds']; $reset['hidden'] = $keep['hidden']; }
                admin_set_setting($db, 'design', json_encode($reset, JSON_UNESCAPED_SLASHES));
                log_action($db, $uid, 'design_reset', post_string('what', 20));
                redirect_to('design', 'Auf die Vereinsfarben zurückgesetzt');
            }
            if ($action === 'save_event_settings') {
                $max = max(0, min(100, (int)($_POST['past_events_max'] ?? 12)));
                $months = max(0, min(120, (int)($_POST['past_events_months'] ?? 12)));
                admin_set_setting($db, 'past_events_max', (string)$max);
                admin_set_setting($db, 'past_events_months', (string)$months);
                log_action($db, $uid, 'event_settings', "Vergangene Termine: max $max, $months Monate");
                redirect_to('events', 'Einstellungen gespeichert');
            }
            if ($action === 'save_notify') {
                $mode = post_string('notify_mode', 20);
                if (!array_key_exists($mode, rcb_notify_modes())) { $mode = ''; }
                if ($mode !== '' && trim((string)($currentUser['email'] ?? '')) === '') { throw new RuntimeException('Für Benachrichtigungen bitte zuerst oben eine E-Mail-Adresse eintragen.'); }
                if ($mode !== '' && (string)($currentUser['notify_mode'] ?? '') === '') {
                    // Beim Einschalten nur künftige Änderungen melden, nicht das ganze bisherige Protokoll.
                    $maxLog = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM logs')->fetchColumn();
                    $db->prepare('UPDATE users SET notify_mode = ?, notify_last_log_id = ?, notify_last_sent = ? WHERE id = ?')->execute([$mode, $maxLog, time(), $uid]);
                } else {
                    $db->prepare('UPDATE users SET notify_mode = ? WHERE id = ?')->execute([$mode, $uid]);
                }
                log_action($db, $uid, 'notify_settings', $mode === '' ? 'aus' : $mode);
                redirect_to('dashboard', $mode === '' ? 'Benachrichtigungen ausgeschaltet' : 'Benachrichtigungen eingeschaltet');
            }
            if ($action === 'save_track') {
                $id = (int)($_POST['id'] ?? 0);
                $name = post_string('name', 180);
                if ($name === '') { throw new RuntimeException('Bitte einen Streckennamen eingeben.'); }
                $status = post_string('status', 20);
                if (!isset(rcb_track_status_labels()[$status])) { $status = 'closed'; }
                $guestMode = post_string('guest_mode', 20);
                if (!array_key_exists($guestMode, rcb_track_guest_modes())) { $guestMode = ''; }
                $equipment = array_values(array_intersect(array_keys(rcb_track_equipment_options()), (array)($_POST['equipment'] ?? [])));
                $facts = ['custom' => [], 'hide' => []];
                $factLabels = (array)($_POST['fact_label'] ?? []); $factValues = (array)($_POST['fact_value'] ?? []); $factShow = (array)($_POST['fact_show'] ?? []);
                foreach (array_slice(array_keys($factLabels), 0, 30) as $i) {
                    $fl = trim(mb_substr((string)$factLabels[$i], 0, 60)); $fv = trim(mb_substr((string)($factValues[$i] ?? ''), 0, 400));
                    if ($fl === '' && $fv === '') { continue; }
                    if ($fl === '') { throw new RuntimeException('Bitte jeder eigenen Steckbrief-Zeile eine Bezeichnung geben.'); }
                    $facts['custom'][] = ['label' => $fl, 'value' => $fv, 'show' => ($factShow[$i] ?? '1') === '1'];
                }
                $showStd = array_map('strval', (array)($_POST['fact_std'] ?? []));
                $facts['hide'] = array_values(array_diff(array_keys(rcb_track_fact_fields()), $showStd));
                $payLinks = [];
                foreach ([1, 2, 3] as $n) {
                    $payUrl = post_string('pay_url_' . $n, 500);
                    if ($payUrl === '') { continue; }
                    if (!str_starts_with(strtolower($payUrl), 'http')) { $payUrl = 'https://' . ltrim($payUrl, '/'); }
                    if (!rcb_track_pay_url_ok($payUrl)) { throw new RuntimeException('Bezahl-Link ' . $n . ': Bitte einen PayPal-Link eintragen (beginnt mit https://paypal.me/ oder https://www.paypal.com/).'); }
                    $payLinks[] = ['label' => post_string('pay_label_' . $n, 80), 'url' => $payUrl];
                }
                $data = [
                    'name' => $name,
                    'surface' => post_string('surface', 100),
                    'length' => post_string('length', 100),
                    'classes' => post_string('classes', 250),
                    'driver_spots' => post_string('driver_spots', 50),
                    'equipment_json' => json_encode($equipment),
                    'equipment_extra' => post_string('equipment_extra', 250),
                    'driving_times' => post_string('driving_times', 2000),
                    'guest_mode' => $guestMode,
                    'guest_note' => post_string('guest_note', 250),
                    'day_fee' => post_string('day_fee', 120),
                    'pay_json' => json_encode($payLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'pay_note' => post_string('pay_note', 250),
                    'facts_json' => json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'description' => post_string('description', 5000),
                    'status' => $status,
                    'status_note' => post_string('status_note', 250),
                    'show_status' => isset($_POST['show_status']) ? 1 : 0,
                    'published' => isset($_POST['published']) ? 1 : 0,
                    // alte Felder mitpflegen
                    'electricity' => in_array('strom', $equipment, true) ? 1 : 0,
                    'toilet' => in_array('toilette', $equipment, true) ? 1 : 0,
                    'paddock' => (in_array('fahrerlager', $equipment, true) || in_array('fahrerlager_ueberdacht', $equipment, true)) ? 1 : 0,
                ];
                $cols = array_keys($data);
                if ($id > 0) {
                    $db->prepare('UPDATE tracks SET ' . implode(', ', array_map(static fn($c) => $c . '=?', $cols)) . ', updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([...array_values($data), $id]);
                } else {
                    $db->prepare('INSERT INTO tracks (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($data));
                }
                log_action($db, $uid, 'track_saved', $name);
                redirect_to('tracks', 'Strecke gespeichert');
            }
            if ($action === 'delete_track') {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare('DELETE FROM tracks WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'track_deleted', 'Strecken-ID ' . $id);
                redirect_to('tracks', 'Strecke gelöscht');
            }
            if ($action === 'save_sponsor') {
                $id = (int)($_POST['id'] ?? 0);
                $name = post_string('name', 180);
                $url = post_string('url', 500);
                $description = post_string('description', 2000);
                $published = isset($_POST['published']) ? 1 : 0;
                if ($name === '') { throw new RuntimeException('Bitte einen Sponsorennamen eingeben.'); }
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) { throw new RuntimeException('Bitte eine gültige URL eingeben.'); }
                if ($id > 0) {
                    $db->prepare('UPDATE sponsors SET name=?, url=?, description=?, published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$name,$url,$description,$published,$id]);
                } else {
                    $db->prepare('INSERT INTO sponsors (name,url,description,published) VALUES (?,?,?,?)')->execute([$name,$url,$description,$published]);
                }
                log_action($db, $uid, 'sponsor_saved', $name);
                redirect_to('sponsors', 'Sponsor gespeichert');
            }
            if ($action === 'delete_sponsor') {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare('DELETE FROM sponsors WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'sponsor_deleted', 'Sponsor-ID ' . $id);
                redirect_to('sponsors', 'Sponsor gelöscht');
            }
            if ($action === 'custom_page_add') {
                $title = post_string('title', 180);
                if ($title === '') { throw new RuntimeException('Bitte einen Titel für die neue Seite eingeben.'); }
                $base = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($title, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue']))), '-');
                $base = substr($base !== '' ? $base : 'seite', 0, 70);
                $slug = $base; $n = 2;
                $exists = $db->prepare('SELECT COUNT(*) FROM pages WHERE slug = ?');
                while (true) { $exists->execute([$slug]); if ((int)$exists->fetchColumn() === 0) { break; } $slug = $base . '-' . $n++; }
                $db->prepare('INSERT INTO pages (slug,title,content,published) VALUES (?,?,?,0)')->execute([$slug, $title, '']);
                if (isset($_POST['in_menu'])) {
                    $menu = admin_custom_menu($db); $menu[] = $slug;
                    admin_set_setting($db, 'nav_custom', json_encode(array_values(array_unique($menu))));
                }
                log_action($db, $uid, 'page_saved', $title);
                redirect_to('pages', 'Seite „' . $title . '“ angelegt – jetzt mit „Bearbeiten“ füllen und anschließend veröffentlichen');
            }
            if ($action === 'custom_page_menu') {
                $slug = post_string('slug', 90);
                $menu = admin_custom_menu($db);
                $inMenu = in_array($slug, $menu, true);
                $menu = $inMenu ? array_values(array_diff($menu, [$slug])) : array_merge($menu, [$slug]);
                admin_set_setting($db, 'nav_custom', json_encode(array_values(array_unique($menu))));
                redirect_to('pages', $inMenu ? 'Seite aus dem Menü genommen' : 'Seite ins Menü aufgenommen');
            }
            if ($action === 'save_page') {
                $id = (int)($_POST['id'] ?? 0);
                $slug = strtolower(post_string('slug', 80));
                $title = post_string('title', 180);
                $content = post_string('content', 20000);
                $published = isset($_POST['published']) ? 1 : 0;
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $title === '') {
                    throw new RuntimeException('Bitte Titel und einen gültigen Seitenpfad (z. B. verein) eingeben.');
                }
                if ($id > 0) {
                    $db->prepare('UPDATE pages SET slug=?, title=?, content=?, published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$slug,$title,$content,$published,$id]);
                } else {
                    $db->prepare('INSERT INTO pages (slug,title,content,published) VALUES (?,?,?,?)')->execute([$slug,$title,$content,$published]);
                }
                log_action($db, $uid, 'page_saved', $title);
                redirect_to('pages', 'Seite gespeichert');
            }
            if ($action === 'delete_page') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('SELECT slug FROM pages WHERE id = ?');
                $stmt->execute([$id]);
                $deletedSlug = $stmt->fetchColumn();
                if (is_string($deletedSlug)) {
                    foreach (['content_blocks', 'content_drafts'] as $table) {
                        $db->prepare("DELETE FROM {$table} WHERE block_key LIKE ?")->execute(['seite.' . $deletedSlug . '.%']);
                    }
                    $db->prepare('DELETE FROM site_updates WHERE url = ?')->execute(['/seite.php?slug=' . rawurlencode($deletedSlug)]);
                    admin_set_setting($db, 'nav_custom', json_encode(array_values(array_diff(admin_custom_menu($db), [$deletedSlug]))));
                }
                $db->prepare('DELETE FROM pages WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'page_deleted', 'Seiten-ID ' . $id);
                redirect_to('pages', 'Seite gelöscht');
            }
            if ($action === 'upload_media') {
                [$filename, $mime] = store_uploaded_image($_FILES['media_file'] ?? null, $publicRoot);
                $uploads = $publicRoot . '/uploads';
                $title = post_string('title', 180);
                $description = post_string('description', 1000);
                $category = post_string('category', 30);
                if (!in_array($category, ['hero','logo','onroad','offroad','event','seite'], true)) {
                    $category = 'onroad';
                }
                $published = isset($_POST['published']) ? 1 : 0;
                try {
                    $db->prepare('INSERT INTO media (filename,title,description,category,mime_type,published) VALUES (?,?,?,?,?,?)')
                       ->execute([$filename, $title, $description, $category, $mime, $published]);
                } catch (Throwable $e) {
                    @unlink($uploads . '/' . $filename);
                    throw $e;
                }
                log_action($db, $uid, 'media_uploaded', $filename);
                redirect_to('media', 'Bild hochgeladen');
            }
            if ($action === 'delete_media') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('SELECT filename FROM media WHERE id=?');
                $stmt->execute([$id]);
                $file = $stmt->fetch();
                if ($file) {
                    $filename = basename((string)$file['filename']);
                    if (preg_match('/^[a-z0-9_-]{1,64}\\.(jpg|png|webp|mp4|webm|mov)$/', $filename)) {
                        @unlink($publicRoot . '/uploads/' . $filename);
                    }
                    $db->prepare('DELETE FROM media WHERE id=?')->execute([$id]);
                    log_action($db, $uid, 'media_deleted', $filename);
                }
                redirect_to('media', 'Datei gelöscht');
            }
            if ($action === 'save_news') {
                $id = (int)($_POST['id'] ?? 0);
                $title = post_string('title', 180);
                $summary = post_string('summary', 500);
                $content = post_string('content', 30000);
                $imageUrl = post_string('image_url', 1000);
                $published = isset($_POST['published']) ? 1 : 0;
                if ($title === '') { throw new RuntimeException('Bitte einen Titel für die Neuigkeit eingeben.'); }
                if ($imageUrl !== '') {
                    $scheme = strtolower((string)parse_url($imageUrl, PHP_URL_SCHEME));
                    if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http','https'], true)) {
                        throw new RuntimeException('Bitte eine gültige Bildadresse mit http oder https eingeben.');
                    }
                }
                // Laufzeit: dauerhaft, 14 Tage, 4 Wochen (jeweils ab heute) oder bis zu einem Datum (einschließlich).
                $expiry = post_string('expiry', 20);
                $expiresAt = null;
                if ($expiry === '14d' || $expiry === '28d') {
                    $expiresAt = date('Y-m-d 23:59:59', strtotime('+' . ($expiry === '14d' ? 14 : 28) . ' days'));
                } elseif ($expiry === 'date') {
                    $until = post_string('expires_on', 10);
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) { throw new RuntimeException('Bitte ein Datum wählen, bis wann die Neuigkeit angezeigt wird.'); }
                    if ($until < date('Y-m-d')) { throw new RuntimeException('Das Ablaufdatum liegt in der Vergangenheit.'); }
                    $expiresAt = $until . ' 23:59:59';
                } elseif ($expiry === 'keep' && $id > 0) {
                    $stmt = $db->prepare('SELECT expires_at FROM news WHERE id = ?'); $stmt->execute([$id]);
                    $expiresAt = ($v = $stmt->fetchColumn()) ? (string)$v : null;
                }
                if ($id > 0) {
                    $db->prepare('UPDATE news SET title=?, summary=?, content=?, image_url=?, published=?, expires_at=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$title,$summary,$content,$imageUrl,$published,$expiresAt,$id]);
                } else {
                    $db->prepare('INSERT INTO news (title,summary,content,image_url,published,expires_at) VALUES (?,?,?,?,?,?)')->execute([$title,$summary,$content,$imageUrl,$published,$expiresAt]);
                }
                log_action($db, $uid, 'news_saved', $title);
                redirect_to('news', 'Neuigkeit gespeichert');
            }
            if ($action === 'delete_news') {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare('DELETE FROM news WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'news_deleted', 'Neuigkeit-ID ' . $id);
                redirect_to('news', 'Neuigkeit gelöscht');
            }
            if ($action === 'save_result') {
                $id = (int)($_POST['id'] ?? 0);
                $title = post_string('title', 180);
                $content = post_string('content', 12000);
                $eventId = (int)($_POST['event_id'] ?? 0);
                $published = isset($_POST['published']) ? 1 : 0;
                if ($title === '') { throw new RuntimeException('Bitte einen Titel für das Ergebnis eingeben.'); }
                if ($eventId > 0) {
                    $check = $db->prepare('SELECT COUNT(*) FROM events WHERE id=?');
                    $check->execute([$eventId]);
                    if ((int)$check->fetchColumn() === 0) { $eventId = 0; }
                }
                if ($id > 0) {
                    $db->prepare('UPDATE results SET event_id=?, title=?, content=?, published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$eventId ?: null,$title,$content,$published,$id]);
                } else {
                    $db->prepare('INSERT INTO results (event_id,title,content,published) VALUES (?,?,?,?)')->execute([$eventId ?: null,$title,$content,$published]);
                    if ($published === 1 && !in_array('ergebnisse', rcb_hidden_pages(admin_setting($db, 'pages_hidden', '[]'), admin_setting($db, 'live_enabled', '0')), true)) {
                        add_site_update($db, 'Neues Ergebnis: ' . $title, '/ergebnisse.php');
                    }
                }
                log_action($db, $uid, 'result_saved', $title);
                redirect_to('results', 'Ergebnis gespeichert');
            }
            if ($action === 'delete_result') {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare('DELETE FROM results WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'result_deleted', 'Ergebnis-ID ' . $id);
                redirect_to('results', 'Ergebnis gelöscht');
            }
            if ($action === 'page_visibility' && str_starts_with((string)($_POST['slug'] ?? ''), 'seite:')) {
                $slug = substr(post_string('slug', 90), 6);
                $stmt = $db->prepare('SELECT title FROM pages WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
                $title = $stmt->fetchColumn();
                if ($title === false) { throw new RuntimeException('Seite nicht gefunden.'); }
                $visible = ($_POST['visible'] ?? '') === '1';
                $db->prepare('UPDATE pages SET published = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?')->execute([$visible ? 1 : 0, $slug]);
                if ($visible) { add_site_update($db, 'Seite online: ' . $title, '/seite.php?slug=' . rawurlencode($slug)); }
                log_action($db, $uid, $visible ? 'page_published' : 'page_hidden', (string)$title);
                redirect_to('pages', $title . ($visible ? ' ist jetzt veröffentlicht' : ' ist jetzt verborgen'));
            }
            if ($action === 'page_visibility') {
                $slug = post_string('slug', 40);
                $pages = rcb_site_pages();
                if (!isset($pages[$slug]) || !$pages[$slug][3]) { throw new RuntimeException('Diese Seite kann nicht verborgen werden.'); }
                $visible = ($_POST['visible'] ?? '') === '1';
                if ($slug === 'live') {
                    admin_set_setting($db, 'live_enabled', $visible ? '1' : '0');
                } else {
                    $hidden = rcb_hidden_pages(admin_setting($db, 'pages_hidden', '[]'), '1');
                    $hidden = $visible ? array_values(array_diff($hidden, [$slug])) : array_values(array_unique(array_merge($hidden, [$slug])));
                    admin_set_setting($db, 'pages_hidden', json_encode($hidden));
                }
                if ($visible) { add_site_update($db, 'Seite online: ' . $pages[$slug][0], $pages[$slug][1]); }
                log_action($db, $uid, $visible ? 'page_published' : 'page_hidden', $pages[$slug][0]);
                redirect_to('pages', $pages[$slug][0] . ($visible ? ' ist jetzt veröffentlicht' : ' ist jetzt verborgen'));
            }
            if ($action === 'page_move') {
                $slug = post_string('slug', 90);
                $order = admin_nav_order($db);
                $pos = array_search($slug, $order, true);
                $target = $pos === false ? false : $pos + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
                if ($pos !== false && $slug !== 'home' && $target >= 1 && $target < count($order)) {
                    [$order[$pos], $order[$target]] = [$order[$target], $order[$pos]];
                    admin_set_setting($db, 'nav_order', json_encode(array_values($order)));
                    log_action($db, $uid, 'nav_order_changed', implode(', ', $order));
                }
                redirect_to('pages', 'Reihenfolge gespeichert');
            }
            if (in_array($action, ['rule_class_add', 'rule_class_move', 'rule_class_toggle', 'rule_class_delete'], true)) {
                $classes = admin_rule_classes($db);
                $key = post_string('key', 41);
                $index = null;
                foreach ($classes as $i => $class) { if ($class['key'] === $key) { $index = $i; } }
                if ($action === 'rule_class_add') {
                    $title = post_string('title', 120);
                    if ($title === '') { throw new RuntimeException('Bitte einen Namen für die Klasse eingeben.'); }
                    $base = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($title, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue']))), '-');
                    $base = substr($base !== '' ? $base : 'klasse', 0, 36);
                    $newKey = $base; $n = 2;
                    $taken = array_merge(array_column($classes, 'key'), array_map('strval', array_keys(rcb_default_rule_classes())), ['allgemein', 'intro', 'funcup', 'klassen']);
                    while (in_array($newKey, $taken, true)) { $newKey = $base . '-' . $n++; }
                    $classes[] = ['key' => $newKey, 'title' => $title, 'published' => false];
                    admin_save_rule_classes($db, $classes);
                    log_action($db, $uid, 'rule_class_added', $title);
                    redirect_to('reglement', 'Klasse „' . $title . '“ angelegt – jetzt Regelwerk bearbeiten und anschließend veröffentlichen');
                }
                if ($index === null) { throw new RuntimeException('Klasse nicht gefunden.'); }
                $label = admin_rule_class_title($db, $classes[$index]);
                if ($action === 'rule_class_move') {
                    $target = $index + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
                    if ($target >= 0 && $target < count($classes)) { [$classes[$index], $classes[$target]] = [$classes[$target], $classes[$index]]; }
                    admin_save_rule_classes($db, $classes);
                    redirect_to('reglement', 'Reihenfolge der Klassen gespeichert');
                }
                if ($action === 'rule_class_toggle') {
                    $classes[$index]['published'] = !$classes[$index]['published'];
                    admin_save_rule_classes($db, $classes);
                    if ($classes[$index]['published']) { add_site_update($db, 'Reglement: ' . $label, '/reglement.php?klasse=' . rawurlencode($key)); }
                    log_action($db, $uid, $classes[$index]['published'] ? 'rule_class_published' : 'rule_class_hidden', $label);
                    redirect_to('reglement', 'Klasse „' . $label . '“ ist jetzt ' . ($classes[$index]['published'] ? 'veröffentlicht' : 'verborgen'));
                }
                // Löschen: Klasse aus der Liste entfernen und ihre Texte (auch Entwürfe) entfernen.
                array_splice($classes, $index, 1);
                admin_save_rule_classes($db, $classes);
                foreach (['content_blocks', 'content_drafts'] as $table) {
                    $db->prepare("DELETE FROM {$table} WHERE block_key = ? OR block_key LIKE ?")->execute(['reglement.' . $key, 'reglement.' . $key . '.%']);
                }
                $db->prepare('DELETE FROM site_updates WHERE url = ?')->execute(['/reglement.php?klasse=' . rawurlencode($key)]);
                log_action($db, $uid, 'rule_class_deleted', $label);
                redirect_to('reglement', 'Klasse „' . $label . '“ gelöscht');
            }
            if ($action === 'clear_site_updates') {
                $db->exec('DELETE FROM site_updates');
                log_action($db, $uid, 'site_updates_cleared', 'Alle automatischen Hinweise entfernt');
                redirect_to('news', 'Alle automatischen Hinweise entfernt');
            }
            if ($action === 'toggle_auto_updates') {
                $enabled = admin_setting($db, 'auto_updates_enabled', '1') === '1';
                admin_set_setting($db, 'auto_updates_enabled', $enabled ? '0' : '1');
                log_action($db, $uid, 'auto_updates_toggled', $enabled ? 'ausgeschaltet' : 'eingeschaltet');
                redirect_to('news', $enabled ? 'Automatische Hinweise ausgeschaltet' : 'Automatische Hinweise eingeschaltet');
            }
            if ($action === 'delete_site_update') {
                $db->prepare('DELETE FROM site_updates WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
                redirect_to('news', 'Hinweis entfernt');
            }
            if ($action === 'save_settings') {
                $allowed = ['site_name','site_description','contact_email','contact_phone','site_enabled','live_enabled','home_news_count','home_events_count','auto_updates_days'];
                $stmt = $db->prepare(Database::isMysql()
                    ? 'INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=CURRENT_TIMESTAMP'
                    : 'INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value, updated_at=CURRENT_TIMESTAMP');
                foreach ($allowed as $key) {
                    $value = in_array($key, ['site_enabled','live_enabled'], true) ? (isset($_POST[$key]) ? '1' : '0') : post_string($key, $key === 'site_description' ? 1000 : 190);
                    if (in_array($key, ['home_news_count', 'home_events_count'], true)) {
                        if (!preg_match('/^\d{1,2}$/', $value) || (int)$value > 20) { throw new RuntimeException('Die Anzahl auf der Startseite muss zwischen 0 und 20 liegen.'); }
                        $value = (string)(int)$value;
                    }
                    if ($key === 'auto_updates_days') {
                        if (!preg_match('/^\d{1,3}$/', $value) || (int)$value > 365) { throw new RuntimeException('Die Anzeigedauer muss zwischen 0 und 365 Tagen liegen.'); }
                        $value = (string)(int)$value;
                    }
                    if ($key === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Bitte eine gültige Kontakt-E-Mail eingeben.'); }
                    $stmt->execute([$key, $value]);
                }
                log_action($db, $uid, 'settings_saved', 'Seiteneinstellungen geändert');
                redirect_to('settings', 'Einstellungen gespeichert');
            }
            if (in_array($action, ['file_upload', 'file_delete', 'file_toggle', 'file_move'], true)) {
                $targets = admin_file_targets($db, admin_editor_scope($currentUser) === 'reglement');
                if ($action === 'file_upload') {
                    $pageKey = post_string('page_key', 120);
                    if (!isset($targets[$pageKey])) { throw new RuntimeException('Bitte eine Seite auswählen.'); }
                    $title = post_string('title', 190);
                    if ($title === '') { $title = trim(preg_replace('/[_-]+/', ' ', pathinfo((string)($_FILES['document']['name'] ?? ''), PATHINFO_FILENAME))); }
                    if ($title === '') { throw new RuntimeException('Bitte einen Titel eintragen.'); }
                    [$path, $size] = store_uploaded_document($_FILES['document'] ?? [], $publicRoot);
                    $next = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM files WHERE page_key = ?');
                    $next->execute([$pageKey]);
                    $db->prepare('INSERT INTO files (title, description, path, size, page_key, sort_order, published) VALUES (?, ?, ?, ?, ?, ?, 1)')
                       ->execute([$title, post_string('description', 500), $path, $size, $pageKey, (int)$next->fetchColumn()]);
                    log_action($db, $uid, 'file_uploaded', $title . ' → ' . $targets[$pageKey]);
                    $pageLabel = $targets[$pageKey];
                    if (str_starts_with($pageKey, 'reglement:')) { $url = '/reglement.php?klasse=' . rawurlencode(substr($pageKey, 10)); }
                    elseif (str_starts_with($pageKey, 'seite:')) { $url = '/seite.php?slug=' . rawurlencode(substr($pageKey, 6)); }
                    else { $url = rcb_site_pages()[$pageKey][1] ?? '/'; }
                    add_site_update($db, 'Neue Datei: ' . $title, $url);
                    redirect_to('files', '„' . $title . '“ hochgeladen – steht jetzt auf „' . $pageLabel . '“');
                }
                $stmt = $db->prepare('SELECT * FROM files WHERE id = ? LIMIT 1');
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $fileRow = $stmt->fetch();
                if (!$fileRow || !isset($targets[(string)$fileRow['page_key']]) && admin_editor_scope($currentUser) === 'reglement') { throw new RuntimeException('Datei nicht gefunden.'); }
                if ($action === 'file_delete') {
                    $db->prepare('DELETE FROM files WHERE id = ?')->execute([(int)$fileRow['id']]);
                    if (preg_match('#^/uploads/dateien/[a-f0-9]{32}\.[a-z]{3,4}$#', (string)$fileRow['path'])) { @unlink($publicRoot . $fileRow['path']); }
                    log_action($db, $uid, 'file_deleted', (string)$fileRow['title']);
                    redirect_to('files', '„' . $fileRow['title'] . '“ gelöscht');
                }
                if ($action === 'file_toggle') {
                    $db->prepare('UPDATE files SET published = CASE WHEN published = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([(int)$fileRow['id']]);
                    redirect_to('files', (int)$fileRow['published'] === 1 ? '„' . $fileRow['title'] . '“ verborgen' : '„' . $fileRow['title'] . '“ wieder sichtbar');
                }
                // Reihenfolge innerhalb der Seite tauschen
                $siblings = $db->prepare('SELECT id FROM files WHERE page_key = ? ORDER BY sort_order, title');
                $siblings->execute([(string)$fileRow['page_key']]);
                $ids = array_map('intval', $siblings->fetchAll(PDO::FETCH_COLUMN));
                $pos = array_search((int)$fileRow['id'], $ids, true);
                $target = $pos + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
                if ($pos !== false && $target >= 0 && $target < count($ids)) { [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]]; }
                $upd = $db->prepare('UPDATE files SET sort_order = ? WHERE id = ?');
                foreach ($ids as $i => $id) { $upd->execute([$i + 1, $id]); }
                redirect_to('files', 'Reihenfolge gespeichert');
            }
            if ($action === 'save_membership') {
                $config = rcb_membership_config_from_json(admin_setting($db, 'membership_config', ''));
                foreach ($config as $flag => $value) { if (is_bool($value)) { $config[$flag] = !empty($_POST[$flag]); } }
                foreach (['monthly_label' => 190, 'bank_holder' => 190, 'bank_iban' => 60, 'bank_bic' => 30, 'bank_purpose' => 300, 'print_footer' => 300] as $key => $max) { $config[$key] = post_string($key, $max); }
                foreach (['fee_note', 'rules_note', 'accept_text', 'guardian_text'] as $key) { $config[$key] = mb_substr(trim(str_replace("\r", '', (string)($_POST[$key] ?? ''))), 0, 1500); }
                // Beitragsstufen: vorhandene Zeilen (ggf. entfernt), neue Zeilen, Reihenfolge
                $feeRows = [];
                foreach ((array)($_POST['fee_label'] ?? []) as $i => $label) {
                    $label = trim(mb_substr((string)$label, 0, 190));
                    if ($label === '' || !empty($_POST['fee_remove'][$i])) { continue; }
                    $price = trim(str_replace(['€', ' '], '', (string)($_POST['fee_price'][$i] ?? '0')));
                    if (!preg_match('/^\d{1,4}([.,]\d{1,2})?$/', $price)) { throw new RuntimeException('Bitte bei „' . $label . '“ einen gültigen Preis eintragen (z. B. 90 oder 7,50).'); }
                    $id = preg_replace('/[^a-z0-9]/', '', (string)($_POST['fee_id'][$i] ?? ''));
                    $feeRows[] = ['id' => $id !== '' ? $id : 'f' . bin2hex(random_bytes(4)), 'label' => $label, 'price' => str_replace('.', ',', $price), 'visible' => !empty($_POST['fee_visible'][$i]), 'sort' => (int)($_POST['fee_sort'][$i] ?? 99)];
                }
                usort($feeRows, static fn($a, $b) => $a['sort'] <=> $b['sort']);
                $config['fees'] = array_map(static fn($f) => ['id' => $f['id'], 'label' => $f['label'], 'price' => $f['price'], 'visible' => $f['visible']], $feeRows);
                $monthlyFee = preg_replace('/[^a-z0-9]/', '', post_string('monthly_fee', 40));
                $config['monthly_fee'] = in_array($monthlyFee, array_column($config['fees'], 'id'), true) ? $monthlyFee : '';
                // Vereinsordnungen
                $ruleRows = [];
                foreach ((array)($_POST['rule_name'] ?? []) as $i => $name) {
                    $name = trim(mb_substr((string)$name, 0, 120));
                    if ($name === '' || !empty($_POST['rule_remove'][$i])) { continue; }
                    $ruleRows[] = ['name' => $name, 'visible' => !empty($_POST['rule_visible'][$i]), 'sort' => (int)($_POST['rule_sort'][$i] ?? 99)];
                }
                usort($ruleRows, static fn($a, $b) => $a['sort'] <=> $b['sort']);
                $config['rules'] = array_map(static fn($r) => ['name' => $r['name'], 'visible' => $r['visible']], $ruleRows);
                admin_set_setting($db, 'membership_config', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                log_action($db, $uid, 'membership_config', 'Mitgliedschaft gespeichert' . ($config['form_enabled'] ? '' : ' (Online-Antrag ausgeschaltet)'));
                redirect_to('membership', 'Gespeichert – die Seite „Mitglied werden“ ist aktualisiert');
            }
            if ($action === 'request_status' || $action === 'request_delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($action === 'request_delete') {
                    $db->prepare('DELETE FROM membership_requests WHERE id = ?')->execute([$id]);
                    log_action($db, $uid, 'membership_request_deleted', 'Anfrage-ID ' . $id);
                    redirect_to('requests', 'Anfrage gelöscht');
                }
                $db->prepare("UPDATE membership_requests SET status = CASE WHEN status = 'neu' THEN 'erledigt' ELSE 'neu' END WHERE id = ?")->execute([$id]);
                redirect_to('requests', 'Status geändert');
            }
            if ($action === 'update_own_account') {
                $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$uid]);
                if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$stmt->fetchColumn())) { throw new RuntimeException('Das aktuelle Passwort stimmt nicht.'); }
                $email = post_string('email', 190);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.'); }
                if (admin_email_taken($db, $email, $uid)) { throw new RuntimeException('Diese E-Mail-Adresse gehört schon zu einem anderen Zugang.'); }
                $displayName = post_string('display_name', 190);
                if ($displayName === '') { throw new RuntimeException('Bitte deinen Namen eintragen.'); }
                $db->prepare('UPDATE users SET email = ?, display_name = ? WHERE id = ?')->execute([$email, $displayName, $uid]);
                $newPassword = (string)($_POST['password'] ?? '');
                if ($newPassword !== '') {
                    admin_check_new_password($newPassword, (string)($_POST['password_confirm'] ?? ''));
                    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $uid]);
                }
                log_action($db, $uid, 'own_account_updated', $newPassword !== '' ? 'E-Mail/Passwort geändert' : 'E-Mail geändert');
                redirect_to('dashboard', $newPassword !== '' ? 'E-Mail und Passwort gespeichert' : 'E-Mail gespeichert');
            }
            if (in_array($action, ['create_invite', 'create_reset_link', 'revoke_token', 'update_user'], true) && ($currentUser['role'] ?? '') !== 'superadmin') {
                throw new RuntimeException('Nur der Hauptadmin darf Zugänge verwalten.');
            }
            if ($action === 'update_user') {
                $stmt = $db->prepare('SELECT id, username, role FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $target = $stmt->fetch();
                if (!$target) { throw new RuntimeException('Benutzer nicht gefunden.'); }
                $displayName = post_string('display_name', 190);
                if ($displayName === '') { throw new RuntimeException('Bitte einen Namen eintragen.'); }
                if ($target['role'] === 'superadmin') {
                    $db->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([$displayName, (int)$target['id']]);
                } else {
                    $permissions = array_values(array_intersect((array)($_POST['permissions'] ?? []), array_keys(admin_permission_labels())));
                    if (!$permissions) { throw new RuntimeException('Bitte mindestens einen Bereich freigeben – oder den Zugang deaktivieren.'); }
                    $db->prepare('UPDATE users SET display_name = ?, permissions = ? WHERE id = ?')->execute([$displayName, json_encode($permissions, JSON_THROW_ON_ERROR), (int)$target['id']]);
                }
                log_action($db, $uid, 'user_updated', $target['username'] . ': ' . $displayName . (isset($permissions) ? ' – ' . implode(', ', $permissions) : ''));
                redirect_to('users', 'Zugang von ' . $displayName . ' gespeichert');
            }
            if ($action === 'create_invite') {
                $permissions = array_values(array_intersect((array)($_POST['permissions'] ?? []), array_keys(admin_permission_labels())));
                if (!$permissions) { throw new RuntimeException('Bitte mindestens einen freizugebenden Bereich auswählen.'); }
                $days = (int)($_POST['days'] ?? 7);
                if (!in_array($days, [1, 3, 7, 14], true)) { $days = 7; }
                $note = post_string('note', 190);
                if ($note === '') { throw new RuntimeException('Bitte den Namen der eingeladenen Person eintragen.'); }
                $token = admin_create_token($db, 'invite', null, $permissions, $note, $days * 86400, $uid);
                $_SESSION['token_link'] = ['kind' => 'invite', 'url' => admin_token_url($script, 'invite', $token), 'label' => $note, 'expires' => time() + $days * 86400];
                log_action($db, $uid, 'invite_created', ($note !== '' ? $note . ': ' : '') . implode(', ', $permissions));
                redirect_to('users', 'Einladungslink erstellt');
            }
            if ($action === 'create_reset_link') {
                $stmt = $db->prepare('SELECT id, username, display_name FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $target = $stmt->fetch();
                if (!$target) { throw new RuntimeException('Benutzer nicht gefunden.'); }
                $token = admin_create_token($db, 'reset', (int)$target['id'], [], 'für ' . $target['username'], 86400, $uid);
                $_SESSION['token_link'] = ['kind' => 'reset', 'url' => admin_token_url($script, 'reset', $token), 'label' => admin_user_label($target), 'expires' => time() + 86400];
                log_action($db, $uid, 'reset_link_created', (string)$target['username']);
                redirect_to('users', 'Link zum Passwort-Zurücksetzen erstellt');
            }
            if ($action === 'revoke_token') {
                $db->prepare('DELETE FROM admin_tokens WHERE id = ? AND used_at IS NULL')->execute([(int)($_POST['id'] ?? 0)]);
                log_action($db, $uid, 'token_revoked', 'Link-ID ' . (int)($_POST['id'] ?? 0));
                redirect_to('users', 'Link widerrufen – er funktioniert nicht mehr');
            }
            if ($action === 'toggle_user') {
                if (($currentUser['role'] ?? '') !== 'superadmin') { throw new RuntimeException('Nur der Hauptadmin darf Benutzer verwalten.'); }
                $target = (int)($_POST['id'] ?? 0);
                if ($target === $uid) { throw new RuntimeException('Das eigene Konto kann hier nicht deaktiviert werden.'); }
                $db->prepare('UPDATE users SET active=CASE WHEN active=1 THEN 0 ELSE 1 END WHERE id=?')->execute([$target]);
                log_action($db, $uid, 'user_toggled', 'Benutzer-ID ' . $target);
                redirect_to('users', 'Benutzerstatus geändert');
            }
            if ($action === 'delete_user') {
                if (($currentUser['role'] ?? '') !== 'superadmin') { throw new RuntimeException('Nur der Hauptadmin darf Benutzer verwalten.'); }
                $target = (int)($_POST['id'] ?? 0);
                if ($target === $uid) { throw new RuntimeException('Das eigene Konto kann nicht gelöscht werden.'); }
                $db->prepare('DELETE FROM users WHERE id=?')->execute([$target]);
                $db->prepare('DELETE FROM admin_tokens WHERE user_id=?')->execute([$target]);
                log_action($db, $uid, 'user_deleted', 'Benutzer-ID ' . $target);
                redirect_to('users', 'Benutzer gelöscht');
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('RCB admin action error: ' . $e->getMessage());
            $error = 'Die Änderung konnte nicht gespeichert werden. Bitte Eingaben prüfen; eventuell ist ein Eintrag bereits vorhanden.';
        } catch (Throwable $e) {
            error_log('RCB admin error: ' . $e->getMessage());
            $error = 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen.';
        }
    }
}

$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$setupMode = $userCount === 0;
$loggedIn = !empty($_SESSION['admin_user_id']);
if ($loggedIn) {
    $stmt = $db->prepare('SELECT id, username, email, display_name, role, permissions, active, notify_mode FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['admin_user_id']]);
    $currentUser = $stmt->fetch();
    if (!$currentUser || (int)$currentUser['active'] !== 1) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
    } else {
        $_SESSION['admin_username'] = $currentUser['username'];
        if (!in_array($page, ['dashboard','events','tracks','reglement','pages','files','sponsors','media','results','news','requests','membership','backup','settings','users','logs','design'], true)) { $page = 'dashboard'; }
        if (!admin_can($currentUser, admin_area_for_page($page))) { http_response_code(403); exit('Für diesen Bereich hast du keine Berechtigung.'); }
    }
}
// Einmal-Links: Einladung annehmen / neues Passwort setzen (funktioniert angemeldet und abgemeldet).
$tokenView = null;
$tokenRow = null;
$tokenUser = null;
if (!$setupMode && isset($_GET['einladung'])) {
    $tokenView = 'invite';
    $tokenRow = admin_find_token($db, (string)$_GET['einladung'], 'invite');
} elseif (!$setupMode && isset($_GET['passwort'])) {
    $tokenView = 'reset';
    $tokenRow = admin_find_token($db, (string)$_GET['passwort'], 'reset');
    if ($tokenRow) {
        $stmt = $db->prepare('SELECT id, username, active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$tokenRow['user_id']]);
        $tokenUser = $stmt->fetch() ?: null;
        if (!$tokenUser || (int)$tokenUser['active'] !== 1) { $tokenRow = null; }
    }
}
$forgotView = !$setupMode && !$loggedIn && isset($_GET['vergessen']);
$recoveryAvailable = strlen($initialSetupKey) >= 10 && !str_starts_with($initialSetupKey, 'REPLACE_WITH_');
function fetch_one(PDO $db, string $table, int $id): ?array {
    $allowed = ['events','tracks','sponsors','pages','news'];
    if (!in_array($table, $allowed, true) || $id < 1) return null;
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
$editId = (int)($_GET['edit'] ?? 0);
$editItem = null;
if ($loggedIn && $editId > 0) {
    $tableForEdit = ['events'=>'events','tracks'=>'tracks','sponsors'=>'sponsors','pages'=>'pages','results'=>'results','news'=>'news'][$page] ?? '';
    if ($tableForEdit !== '') $editItem = fetch_one($db, $tableForEdit, $editId);
}
$counts = [];
if ($loggedIn) {
    foreach (['events'=>'Termine','tracks'=>'Strecken','sponsors'=>'Sponsoren','pages'=>'Seiten','users'=>'Benutzer','media'=>'Bilder','results'=>'Ergebnisse','news'=>'Neuigkeiten'] as $table=>$label) {
        $counts[$table] = (int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $loggedIn ? 'Admin – ' . h($currentUser['username']) : 'Admin-Anmeldung' ?> | RC Bande Zwickau</title>
<style>

.color-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px}.color-row input[type=color]{width:64px;height:40px;padding:2px;border-radius:8px;cursor:pointer}.design-preview{margin:14px 0 6px;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#f3f6f9;color:#1d2733}.dp-head{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--p-blue);color:#fff;font-weight:700}.dp-head span,.dp-btn{padding:5px 10px;border-radius:6px;background:var(--p-red);color:#fff;font-size:.85rem}.dp-body{padding:12px 14px}.dp-body strong{color:var(--p-blue);font-size:1.15rem}.dp-btn{display:inline-block;font-weight:700}.section-groups{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}.design-form .table select{min-width:180px}.pay-row{display:grid;gap:4px;margin:6px 0 4px;padding:10px 12px;border:1px solid var(--line);border-radius:10px}.fact-row{display:grid;grid-template-columns:1fr auto;gap:6px;align-items:center;margin:8px 0;padding:10px;border:1px solid var(--line);border-radius:10px}.fact-row .fact-show{grid-column:1;grid-row:1;margin:0;white-space:nowrap;font-size:.85rem}.fact-btns{grid-column:2;grid-row:1;display:flex;gap:4px}.fact-btns button{padding:6px 10px;min-width:0}.fact-row input[name^=fact_]{grid-column:1/-1}.expiry-chips{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}.expiry-chips label{margin:0;cursor:pointer}.expiry-chips input{position:absolute;opacity:0;pointer-events:none}.expiry-chips span{display:block;padding:10px 12px;border:2px solid var(--line);border-radius:12px;font-weight:700;text-align:center;transition:.15s}.expiry-chips small{display:block;font-weight:500;font-size:.78rem;opacity:.75;margin-top:2px}.expiry-chips input:checked+span{border-color:#c9272c;background:rgba(201,39,44,.12)}.expiry-chips input:focus-visible+span{outline:2px solid #c9272c;outline-offset:2px}.expiry-date{margin-top:10px}.expiry:not(.show-date) .expiry-date{display:none}.badge.warn{background:#fff1d6;color:#8a5a00}.nav-group{margin:16px 14px 4px;font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#7d8594}.nav-badge{display:inline-block;min-width:20px;padding:1px 6px;margin-left:4px;border-radius:999px;background:#c9272c;color:#fff;font-size:.75rem;font-weight:800;text-align:center}fieldset .check-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:2px 12px}td.actions form{display:inline-block;margin:2px 4px 2px 0}table.table td.actions{white-space:normal;min-width:180px}.membership-form textarea{min-height:0;margin:0 0 10px 30px;width:calc(100% - 30px)}.membership-form>.columns input[name=print_footer]{margin-left:30px;width:calc(100% - 30px)}.toggle-row{display:flex;align-items:flex-start;gap:10px;margin:12px 0 6px}.toggle-row input{margin-top:3px}.master-toggle{border-color:#78dba9}.master-toggle.is-off{border-color:var(--amber)}.master-toggle .toggle-row{margin:0;font-size:1.08rem}.sub-block{margin-top:16px;padding-top:4px;border-top:1px solid var(--line)}.table tr.is-hidden-row td{opacity:.55}.big-check{font-size:1.05rem;margin:4px 0}.table tr.new-row td{background:rgba(120,219,169,.06)}.plain-list{margin:0 0 12px;padding-left:18px}.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:760px){.form-row-2{grid-template-columns:1fr}}.sticky-save{position:sticky;bottom:0;z-index:5;margin-top:22px;padding:14px 0;background:var(--bg,#f4f6f8);align-items:center;gap:16px}.request-card{margin-bottom:16px}.request-card.is-new{border-color:#78dba9}.request-data{display:grid;grid-template-columns:max-content 1fr;gap:6px 16px;margin:14px 0 0}.request-data dt{color:var(--muted)}.request-data dd{margin:0;overflow-wrap:anywhere}.link-flash{border-color:#78dba9;margin-bottom:22px}.link-field{flex:1;min-width:260px;font-family:ui-monospace,monospace;font-size:13px}.recovery{margin:18px 0 10px;padding-top:12px;border-top:1px solid var(--line)}.recovery summary{cursor:pointer;color:var(--muted)}.draft-banner{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px;border-color:#f6c76b}.draft-banner h2{margin:0 0 4px}.draft-banner form{display:inline}.loc-tabs{display:flex;flex-wrap:wrap;align-items:center;gap:0;margin:4px 0}.loc-title{width:100%;margin-bottom:6px;font-weight:700}.loc-tabs>input[type=radio]{position:absolute;opacity:0;pointer-events:none}.loc-tabs>label{padding:8px 14px;border:1px solid var(--line);background:transparent;cursor:pointer;font-weight:600;margin:0}.loc-tabs>label[for=loc-club]{border-radius:8px 0 0 8px}.loc-tabs>label[for=loc-other]{border-radius:0 8px 8px 0;border-left:0}.loc-tabs>input:checked+label{background:#d71920;border-color:#d71920;color:#fff}.loc-tabs>input:focus-visible+label{outline:2px solid #fff;outline-offset:2px}.loc-panel{display:none;width:100%;margin-top:10px}#loc-club:checked~.loc-panel-club,#loc-other:checked~.loc-panel-other{display:block}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}.card-head-row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:10px}.card-head-row form{display:inline}.columns.events-columns{grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)}fieldset{margin:6px 0;padding:12px 14px 14px;border:1px solid var(--line);border-radius:10px}legend{padding:0 6px;font-weight:700}.field-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.class-row{display:grid;grid-template-columns:auto minmax(0,1fr);gap:6px 10px;align-items:center;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--line)}.class-row>input:first-child{grid-column:1/-1}.class-row .check{margin:0;white-space:nowrap}@media(max-width:1100px){.columns.events-columns{grid-template-columns:1fr}}@media(max-width:640px){.field-row,.class-row{grid-template-columns:1fr}}.page-group .page-preview-body{padding:16px}.group-children{margin:8px 0 12px;padding-left:18px}.group-children li{margin:6px 0}.group-children a{margin-left:6px}.page-preview.is-hidden{opacity:.75;border-style:dashed}.page-preview-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.page-pos{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#30323a;font-size:12px;font-weight:700}.page-preview-body .form-actions{flex-wrap:wrap;gap:6px}.page-preview-body form,.actions form{display:inline}.move-buttons{display:inline-flex;gap:4px;margin-left:auto}button.small{padding:6px 10px;min-width:34px}.inline-add{margin-top:16px}.inline-add .form-actions input{flex:1;min-width:200px}.page-previews{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}.page-preview{overflow:hidden;border:1px solid var(--line);border-radius:12px;background:var(--panel)}.page-preview-frame{position:relative;height:190px;overflow:hidden;background:#fff}.page-preview-frame iframe{position:absolute;top:0;left:0;width:500%;height:500%;border:0;transform:scale(.2);transform-origin:0 0;pointer-events:none}.page-preview-body{padding:12px 14px}.page-preview-body .form-actions{margin-top:10px}.media-item video{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;background:#000}.media-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.media-item{overflow:hidden;border:1px solid var(--line);border-radius:10px;background:var(--panel2)}.media-item img{display:block;width:100%;height:160px;object-fit:cover;background:#111}.media-item-body{padding:12px}.media-item-body form{margin-top:10px}.media-item-body .hint{overflow-wrap:anywhere}
:root{color-scheme:dark;--bg:#101114;--panel:#191b20;--panel2:#202229;--text:#f4f4f5;--muted:#a1a1aa;--line:#34363d;--red:#d71920;--red2:#b8141a;--green:#78dba9;--amber:#f6c76b}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a{color:#ff8c91;text-decoration:none}a:hover{text-decoration:underline}button,input,textarea,select{font:inherit}button,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 15px;border:0;border-radius:9px;background:var(--red);color:white;font-weight:750;cursor:pointer;text-decoration:none}button:hover,.button:hover{background:var(--red2);text-decoration:none}.button.secondary,button.secondary{background:#30323a;color:var(--text)}.button.danger,button.danger{background:#702126}.wrap{width:min(1400px,100%);margin:auto;padding:24px}.login-wrap{min-height:100vh;display:grid;place-items:center;padding:22px;background:radial-gradient(ellipse at top,rgba(215,25,32,.18),transparent 48%),var(--bg)}.login-card{width:min(100%,480px);padding:clamp(24px,5vw,42px);background:var(--panel);border:1px solid var(--line);border-radius:18px;box-shadow:0 24px 70px #0006}.brand-mark{display:grid;place-items:center;width:58px;height:58px;margin-bottom:20px;border-radius:14px;background:var(--red);font-weight:950;font-size:20px}.eyebrow{margin:0 0 6px;color:#ff8c91;font-size:12px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}h1,h2,h3{line-height:1.2}h1{font-size:clamp(27px,4vw,36px);margin:0 0 10px}h2{font-size:20px;margin:0 0 12px}h3{font-size:16px;margin:0 0 10px}.muted,.intro{color:var(--muted)}.intro{margin:0 0 25px}form.stack{display:grid;gap:10px}label{display:block;font-size:13px;font-weight:750;margin:8px 0 5px}input,textarea,select{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:9px;background:#101114;color:var(--text);outline:none}textarea{min-height:140px;resize:vertical}input:focus,textarea:focus,select:focus{border-color:var(--red);box-shadow:0 0 0 3px #d7192030}.check{display:flex;align-items:center;gap:9px;margin:12px 0;color:#dedee2}.check input{width:auto}.alert{margin:0 0 18px;padding:12px 14px;border:1px solid #7c3439;border-radius:9px;background:#381b1e;color:#ffdadd}.notice{margin:0 0 18px;padding:12px 14px;border:1px solid #286746;border-radius:9px;background:#142a20;color:#baf3d2}.admin-top{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 24px;border-bottom:1px solid var(--line);background:#15161a;position:sticky;top:0;z-index:5}.brand{display:flex;align-items:center;gap:12px;font-weight:850}.brand .brand-mark{width:42px;height:42px;margin:0;border-radius:10px;font-size:15px}.top-actions{display:flex;align-items:center;gap:12px}.shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:calc(100vh - 75px)}.sidebar{padding:20px 14px;border-right:1px solid var(--line);background:#15161a}.nav-label{padding:10px 12px;color:#777983;font-size:11px;letter-spacing:.12em;font-weight:850;text-transform:uppercase}.nav{display:grid;gap:4px}.nav a{display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:9px;color:#d4d4d8}.nav a.active,.nav a:hover{background:#2a1a1d;color:#fff;text-decoration:none}.nav a.active{box-shadow:inset 3px 0 var(--red)}main.content{min-width:0;padding:clamp(18px,3vw,36px)}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:24px}.page-head p{margin:0;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card{min-width:0;padding:19px;border:1px solid var(--line);border-radius:13px;background:var(--panel)}.stat{font-size:30px;font-weight:850;letter-spacing:-.04em}.stat-label{color:var(--muted);font-size:13px}.tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:20px}.tile{display:block;padding:20px;border:1px solid var(--line);border-radius:13px;background:var(--panel);color:var(--text)}.tile:hover{background:var(--panel2);border-color:#565963;text-decoration:none}.tile-icon{font-size:25px}.tile p{margin:5px 0 0;color:var(--muted);font-size:13px}.columns{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:18px;align-items:start}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;font-size:14px}.table th,.table td{padding:11px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.table th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em}.table td.actions{white-space:nowrap}.table form{display:inline}.table .button,.table button{padding:6px 9px;font-size:12px}.badge{display:inline-block;padding:3px 8px;border-radius:20px;background:#30323a;color:#ddd;font-size:12px}.badge.yes{background:#183c2a;color:#a8f0c5}.badge.no{background:#3c2828;color:#f3b1b1}.form-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.empty{padding:22px;color:var(--muted);text-align:center}.hint{color:var(--muted);font-size:12px}.footer{padding:20px 0;color:#777983;font-size:12px}@media(max-width:1000px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.columns{grid-template-columns:1fr}}@media(max-width:720px){.admin-top{padding:12px 15px}.top-actions .user-label{display:none}.shell{grid-template-columns:1fr}.sidebar{padding:10px;border-right:0;border-bottom:1px solid var(--line);overflow:auto}.nav{display:flex;gap:5px;width:max-content}.nav-label{display:none}.nav a{white-space:nowrap;padding:8px 10px;font-size:13px}.wrap{padding:14px}main.content{padding:20px 14px}.tiles{grid-template-columns:1fr 1fr}.page-head{display:block}.page-head .button{margin-top:14px}}@media(max-width:440px){.grid,.tiles{grid-template-columns:1fr}.login-card{padding:24px}}
.password-field{position:relative;display:flex;align-items:center;width:100%}.password-field input{padding-right:98px}.password-toggle{position:absolute;right:6px;top:50%;transform:translateY(-50%);min-height:34px;margin:0;padding:6px 9px;border:1px solid var(--line);background:#30323a;color:var(--text);font-size:13px;line-height:1.2}.password-toggle:hover{background:#454854}.password-toggle:focus-visible{outline:2px solid #fff;outline-offset:2px}
</style>
<script src="/assets/js/password-toggle.js" defer></script>
<script src="/assets/js/admin.js" defer></script>
</head>
<body>
<?php if ($setupMode): ?>
<div class="login-wrap"><section class="login-card">
<div class="brand-mark">RCB</div><p class="eyebrow">RC Bande Zwickau e.V.</p><h1>Adminbereich einrichten</h1>
<p class="intro">Es gibt noch kein Administratorkonto. Lege jetzt das erste Konto an. Diese Einrichtung wird automatisch gesperrt, sobald ein Konto existiert.</p>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= h($script) ?>">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="initial_setup">
<?php if ($setupKeyRequired): ?><label for="initial_setup_key">Einmaliger Einrichtungsschlüssel</label><input id="initial_setup_key" name="initial_setup_key" type="password" required minlength="10" autocomplete="off"><p class="hint">Steht in <code>config/database.php</code> beim Eintrag <code>initial_setup_key</code>. Das ist nicht das Datenbank-Passwort.</p><?php endif; ?>
<label for="display_name">Dein Name (Vor- und Nachname)</label><input id="display_name" name="display_name" required maxlength="190" autocomplete="name">
<label for="username">Benutzername</label><input id="username" name="username" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9._-]+" autocomplete="username">
<label for="email">E-Mail</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email"><p class="hint">Wird für „Passwort vergessen“ gebraucht.</p>
<label for="password">Eigenes Passwort (mindestens 12 Zeichen)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
<label for="password_confirm">Passwort wiederholen</label><input id="password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
<button type="submit">Administratorkonto erstellen</button>
</form><p class="hint">Tipp: Verwende ein einzigartiges Passwort, das du nirgendwo sonst nutzt.</p>
</section></div>
<?php elseif ($tokenView !== null): ?>
<div class="login-wrap"><section class="login-card">
<div class="brand-mark">RCB</div><p class="eyebrow">RC Bande Zwickau e.V.</p>
<?php if (!$tokenRow): ?>
<h1><?= $tokenView === 'invite' ? 'Einladung nicht gültig' : 'Link nicht gültig' ?></h1>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<p class="intro">Dieser Link ist abgelaufen, wurde schon benutzt oder widerrufen. Bitte beim Hauptadmin einen neuen Link anfordern.</p>
<a href="<?= h($script) ?>">← Zur Anmeldung</a>
<?php elseif ($tokenView === 'invite'): ?>
<?php $invitePerms = array_intersect_key(admin_permission_labels(), array_flip((array)json_decode((string)$tokenRow['permissions'], true))); ?>
<h1>Willkommen im Admin-Team</h1>
<p class="intro">Du wurdest<?= $tokenRow['note'] !== '' ? ' als <strong>' . h($tokenRow['note']) . '</strong>' : '' ?> eingeladen. Lege jetzt deinen eigenen Zugang an.</p>
<p class="hint">Freigegeben: <strong><?= h(implode(', ', $invitePerms)) ?></strong> · Link gültig bis <?= h(date('d.m.Y H:i', (int)$tokenRow['expires_at'])) ?> Uhr</p>
<?php if ($loggedIn): ?><div class="notice">Du bist gerade als <strong><?= h($currentUser['username']) ?></strong> angemeldet. Wenn du die Einladung annimmst, wirst du mit dem neuen Zugang angemeldet.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= h($script) ?>?einladung=<?= h((string)$_GET['einladung']) ?>">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="accept_invite">
<label for="display_name">Dein Name (Vor- und Nachname)</label><input id="display_name" name="display_name" required maxlength="190" autocomplete="name" value="<?= h($_POST['display_name'] ?? $tokenRow['note']) ?>">
<label for="username">Benutzername (zum Anmelden)</label><input id="username" name="username" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9._-]+" autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
<label for="email">E-Mail</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>"><p class="hint">Wird für „Passwort vergessen“ gebraucht.</p>
<label for="password">Passwort (mindestens 12 Zeichen)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
<label for="password_confirm">Passwort wiederholen</label><input id="password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
<button type="submit">Zugang anlegen</button>
</form>
<?php else: ?>
<h1>Neues Passwort festlegen</h1>
<p class="intro">Für den Zugang <strong><?= h($tokenUser['username']) ?></strong>. Der Link funktioniert nur einmal.</p>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= h($script) ?>?passwort=<?= h((string)$_GET['passwort']) ?>">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="reset_password">
<input type="text" name="username" value="<?= h($tokenUser['username']) ?>" autocomplete="username" hidden>
<label for="password">Neues Passwort (mindestens 12 Zeichen)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
<label for="password_confirm">Passwort wiederholen</label><input id="password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
<button type="submit">Passwort speichern</button>
</form>
<?php endif; ?>
</section></div>
<?php elseif ($forgotView): ?>
<div class="login-wrap"><section class="login-card">
<div class="brand-mark">RCB</div><p class="eyebrow">RC Bande Zwickau e.V.</p><h1>Passwort vergessen</h1>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
<p class="intro">Gib deinen Benutzernamen oder deine E-Mail-Adresse ein. Ist bei deinem Zugang eine E-Mail hinterlegt, bekommst du einen Link zum Zurücksetzen.</p>
<form class="stack" method="post" action="<?= h($script) ?>?vergessen=1">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="forgot_password">
<label for="identifier">Benutzername oder E-Mail</label><input id="identifier" name="identifier" required maxlength="190" autocomplete="username">
<button type="submit">Link anfordern</button>
</form>
<p class="hint">Keine E-Mail hinterlegt? Dann kann dir der Hauptadmin unter „Benutzer“ einen Link zum Zurücksetzen erstellen.</p>
<?php if ($recoveryAvailable): ?>
<details class="recovery"><summary>Hauptadmin: Notfall-Zugang mit Einrichtungsschlüssel</summary>
<p class="hint">Nur für den Hauptadmin. Der Einrichtungsschlüssel steht auf dem Server in <code>config/database.php</code> beim Eintrag <code>initial_setup_key</code>.</p>
<form class="stack" method="post" action="<?= h($script) ?>?vergessen=1">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="recover_superadmin">
<label for="r_username">Benutzername des Hauptadmins</label><input id="r_username" name="username" required maxlength="190" autocomplete="username">
<label for="setup_key">Einrichtungsschlüssel</label><input id="setup_key" name="setup_key" type="password" required autocomplete="off">
<label for="r_password">Neues Passwort (mindestens 12 Zeichen)</label><input id="r_password" name="password" type="password" required minlength="12" autocomplete="new-password">
<label for="r_password_confirm">Passwort wiederholen</label><input id="r_password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
<button type="submit">Passwort neu setzen</button>
</form></details>
<?php endif; ?>
<a href="<?= h($script) ?>">← Zur Anmeldung</a>
</section></div>
<?php elseif (!$loggedIn): ?>
<div class="login-wrap"><section class="login-card">
<div class="brand-mark">RCB</div><p class="eyebrow">RC Bande Zwickau e.V.</p><h1>Admin-Anmeldung</h1><p class="intro">Melde dich an, um die Vereinsplattform zu verwalten.</p>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice"><?= h($notice) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= h($script) ?>">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="login">
<label for="username">Benutzername</label><input id="username" name="username" required maxlength="190" autocomplete="username">
<label for="password">Passwort</label><input id="password" name="password" type="password" required autocomplete="current-password">
<button type="submit">Sicher anmelden</button>
</form><p class="hint"><a href="<?= h($script) ?>?vergessen=1">Passwort vergessen?</a></p><p class="hint">Zugang nur für berechtigte Vereinsmitglieder.</p><a href="/">← Zur Webseite</a>
</section></div>
<?php else: ?>
<header class="admin-top"><div class="brand"><div class="brand-mark">RCB</div><div>RCB <span class="muted">/ Admin</span></div></div><div class="top-actions"><span class="user-label muted"><?= h($currentUser['username']) ?></span><form method="post" action="<?= h($script) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="secondary" type="submit">Abmelden</button></form></div></header>
<div class="shell"><aside class="sidebar"><div class="nav-label">Verwaltung</div><nav class="nav">
<?php
// Menü nach Häufigkeit: oben das Tagesgeschäft, unten Einrichtung und Technik.
$navGroups = [
    '' => ['dashboard' => '▦ Übersicht', 'news' => '📰 Neuigkeiten', 'events' => '▣ Termine', 'reglement' => '📋 Reglement', 'pages' => '▤ Seiten', 'media' => '▧ Bilder & Videos', 'requests' => '✉ Mitgliedsanträge'],
    'Gelegentlich' => ['files' => '📎 Dateien', 'results' => '🏁 Ergebnisse', 'sponsors' => '♧ Sponsoren', 'membership' => '🪪 Mitgliedschaft'],
    'Einrichtung' => ['tracks' => '⌁ Strecken', 'design' => '🎨 Design', 'users' => '♙ Benutzer', 'settings' => '⚙ Einstellungen', 'backup' => '⬇ Datenbank-Backup', 'logs' => '☷ Protokoll'],
];
$navNewRequests = admin_can($currentUser, 'requests') ? (int)$db->query("SELECT COUNT(*) FROM membership_requests WHERE status = 'neu'")->fetchColumn() : 0;
foreach ($navGroups as $groupLabel => $items):
    $visible = array_filter($items, static fn($k) => admin_can($currentUser, admin_area_for_page($k)), ARRAY_FILTER_USE_KEY);
    if (!$visible) { continue; }
    if ($groupLabel !== ''): ?><div class="nav-group"><?= h($groupLabel) ?></div><?php endif;
    foreach ($visible as $key => $label): ?><a class="<?= $page === $key ? 'active' : '' ?>" href="<?= h($script) ?>?page=<?= h($key) ?>"><?= h($label) ?><?php if ($key === 'requests' && $navNewRequests > 0): ?> <span class="nav-badge" title="Neue Anträge"><?= $navNewRequests ?></span><?php endif; ?></a><?php endforeach;
endforeach; ?>
</nav><div class="footer">RCB Plattform<br>Verwaltungsbereich</div></aside>
<main class="content">
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice" role="status"><?= h($notice) ?></div><?php endif; ?>
<?php if ($page === 'dashboard'): ?>
<div class="page-head"><div><p class="eyebrow">Übersicht</p><h1>Willkommen, <?= h(trim((string)($currentUser['display_name'] ?? '')) !== '' ? $currentUser['display_name'] : $currentUser['username']) ?>!</h1><p>Hier verwaltest du die Inhalte der RC Bande Zwickau.</p></div></div>
<?php if (admin_can($currentUser, 'requests')) { $newRequests = (int)$db->query("SELECT COUNT(*) FROM membership_requests WHERE status = 'neu'")->fetchColumn(); if ($newRequests > 0): ?><div class="notice"><a href="<?= h($script) ?>?page=requests"><strong><?= $newRequests ?> neue<?= $newRequests === 1 ? 'r' : '' ?> Mitgliedsantr<?= $newRequests === 1 ? 'ag' : 'äge' ?></strong> ansehen →</a></div><?php endif; } ?>
<?php if (trim((string)($currentUser['email'] ?? '')) === ''): ?><div class="alert" role="alert">Für deinen Zugang ist noch keine E-Mail-Adresse hinterlegt. Bitte unten unter „Mein Zugang“ eintragen – sonst funktioniert „Passwort vergessen“ nicht.</div><?php endif; ?>
<div class="grid"><?php foreach (['events'=>'Termine','tracks'=>'Strecken','pages'=>'Seiten','sponsors'=>'Sponsoren','news'=>'Neuigkeiten','users'=>'Benutzer'] as $key=>$label): if (!admin_can($currentUser, $key)) { continue; } ?><a class="card" href="<?= h($script) ?>?page=<?= h($key) ?>"><div class="stat"><?= (int)$counts[$key] ?></div><div class="stat-label"><?= h($label) ?> angelegt</div></a><?php endforeach; ?></div>
<div class="tiles"><?php foreach (['news'=>['📰','Neuigkeiten','Vereinsmeldungen erstellen und veröffentlichen.'],'events'=>['📅','Termine','Veranstaltungen anlegen und bearbeiten.'],'reglement'=>['📋','Reglement','Regelwerk und Klassen pflegen.'],'pages'=>['📄','Vereinsseiten','Texte und Veröffentlichungsstatus verwalten.'],'media'=>['🖼️','Bilder & Videos','Bilder und Clips hochladen und verwalten.'],'requests'=>['✉️','Mitgliedsanträge','Online-Aufnahmeanträge und Änderungsmeldungen.'],'files'=>['📎','Dateien','Anträge, Satzung und andere Dokumente auf den passenden Seiten anbieten.'],'results'=>['🏆','Ergebnisse','Rennberichte veröffentlichen.'],'sponsors'=>['🤝','Sponsoren','Sponsoren und Weblinks eintragen.'],'membership'=>['🪪','Mitgliedschaft','Aufnahmeantrag, Beiträge, Ordnungen und Bankverbindung pflegen.'],'tracks'=>['🏁','Strecken','Onroad- und Offroad-Strecken pflegen.'],'design'=>['🎨','Design','Farben, Schriftgröße, Hintergründe und Abschnitte der Seiten.'],'users'=>['🔐','Benutzer','Adminzugänge verwalten.'],'settings'=>['⚙️','Einstellungen','Name, Kontakt, Wartungsmodus und Live-Anzeige.'],'backup'=>['⬇️','Datenbank-Backup','SQL-Export der Plattformdaten herunterladen.']] as $key=>$tile): if (!admin_can($currentUser, $key)) { continue; } ?><a class="tile" href="<?= h($script) ?>?page=<?= h($key) ?>"><div class="tile-icon"><?= $tile[0] ?></div><h2><?= h($tile[1]) ?></h2><p><?= h($tile[2]) ?></p></a><?php endforeach; ?></div>
<section class="card" id="mein-zugang" style="max-width:760px;margin-top:22px"><h2>Mein Zugang</h2><p class="hint">Angemeldet als <strong><?= h($currentUser['username']) ?></strong>. Die E-Mail-Adresse wird für „Passwort vergessen“ gebraucht.</p>
<form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_own_account">
<input type="text" name="username" value="<?= h($currentUser['username']) ?>" autocomplete="username" hidden>
<label for="own_name">Name (Vor- und Nachname)</label><input id="own_name" name="display_name" required maxlength="190" autocomplete="name" value="<?= h($currentUser['display_name'] ?? '') ?>">
<label for="own_email">E-Mail</label><input id="own_email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= h($currentUser['email'] ?? '') ?>">
<label for="own_password">Neues Passwort (leer lassen, wenn es bleiben soll)</label><input id="own_password" name="password" type="password" minlength="12" autocomplete="new-password">
<label for="own_password_confirm">Neues Passwort wiederholen</label><input id="own_password_confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password">
<label for="current_password">Aktuelles Passwort (zur Bestätigung)</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password">
<button type="submit">Speichern</button></form><form class="stack notify-form" method="post" style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_notify">
<h3 style="margin:0">🔔 Benachrichtigung bei Änderungen</h3>
<p class="hint" style="margin:0">Bekomme eine E-Mail, wenn <strong>andere</strong> Admins etwas an der Webseite ändern – mit Name, Uhrzeit und was geändert wurde. Deine eigenen Änderungen werden nicht gemeldet.</p>
<?php $myNotify = (string)($currentUser['notify_mode'] ?? ''); foreach (rcb_notify_modes() as $nk => $nl): ?><label class="check"><input type="radio" name="notify_mode" value="<?= h($nk) ?>"<?= $myNotify === $nk ? ' checked' : '' ?>> <?= h($nl) ?></label><?php endforeach; ?>
<p class="hint" style="margin:0">Gesendet an <?= h((string)($currentUser['email'] ?? '') ?: '– bitte oben E-Mail eintragen –') ?>. Mehrere Änderungen hintereinander kommen gesammelt in einer E-Mail.</p>
<div class="form-actions"><button type="submit" class="secondary">Benachrichtigung speichern</button></div></form>
</section>
<?php elseif ($page === 'backup'): ?>
<div class="page-head"><div><p class="eyebrow">Datensicherung</p><h1>Datenbank-Backup</h1><p>Erstelle einen SQL-Export deiner Vereinsplattform.</p></div></div>
<section class="card"><h2>Backup herunterladen</h2><p>Der Export enthält Tabellenstruktur und gespeicherte Daten der SQLite-Datenbank. Die Datei wird direkt erzeugt und nicht öffentlich auf dem Webserver abgelegt.</p><div class="notice">Wichtig: Bewahre die Datei sicher auf, da sie personenbezogene Verwaltungsdaten und Zugangsdaten-Hashes enthalten kann. Ein Import ist nur in eine frische/leere SQLite-Datenbank vorgesehen. Die Wiederherstellung erfolgt über den Hosting-Anbieter oder eine lokale SQLite-Umgebung; es gibt bewusst keinen öffentlich erreichbaren Restore-Upload.</div><form method="post" class="stack" style="margin-top:18px"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="backup_export"><button type="submit">SQL-Backup herunterladen</button></form></section>
<?php elseif ($page === 'events'): ?>
<?php
$item = $editItem ?: ['id'=>0,'title'=>'','description'=>'','event_date'=>'','end_date'=>'','start_time'=>'','location'=>'','registration_url'=>'','announcement_url'=>'','announcement_text'=>'','classes_json'=>'[]','published'=>0,'results_url'=>''];
$rows = $db->query('SELECT * FROM events ORDER BY event_date DESC, id DESC')->fetchAll();
$uploadedPdf = str_starts_with((string)$item['announcement_url'], '/uploads/ausschreibungen/') ? (string)$item['announcement_url'] : '';
$eventClasses = json_decode((string)($item['classes_json'] ?? '[]'), true);
$eventClasses = is_array($eventClasses) ? $eventClasses : [];
$classRows = array_merge($eventClasses, array_fill(0, max(2, 4 - count($eventClasses)), ['name' => '', 'guest' => false, 'url' => '']));
$trackOptions = $db->query('SELECT id, name FROM tracks ORDER BY name')->fetchAll();
$itemTrack = (int)($item['track_id'] ?? 0);
$isClubLocation = !$editItem || $itemTrack > 0 || (str_starts_with((string)$item['location'], 'Vereinsgelände') || str_ends_with((string)$item['location'], 'der RC Bande Zwickau')) || (string)$item['location'] === '';
$ruleTitles = array_map(static fn($c) => admin_rule_class_title($db, $c), admin_rule_classes($db));
$classRow = static fn(int $i, array $c): string => '<div class="class-row"><input name="class[' . $i . '][name]" list="rule-classes" maxlength="120" placeholder="Klasse, z. B. TT02 Cup" value="' . h($c['name'] ?? '') . '" aria-label="Klasse"><label class="check"><input type="checkbox" name="class[' . $i . '][guest]" value="1"' . (!empty($c['guest']) ? ' checked' : '') . '> Gastklasse</label><input name="class[' . $i . '][url]" type="url" maxlength="1000" placeholder="Nennung für diese Klasse (optional)" value="' . h($c['url'] ?? '') . '" aria-label="Nennungslink der Klasse"></div>';
?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Termine</h1><p>Veranstaltungen mit Nennung, Ausschreibung und den ausgeschriebenen Klassen.</p></div></div>
<div class="columns events-columns"><section class="card"><h2><?= $editItem ? 'Termin bearbeiten' : 'Termin anlegen' ?></h2>
<form class="stack" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_event"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<label for="title">Veranstaltung *</label><input id="title" name="title" required maxlength="180" placeholder="z. B. RCK-Challenge oder Tamiya Euro Cup (TEC)" value="<?= h($item['title']) ?>">
<div class="field-row"><div><label for="event_date">Datum (erster Tag)</label><input id="event_date" name="event_date" type="date" value="<?= h($item['event_date']) ?>"></div><div><label for="end_date">bis (mehrtägig)</label><input id="end_date" name="end_date" type="date" value="<?= h($item['end_date'] ?? '') ?>"></div><div><label for="start_time">Beginn</label><input id="start_time" name="start_time" type="time" value="<?= h(substr((string)$item['start_time'], 0, 5)) ?>"></div></div>
<div class="loc-tabs" role="group" aria-label="Ort"><span class="loc-title">Ort</span>
<input type="radio" id="loc-club" name="location_mode" value="club"<?= $isClubLocation ? ' checked' : '' ?>><label for="loc-club">Unsere Strecken</label>
<input type="radio" id="loc-other" name="location_mode" value="other"<?= $isClubLocation ? '' : ' checked' ?>><label for="loc-other">Anderer Ort</label>
<div class="loc-panel loc-panel-club"><label for="location_track" class="sr-only">Strecke</label><select id="location_track" name="location_track"><option value="0">Beide Strecken (Onroad und Offroad)</option><?php foreach ($trackOptions as $t): ?><option value="<?= (int)$t['id'] ?>"<?= $itemTrack === (int)$t['id'] ? ' selected' : '' ?>><?= h(stripos((string)$t['name'], 'strecke') !== false ? $t['name'] : $t['name'] . '-Strecke') ?></option><?php endforeach; ?></select><p class="hint">Reichenbacher Straße 125A, 08056 Zwickau. Auf der Terminseite wird die Strecke verlinkt.</p></div>
<div class="loc-panel loc-panel-other"><label for="location_text" class="sr-only">Ort</label><input id="location_text" name="location_text" maxlength="180" placeholder="z. B. Messe Chemnitz, Halle 2" value="<?= $isClubLocation ? '' : h($item['location']) ?>"></div>
</div>
<fieldset><legend>Nennung</legend><label for="registration_url">Link zur Nennung</label><input id="registration_url" name="registration_url" type="url" maxlength="1000" placeholder="https://www.myrcm.ch/…" value="<?= h($item['registration_url'] ?? '') ?>"><p class="hint">Erscheint als Knopf „Nennung bei myRCM ↗“ bzw. mit dem Namen der Seite.</p></fieldset>
<fieldset><legend>Ausschreibung</legend>
<label for="announcement_url">Link zur Ausschreibung</label><input id="announcement_url" name="announcement_url" type="url" maxlength="1000" placeholder="https://… (z. B. rck-challenge.de)" value="<?= $uploadedPdf === '' ? h($item['announcement_url'] ?? '') : '' ?>">
<label for="announcement_file">oder PDF hochladen</label><input id="announcement_file" name="announcement_file" type="file" accept="application/pdf">
<?php if ($uploadedPdf !== ''): ?><p class="hint">Aktuell: <a href="<?= h($uploadedPdf) ?>" target="_blank" rel="noopener">hochgeladene Ausschreibung (PDF)</a> – bleibt erhalten, solange du nichts anderes einträgst.</p><label class="check"><input type="checkbox" name="remove_announcement" value="1"> PDF entfernen</label><?php endif; ?>
<label for="announcement_text">oder Ausschreibungstext</label><textarea id="announcement_text" name="announcement_text" maxlength="30000" rows="5" placeholder="Wird auf der Terminseite zum Aufklappen angezeigt."><?= h($item['announcement_text'] ?? '') ?></textarea>
</fieldset>
<fieldset><legend>Ergebnisse</legend><label for="results_url">Link zu den Ergebnissen</label><input id="results_url" name="results_url" type="url" maxlength="1000" placeholder="https://www.myrcm.ch/… (nach dem Rennen eintragen)" value="<?= h($item['results_url'] ?? '') ?>"><p class="hint">Sobald der Termin vorbei ist, erscheint er automatisch oben auf der Seite „Ergebnisse“ – mit diesem Link als Knopf. Ohne Link steht dort bei Rennen bis 3 Wochen nach dem Termin „Ergebnisse folgen“.</p></fieldset>
<fieldset><legend>Klassen</legend><p class="hint">Ausgeschriebene Klassen. Stimmt der Name mit einer Reglement-Klasse überein, wird das Regelwerk automatisch verlinkt. Leere Zeilen werden ignoriert.</p>
<datalist id="rule-classes"><?php foreach ($ruleTitles as $t): ?><option value="<?= h($t) ?>"><?php endforeach; ?></datalist>
<div id="class-rows"><?php foreach ($classRows as $i => $c) { echo $classRow($i, $c); } ?></div>
<template id="class-row-template"><?= $classRow(999, []) ?></template>
<button type="button" class="secondary small" data-add-class>+ Weitere Klasse</button></fieldset>
<label for="description">Hinweise</label><textarea id="description" name="description" maxlength="5000" rows="3" placeholder="z. B. Zeitplan, Startgeld, Besonderheiten"><?= h($item['description']) ?></textarea>
<label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht</label>
<div class="form-actions"><button type="submit">Speichern</button><?php if ($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=events">Abbrechen</a><?php endif; ?></div></form></section>
<section class="card"><h2>Vorhandene Termine</h2><div class="table-wrap"><table class="table"><thead><tr><th>Termin</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach ($rows as $row): $rc = json_decode((string)($row['classes_json'] ?? ''), true); ?><tr><td><strong><?= h($row['title']) ?></strong><br><span class="muted"><?= h($row['event_date'] ? date('d.m.Y', strtotime((string)$row['event_date'])) : 'ohne Datum') ?><?= !empty($row['end_date']) ? '–' . h(date('d.m.Y', strtotime((string)$row['end_date']))) : '' ?></span><br><?php if (!empty($row['registration_url'])): ?><span class="badge">Nennung</span> <?php endif; ?><?php if (!empty($row['announcement_url']) || !empty($row['announcement_text'])): ?><span class="badge">Ausschreibung</span> <?php endif; ?><?php if (is_array($rc) && $rc): ?><span class="badge"><?= count($rc) ?> <?= count($rc) === 1 ? 'Klasse' : 'Klassen' ?></span><?php endif; ?></td><td><span class="badge <?= $row['published'] ? 'yes' : 'no' ?>"><?= $row['published'] ? 'Veröffentlicht' : 'Entwurf' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=events&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" data-confirm="Diesen Termin wirklich löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="3" class="empty">Noch keine Termine angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php $pastMax = (int)admin_setting($db, 'past_events_max', '12'); $pastMonths = (int)admin_setting($db, 'past_events_months', '12'); ?>
<section class="card" style="margin-top:22px"><h2>Vergangene Veranstaltungen auf der Terminseite</h2><form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_event_settings">
<div class="field-row"><div><label for="past_events_max">Höchstens anzeigen</label><input id="past_events_max" name="past_events_max" type="number" min="0" max="100" value="<?= $pastMax ?>"></div><div><label for="past_events_months">Zeitraum in Monaten</label><input id="past_events_months" name="past_events_months" type="number" min="0" max="120" value="<?= $pastMonths ?>"></div></div>
<p class="hint">Zeigt die neuesten vergangenen Termine, die nicht älter als der Zeitraum sind. 0 Monate = ohne zeitliche Grenze, 0 Termine = Bereich bleibt leer. Die Termine selbst werden nicht gelöscht.</p>
<div class="form-actions"><button type="submit">Speichern</button></div></form></section>
<?php elseif ($page === 'design'): ?>
<?php
$design = rcb_design_from_json((string)($db->query("SELECT setting_value FROM settings WHERE setting_key='design'")->fetchColumn() ?: ''));
$defaults = rcb_design_defaults();
$designPages = [];
foreach (rcb_site_pages() as $slug => [$label]) { if (!in_array($slug, ['live', 'downloads'], true)) { $designPages[$slug] = $label; } }
foreach ($db->query('SELECT slug, title FROM pages ORDER BY title')->fetchAll() as $cp) { $designPages['seite:' . $cp['slug']] = 'Eigene Seite: ' . $cp['title']; }
$bgImages = $db->query("SELECT filename, title FROM media WHERE published = 1 AND category <> 'video' ORDER BY id DESC")->fetchAll();
$sectionLabels = ['home' => 'Startseite', 'onroad' => 'Onroad-Strecke', 'offroad' => 'Offroad-Strecke', 'termine' => 'Termine', 'ergebnisse' => 'Ergebnisse', 'kontakt' => 'Kontakt'];
$contrast = static fn(string $hex): string => rcb_design_contrast_white($hex) < 4.5 ? '<p class="hint" style="color:#f6c76b">⚠ Weiße Schrift ist auf dieser Farbe schlecht lesbar (Kontrast ' . number_format(rcb_design_contrast_white($hex), 1, ',', '') . ':1, empfohlen mindestens 4,5:1). Bitte eine dunklere Farbe wählen.</p>' : '';
?>
<div class="page-head"><div><p class="eyebrow">Website</p><h1>Design</h1><p>Farben, Schriftgröße, Hintergründe und sichtbare Abschnitte. Änderungen gelten sofort nach dem Speichern für die ganze Webseite. Texte selbst gestaltest du direkt auf der Seite: im Bearbeiten-Modus gibt es dafür Ausrichtung, Textgröße und Textfarben.</p></div></div>
<form method="post" class="stack design-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_design">
<div class="columns">
<section class="card"><h2>Vereinsfarben</h2>
<label for="red">Hauptfarbe (Knöpfe, „Mitglied werden“, Hervorhebungen)</label><div class="color-row"><input type="color" id="red" name="red" value="<?= h($design['red']) ?>"><code><?= h($design['red']) ?></code><?= $design['red'] !== $defaults['red'] ? '<span class="muted">Vereinsrot: ' . h($defaults['red']) . '</span>' : '' ?></div><?= $contrast($design['red']) ?>
<label for="blue">Grundfarbe (Kopfleiste, Überschriften, Titelbereiche)</label><div class="color-row"><input type="color" id="blue" name="blue" value="<?= h($design['blue']) ?>"><code><?= h($design['blue']) ?></code><?= $design['blue'] !== $defaults['blue'] ? '<span class="muted">Vereinsblau: ' . h($defaults['blue']) . '</span>' : '' ?></div><?= $contrast($design['blue']) ?>
<div class="design-preview" style="--p-red:<?= h($design['red']) ?>;--p-blue:<?= h($design['blue']) ?>"><div class="dp-head">RC Bande Zwickau <span>Mitglied werden</span></div><div class="dp-body"><strong>Überschrift</strong><p>So wirken die Farben zusammen.</p><span class="dp-btn">Knopf</span></div></div>
<p class="hint">Helle oder abgedunkelte Varianten (z. B. für Hover-Effekte oder hellblaue Flächen) werden automatisch aus diesen zwei Farben berechnet.</p>
</section>
<section class="card"><h2>Schriftgröße</h2>
<p class="hint">Gilt für alle Texte der Webseite. „Groß“ hilft beim Lesen auf dem Handy und für ältere Besucher.</p>
<?php foreach (rcb_design_font_sizes() as $key => [$label, $css]): ?><label class="check big-check"><input type="radio" name="font" value="<?= h($key) ?>"<?= $design['font'] === $key ? ' checked' : '' ?>> <span style="font-size:<?= h($css) ?>"><?= h($label) ?> – Beispieltext</span></label><?php endforeach; ?>
</section>
</div>
<section class="card"><h2>Hintergrund pro Seite</h2><p class="hint">Hintergrund des Inhaltsbereichs unter dem Titelbild. Für ein Hintergrundbild zuerst unter „Bilder &amp; Videos“ hochladen – es wird automatisch stark aufgehellt, damit Texte lesbar bleiben.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>Seite</th><th>Hintergrund</th><th>Bild (nur bei „Bild“)</th></tr></thead><tbody>
<?php foreach ($designPages as $key => $label): $bg = $design['backgrounds'][$key] ?? ['type' => 'standard', 'image' => '']; ?>
<tr><td><strong><?= h($label) ?></strong></td><td><select name="bg[<?= h($key) ?>]" aria-label="Hintergrund <?= h($label) ?>"><?php foreach (rcb_design_background_types() as $t => $tl): ?><option value="<?= h($t) ?>"<?= $bg['type'] === $t ? ' selected' : '' ?>><?= h($tl) ?></option><?php endforeach; ?></select></td>
<td><select name="bgimg[<?= h($key) ?>]" aria-label="Hintergrundbild <?= h($label) ?>"><option value="">– Bild wählen –</option><?php foreach ($bgImages as $img): $u = '/uploads/' . rawurlencode((string)$img['filename']); ?><option value="<?= h($u) ?>"<?= $bg['image'] === $u ? ' selected' : '' ?>><?= h(($img['title'] ?: $img['filename'])) ?></option><?php endforeach; ?></select></td></tr>
<?php endforeach; ?></tbody></table></div>
</section>
<section class="card"><h2>Abschnitte ein- und ausblenden</h2><p class="hint">Ausgeblendete Abschnitte verschwinden für Besucher, bleiben aber gespeichert. Im Bearbeiten-Modus siehst du sie weiter – blass und rot markiert.</p>
<div class="section-groups"><?php foreach (rcb_design_sections() as $pageKey => $sections): ?><fieldset><legend><?= h($sectionLabels[$pageKey] ?? $pageKey) ?></legend><?php foreach ($sections as $id => $label): $full = $pageKey . '.' . $id; ?><label class="check"><input type="checkbox" name="show[]" value="<?= h($full) ?>"<?= in_array($full, $design['hidden'], true) ? '' : ' checked' ?>> <?= h($label) ?></label><?php endforeach; ?></fieldset><?php endforeach; ?></div>
</section>
<div class="form-actions sticky-save"><button type="submit">Design speichern</button><a class="button secondary" href="/" target="_blank" rel="noopener">Webseite ansehen</a></div>
</form>
<section class="card" style="margin-top:22px"><h2>Zurücksetzen</h2><div class="form-actions">
<form method="post" data-confirm="Farben und Schriftgröße auf den Vereinsstandard zurücksetzen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="reset_design"><input type="hidden" name="what" value="colors"><button class="secondary" type="submit">Farben &amp; Schrift zurücksetzen</button></form>
<form method="post" data-confirm="Das komplette Design zurücksetzen? Auch Hintergründe und ausgeblendete Abschnitte werden zurückgesetzt."><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="reset_design"><input type="hidden" name="what" value="all"><button class="danger" type="submit">Alles zurücksetzen</button></form>
</div></section>
<?php elseif ($page === 'tracks'): ?>
<?php $item=$editItem ?: ['id'=>0,'name'=>'','description'=>'','surface'=>'','length'=>'','classes'=>'','electricity'=>0,'toilet'=>0,'paddock'=>0,'published'=>0,'status'=>'open','show_status'=>0,'status_note'=>'','driver_spots'=>'','equipment_json'=>'','equipment_extra'=>'','guest_mode'=>'','guest_note'=>'','driving_times'=>'','day_fee'=>'','pay_json'=>'','pay_note'=>'','facts_json'=>'']; $rows=$db->query('SELECT * FROM tracks ORDER BY id DESC')->fetchAll(); $itemEquipment = rcb_track_equipment($item); $statusLabels = rcb_track_status_labels(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Strecken</h1><p>Steckbrief, Ausstattung und aktueller Zustand der Strecken. Was hier steht, erscheint im Kasten „Steckbrief“ auf der jeweiligen Streckenseite. Leere Felder werden nicht angezeigt.</p></div></div>
<div class="columns"><section class="card"><h2><?= $editItem ? 'Strecke bearbeiten' : 'Strecke anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_track"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<label for="name">Streckenname *</label><input id="name" name="name" required maxlength="180" value="<?= h($item['name']) ?>"><p class="hint">Enthält der Name „Onroad“ oder „Offroad“, wird die Strecke der passenden Seite zugeordnet.</p>
<fieldset><legend>Aktueller Zustand</legend>
<label for="status">Status</label><select id="status" name="status"><?php foreach ($statusLabels as $key => [$label]): ?><option value="<?= h($key) ?>"<?= $item['status'] === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
<label for="status_note">Hinweis zum Status</label><input id="status_note" name="status_note" maxlength="250" placeholder="z. B. Winterpause bis Mitte März · nach Regen erst ab Mittag befahrbar" value="<?= h($item['status_note'] ?? '') ?>">
<label class="check"><input type="checkbox" name="show_status" value="1"<?= checked($item['show_status'] ?? 0) ?>> Status auf der Webseite anzeigen (farbiger Hinweis über dem Steckbrief)</label>
</fieldset>
<fieldset><legend>Steckbrief</legend>
<label for="surface">Belag</label><input id="surface" name="surface" maxlength="100" placeholder="z. B. Asphalt, Kunstrasen" value="<?= h($item['surface']) ?>">
<label for="length">Länge</label><input id="length" name="length" maxlength="100" placeholder="z. B. ca. 210 m" value="<?= h($item['length']) ?>">
<label for="classes">Maßstab / Klassen</label><input id="classes" name="classes" maxlength="250" placeholder="z. B. Tourenwagen 1:10" value="<?= h($item['classes']) ?>">
<label for="driver_spots">Fahrerstand</label><input id="driver_spots" name="driver_spots" maxlength="50" placeholder="z. B. 10 Plätze" value="<?= h($item['driver_spots'] ?? '') ?>">
<label for="driving_times">Fahrzeiten</label><textarea id="driving_times" name="driving_times" rows="3" maxlength="2000" placeholder="z. B. Mo–Sa 9–20 Uhr, So/Feiertag 10–13 und 15–19 Uhr"><?= h($item['driving_times'] ?? '') ?></textarea>
</fieldset>
<fieldset><legend>Ausstattung</legend><div class="check-grid"><?php foreach (rcb_track_equipment_options() as $key => $label): ?><label class="check"><input type="checkbox" name="equipment[]" value="<?= h($key) ?>"<?= in_array($key, $itemEquipment, true) ? ' checked' : '' ?>> <?= h($label) ?></label><?php endforeach; ?></div>
<label for="equipment_extra">Sonstiges</label><input id="equipment_extra" name="equipment_extra" maxlength="250" placeholder="z. B. Druckluft, Waschplatz" value="<?= h($item['equipment_extra'] ?? '') ?>">
</fieldset>
<fieldset><legend>Fahren als Gast</legend>
<label for="guest_mode">Wer darf fahren?</label><select id="guest_mode" name="guest_mode"><?php foreach (rcb_track_guest_modes() as $key => $label): ?><option value="<?= h($key) ?>"<?= (string)($item['guest_mode'] ?? '') === $key ? ' selected' : '' ?>><?= h($key === '' ? '– nicht anzeigen –' : $label) ?></option><?php endforeach; ?></select>
<label for="guest_note">Zusatz für Gäste</label><input id="guest_note" name="guest_note" maxlength="250" placeholder="z. B. Anmeldung vorher per E-Mail oder Facebook" value="<?= h($item['guest_note'] ?? '') ?>">
</fieldset>
<fieldset><legend>Steckbrief: eigene Zeilen</legend>
<p class="hint">Weitere Angaben für den Steckbrief, z. B. Oberfläche, Grip, empfohlene Reifen. Mit ↑ ↓ sortieren, mit dem Haken ein- oder ausblenden.</p>
<?php $itemFacts = rcb_track_facts($item); ?>
<div class="facts-list" data-facts>
<?php foreach ($itemFacts['custom'] ?: [['label' => '', 'value' => '', 'show' => true]] as $f): ?>
<div class="fact-row"><input type="hidden" name="fact_show[]" value="<?= $f['show'] ? '1' : '0' ?>"><label class="check fact-show" title="Auf der Webseite anzeigen"><input type="checkbox" data-fact-show<?= $f['show'] ? ' checked' : '' ?>> zeigen</label><input name="fact_label[]" maxlength="60" placeholder="Bezeichnung, z. B. Grip" value="<?= h($f['label']) ?>" aria-label="Bezeichnung"><input name="fact_value[]" maxlength="400" placeholder="Text, z. B. größtenteils mittel" value="<?= h($f['value']) ?>" aria-label="Text"><span class="fact-btns"><button type="button" class="secondary" data-fact-up title="Nach oben" aria-label="Nach oben">↑</button><button type="button" class="secondary" data-fact-down title="Nach unten" aria-label="Nach unten">↓</button><button type="button" class="danger" data-fact-del title="Zeile entfernen" aria-label="Zeile entfernen">✕</button></span></div>
<?php endforeach; ?>
</div>
<button type="button" class="secondary" data-fact-add>+ Zeile hinzufügen</button>
</fieldset>
<fieldset><legend>Steckbrief: was wird angezeigt?</legend>
<p class="hint">Leere Felder erscheinen ohnehin nicht. Hier kannst du ausgefüllte Angaben trotzdem ausblenden.</p>
<div class="check-grid"><?php foreach (rcb_track_fact_fields() as $fk => $fl): ?><label class="check"><input type="checkbox" name="fact_std[]" value="<?= h($fk) ?>"<?= in_array($fk, $itemFacts['hide'], true) ? '' : ' checked' ?>> <?= h($fl) ?></label><?php endforeach; ?></div>
</fieldset>
<fieldset><legend>Tagesgebühr &amp; Bezahlen (optional)</legend>
<?php $itemPay = rcb_track_pay_links($item); ?>
<label for="day_fee">Tagesgebühr</label><input id="day_fee" name="day_fee" maxlength="120" placeholder="z. B. 5 € pro Tag, Jugendliche 3 €" value="<?= h($item['day_fee'] ?? '') ?>">
<p class="hint">Bezahl-Knöpfe erscheinen nur, wenn ein Link eingetragen ist. Am einfachsten ist ein PayPal.Me-Link des Vereinskontos mit Betrag, z. B. <code>https://paypal.me/RCBandeZwickau/5EUR</code> – dann ist der Betrag beim Bezahlen schon eingetragen. Bis zu drei Knöpfe, z. B. für Erwachsene, Jugendliche und Kinder.</p>
<?php foreach ([1, 2, 3] as $n): $pl = $itemPay[$n - 1] ?? ['label' => '', 'url' => '']; ?>
<div class="pay-row"><div><label for="pay_label_<?= $n ?>">Knopf <?= $n ?> – Beschriftung</label><input id="pay_label_<?= $n ?>" name="pay_label_<?= $n ?>" maxlength="80" placeholder="<?= ['', 'z. B. Erwachsene 5 € bezahlen', 'z. B. Jugendliche 3 € bezahlen', 'z. B. Kinder 2 € bezahlen'][$n] ?>" value="<?= h($pl['label']) ?>"></div><div><label for="pay_url_<?= $n ?>">Knopf <?= $n ?> – PayPal-Link</label><input id="pay_url_<?= $n ?>" name="pay_url_<?= $n ?>" maxlength="500" inputmode="url" placeholder="https://paypal.me/…" value="<?= h($pl['url']) ?>"></div></div>
<?php endforeach; ?>
<label for="pay_note">Hinweis zum Bezahlen</label><input id="pay_note" name="pay_note" maxlength="250" placeholder="z. B. Bitte Name und Datum als Nachricht angeben" value="<?= h($item['pay_note'] ?? '') ?>">
</fieldset>
<label for="description">Weitere Hinweise</label><textarea id="description" name="description" rows="3" maxlength="5000" placeholder="z. B. Nitro-Modelle nur mit Schalldämpfer"><?= h($item['description']) ?></textarea>
<label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht</label>
<div class="form-actions"><button type="submit">Speichern</button><?php if ($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=tracks">Abbrechen</a><?php endif; ?></div></form></section><section class="card"><h2>Vorhandene Strecken</h2><div class="table-wrap"><table class="table"><thead><tr><th>Strecke</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['name']) ?></strong><br><span class="muted"><?= h($row['surface']) ?> <?= h($row['length']) ?></span></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published'] ? 'Online' : 'Entwurf' ?></span><br><span class="muted"><?= h($statusLabels[$row['status']][0] ?? 'Geschlossen') ?><?= (int)($row['show_status'] ?? 0) === 1 ? ' (sichtbar)' : '' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=tracks&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" data-confirm="Diese Strecke wirklich löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_track"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="3" class="empty">Noch keine Strecken angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'sponsors'): ?>
<?php $item=$editItem ?: ['id'=>0,'name'=>'','url'=>'','description'=>'','published'=>0]; $rows=$db->query('SELECT * FROM sponsors ORDER BY name')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Sponsoren</h1><p>Partner und Sponsor-Webseiten verwalten.</p></div></div><div class="columns"><section class="card"><h2><?= $editItem?'Sponsor bearbeiten':'Sponsor anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_sponsor"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="name">Name *</label><input id="name" name="name" required maxlength="180" value="<?= h($item['name']) ?>"><label for="url">Webseite</label><input id="url" name="url" type="url" maxlength="500" placeholder="https://…" value="<?= h($item['url']) ?>"><label for="description">Beschreibung</label><textarea id="description" name="description" maxlength="2000"><?= h($item['description']) ?></textarea><label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht</label><div class="form-actions"><button type="submit">Speichern</button><?php if($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=sponsors">Abbrechen</a><?php endif; ?></div></form></section><section class="card"><h2>Vorhandene Sponsoren</h2><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= h($row['name']) ?></strong><?php if($row['url']): ?><br><a href="<?= h($row['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($row['url']) ?></a><?php endif; ?></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=sponsors&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" data-confirm="Diesen Sponsor wirklich löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_sponsor"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="4" class="empty">Noch keine Sponsoren angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'reglement'): ?>
<?php $ruleClasses = admin_rule_classes($db); ?>
<div class="page-head"><div><p class="eyebrow">Website</p><h1>Reglement</h1><p>Allgemeine Regeln, Fun Cup und die einzelnen Klassen bzw. Rennen. Neue Klassen sind zuerst verborgen: Regelwerk im Editor ausfüllen, Entwurf veröffentlichen, dann die Klasse veröffentlichen.</p></div><div class="form-actions"><a class="button" href="/reglement.php?bearbeiten=1">✎ Allgemeinen Teil bearbeiten</a><a class="button secondary" href="/reglement.php" target="_blank" rel="noopener">Reglement ansehen</a></div></div>
<?php if (in_array('reglement', rcb_hidden_pages(admin_setting($db, 'pages_hidden', '[]'), admin_setting($db, 'live_enabled', '0')), true)): ?><p class="alert">Die Reglement-Seite ist zurzeit verborgen. Veröffentlichen unter <a href="<?= h($script) ?>?page=pages">Seiten</a>.</p><?php endif; ?>
<?php admin_draft_banner($db, 'reglement', '/reglement.php?vorschau=1', admin_editor_scope($currentUser) === 'reglement'); ?>
<h2>Klassen &amp; Rennen</h2>
<section class="card"><div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Klasse</th><th>Status</th><th>Aktion</th></tr></thead><tbody>
<?php foreach ($ruleClasses as $i => $class): $label = admin_rule_class_title($db, $class); $hf = '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="key" value="' . h($class['key']) . '">'; ?>
<tr><td><?= $i + 1 ?></td><td><strong><?= h($label) ?></strong></td><td><span class="badge <?= $class['published'] ? 'yes' : 'no' ?>"><?= $class['published'] ? 'Online' : 'Verborgen' ?></span></td><td class="actions">
<a class="button" href="/reglement.php?klasse=<?= h(rawurlencode($class['key'])) ?>&amp;bearbeiten=1">✎ Regelwerk bearbeiten</a>
<form method="post"><?= $hf ?><input type="hidden" name="action" value="rule_class_toggle"><button type="submit" class="<?= $class['published'] ? 'secondary' : '' ?>"><?= $class['published'] ? 'Verbergen' : '⬆ Veröffentlichen' ?></button></form>
<?php if ($i > 0): ?><form method="post"><?= $hf ?><input type="hidden" name="action" value="rule_class_move"><input type="hidden" name="dir" value="up"><button type="submit" class="secondary small">↑</button></form><?php endif; ?>
<?php if ($i < count($ruleClasses) - 1): ?><form method="post"><?= $hf ?><input type="hidden" name="action" value="rule_class_move"><input type="hidden" name="dir" value="down"><button type="submit" class="secondary small">↓</button></form><?php endif; ?>
<form method="post" data-confirm="Klasse „<?= h($label) ?>“ mit ihrem gesamten Regelwerk unwiderruflich löschen?"><?= $hf ?><input type="hidden" name="action" value="rule_class_delete"><button type="submit" class="danger">Löschen</button></form>
</td></tr>
<?php endforeach; ?>
<?php if (!$ruleClasses): ?><tr><td colspan="4" class="empty">Keine Klassen vorhanden.</td></tr><?php endif; ?>
</tbody></table></div>
<form class="inline-add" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="rule_class_add"><label for="new_class">Neue Klasse / neues Rennen</label><div class="form-actions"><input id="new_class" name="title" required maxlength="120" placeholder="z. B. Mini-Z Cup"><button type="submit">+ Hinzufügen</button></div></form>
</section>
<?php elseif ($page === 'pages'): ?>
<?php $item=$editItem ?: ['id'=>0,'slug'=>'','title'=>'','content'=>'','published'=>0]; $rows=$db->query('SELECT * FROM pages ORDER BY title')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Website</p><h1>Seiten bearbeiten</h1><p>Jede Seite wird so angezeigt, wie sie online ist. Mit „Bearbeiten“ öffnest du sie im Editor und änderst Texte und Bilder direkt an der richtigen Stelle. Gespeicherte Änderungen bleiben ein Entwurf, bis du sie veröffentlichst.</p></div></div>
<?php admin_draft_banner($db, 'pages', '/?vorschau=1'); ?>
<?php
$allPages = rcb_site_pages();
$groups = rcb_nav_groups();
$hiddenPages = rcb_hidden_pages(admin_setting($db, 'pages_hidden', '[]'), admin_setting($db, 'live_enabled', '0'));
$customRows = $db->query('SELECT id, slug, title, published FROM pages ORDER BY title')->fetchAll();
$customBySlug = [];
foreach ($customRows as $row) { $customBySlug[(string)$row['slug']] = $row; }
$customMenu = admin_custom_menu($db);
$navOrder = admin_nav_order($db);
$form = static fn(string $action, array $fields, string $label, string $class = 'secondary', string $confirm = '') =>
    '<form method="post"' . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . '><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="' . h($action) . '">'
    . implode('', array_map(static fn($k, $v) => '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">', array_keys($fields), $fields))
    . '<button type="submit" class="' . h($class) . '">' . h($label) . '</button></form>';
$moveButtons = static function (string $key, ?int $pos, int $count) use ($form): string {
    if ($pos === null || $key === 'home') { return ''; }
    return '<span class="move-buttons">' . ($pos > 1 ? $form('page_move', ['slug' => $key, 'dir' => 'up'], '↑', 'secondary small') : '') . ($pos < $count - 1 ? $form('page_move', ['slug' => $key, 'dir' => 'down'], '↓', 'secondary small') : '') . '</span>';
};
// Karte einer Seite: Vorschau, Status, Bearbeiten, Veröffentlichen/Verbergen, ggf. Menü und Reihenfolge.
$pageCard = static function (string $key, ?int $pos = null, int $count = 0) use ($allPages, $hiddenPages, $customBySlug, $customMenu, $form, $moveButtons): string {
    $isCustom = str_starts_with($key, 'seite:');
    if ($isCustom) {
        $row = $customBySlug[substr($key, 6)];
        [$label, $url, $hideable, $hidden] = [(string)$row['title'], '/seite.php?slug=' . rawurlencode((string)$row['slug']), true, (int)$row['published'] !== 1];
    } else {
        [$label, $url, , $hideable] = $allPages[$key];
        $hidden = in_array($key, $hiddenPages, true);
    }
    $sep = str_contains($url, '?') ? '&' : '?';
    $html = '<article class="page-preview' . ($hidden ? ' is-hidden' : '') . '"><div class="page-preview-frame"><iframe src="' . h($url . ($hidden ? $sep . 'vorschau=1' : '')) . '" title="Vorschau ' . h($label) . '" loading="lazy" tabindex="-1" aria-hidden="true"></iframe></div><div class="page-preview-body">';
    $html .= '<div class="page-preview-title">' . ($pos !== null ? '<span class="page-pos">' . ($pos + 1) . '</span>' : '') . '<strong>' . h($label) . '</strong> ' . ($hidden ? '<span class="badge no">Verborgen</span>' : '<span class="badge yes">Online</span>') . ($isCustom ? ' <span class="badge">Eigene Seite</span>' : '') . '</div>';
    $html .= '<div class="form-actions"><a class="button" href="' . h($url . $sep . 'bearbeiten=1') . '">✎ Bearbeiten</a>';
    if ($hideable) {
        $html .= $hidden
            ? $form('page_visibility', ['slug' => $key, 'visible' => '1'], '⬆ Veröffentlichen', '')
            : $form('page_visibility', ['slug' => $key, 'visible' => '0'], 'Verbergen', 'secondary', $label . ' von der Website nehmen? Die Seite verschwindet aus dem Menü, die Inhalte bleiben erhalten.');
    }
    if ($isCustom) {
        $slug = substr($key, 6);
        $inMenu = in_array($slug, $customMenu, true);
        $html .= $form('custom_page_menu', ['slug' => $slug], $inMenu ? 'Aus dem Menü' : '+ Ins Menü');
        $html .= $form('delete_page', ['id' => (string)$customBySlug[$slug]['id']], 'Löschen', 'danger', 'Seite „' . $label . '“ mit allen Inhalten unwiderruflich löschen?');
    }
    $html .= $moveButtons($key, $pos, $count);
    return $html . '</div></div></article>';
};
?>
<h2>Menü</h2><p class="muted" style="margin-top:-6px">In dieser Reihenfolge erscheinen die Punkte im Menü. Mit ↑ ↓ verschieben, mit „Verbergen“ von der Website nehmen. Verborgene Seiten kannst du weiter bearbeiten und später wieder veröffentlichen.</p>
<div class="page-previews">
<?php foreach ($navOrder as $pos => $key): ?>
<?php if (isset($groups[$key])): [$groupLabel, $children] = $groups[$key]; ?>
<article class="page-preview page-group"><div class="page-preview-body">
<div class="page-preview-title"><span class="page-pos"><?= $pos + 1 ?></span><strong><?= h($groupLabel) ?></strong> <span class="badge">Untermenü</span></div>
<p class="muted">Klappt im Menü auf, eigene Seite gibt es keine. Enthält:</p>
<ul class="group-children"><?php foreach ($children as $child): $childHidden = in_array($child, $hiddenPages, true); ?><li><strong><?= h($allPages[$child][0]) ?></strong> <span class="badge <?= $childHidden ? 'no' : 'yes' ?>"><?= $childHidden ? 'Verborgen' : 'Online' ?></span> <a href="<?= h($allPages[$child][1]) ?>?bearbeiten=1">✎ Bearbeiten</a></li><?php endforeach; ?></ul>
<div class="form-actions"><?= $moveButtons($key, $pos, count($navOrder)) ?></div>
</div></article>
<?php else: echo $pageCard($key, $pos, count($navOrder)); endif; ?>
<?php endforeach; ?>
</div>
<h2 style="margin-top:30px">Unterseiten</h2><p class="muted" style="margin-top:-6px">Seiten im Untermenü „Strecken“.</p>
<div class="page-previews"><?php foreach ($groups as [, $children]) { foreach ($children as $child) { echo $pageCard($child); } } ?></div>
<h2 style="margin-top:30px">Eigene Seiten</h2><p class="muted" style="margin-top:-6px">Zusätzliche Seiten, z. B. Ausschreibungen oder Vorstand. Neue Seiten sind zuerst verborgen: mit „Bearbeiten“ füllen, dann veröffentlichen. Mit „+ Ins Menü“ erscheinen sie im Menü.</p>
<section class="card"><form class="inline-add" method="post" style="margin-top:0"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="custom_page_add"><label for="new_page_title">Neue Seite anlegen</label><div class="form-actions"><input id="new_page_title" name="title" required maxlength="180" placeholder="Titel, z. B. Vorstand"><label class="check" style="margin:0"><input type="checkbox" name="in_menu" value="1" checked> ins Menü</label><button type="submit">+ Seite anlegen</button></div></form></section>
<?php $customOutside = array_filter(array_keys($customBySlug), static fn($slug) => !in_array((string)$slug, $customMenu, true)); ?>
<?php if ($customOutside): ?><div class="page-previews" style="margin-top:16px"><?php foreach ($customOutside as $slug) { echo $pageCard('seite:' . $slug); } ?></div><?php elseif (!$customBySlug): ?><p class="muted">Noch keine eigenen Seiten angelegt.</p><?php else: ?><p class="muted">Alle eigenen Seiten stehen im Menü (siehe oben).</p><?php endif; ?>
<h2 style="margin-top:30px">Fußzeile &amp; Rechtliches</h2><p class="muted" style="margin-top:-6px">Impressum und Datenschutz müssen immer online bleiben.</p>
<div class="page-previews"><?php foreach (['impressum', 'datenschutz', 'haftungsausschluss'] as $slug) { echo $pageCard($slug); } ?></div>
<p class="muted" style="margin-top:22px">Die Klassen und Rennen des Reglements verwaltest du unter <a href="<?= h($script) ?>?page=reglement">📋 Reglement</a>.</p>
<?php elseif ($page === 'news'): ?>
<?php $item=$editItem ?: ['id'=>0,'title'=>'','summary'=>'','content'=>'','image_url'=>'','published'=>1,'expires_at'=>null]; $rows=$db->query('SELECT * FROM news ORDER BY created_at DESC, id DESC')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Neuigkeiten</h1><p>Eigene Meldungen stehen auf der Startseite immer ganz oben – darunter folgen die automatischen Hinweise auf neue Inhalte.</p></div></div>
<?php
$siteUpdates = $db->query('SELECT id, title, url, created_at FROM site_updates ORDER BY created_at DESC, id DESC LIMIT 100')->fetchAll();
$autoEnabled = admin_setting($db, 'auto_updates_enabled', '1') === '1';
$newsCount = (int)admin_setting($db, 'home_news_count', '6');
// Welche Einträge stehen gerade auf der Startseite? (gleiche Mischung wie dort)
$feed = [];
$nowStr = date('Y-m-d H:i:s');
$manualOn = [];
foreach ($db->query('SELECT id, expires_at FROM news WHERE published = 1 ORDER BY created_at DESC, id DESC')->fetchAll() as $n) { if (empty($n['expires_at']) || (string)$n['expires_at'] >= $nowStr) { $manualOn[] = 'n' . $n['id']; } }
$manualOn = array_slice($manualOn, 0, 20);
$autoDays = (int)admin_setting($db, 'auto_updates_days', '30');
$autoCutoff = $autoDays > 0 ? date('Y-m-d H:i:s', time() - $autoDays * 86400) : '';
foreach ($siteUpdates as $u) { if ($autoCutoff === '' || (string)$u['created_at'] >= $autoCutoff) { $feed[] = ['u' . $u['id'], (string)$u['created_at']]; } }
usort($feed, static fn($a, $b) => strcmp($b[1], $a[1]));
$onHome = array_merge($manualOn, array_column(array_slice($feed, 0, max(0, $newsCount - count($manualOn))), 0));
$expiryInfo = static function (?string $exp) use ($nowStr): array {
    if ($exp === null || $exp === '') { return ['dauerhaft', 'yes']; }
    if ($exp < $nowStr) { return ['abgelaufen am ' . date('d.m.Y', strtotime($exp)), 'no']; }
    $days = (int)ceil((strtotime(substr($exp, 0, 10)) - strtotime(date('Y-m-d'))) / 86400);
    return ['bis ' . date('d.m.Y', strtotime($exp)) . ($days <= 0 ? ' (heute letzter Tag)' : ' (noch ' . $days . ($days === 1 ? ' Tag)' : ' Tage)')), $days <= 3 ? 'warn' : 'yes'];
};
$csrf = '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
?>
<div class="columns"><section class="card"><h2><?= $editItem?'Neuigkeit bearbeiten':'Neuigkeit anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_news"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="title">Titel *</label><input id="title" name="title" required maxlength="180" value="<?= h($item['title']) ?>"><label for="summary">Kurztext</label><textarea id="summary" name="summary" maxlength="500" rows="3"><?= h($item['summary']) ?></textarea><label for="content">Meldung</label><textarea id="content" name="content" maxlength="30000" rows="10"><?= h($item['content']) ?></textarea><label for="image_url">Bildadresse (optional)</label><input id="image_url" name="image_url" type="url" maxlength="1000" placeholder="https://…" value="<?= h($item['image_url']) ?>"><?php $curExp = (string)($item['expires_at'] ?? ''); $curActive = $curExp !== '' && $curExp >= $nowStr; ?>
<fieldset class="expiry"><legend>Wie lange auf der Startseite?</legend>
<div class="expiry-chips">
<?php if ($editItem && $curActive): ?><label><input type="radio" name="expiry" value="keep" checked><span>Unverändert<small>bis <?= h(date('d.m.Y', strtotime($curExp))) ?></small></span></label><?php endif; ?>
<label><input type="radio" name="expiry" value="forever"<?= $editItem && $curExp === '' ? ' checked' : '' ?>><span>Dauerhaft<small>bis ich sie lösche</small></span></label>
<label><input type="radio" name="expiry" value="14d"><span>14 Tage<small>bis <?= h(date('d.m.', strtotime('+14 days'))) ?></small></span></label>
<label><input type="radio" name="expiry" value="28d"<?= !$editItem ? ' checked' : '' ?>><span>4 Wochen<small>bis <?= h(date('d.m.', strtotime('+28 days'))) ?></small></span></label>
<label><input type="radio" name="expiry" value="date"<?= $editItem && $curExp !== '' && !$curActive ? ' checked' : '' ?>><span>Bis Datum<small>selbst wählen</small></span></label>
</div>
<div class="expiry-date"><label for="expires_on">Angezeigt bis einschließlich</label><input id="expires_on" name="expires_on" type="date" min="<?= h(date('Y-m-d')) ?>" value="<?= h($curExp !== '' ? substr($curExp, 0, 10) : date('Y-m-d', strtotime('+7 days'))) ?>"></div>
<p class="hint" data-expiry-info>Danach verschwindet die Meldung von selbst von der Startseite. Sie bleibt hier gespeichert und lässt sich jederzeit verlängern.</p>
</fieldset>
<label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht</label><div class="form-actions"><button type="submit">Speichern</button><?php if($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=news">Abbrechen</a><?php endif; ?></div></form></section>
<section class="card"><h2>Eigene Neuigkeiten</h2><div class="table-wrap"><table class="table"><thead><tr><th>Meldung</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): [$expText, $expClass] = $expiryInfo($row['expires_at'] ?? null); ?><tr><td><strong><?= h($row['title']) ?></strong><br><span class="muted">vom <?= h(date('d.m.Y', strtotime((string)$row['created_at']) ?: time())) ?></span></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span><br><span class="badge <?= h($expClass) ?>"><?= h($expText) ?></span><?php if ($row['published'] && in_array('n' . $row['id'], $onHome, true)): ?><br><span class="muted">steht auf der Startseite</span><?php endif; ?></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=news&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" data-confirm="Diese Neuigkeit wirklich löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_news"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="3" class="empty">Noch keine Neuigkeiten angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<section class="card" style="margin-top:22px"><div class="card-head-row"><div><h2 style="margin:0">Automatische Hinweise „Neu auf der Seite“</h2><p class="muted" style="margin:6px 0 0">Entstehen beim Veröffentlichen von Seitenänderungen, Seiten, Reglement-Klassen sowie neuer Termine und Ergebnisse. Einzelne kannst du mit „Entfernen“ von der Startseite nehmen.</p></div>
<div class="form-actions"><form method="post"><?= $csrf ?><input type="hidden" name="action" value="toggle_auto_updates"><button type="submit" class="<?= $autoEnabled ? 'secondary' : '' ?>"><?= $autoEnabled ? 'Automatik ausschalten' : 'Automatik einschalten' ?></button></form><?php if ($siteUpdates): ?><form method="post" data-confirm="Alle automatischen Hinweise von der Startseite entfernen?"><?= $csrf ?><input type="hidden" name="action" value="clear_site_updates"><button type="submit" class="danger">Alle entfernen</button></form><?php endif; ?></div></div>
<p class="hint">Automatik ist <strong><?= $autoEnabled ? 'eingeschaltet' : 'ausgeschaltet – es entstehen keine neuen Hinweise' ?></strong>. Auf der Startseite stehen die <?= $newsCount ?> neuesten Einträge. Automatische Hinweise verschwinden dort <?= $autoDays > 0 ? 'nach <strong>' . $autoDays . ' Tagen</strong> von selbst' : '<strong>nicht</strong> von selbst' ?> (beides einstellbar unter Einstellungen).</p>
<div class="table-wrap"><table class="table"><thead><tr><th>Datum</th><th>Hinweis</th><th>Startseite</th><th>Aktion</th></tr></thead><tbody>
<?php foreach ($siteUpdates as $row): ?><tr><td><?= h(date('d.m.Y', strtotime((string)$row['created_at']) ?: time())) ?></td><td><strong><?= h($row['title']) ?></strong><?php if ($row['url'] !== ''): ?><br><a class="muted" href="<?= h($row['url']) ?>" target="_blank" rel="noopener"><?= h($row['url']) ?></a><?php endif; ?></td><td><?= in_array('u' . $row['id'], $onHome, true) ? '<span class="badge yes">sichtbar</span>' : '<span class="badge">nicht mehr</span>' ?></td><td class="actions"><form method="post"><?= $csrf ?><input type="hidden" name="action" value="delete_site_update"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Entfernen</button></form></td></tr><?php endforeach; ?>
<?php if (!$siteUpdates): ?><tr><td colspan="4" class="empty">Keine automatischen Hinweise vorhanden.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php elseif ($page === 'results'): ?>
<?php $item=$editItem ?: ['id'=>0,'event_id'=>0,'title'=>'','content'=>'','published'=>0]; $rows=$db->query('SELECT r.*, e.title AS event_title FROM results r LEFT JOIN events e ON e.id=r.event_id ORDER BY r.updated_at DESC, r.id DESC')->fetchAll(); $eventOptions=$db->query('SELECT id,title,event_date FROM events ORDER BY event_date DESC,id DESC')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Rennbetrieb</p><h1>Ergebnisse</h1><p>Ergebnislisten und Rennberichte veröffentlichen. Platzierungen können als Text eingefügt werden.</p></div></div>
<div class="columns"><section class="card"><h2><?= $editItem?'Ergebnis bearbeiten':'Ergebnis anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_result"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="title">Titel *</label><input id="title" name="title" required maxlength="180" value="<?= h($item['title']) ?>"><label for="event_id">Veranstaltung (optional)</label><select id="event_id" name="event_id"><option value="0">Keine Veranstaltung verknüpfen</option><?php foreach($eventOptions as $ev): ?><option value="<?= (int)$ev['id'] ?>"<?= (int)$item['event_id']===(int)$ev['id']?' selected':'' ?>><?= h($ev['title']) ?><?= $ev['event_date'] ? ' – '.h($ev['event_date']) : '' ?></option><?php endforeach; ?></select><label for="content">Ergebnis / Rennbericht</label><textarea id="content" name="content" maxlength="12000" rows="10" placeholder="Zum Beispiel: Platzierung, Fahrer, Runden oder Rennbericht"><?= h($item['content']) ?></textarea><p class="hint">Text wird sicher als Text ausgegeben. HTML oder Tabellen-Markup werden nicht ausgeführt.</p><label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlichen</label><div class="form-actions"><button type="submit">Ergebnis speichern</button><?php if($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=results">Abbrechen</a><?php endif; ?></div></form></section><section class="card"><h2>Gespeicherte Ergebnisse</h2><div class="table-wrap"><table class="table"><thead><tr><th>Ergebnis</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= h($row['title']) ?></strong><br><span class="muted"><?= h($row['event_title'] ?: 'Ohne Veranstaltung') ?></span></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=results&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" data-confirm="Dieses Ergebnis löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_result"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="4" class="empty">Noch keine Ergebnisse angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'media'): ?>
<?php $rows=$db->query('SELECT * FROM media ORDER BY uploaded_at DESC, id DESC')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Medien</p><h1>Bilder &amp; Videos</h1><p>Alle hochgeladenen Bilder und Clips. Videos werden direkt auf der Streckenseite im Bearbeiten-Modus hinzugefügt.</p></div></div>
<div class="columns"><section class="card"><h2>Bild hochladen</h2><form class="stack" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="upload_media"><label for="media_file">Bilddatei *</label><input id="media_file" name="media_file" type="file" accept="image/jpeg,image/png,image/webp" required><p class="hint">JPG, PNG oder WebP · maximal 8 MB. Bilder werden vor dem Speichern geprüft.</p><label for="title">Bildtitel</label><input id="title" name="title" maxlength="180" placeholder="z. B. Onroad-Strecke"><label for="category">Verwendung</label><select id="category" name="category"><option value="hero">Titelbild / Startseite</option><option value="logo">Vereinslogo</option><option value="onroad">Onroad-Strecke</option><option value="offroad">Offroad-Strecke</option><option value="event">Veranstaltungsbild</option></select><label for="description">Beschreibung / Alternativtext</label><textarea id="description" name="description" maxlength="1000" placeholder="Kurze Beschreibung des Bildes"></textarea><label class="check"><input type="checkbox" name="published" value="1"> Auf der Website veröffentlichen</label><div class="form-actions"><button type="submit">Bild hochladen</button></div></form></section>
<section class="card"><h2>Vorhandene Bilder &amp; Videos</h2><?php if ($rows): ?><div class="media-grid"><?php foreach($rows as $row): ?><article class="media-item"><?php if ($row['category'] === 'video'): ?><video src="/uploads/<?= h(rawurlencode($row['filename'])) ?>#t=0.5" preload="metadata" muted controls playsinline></video><?php else: ?><img src="/uploads/<?= h(rawurlencode($row['filename'])) ?>" alt="<?= h($row['description'] ?: $row['title']) ?>"><?php endif; ?><div class="media-item-body"><strong><?= h($row['title'] ?: $row['filename']) ?></strong><p class="muted"><?= h($row['description']) ?></p><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span><p class="hint">Verwendung: <?= h(['gallery'=>'Nicht mehr verwendet','seite'=>'Seiten-Editor','hero'=>'Titelbild','logo'=>'Vereinslogo','onroad'=>'Onroad','offroad'=>'Offroad','event'=>'Veranstaltung','video'=>'Video (Streckenseite)'][$row['category']] ?? $row['category']) ?></p><p class="hint">/uploads/<?= h($row['filename']) ?></p><form method="post" data-confirm="Dieses Bild und den Dateieintrag löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_media"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></div></article><?php endforeach; ?></div><?php else: ?><p class="empty">Noch keine Bilder hochgeladen.</p><?php endif; ?></section></div>
<?php elseif ($page === 'settings'): ?>
<?php $settingRows=$db->query('SELECT setting_key,setting_value FROM settings')->fetchAll(); $settings=[]; foreach($settingRows as $sr){$settings[$sr['setting_key']]=$sr['setting_value'];} ?>
<div class="page-head"><div><p class="eyebrow">System</p><h1>Einstellungen</h1><p>Grundlegende Angaben für die Plattform.</p></div></div><section class="card" style="max-width:760px"><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_settings"><label for="site_name">Name der Webseite</label><input id="site_name" name="site_name" maxlength="190" value="<?= h($settings['site_name']??'RC Bande Zwickau e.V.') ?>"><label for="site_description">Kurzbeschreibung</label><textarea id="site_description" name="site_description" maxlength="1000"><?= h($settings['site_description']??'') ?></textarea><label for="contact_email">Kontakt-E-Mail</label><input id="contact_email" name="contact_email" type="email" maxlength="190" value="<?= h($settings['contact_email']??'') ?>"><label for="contact_phone">Telefon</label><input id="contact_phone" name="contact_phone" maxlength="190" value="<?= h($settings['contact_phone']??'') ?>"><fieldset><legend>Startseite</legend><div class="field-row" style="grid-template-columns:repeat(2,minmax(0,1fr))"><div><label for="home_news_count">Anzahl Neuigkeiten</label><input id="home_news_count" name="home_news_count" type="number" min="0" max="20" required value="<?= h($settings['home_news_count'] ?? '6') ?>"></div><div><label for="home_events_count">Anzahl Termine</label><input id="home_events_count" name="home_events_count" type="number" min="0" max="20" required value="<?= h($settings['home_events_count'] ?? '3') ?>"></div></div><p class="hint">Wie viele der neuesten Neuigkeiten (eigene Meldungen und automatische Hinweise zusammen) und wie viele der nächsten Termine auf der Startseite stehen. 0 blendet den Bereich aus.</p><label for="auto_updates_days">Automatische Hinweise anzeigen für (Tage)</label><input id="auto_updates_days" name="auto_updates_days" type="number" min="0" max="365" required value="<?= h($settings['auto_updates_days'] ?? '30') ?>" style="max-width:160px"><p class="hint">Danach verschwindet ein Hinweis wie „Reglement wurde aktualisiert“ von selbst von der Startseite. 0 = unbegrenzt. Eigene Neuigkeiten bleiben davon unberührt.</p></fieldset>
<label class="check"><input type="checkbox" name="site_enabled" value="1"<?= checked($settings['site_enabled']??1) ?>> Webseite aktiv (deaktiviert den öffentlichen Bereich für Wartungsarbeiten)</label><label class="check"><input type="checkbox" name="live_enabled" value="1"<?= checked($settings['live_enabled']??0) ?>> Live-Timing veröffentlichen (Seite und Menüpunkt für Besucher sichtbar)</label><div class="form-actions"><button type="submit">Einstellungen speichern</button></div></form><p class="hint">Kontaktangaben und Wartungsmodus werden von den öffentlichen PHP-Seiten verwendet. Die Aktivierung von Live-Timing schaltet noch keine Zeitnahme-Schnittstelle frei.</p></section>
<?php elseif ($page === 'files'): ?>
<?php $reglementOnlyFiles = admin_editor_scope($currentUser) === 'reglement'; $fileTargets = admin_file_targets($db, $reglementOnlyFiles); $allFiles = $db->query('SELECT * FROM files ORDER BY page_key, sort_order, title')->fetchAll(); $byPage = []; foreach ($allFiles as $f) { if ($reglementOnlyFiles && !isset($fileTargets[$f['page_key']])) { continue; } $byPage[(string)$f['page_key']][] = $f; } $hf = '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'; ?>
<div class="page-head"><div><p class="eyebrow">Website</p><h1>Dateien</h1><p>Anträge, Satzung, Ausschreibungen und andere Dokumente. Jede Datei erscheint als Download-Kasten auf der Seite, der du sie zuordnest – z. B. den Aufnahmeantrag auf „Mitglied werden“.</p></div></div>
<div class="columns"><section class="card"><h2>Datei hochladen</h2><form class="stack" method="post" enctype="multipart/form-data"><?= $hf ?><input type="hidden" name="action" value="file_upload">
<label for="document">Datei (PDF, Word, Excel, OpenDocument – max. 20 MB)</label><input id="document" name="document" type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods">
<label for="file_title">Titel (leer = Dateiname)</label><input id="file_title" name="title" maxlength="190" placeholder="z. B. Aufnahmeantrag">
<label for="file_desc">Kurze Beschreibung (optional)</label><input id="file_desc" name="description" maxlength="500" placeholder="z. B. Ausdrucken, ausfüllen, unterschreiben und bei uns abgeben">
<label for="page_key">Auf welcher Seite anbieten?</label><select id="page_key" name="page_key" required><option value="">– Seite wählen –</option><?php foreach ($fileTargets as $key => $label): ?><option value="<?= h($key) ?>"<?= ($_GET['seite'] ?? '') === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
<button type="submit">Hochladen</button></form></section>
<section class="card"><h2>Vorhandene Dateien</h2><?php if (!$byPage): ?><p class="muted">Noch keine Dateien hochgeladen.</p><?php endif; ?>
<?php foreach ($byPage as $key => $list): ?><h3 style="margin:18px 0 8px"><?= h($fileTargets[$key] ?? 'Ohne gültige Seite (' . $key . ')') ?></h3><div class="table-wrap"><table class="table"><tbody>
<?php foreach ($list as $i => $f): $ext = strtoupper(pathinfo((string)$f['path'], PATHINFO_EXTENSION)); ?><tr><td><strong><?= h($f['title']) ?></strong><?php if ($f['description'] !== ''): ?><br><span class="hint"><?= h($f['description']) ?></span><?php endif; ?><br><span class="hint"><?= h($ext) ?> · <?= h((string)max(1, (int)round((int)$f['size'] / 1024))) ?> KB</span></td><td><span class="badge <?= (int)$f['published'] === 1 ? 'yes' : 'no' ?>"><?= (int)$f['published'] === 1 ? 'Sichtbar' : 'Verborgen' ?></span></td><td class="actions">
<a class="button secondary" href="<?= h($f['path']) ?>" target="_blank" rel="noopener">Ansehen</a>
<?php if ($i > 0): ?><form method="post"><?= $hf ?><input type="hidden" name="action" value="file_move"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="dir" value="up"><button class="secondary small" type="submit">↑</button></form><?php endif; ?>
<?php if ($i < count($list) - 1): ?><form method="post"><?= $hf ?><input type="hidden" name="action" value="file_move"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="dir" value="down"><button class="secondary small" type="submit">↓</button></form><?php endif; ?>
<form method="post"><?= $hf ?><input type="hidden" name="action" value="file_toggle"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="secondary" type="submit"><?= (int)$f['published'] === 1 ? 'Verbergen' : 'Zeigen' ?></button></form>
<form method="post" data-confirm="„<?= h($f['title']) ?>“ endgültig löschen?"><?= $hf ?><input type="hidden" name="action" value="file_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="danger" type="submit">Löschen</button></form>
</td></tr><?php endforeach; ?></tbody></table></div><?php endforeach; ?></section></div>
<?php elseif ($page === 'membership'): ?>
<?php $mc = rcb_membership_config_from_json(admin_setting($db, 'membership_config', '')); $memberFiles = $db->prepare('SELECT id, title, path, published FROM files WHERE page_key = ? ORDER BY sort_order, title'); $memberFiles->execute(['mitglied']); $memberFiles = $memberFiles->fetchAll(); $chk = static fn($v) => $v ? ' checked' : '';
$toggle = static fn(string $name, string $label, string $hint = '') => '<label class="check toggle-row"><input type="checkbox" name="' . $name . '" value="1"' . (!empty($mc[$name]) ? ' checked' : '') . '> <span><strong>' . h($label) . '</strong>' . ($hint !== '' ? '<br><span class="hint">' . h($hint) . '</span>' : '') . '</span></label>'; ?>
<div class="page-head"><div><p class="eyebrow">Verein</p><h1>Mitgliedschaft</h1><p>Alles rund um „Mitglied werden“. Jeder Punkt lässt sich mit seinem Häkchen ein- und ausblenden – ausgeblendete Inhalte bleiben gespeichert und sind mit einem Klick wieder da. Bereits eingegangene Anträge behalten ihren damaligen Wortlaut.</p></div><div class="form-actions"><a class="button secondary" href="/mitglied-werden.php" target="_blank" rel="noopener">Seite ansehen</a><a class="button secondary" href="<?= h($script) ?>?page=requests">✉ Eingegangene Anträge</a></div></div>
<form method="post" class="membership-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_membership">

<section class="card master-toggle<?= $mc['form_enabled'] ? '' : ' is-off' ?>"><?= $toggle('form_enabled', 'Online-Antrag auf der Seite anzeigen', 'Ausgeschaltet verschwindet das ganze Formular. Alles, was darunter eingestellt ist, bleibt gespeichert.') ?></section>

<div class="columns" style="margin-top:22px">
<section class="card"><h2>Teile des Formulars</h2>
<p class="hint">Immer dabei: Name, Vorname, Geburtstag, Anschrift, E-Mail und die Datenschutz-Zustimmung – sie werden für die Aufnahme bzw. aus rechtlichen Gründen gebraucht.</p>
<?= $toggle('allow_change', 'Änderungsmeldungen erlauben', 'Auswahl „Aufnahme“ oder „Änderung meiner Mitgliedsdaten“') ?>
<?= $toggle('show_member_no', 'Feld „Mitgliedernummer“', 'nur sichtbar, wenn Änderungsmeldungen erlaubt sind') ?>
<?= $toggle('show_phone', 'Feld „Telefon“') ?>
<?= $toggle('show_mobile', 'Feld „Mobil“') ?>
<?= $toggle('show_family', 'Frage „Sind bereits Familienmitglieder im Verein?“') ?>
<?= $toggle('show_guardian', 'Feld „Gesetzliche(r) Vertreter“ bei Minderjährigen', 'Pflicht, wenn das Mitglied unter 18 ist') ?>
<?= $toggle('show_guardian_text', 'Hinweis bei Minderjährigen') ?>
<textarea name="guardian_text" rows="2" aria-label="Hinweis bei Minderjährigen"><?= h($mc['guardian_text']) ?></textarea>
<?= $toggle('show_message', 'Feld „Bemerkung“') ?>
<?= $toggle('show_accept', 'Bestätigungs-Häkchen (Pflicht)') ?>
<textarea name="accept_text" rows="3" aria-label="Text des Bestätigungs-Häkchens"><?= h($mc['accept_text']) ?></textarea>
</section>
<section class="card"><h2>Bankverbindung</h2>
<?= $toggle('show_bank_page', 'Auf der Seite anzeigen') ?>
<?= $toggle('show_bank_print', 'Im ausgedruckten Antrag anzeigen') ?>
<label for="bank_holder">Kontoinhaber</label><input id="bank_holder" name="bank_holder" maxlength="190" value="<?= h($mc['bank_holder']) ?>">
<label for="bank_iban">IBAN</label><input id="bank_iban" name="bank_iban" maxlength="60" value="<?= h($mc['bank_iban']) ?>">
<label for="bank_bic">BIC</label><input id="bank_bic" name="bank_bic" maxlength="30" value="<?= h($mc['bank_bic']) ?>">
<label for="bank_purpose">Verwendungszweck</label><input id="bank_purpose" name="bank_purpose" maxlength="300" value="<?= h($mc['bank_purpose']) ?>">
<h3 style="margin-top:22px">Ausdruck</h3>
<?= $toggle('show_print_footer', 'Fußzeile im Ausdruck') ?>
<input name="print_footer" maxlength="300" value="<?= h($mc['print_footer']) ?>" aria-label="Fußzeile im Ausdruck">
<h3 style="margin-top:22px">Dateien auf „Mitglied werden“</h3>
<?php if (!$memberFiles): ?><p class="muted">Noch keine Dateien.</p><?php else: ?><ul class="plain-list"><?php foreach ($memberFiles as $mf): ?><li><a href="<?= h($mf['path']) ?>" target="_blank" rel="noopener"><?= h($mf['title']) ?></a><?= (int)$mf['published'] === 1 ? ' <span class="badge yes">sichtbar</span>' : ' <span class="badge no">verborgen</span>' ?></li><?php endforeach; ?></ul><?php endif; ?>
<p><a class="button secondary" href="<?= h($script) ?>?page=files&amp;seite=mitglied">📎 Dateien ein-/ausblenden, tauschen oder löschen</a></p>
<p class="hint">Enthält der Titel einer Datei den Namen einer Vereinsordnung (z. B. „Satzung“), wird sie im Formular daneben verlinkt.</p>
</section></div>

<section class="card" style="margin-top:22px"><h2>Jahresbeiträge</h2>
<?= $toggle('show_fees', 'Beitragsauswahl im Formular anzeigen') ?>
<p class="hint">Je Stufe: „sichtbar“ blendet sie ein/aus, „weg“ löscht sie. Neue Stufe in eine leere Zeile eintragen. Reihenfolge über die Zahl. Preise wie „90“ oder „7,50“; 0 = beitragsfrei.</p>
<div class="table-wrap"><table class="table"><thead><tr><th style="width:70px">Reihe</th><th>Bezeichnung</th><th style="width:110px">Preis (€)</th><th style="width:90px">Sichtbar</th><th style="width:80px">Löschen</th></tr></thead><tbody>
<?php $rowsFees = $mc['fees']; for ($n = 0; $n < 2; $n++) { $rowsFees[] = ['id' => '', 'label' => '', 'price' => '', 'visible' => true]; } foreach ($rowsFees as $i => $fee): $isNew = $fee['id'] === ''; ?>
<tr class="<?= $isNew ? 'new-row' : '' ?><?= !$fee['visible'] ? ' is-hidden-row' : '' ?>"><td><input type="number" name="fee_sort[<?= $i ?>]" value="<?= $i + 1 ?>" min="1" max="99" style="width:64px"></td><td><input type="hidden" name="fee_id[<?= $i ?>]" value="<?= h($fee['id']) ?>"><input name="fee_label[<?= $i ?>]" maxlength="190" value="<?= h($fee['label']) ?>" placeholder="<?= $isNew ? 'Neue Beitragsstufe …' : '' ?>"></td><td><input name="fee_price[<?= $i ?>]" maxlength="8" value="<?= h($fee['price']) ?>" placeholder="<?= $isNew ? 'z. B. 30' : '' ?>"></td><td><label class="check"><input type="checkbox" name="fee_visible[<?= $i ?>]" value="1"<?= $chk($fee['visible']) ?>> sichtbar</label></td><td><?php if (!$isNew): ?><label class="check"><input type="checkbox" name="fee_remove[<?= $i ?>]" value="1"> weg</label><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="sub-block"><?= $toggle('monthly_enabled', 'Monatliche Zahlung anbieten') ?>
<div class="form-row-2"><div><label for="monthly_label">Text dazu</label><input id="monthly_label" name="monthly_label" maxlength="190" value="<?= h($mc['monthly_label']) ?>"></div>
<div><label for="monthly_fee">Gilt nur für</label><select id="monthly_fee" name="monthly_fee"><option value="">alle Beitragsstufen</option><?php foreach ($mc['fees'] as $fee): ?><option value="<?= h($fee['id']) ?>"<?= $mc['monthly_fee'] === $fee['id'] ? ' selected' : '' ?>><?= h($fee['label']) ?></option><?php endforeach; ?></select></div></div></div>
<div class="sub-block"><?= $toggle('show_fee_note', 'Hinweis unter den Beiträgen') ?><textarea name="fee_note" rows="2" aria-label="Hinweis unter den Beiträgen"><?= h($mc['fee_note']) ?></textarea></div>
</section>

<section class="card" style="margin-top:22px"><h2>Vereinsordnungen</h2>
<?= $toggle('show_rules', 'Fragen zu den Vereinsordnungen anzeigen', 'je Ordnung: „… gelesen und verstanden – Ja / Nein“') ?>
<div class="table-wrap"><table class="table"><thead><tr><th style="width:70px">Reihe</th><th>Name</th><th style="width:90px">Sichtbar</th><th style="width:80px">Löschen</th></tr></thead><tbody>
<?php $rowsRules = $mc['rules']; for ($n = 0; $n < 2; $n++) { $rowsRules[] = ['name' => '', 'visible' => true]; } foreach ($rowsRules as $i => $rule): $isNew = $i >= count($mc['rules']); ?>
<tr class="<?= $isNew ? 'new-row' : '' ?><?= !$rule['visible'] ? ' is-hidden-row' : '' ?>"><td><input type="number" name="rule_sort[<?= $i ?>]" value="<?= $i + 1 ?>" min="1" max="99" style="width:64px"></td><td><input name="rule_name[<?= $i ?>]" maxlength="120" value="<?= h($rule['name']) ?>" placeholder="<?= $isNew ? 'z. B. Platzordnung' : '' ?>"></td><td><label class="check"><input type="checkbox" name="rule_visible[<?= $i ?>]" value="1"<?= $chk($rule['visible']) ?>> sichtbar</label></td><td><?php if (!$isNew): ?><label class="check"><input type="checkbox" name="rule_remove[<?= $i ?>]" value="1"> weg</label><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="sub-block"><?= $toggle('show_rules_note', 'Hinweis unter den Ordnungen') ?><textarea name="rules_note" rows="2" aria-label="Hinweis unter den Ordnungen"><?= h($mc['rules_note']) ?></textarea></div>
</section>
<div class="form-actions sticky-save"><button type="submit">Speichern</button><span class="hint">Die Texte oben auf der Seite („So wirst du Mitglied“ usw.) änderst du direkt auf der Seite im Bearbeiten-Modus.</span></div>
</form>
<?php elseif ($page === 'requests'): ?>
<?php $requests = $db->query('SELECT * FROM membership_requests ORDER BY created_at DESC, id DESC')->fetchAll(); $hf = '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'; ?>
<div class="page-head"><div><p class="eyebrow">Verein</p><h1>Mitgliedsanträge</h1><p>Aufnahmeanträge und Änderungsmeldungen aus dem Online-Formular auf „Mitglied werden“. Jeder Antrag geht zusätzlich per E-Mail an <strong><?= h(admin_setting($db, 'contact_email', '')) ?: 'die Kontakt-E-Mail (unter Einstellungen eintragen)' ?></strong>.</p></div></div>
<?php if (!$requests): ?><section class="card"><p class="muted">Noch keine Anfragen.</p></section><?php endif; ?>
<?php foreach ($requests as $r): ?><section class="card request-card<?= $r['status'] === 'neu' ? ' is-new' : '' ?>"><div class="card-head-row"><div><h2 style="margin:0"><?= h($r['first_name'] . ' ' . $r['last_name']) ?><?php if ((string)$r['interest'] === 'Aufnahmeantrag' || (string)$r['interest'] === 'Änderungsmeldung'): ?> <span class="badge <?= $r['interest'] === 'Aufnahmeantrag' ? 'yes' : 'no' ?>"><?= h($r['interest']) ?></span><?php endif; ?></h2><p class="hint" style="margin:4px 0 0"><?= h(date('d.m.Y H:i', strtotime((string)$r['created_at']) ?: time())) ?> Uhr · <?= $r['status'] === 'neu' ? '<strong>neu</strong>' : 'erledigt' ?><?= (int)$r['mail_sent'] === 1 ? ' · E-Mail versendet' : ' · <span style="color:var(--amber)">E-Mail konnte nicht versendet werden</span>' ?></p></div>
<div class="form-actions"><form method="post"><?= $hf ?><input type="hidden" name="action" value="request_status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="<?= $r['status'] === 'neu' ? '' : 'secondary' ?>" type="submit"><?= $r['status'] === 'neu' ? '✓ Als erledigt markieren' : 'Wieder öffnen' ?></button></form><form method="post" data-confirm="Anfrage von <?= h($r['first_name'] . ' ' . $r['last_name']) ?> endgültig löschen?"><?= $hf ?><input type="hidden" name="action" value="request_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="danger" type="submit">Löschen</button></form></div></div>
<?php if (rcb_membership_details($r)): ?><dl class="request-data"><?php foreach (rcb_membership_summary($r) as [$label, $value]): ?><dt><?= h($label) ?></dt><dd><?= $label === 'E-Mail' ? '<a href="mailto:' . h($value) . '">' . h($value) . '</a>' : nl2br(h($value)) ?></dd><?php endforeach; ?></dl>
<?php else: ?><dl class="request-data"><dt>E-Mail</dt><dd><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></dd><?php foreach (['phone' => 'Telefon', 'birth_date' => 'Geburtsdatum', 'address' => 'Anschrift', 'interest' => 'Interesse', 'vehicle' => 'Fahrzeug / Klasse'] as $field => $label): if ((string)$r[$field] === '') { continue; } ?><dt><?= h($label) ?></dt><dd><?= h($field === 'birth_date' ? (date('d.m.Y', strtotime((string)$r[$field])) ?: $r[$field]) : $r[$field]) ?></dd><?php endforeach; ?><?php if (trim((string)$r['message']) !== ''): ?><dt>Nachricht</dt><dd><?= nl2br(h($r['message'])) ?></dd><?php endif; ?></dl><?php endif; ?></section>
<?php endforeach; ?>
<p class="hint">Datenschutz: Bitte Anfragen löschen, sobald sie erledigt sind und nicht mehr gebraucht werden (z. B. nach der Aufnahme oder einer Absage).</p>
<?php elseif ($page === 'users'): ?>
<?php $rows=$db->query('SELECT id,username,email,display_name,role,permissions,active,created_at FROM users ORDER BY id')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Sicherheit</p><h1>Benutzerverwaltung</h1><p>Administratorkonten erstellen, einladen, deaktivieren oder entfernen.</p></div></div>
<?php $flashLink = $_SESSION['token_link'] ?? null; unset($_SESSION['token_link']); if (is_array($flashLink)): ?>
<section class="card link-flash"><h2><?= $flashLink['kind'] === 'invite' ? '✉ Einladungslink' : '🔑 Link zum Passwort-Zurücksetzen' ?><?= $flashLink['label'] !== '' ? ' – ' . h($flashLink['label']) : '' ?></h2>
<p>Schick diesen Link z. B. per WhatsApp, Signal oder E-Mail. Er funktioniert <strong>nur einmal</strong> und nur bis <strong><?= h(date('d.m.Y H:i', (int)$flashLink['expires'])) ?> Uhr</strong>. <strong>Er wird nur jetzt angezeigt</strong> – bei Bedarf einfach einen neuen erstellen.</p>
<div class="form-actions"><input id="flash-link" class="link-field" readonly value="<?= h($flashLink['url']) ?>"><button type="button" data-copy="flash-link">Kopieren</button></div></section>
<?php endif; ?>
<?php if (($currentUser['role'] ?? '') === 'superadmin' && $editId > 0): $eu = $db->prepare('SELECT id, username, email, display_name, role, permissions FROM users WHERE id = ? LIMIT 1'); $eu->execute([$editId]); $eu = $eu->fetch(); if ($eu): $euPerms = admin_permissions($eu); ?>
<section class="card" id="bearbeiten" style="margin-bottom:22px;border-color:#f6c76b"><h2>✎ <?= h(admin_user_label($eu)) ?> bearbeiten</h2>
<form class="stack" method="post" action="<?= h($script) ?>?page=users"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="id" value="<?= (int)$eu['id'] ?>">
<label for="eu_name">Name (Vor- und Nachname)</label><input id="eu_name" name="display_name" required maxlength="190" value="<?= h($eu['display_name']) ?>">
<?php if ($eu['role'] === 'superadmin'): ?><p class="hint">Hauptadmin – hat immer Zugriff auf alle Bereiche.</p>
<?php else: ?><fieldset class="stack"><legend>Freigegebene Bereiche</legend><?php foreach (admin_permission_labels() as $perm=>$permLabel): ?><label class="check"><input type="checkbox" name="permissions[]" value="<?= h($perm) ?>"<?= in_array($perm, $euPerms, true) ? ' checked' : '' ?>> <?= h($permLabel) ?></label><?php endforeach; ?><p class="hint">„Reglement“ erlaubt nur das Regelwerk. „Seiten“ schließt das Reglement mit ein.</p></fieldset><?php endif; ?>
<div class="form-actions"><button type="submit">Speichern</button><a class="button secondary" href="<?= h($script) ?>?page=users">Abbrechen</a></div></form></section>
<?php endif; endif; ?>
<div class="columns"><?php if (($currentUser['role'] ?? '') === 'superadmin'): ?><section class="card"><h2>✉ Neuen Admin einladen</h2><p class="hint">Erstellt einen Link, mit dem die Person ihren Zugang selbst anlegt (Benutzername und Passwort wählt sie selbst). Der Link funktioniert nur einmal und nur mit den hier gewählten Freigaben.</p>
<form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create_invite">
<label for="invite_note">Name der Person</label><input id="invite_note" name="note" required maxlength="190" placeholder="z. B. Thomas Thamm"><p class="hint">Wird als Klarname beim Zugang angezeigt, egal welchen Benutzernamen die Person wählt.</p>
<fieldset class="stack"><legend>Freigegebene Bereiche</legend><?php foreach (admin_permission_labels() as $perm=>$permLabel): ?><label class="check"><input type="checkbox" name="permissions[]" value="<?= h($perm) ?>"> <?= h($permLabel) ?></label><?php endforeach; ?></fieldset>
<label for="invite_days">Link gültig für</label><select id="invite_days" name="days"><option value="1">1 Tag</option><option value="3">3 Tage</option><option value="7" selected>7 Tage</option><option value="14">14 Tage</option></select>
<button type="submit">Einladungslink erstellen</button></form></section>
<?php endif; ?><section class="card"><h2>Vorhandene Benutzer</h2><div class="table-wrap"><table class="table"><thead><tr><th>Benutzer</th><th>Rolle / Rechte</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= h(trim((string)$row['display_name']) !== '' ? $row['display_name'] : $row['username']) ?></strong><br><span class="hint">Benutzer: <?= h($row['username']) ?></span><br><span class="muted"><?= h($row['email']) ?></span><br><span class="hint">Seit <?= h($row['created_at']) ?></span></td><td><strong><?= ($row['role'] === 'superadmin') ? 'Hauptadmin' : 'Unteradmin' ?></strong><br><span class="hint"><?= h($row['role'] === 'superadmin' ? 'Alle Bereiche' : implode(', ', array_map(static fn($p) => admin_permission_labels()[$p] ?? $p, admin_permissions($row)))) ?></span></td><td><span class="badge <?= $row['active']?'yes':'no' ?>"><?= $row['active']?'Aktiv':'Deaktiviert' ?></span></td><td class="actions"><?php if (($currentUser['role'] ?? '') === 'superadmin'): ?><a class="button secondary" href="<?= h($script) ?>?page=users&amp;edit=<?= (int)$row['id'] ?>#bearbeiten">✎ Bearbeiten</a><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create_reset_link"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="secondary" type="submit" title="Erstellt einen Einmal-Link, mit dem dieser Benutzer ein neues Passwort festlegt (24 Stunden gültig)">🔑 Passwort-Link</button></form><?php endif; ?><?php if((int)$row['id'] !== (int)$currentUser['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="secondary" type="submit"><?= $row['active']?'Deaktivieren':'Aktivieren' ?></button></form><form method="post" data-confirm="Diesen Benutzer unwiderruflich löschen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form><?php else: ?><span class="hint">Aktuelle Sitzung</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<?php if (($currentUser['role'] ?? '') === 'superadmin'): ?>
<?php $openLinks = $db->prepare('SELECT t.id, t.kind, t.permissions, t.note, t.expires_at, u.username, u.display_name FROM admin_tokens t LEFT JOIN users u ON u.id = t.user_id WHERE t.used_at IS NULL AND t.expires_at >= ? ORDER BY t.expires_at'); $openLinks->execute([time()]); $openLinks = $openLinks->fetchAll(); ?>
<div style="margin-top:22px"><section class="card"><h2>Offene Links</h2><?php if (!$openLinks): ?><p class="muted">Keine offenen Einladungen oder Passwort-Links.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Art</th><th>Für</th><th>Gültig bis</th><th></th></tr></thead><tbody>
<?php foreach ($openLinks as $link): ?><tr><td><?= $link['kind'] === 'invite' ? '✉ Einladung' : '🔑 Passwort' ?></td><td><?= h($link['kind'] === 'reset' ? ($link['username'] !== null ? admin_user_label($link) : '–') . ($link['note'] === 'Passwort vergessen (E-Mail)' ? ' (per E-Mail angefordert)' : '') : ($link['note'] !== '' ? $link['note'] : '–')) ?><?php if ($link['kind'] === 'invite'): ?><br><span class="hint"><?= h(implode(', ', array_intersect_key(admin_permission_labels(), array_flip((array)json_decode((string)$link['permissions'], true))))) ?></span><?php endif; ?></td><td><?= h(date('d.m.Y H:i', (int)$link['expires_at'])) ?></td><td><form method="post" data-confirm="Diesen Link ungültig machen?"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="revoke_token"><input type="hidden" name="id" value="<?= (int)$link['id'] ?>"><button class="danger" type="submit">Widerrufen</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?><p class="hint">Aus Sicherheitsgründen wird ein Link nur direkt nach dem Erstellen angezeigt. Ist er verloren gegangen: widerrufen und neu erstellen.</p></section></div>
<?php endif; ?>
<?php elseif ($page === 'logs'): ?>
<?php $rows=$db->query('SELECT logs.id,logs.action,logs.details,logs.created_at,users.username FROM logs LEFT JOIN users ON users.id=logs.user_id ORDER BY logs.id DESC LIMIT 200')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Sicherheit</p><h1>Aktionsprotokoll</h1><p>Die letzten 200 Anmeldungen und Verwaltungsaktionen.</p></div></div><section class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Zeit</th><th>Benutzer</th><th>Aktion</th><th>Details</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= h($row['created_at']) ?></td><td><?= h($row['username']??'System') ?></td><td><?= h($row['action']) ?></td><td><?= h($row['details']) ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="4" class="empty">Noch keine Einträge vorhanden.</td></tr><?php endif; ?></tbody></table></div></section>
<?php else: ?>
<div class="page-head"><div><p class="eyebrow">Admin</p><h1>Seite nicht gefunden</h1><p>Dieser Menüpunkt existiert nicht.</p></div></div><a class="button" href="<?= h($script) ?>?page=dashboard">Zur Übersicht</a>
<?php endif; ?>
</main></div>
<?php endif; ?>
</body></html>
