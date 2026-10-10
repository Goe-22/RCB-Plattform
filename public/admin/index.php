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
$storage = $root . '/storage';
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
    http_response_code(500);
    exit('Speicherverzeichnis konnte nicht angelegt werden. Bitte Hosting prüfen.');
}
$dbFile = $storage . '/database.sqlite';
try {
    $db = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT DEFAULT '',
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'admin',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_value TEXT DEFAULT '',
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        event_date TEXT DEFAULT '',
        start_time TEXT DEFAULT '',
        location TEXT DEFAULT '',
        published INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS tracks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT DEFAULT '',
        surface TEXT DEFAULT '',
        length TEXT DEFAULT '',
        classes TEXT DEFAULT '',
        electricity INTEGER DEFAULT 0,
        toilet INTEGER DEFAULT 0,
        paddock INTEGER DEFAULT 0,
        guest_drivers INTEGER DEFAULT 0,
        published INTEGER DEFAULT 0,
        show_status INTEGER DEFAULT 0,
        status TEXT DEFAULT 'closed',
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS sponsors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        url TEXT DEFAULT '',
        description TEXT DEFAULT '',
        published INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS pages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        content TEXT DEFAULT '',
        published INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        title TEXT DEFAULT '',
        description TEXT DEFAULT '',
        category TEXT DEFAULT 'gallery',
        mime_type TEXT DEFAULT '',
        published INTEGER NOT NULL DEFAULT 0,
        uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        details TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash TEXT NOT NULL,
        attempted_at INTEGER NOT NULL
    )");
    @chmod($dbFile, 0640);
} catch (Throwable $e) {
    error_log('RCB admin database error: ' . $e->getMessage());
    http_response_code(500);
    exit('Die Admin-Datenbank konnte nicht geöffnet werden. Bitte PHP mit PDO_SQLite aktivieren und Schreibrechte für storage/ setzen.');
}

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
function log_action(PDO $db, ?int $userId, string $action, string $details = ''): void {
    $stmt = $db->prepare('INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, (function_exists('mb_substr') ? mb_substr($details, 0, 500) : substr($details, 0, 500))]);
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

$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$setupMode = $userCount === 0;
$error = '';
$notice = trim((string)($_GET['notice'] ?? ''));
$page = preg_replace('/[^a-z]/', '', (string)($_GET['page'] ?? 'dashboard')) ?: 'dashboard';
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($setupMode && $action === 'initial_setup') {
        $username = post_string('username', 60);
        $email = post_string('email', 190);
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
            $error = 'Der Benutzername muss 3–60 Zeichen haben (Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich).';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        } elseif (strlen($password) < 12) {
            $error = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, active) VALUES (?, ?, ?, ?, 1)');
            try {
                $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
                $newId = (int)$db->lastInsertId();
                log_action($db, $newId, 'initial_setup', 'Erstes Administratorkonto erstellt');
                session_regenerate_id(true);
                $_SESSION['admin_user_id'] = $newId;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_role'] = 'admin';
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
            $stmt = $db->prepare('SELECT id, username, password_hash, role, active FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && (int)$user['active'] === 1 && password_verify($password, $user['password_hash'])) {
                $db->prepare('DELETE FROM admin_login_attempts WHERE ip_hash = ?')->execute([$ipHash]);
                session_regenerate_id(true);
                $_SESSION['admin_user_id'] = (int)$user['id'];
                $_SESSION['admin_username'] = (string)$user['username'];
                $_SESSION['admin_role'] = (string)$user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                log_action($db, (int)$user['id'], 'login', 'Anmeldung erfolgreich');
                redirect_to('dashboard', 'Willkommen zurück');
            }
            $db->prepare('INSERT INTO admin_login_attempts (ip_hash, attempted_at) VALUES (?, ?)')->execute([$ipHash, time()]);
            $error = 'Anmeldung fehlgeschlagen. Bitte Zugangsdaten prüfen.';
        }
    } elseif (!empty($_SESSION['admin_user_id'])) {
        $uid = (int)$_SESSION['admin_user_id'];
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
                $values = [post_string('title', 180), post_string('description', 5000), post_string('event_date', 10), post_string('start_time', 5), post_string('location', 180), isset($_POST['published']) ? 1 : 0];
                if ($values[0] === '') { throw new RuntimeException('Bitte einen Veranstaltungstitel eingeben.'); }
                if ($id > 0) {
                    $values[] = $id;
                    $db->prepare('UPDATE events SET title=?, description=?, event_date=?, start_time=?, location=?, published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($values);
                } else {
                    $db->prepare('INSERT INTO events (title, description, event_date, start_time, location, published) VALUES (?, ?, ?, ?, ?, ?)')->execute($values);
                }
                log_action($db, $uid, 'event_saved', $values[0]);
                redirect_to('events', 'Termin gespeichert');
            }
            if ($action === 'delete_event') {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare('DELETE FROM events WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'event_deleted', 'Termin-ID ' . $id);
                redirect_to('events', 'Termin gelöscht');
            }
            if ($action === 'save_track') {
                $id = (int)($_POST['id'] ?? 0);
                $values = [post_string('name', 180), post_string('description', 5000), post_string('surface', 100), post_string('length', 100), post_string('classes', 500), isset($_POST['electricity']) ? 1 : 0, isset($_POST['toilet']) ? 1 : 0, isset($_POST['paddock']) ? 1 : 0, isset($_POST['guest_drivers']) ? 1 : 0, isset($_POST['published']) ? 1 : 0, post_string('status', 20)];
                if ($values[0] === '') { throw new RuntimeException('Bitte einen Streckennamen eingeben.'); }
                if (!in_array($values[10], ['open','closed','maintenance'], true)) { $values[10] = 'closed'; }
                if ($id > 0) {
                    $values[] = $id;
                    $db->prepare('UPDATE tracks SET name=?, description=?, surface=?, length=?, classes=?, electricity=?, toilet=?, paddock=?, guest_drivers=?, published=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($values);
                } else {
                    $db->prepare('INSERT INTO tracks (name, description, surface, length, classes, electricity, toilet, paddock, guest_drivers, published, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($values);
                }
                log_action($db, $uid, 'track_saved', $values[0]);
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
                $db->prepare('DELETE FROM pages WHERE id=?')->execute([$id]);
                log_action($db, $uid, 'page_deleted', 'Seiten-ID ' . $id);
                redirect_to('pages', 'Seite gelöscht');
            }
            if ($action === 'upload_media') {
                if (!isset($_FILES['media_file']) || !is_array($_FILES['media_file'])) {
                    throw new RuntimeException('Bitte eine Bilddatei auswählen.');
                }
                $file = $_FILES['media_file'];
                if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Der Upload ist fehlgeschlagen. Bitte Datei und maximale Uploadgröße prüfen.');
                }
                if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 8 * 1024 * 1024) {
                    throw new RuntimeException('Das Bild darf maximal 8 MB groß sein.');
                }
                if (!is_uploaded_file((string)$file['tmp_name'])) {
                    throw new RuntimeException('Die hochgeladene Datei wurde nicht akzeptiert.');
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
                $uploads = $root . '/public/uploads';
                if (!is_dir($uploads) && !mkdir($uploads, 0755, true) && !is_dir($uploads)) {
                    throw new RuntimeException('Der Upload-Ordner konnte nicht angelegt werden.');
                }
                $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                if (!move_uploaded_file((string)$file['tmp_name'], $uploads . '/' . $filename)) {
                    throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
                }
                $title = post_string('title', 180);
                $description = post_string('description', 1000);
                $published = isset($_POST['published']) ? 1 : 0;
                try {
                    $db->prepare('INSERT INTO media (filename,title,description,category,mime_type,published) VALUES (?,?,?,?,?,?)')
                       ->execute([$filename, $title, $description, 'gallery', $mime, $published]);
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
                    if (preg_match('/^[a-f0-9]{32}\\.(jpg|png|webp)$/', $filename)) {
                        @unlink($root . '/public/uploads/' . $filename);
                    }
                    $db->prepare('DELETE FROM media WHERE id=?')->execute([$id]);
                    log_action($db, $uid, 'media_deleted', $filename);
                }
                redirect_to('media', 'Bild gelöscht');
            }
            if ($action === 'save_settings') {
                $allowed = ['site_name','site_description','contact_email','contact_phone','site_enabled'];
                $stmt = $db->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value, updated_at=CURRENT_TIMESTAMP');
                foreach ($allowed as $key) {
                    $value = $key === 'site_enabled' ? (isset($_POST[$key]) ? '1' : '0') : post_string($key, $key === 'site_description' ? 1000 : 190);
                    if ($key === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Bitte eine gültige Kontakt-E-Mail eingeben.'); }
                    $stmt->execute([$key, $value]);
                }
                log_action($db, $uid, 'settings_saved', 'Seiteneinstellungen geändert');
                redirect_to('settings', 'Einstellungen gespeichert');
            }
            if ($action === 'create_user') {
                $username = post_string('username', 60);
                $email = post_string('email', 190);
                $password = (string)($_POST['password'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) { throw new RuntimeException('Der Benutzername muss 3–60 Zeichen haben.'); }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.'); }
                if (strlen($password) < 12) { throw new RuntimeException('Das Passwort muss mindestens 12 Zeichen lang sein.'); }
                $db->prepare('INSERT INTO users (username,email,password_hash,role,active) VALUES (?,?,?,\'admin\',1)')->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT)]);
                log_action($db, $uid, 'user_created', $username);
                redirect_to('users', 'Benutzer angelegt');
            }
            if ($action === 'toggle_user') {
                $target = (int)($_POST['id'] ?? 0);
                if ($target === $uid) { throw new RuntimeException('Das eigene Konto kann hier nicht deaktiviert werden.'); }
                $db->prepare('UPDATE users SET active=CASE WHEN active=1 THEN 0 ELSE 1 END WHERE id=?')->execute([$target]);
                log_action($db, $uid, 'user_toggled', 'Benutzer-ID ' . $target);
                redirect_to('users', 'Benutzerstatus geändert');
            }
            if ($action === 'delete_user') {
                $target = (int)($_POST['id'] ?? 0);
                if ($target === $uid) { throw new RuntimeException('Das eigene Konto kann nicht gelöscht werden.'); }
                $db->prepare('DELETE FROM users WHERE id=?')->execute([$target]);
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
    $stmt = $db->prepare('SELECT id, username, email, role, active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['admin_user_id']]);
    $currentUser = $stmt->fetch();
    if (!$currentUser || (int)$currentUser['active'] !== 1) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
    } else {
        $_SESSION['admin_username'] = $currentUser['username'];
    }
}
function fetch_one(PDO $db, string $table, int $id): ?array {
    $allowed = ['events','tracks','sponsors','pages'];
    if (!in_array($table, $allowed, true) || $id < 1) return null;
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
$editId = (int)($_GET['edit'] ?? 0);
$editItem = null;
if ($loggedIn && $editId > 0) {
    $tableForEdit = ['events'=>'events','tracks'=>'tracks','sponsors'=>'sponsors','pages'=>'pages'][$page] ?? '';
    if ($tableForEdit !== '') $editItem = fetch_one($db, $tableForEdit, $editId);
}
$counts = [];
if ($loggedIn) {
    foreach (['events'=>'Termine','tracks'=>'Strecken','sponsors'=>'Sponsoren','pages'=>'Seiten','users'=>'Benutzer','media'=>'Bilder'] as $table=>$label) {
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
.media-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.media-item{overflow:hidden;border:1px solid var(--line);border-radius:10px;background:var(--panel2)}.media-item img{display:block;width:100%;height:160px;object-fit:cover;background:#111}.media-item-body{padding:12px}.media-item-body form{margin-top:10px}.media-item-body .hint{overflow-wrap:anywhere}
:root{color-scheme:dark;--bg:#101114;--panel:#191b20;--panel2:#202229;--text:#f4f4f5;--muted:#a1a1aa;--line:#34363d;--red:#d71920;--red2:#b8141a;--green:#78dba9;--amber:#f6c76b}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a{color:#ff8c91;text-decoration:none}a:hover{text-decoration:underline}button,input,textarea,select{font:inherit}button,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 15px;border:0;border-radius:9px;background:var(--red);color:white;font-weight:750;cursor:pointer;text-decoration:none}button:hover,.button:hover{background:var(--red2);text-decoration:none}.button.secondary,button.secondary{background:#30323a;color:var(--text)}.button.danger,button.danger{background:#702126}.wrap{width:min(1400px,100%);margin:auto;padding:24px}.login-wrap{min-height:100vh;display:grid;place-items:center;padding:22px;background:radial-gradient(ellipse at top,rgba(215,25,32,.18),transparent 48%),var(--bg)}.login-card{width:min(100%,480px);padding:clamp(24px,5vw,42px);background:var(--panel);border:1px solid var(--line);border-radius:18px;box-shadow:0 24px 70px #0006}.brand-mark{display:grid;place-items:center;width:58px;height:58px;margin-bottom:20px;border-radius:14px;background:var(--red);font-weight:950;font-size:20px}.eyebrow{margin:0 0 6px;color:#ff8c91;font-size:12px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}h1,h2,h3{line-height:1.2}h1{font-size:clamp(27px,4vw,36px);margin:0 0 10px}h2{font-size:20px;margin:0 0 12px}h3{font-size:16px;margin:0 0 10px}.muted,.intro{color:var(--muted)}.intro{margin:0 0 25px}form.stack{display:grid;gap:10px}label{display:block;font-size:13px;font-weight:750;margin:8px 0 5px}input,textarea,select{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:9px;background:#101114;color:var(--text);outline:none}textarea{min-height:140px;resize:vertical}input:focus,textarea:focus,select:focus{border-color:var(--red);box-shadow:0 0 0 3px #d7192030}.check{display:flex;align-items:center;gap:9px;margin:12px 0;color:#dedee2}.check input{width:auto}.alert{margin:0 0 18px;padding:12px 14px;border:1px solid #7c3439;border-radius:9px;background:#381b1e;color:#ffdadd}.notice{margin:0 0 18px;padding:12px 14px;border:1px solid #286746;border-radius:9px;background:#142a20;color:#baf3d2}.admin-top{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 24px;border-bottom:1px solid var(--line);background:#15161a;position:sticky;top:0;z-index:5}.brand{display:flex;align-items:center;gap:12px;font-weight:850}.brand .brand-mark{width:42px;height:42px;margin:0;border-radius:10px;font-size:15px}.top-actions{display:flex;align-items:center;gap:12px}.shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:calc(100vh - 75px)}.sidebar{padding:20px 14px;border-right:1px solid var(--line);background:#15161a}.nav-label{padding:10px 12px;color:#777983;font-size:11px;letter-spacing:.12em;font-weight:850;text-transform:uppercase}.nav{display:grid;gap:4px}.nav a{display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:9px;color:#d4d4d8}.nav a.active,.nav a:hover{background:#2a1a1d;color:#fff;text-decoration:none}.nav a.active{box-shadow:inset 3px 0 var(--red)}main.content{min-width:0;padding:clamp(18px,3vw,36px)}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:24px}.page-head p{margin:0;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card{min-width:0;padding:19px;border:1px solid var(--line);border-radius:13px;background:var(--panel)}.stat{font-size:30px;font-weight:850;letter-spacing:-.04em}.stat-label{color:var(--muted);font-size:13px}.tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:20px}.tile{display:block;padding:20px;border:1px solid var(--line);border-radius:13px;background:var(--panel);color:var(--text)}.tile:hover{background:var(--panel2);border-color:#565963;text-decoration:none}.tile-icon{font-size:25px}.tile p{margin:5px 0 0;color:var(--muted);font-size:13px}.columns{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:18px;align-items:start}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;font-size:14px}.table th,.table td{padding:11px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.table th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em}.table td.actions{white-space:nowrap}.table form{display:inline}.table .button,.table button{padding:6px 9px;font-size:12px}.badge{display:inline-block;padding:3px 8px;border-radius:20px;background:#30323a;color:#ddd;font-size:12px}.badge.yes{background:#183c2a;color:#a8f0c5}.badge.no{background:#3c2828;color:#f3b1b1}.form-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.empty{padding:22px;color:var(--muted);text-align:center}.hint{color:var(--muted);font-size:12px}.footer{padding:20px 0;color:#777983;font-size:12px}@media(max-width:1000px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.columns{grid-template-columns:1fr}}@media(max-width:720px){.admin-top{padding:12px 15px}.top-actions .user-label{display:none}.shell{grid-template-columns:1fr}.sidebar{padding:10px;border-right:0;border-bottom:1px solid var(--line);overflow:auto}.nav{display:flex;gap:5px;width:max-content}.nav-label{display:none}.nav a{white-space:nowrap;padding:8px 10px;font-size:13px}.wrap{padding:14px}main.content{padding:20px 14px}.tiles{grid-template-columns:1fr 1fr}.page-head{display:block}.page-head .button{margin-top:14px}}@media(max-width:440px){.grid,.tiles{grid-template-columns:1fr}.login-card{padding:24px}}
</style>
</head>
<body>
<?php if ($setupMode): ?>
<div class="login-wrap"><section class="login-card">
<div class="brand-mark">RCB</div><p class="eyebrow">RC Bande Zwickau e.V.</p><h1>Adminbereich einrichten</h1>
<p class="intro">Es gibt noch kein Administratorkonto. Lege jetzt das erste Konto an. Diese Einrichtung wird automatisch gesperrt, sobald ein Konto existiert.</p>
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= h($script) ?>">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="initial_setup">
<label for="username">Benutzername</label><input id="username" name="username" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9._-]+" autocomplete="username">
<label for="email">E-Mail (optional)</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email">
<label for="password">Eigenes Passwort (mindestens 12 Zeichen)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
<label for="password_confirm">Passwort wiederholen</label><input id="password_confirm" name="password_confirm" type="password" required minlength="12" autocomplete="new-password">
<button type="submit">Administratorkonto erstellen</button>
</form><p class="hint">Tipp: Verwende ein einzigartiges Passwort, das du nirgendwo sonst nutzt.</p>
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
</form><p class="hint">Zugang nur für berechtigte Vereinsmitglieder.</p><a href="/">← Zur Webseite</a>
</section></div>
<?php else: ?>
<header class="admin-top"><div class="brand"><div class="brand-mark">RCB</div><div>RCB <span class="muted">/ Admin</span></div></div><div class="top-actions"><span class="user-label muted"><?= h($currentUser['username']) ?></span><form method="post" action="<?= h($script) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="secondary" type="submit">Abmelden</button></form></div></header>
<div class="shell"><aside class="sidebar"><div class="nav-label">Verwaltung</div><nav class="nav">
<?php foreach (['dashboard'=>'▦ Übersicht','events'=>'▣ Termine','tracks'=>'⌁ Strecken','pages'=>'▤ Seiten','sponsors'=>'♧ Sponsoren','media'=>'▧ Bilder','settings'=>'⚙ Einstellungen','users'=>'♙ Benutzer','logs'=>'☷ Protokoll'] as $key=>$label): ?><a class="<?= $page === $key ? 'active' : '' ?>" href="<?= h($script) ?>?page=<?= h($key) ?>"><?= h($label) ?></a><?php endforeach; ?>
</nav><div class="footer">RCB Plattform<br>Verwaltungsbereich</div></aside>
<main class="content">
<?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice" role="status"><?= h($notice) ?></div><?php endif; ?>
<?php if ($page === 'dashboard'): ?>
<div class="page-head"><div><p class="eyebrow">Übersicht</p><h1>Willkommen, <?= h($currentUser['username']) ?>!</h1><p>Hier verwaltest du die Inhalte der RC Bande Zwickau.</p></div></div>
<div class="grid"><?php foreach (['events'=>'Termine','tracks'=>'Strecken','pages'=>'Seiten','sponsors'=>'Sponsoren','users'=>'Benutzer'] as $key=>$label): ?><a class="card" href="<?= h($script) ?>?page=<?= h($key) ?>"><div class="stat"><?= (int)$counts[$key] ?></div><div class="stat-label"><?= h($label) ?> angelegt</div></a><?php endforeach; ?></div>
<div class="tiles"><?php foreach (['events'=>['📅','Termine','Veranstaltungen anlegen und bearbeiten.'],'tracks'=>['🏁','Strecken','Onroad- und Offroad-Strecken pflegen.'],'pages'=>['📄','Vereinsseiten','Texte und Veröffentlichungsstatus verwalten.'],'sponsors'=>['🤝','Sponsoren','Sponsoren und Weblinks eintragen.'],'settings'=>['⚙️','Einstellungen','Name und Kontaktdaten der Plattform.'],'users'=>['🔐','Benutzer','Adminzugänge verwalten.']] as $key=>$tile): ?><a class="tile" href="<?= h($script) ?>?page=<?= h($key) ?>"><div class="tile-icon"><?= $tile[0] ?></div><h2><?= h($tile[1]) ?></h2><p><?= h($tile[2]) ?></p></a><?php endforeach; ?></div>
<?php elseif ($page === 'events'): ?>
<?php $item = $editItem ?: ['id'=>0,'title'=>'','description'=>'','event_date'=>'','start_time'=>'','location'=>'','published'=>0]; $rows=$db->query('SELECT * FROM events ORDER BY event_date DESC, id DESC')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Termine</h1><p>Veranstaltungen und Renntermine verwalten.</p></div></div>
<div class="columns"><section class="card"><h2><?= $editItem ? 'Termin bearbeiten' : 'Termin anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_event"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="title">Titel *</label><input id="title" name="title" required maxlength="180" value="<?= h($item['title']) ?>"><label for="event_date">Datum</label><input id="event_date" name="event_date" type="date" value="<?= h($item['event_date']) ?>"><label for="start_time">Uhrzeit</label><input id="start_time" name="start_time" type="time" value="<?= h($item['start_time']) ?>"><label for="location">Ort</label><input id="location" name="location" maxlength="180" value="<?= h($item['location']) ?>"><label for="description">Beschreibung</label><textarea id="description" name="description" maxlength="5000"><?= h($item['description']) ?></textarea><label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht</label><div class="form-actions"><button type="submit">Speichern</button><?php if ($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=events">Abbrechen</a><?php endif; ?></div></form></section>
<section class="card"><h2>Vorhandene Termine</h2><div class="table-wrap"><table class="table"><thead><tr><th>Termin</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['title']) ?></strong><br><span class="muted"><?= h($row['event_date']) ?> <?= h($row['start_time']) ?></span></td><td><span class="badge <?= $row['published'] ? 'yes' : 'no' ?>"><?= $row['published'] ? 'Veröffentlicht' : 'Entwurf' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=events&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Diesen Termin wirklich löschen?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="3" class="empty">Noch keine Termine angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'tracks'): ?>
<?php $item=$editItem ?: ['id'=>0,'name'=>'','description'=>'','surface'=>'','length'=>'','classes'=>'','electricity'=>0,'toilet'=>0,'paddock'=>0,'guest_drivers'=>0,'published'=>0,'status'=>'closed']; $rows=$db->query('SELECT * FROM tracks ORDER BY id DESC')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Strecken</h1><p>Onroad- und Offroad-Streckeninformationen pflegen.</p></div></div>
<div class="columns"><section class="card"><h2><?= $editItem ? 'Strecke bearbeiten' : 'Strecke anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_track"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="name">Streckenname *</label><input id="name" name="name" required maxlength="180" value="<?= h($item['name']) ?>"><label for="surface">Art / Belag</label><input id="surface" name="surface" maxlength="100" placeholder="z. B. Onroad / Asphalt" value="<?= h($item['surface']) ?>"><label for="length">Länge</label><input id="length" name="length" maxlength="100" placeholder="z. B. 180 m" value="<?= h($item['length']) ?>"><label for="classes">Fahrzeugklassen</label><input id="classes" name="classes" maxlength="500" value="<?= h($item['classes']) ?>"><label for="status">Streckenstatus</label><select id="status" name="status"><option value="open"<?= $item['status']==='open'?' selected':'' ?>>Geöffnet</option><option value="closed"<?= $item['status']==='closed'?' selected':'' ?>>Geschlossen</option><option value="maintenance"<?= $item['status']==='maintenance'?' selected':'' ?>>Wartung</option></select><label for="description">Beschreibung</label><textarea id="description" name="description" maxlength="5000"><?= h($item['description']) ?></textarea><?php foreach (['electricity'=>'Strom vorhanden','toilet'=>'Toilette vorhanden','paddock'=>'Fahrerlager vorhanden','guest_drivers'=>'Gastfahrer willkommen','published'=>'Veröffentlicht'] as $key=>$label): ?><label class="check"><input type="checkbox" name="<?= h($key) ?>" value="1"<?= checked($item[$key]) ?>> <?= h($label) ?></label><?php endforeach; ?><div class="form-actions"><button type="submit">Speichern</button><?php if ($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=tracks">Abbrechen</a><?php endif; ?></div></form></section><section class="card"><h2>Vorhandene Strecken</h2><div class="table-wrap"><table class="table"><thead><tr><th>Strecke</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= h($row['name']) ?></strong><br><span class="muted"><?= h($row['surface']) ?> <?= h($row['length']) ?></span></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= h(['open'=>'Geöffnet','closed'=>'Geschlossen','maintenance'=>'Wartung'][$row['status']] ?? 'Geschlossen') ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=tracks&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Diese Strecke wirklich löschen?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_track"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="3" class="empty">Noch keine Strecken angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'sponsors'): ?>
<?php $item=$editItem ?: ['id'=>0,'name'=>'','url'=>'','description'=>'','published'=>0]; $rows=$db->query('SELECT * FROM sponsors ORDER BY name')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Sponsoren</h1><p>Partner und Sponsor-Webseiten verwalten.</p></div></div><div class="columns"><section class="card"><h2><?= $editItem?'Sponsor bearbeiten':'Sponsor anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_sponsor"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="name">Name *</label><input id="name" name="name" required maxlength="180" value="<?= h($item['name']) ?>"><label for="url">Webseite</label><input id="url" name="url" type="url" maxlength="500" placeholder="https://…" value="<?= h($item['url']) ?>"><label for="description">Beschreibung</label><textarea id="description" name="description" maxlength="2000"><?= h($item['description']) ?></textarea><label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht</label><div class="form-actions"><button type="submit">Speichern</button><?php if($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=sponsors">Abbrechen</a><?php endif; ?></div></form></section><section class="card"><h2>Vorhandene Sponsoren</h2><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= h($row['name']) ?></strong><?php if($row['url']): ?><br><a href="<?= h($row['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($row['url']) ?></a><?php endif; ?></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=sponsors&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Diesen Sponsor wirklich löschen?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_sponsor"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="3" class="empty">Noch keine Sponsoren angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'pages'): ?>
<?php $item=$editItem ?: ['id'=>0,'slug'=>'','title'=>'','content'=>'','published'=>0]; $rows=$db->query('SELECT * FROM pages ORDER BY title')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Verwaltung</p><h1>Seiteninhalte</h1><p>Texte als Entwurf speichern oder als veröffentlicht markieren. Inhalte werden sicher als Text gespeichert.</p></div></div><div class="columns"><section class="card"><h2><?= $editItem?'Seite bearbeiten':'Seite anlegen' ?></h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_page"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label for="title">Seitentitel *</label><input id="title" name="title" required maxlength="180" value="<?= h($item['title']) ?>"><label for="slug">Seitenkennung *</label><input id="slug" name="slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="80" placeholder="z. B. verein" value="<?= h($item['slug']) ?>"><label for="content">Textinhalt</label><textarea id="content" name="content" maxlength="20000"><?= h($item['content']) ?></textarea><p class="hint">HTML wird aus Sicherheitsgründen nicht ausgeführt. Veröffentlichte Seiteninhalte können öffentlich über /seite.php?slug=SEITENKENNUNG aufgerufen werden.</p><label class="check"><input type="checkbox" name="published" value="1"<?= checked($item['published']) ?>> Veröffentlicht markieren</label><div class="form-actions"><button type="submit">Speichern</button><?php if($editItem): ?><a class="button secondary" href="<?= h($script) ?>?page=pages">Abbrechen</a><?php endif; ?></div></form></section><section class="card"><h2>Angelegte Seiten</h2><div class="table-wrap"><table class="table"><thead><tr><th>Seite</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= h($row['title']) ?></strong><br><span class="muted">/<?= h($row['slug']) ?></span></td><td><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span></td><td class="actions"><a class="button secondary" href="<?= h($script) ?>?page=pages&amp;edit=<?= (int)$row['id'] ?>">Bearbeiten</a><form method="post" onsubmit="return confirm('Diese Seite wirklich löschen?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_page"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="3" class="empty">Noch keine Seiten angelegt.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php elseif ($page === 'media'): ?>
<?php $rows=$db->query('SELECT * FROM media ORDER BY uploaded_at DESC, id DESC')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Medien</p><h1>Bilderverwaltung</h1><p>Bilder für Galerie und spätere Einbindung in die Vereinsseiten hochladen.</p></div></div>
<div class="columns"><section class="card"><h2>Bild hochladen</h2><form class="stack" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="upload_media"><label for="media_file">Bilddatei *</label><input id="media_file" name="media_file" type="file" accept="image/jpeg,image/png,image/webp" required><p class="hint">JPG, PNG oder WebP · maximal 8 MB. Bilder werden vor dem Speichern geprüft.</p><label for="title">Bildtitel</label><input id="title" name="title" maxlength="180" placeholder="z. B. Onroad-Strecke"><label for="description">Beschreibung / Alternativtext</label><textarea id="description" name="description" maxlength="1000" placeholder="Kurze Beschreibung des Bildes"></textarea><label class="check"><input type="checkbox" name="published" value="1"> In der öffentlichen Galerie veröffentlichen</label><div class="form-actions"><button type="submit">Bild hochladen</button></div></form></section>
<section class="card"><h2>Vorhandene Bilder</h2><?php if ($rows): ?><div class="media-grid"><?php foreach($rows as $row): ?><article class="media-item"><img src="/uploads/<?= h(rawurlencode($row['filename'])) ?>" alt="<?= h($row['description'] ?: $row['title']) ?>"><div class="media-item-body"><strong><?= h($row['title'] ?: $row['filename']) ?></strong><p class="muted"><?= h($row['description']) ?></p><span class="badge <?= $row['published']?'yes':'no' ?>"><?= $row['published']?'Veröffentlicht':'Entwurf' ?></span><p class="hint">/uploads/<?= h($row['filename']) ?></p><form method="post" onsubmit="return confirm('Dieses Bild und den Dateieintrag löschen?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_media"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form></div></article><?php endforeach; ?></div><?php else: ?><p class="empty">Noch keine Bilder hochgeladen.</p><?php endif; ?></section></div>
<?php elseif ($page === 'settings'): ?>
<?php $settingRows=$db->query('SELECT setting_key,setting_value FROM settings')->fetchAll(); $settings=[]; foreach($settingRows as $sr){$settings[$sr['setting_key']]=$sr['setting_value'];} ?>
<div class="page-head"><div><p class="eyebrow">System</p><h1>Einstellungen</h1><p>Grundlegende Angaben für die Plattform.</p></div></div><section class="card" style="max-width:760px"><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_settings"><label for="site_name">Name der Webseite</label><input id="site_name" name="site_name" maxlength="190" value="<?= h($settings['site_name']??'RC Bande Zwickau e.V.') ?>"><label for="site_description">Kurzbeschreibung</label><textarea id="site_description" name="site_description" maxlength="1000"><?= h($settings['site_description']??'') ?></textarea><label for="contact_email">Kontakt-E-Mail</label><input id="contact_email" name="contact_email" type="email" maxlength="190" value="<?= h($settings['contact_email']??'') ?>"><label for="contact_phone">Telefon</label><input id="contact_phone" name="contact_phone" maxlength="190" value="<?= h($settings['contact_phone']??'') ?>"><label class="check"><input type="checkbox" name="site_enabled" value="1"<?= checked($settings['site_enabled']??1) ?>> Webseite aktiv</label><div class="form-actions"><button type="submit">Einstellungen speichern</button></div></form><p class="hint">Name, Beschreibung und Kontaktangaben werden von den öffentlichen PHP-Seiten verwendet. Die Option „Webseite aktiv“ wird aktuell noch nicht als Wartungssperre erzwungen.</p></section>
<?php elseif ($page === 'users'): ?>
<?php $rows=$db->query('SELECT id,username,email,role,active,created_at FROM users ORDER BY id')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Sicherheit</p><h1>Benutzerverwaltung</h1><p>Administratorkonten erstellen, deaktivieren oder entfernen.</p></div></div><div class="columns"><section class="card"><h2>Adminzugang hinzufügen</h2><form class="stack" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create_user"><label for="username">Benutzername</label><input id="username" name="username" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9._-]+"><label for="email">E-Mail</label><input id="email" name="email" type="email" maxlength="190"><label for="password">Startpasswort (mindestens 12 Zeichen)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"><button type="submit">Benutzer anlegen</button></form></section><section class="card"><h2>Vorhandene Benutzer</h2><div class="table-wrap"><table class="table"><thead><tr><th>Benutzer</th><th>Status</th><th>Aktion</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= h($row['username']) ?></strong><br><span class="muted"><?= h($row['email']) ?></span><br><span class="hint">Seit <?= h($row['created_at']) ?></span></td><td><span class="badge <?= $row['active']?'yes':'no' ?>"><?= $row['active']?'Aktiv':'Deaktiviert' ?></span></td><td class="actions"><?php if((int)$row['id'] !== (int)$currentUser['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="secondary" type="submit"><?= $row['active']?'Deaktivieren':'Aktivieren' ?></button></form><form method="post" onsubmit="return confirm('Diesen Benutzer unwiderruflich löschen?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Löschen</button></form><?php else: ?><span class="hint">Aktuelle Sitzung</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<?php elseif ($page === 'logs'): ?>
<?php $rows=$db->query('SELECT logs.id,logs.action,logs.details,logs.created_at,users.username FROM logs LEFT JOIN users ON users.id=logs.user_id ORDER BY logs.id DESC LIMIT 200')->fetchAll(); ?>
<div class="page-head"><div><p class="eyebrow">Sicherheit</p><h1>Aktionsprotokoll</h1><p>Die letzten 200 Anmeldungen und Verwaltungsaktionen.</p></div></div><section class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Zeit</th><th>Benutzer</th><th>Aktion</th><th>Details</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= h($row['created_at']) ?></td><td><?= h($row['username']??'System') ?></td><td><?= h($row['action']) ?></td><td><?= h($row['details']) ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="4" class="empty">Noch keine Einträge vorhanden.</td></tr><?php endif; ?></tbody></table></div></section>
<?php else: ?>
<div class="page-head"><div><p class="eyebrow">Admin</p><h1>Seite nicht gefunden</h1><p>Dieser Menüpunkt existiert nicht.</p></div></div><a class="button" href="<?= h($script) ?>?page=dashboard">Zur Übersicht</a>
<?php endif; ?>
</main></div>
<?php endif; ?>
</body></html>
