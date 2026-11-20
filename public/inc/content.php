<?php
declare(strict_types=1);

/**
 * Bearbeitbare Seitenbereiche (Texte und Bilder).
 *
 * Jede Seite gibt ihre Texte über rcb_text()/rcb_rich() und Bilder über rcb_img() aus.
 * Gespeicherte Inhalte liegen in der Tabelle content_blocks; ohne Eintrag gilt der Standardtext.
 * Mit ?bearbeiten=1 und angemeldetem Admin werden die Bereiche direkt auf der Seite bearbeitbar.
 */

/** Angemeldeter Admin mit Seitenrechten, wenn ?bearbeiten=1 oder ?vorschau=1 aufgerufen wird; sonst null. */
function rcb_admin_viewer(): ?array
{
    static $resolved = false;
    static $viewer = null;
    if ($resolved) {
        return $viewer;
    }
    $resolved = true;
    if ((!isset($_GET['bearbeiten']) && !isset($_GET['vorschau'])) || empty($_COOKIE['rcb_admin_session'])) {
        return null;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('rcb_admin_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start(['read_and_close' => true]);
    }
    $userId = (int)($_SESSION['admin_user_id'] ?? 0);
    $csrf = (string)($_SESSION['csrf_token'] ?? '');
    if ($userId < 1 || $csrf === '') {
        return null;
    }
    $rows = rcb_rows('SELECT id, username, role, permissions, active FROM users WHERE id = ? LIMIT 1', [$userId]);
    $user = $rows[0] ?? null;
    if (!$user || (int)$user['active'] !== 1) {
        return null;
    }
    $permissions = json_decode((string)($user['permissions'] ?? '[]'), true);
    if (($user['role'] ?? '') !== 'superadmin' && !in_array('pages', is_array($permissions) ? $permissions : [], true)) {
        return null;
    }
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    $user['csrf'] = $csrf;
    $user['mode'] = isset($_GET['bearbeiten']) ? 'edit' : 'preview';
    $viewer = $user;
    return $viewer;
}

/** Admin im Bearbeitungsmodus (Texte und Bilder sind direkt änderbar), sonst null. */
function rcb_editor(): ?array
{
    $viewer = rcb_admin_viewer();
    return $viewer && $viewer['mode'] === 'edit' ? $viewer : null;
}

/** Werden unveröffentlichte Entwürfe angezeigt (Bearbeitungsmodus oder Entwurfsvorschau)? */
function rcb_showing_drafts(): bool
{
    return rcb_admin_viewer() !== null;
}

/** Alle gespeicherten Seitenbereiche (einmal pro Seitenaufruf geladen). */
function rcb_blocks(): array
{
    static $blocks = null;
    if ($blocks === null) {
        $blocks = [];
        foreach (rcb_rows('SELECT block_key, content FROM content_blocks') as $row) {
            $blocks[(string)$row['block_key']] = (string)($row['content'] ?? '');
        }
        // Für angemeldete Admins: Entwürfe überlagern den veröffentlichten Stand (NULL = auf Standard zurücksetzen).
        if (rcb_showing_drafts()) {
            foreach (rcb_rows('SELECT block_key, content FROM content_drafts') as $row) {
                if ($row['content'] === null) {
                    unset($blocks[(string)$row['block_key']]);
                } else {
                    $blocks[(string)$row['block_key']] = (string)$row['content'];
                }
            }
        }
    }
    return $blocks;
}

function rcb_block(string $key, string $default = ''): string
{
    $blocks = rcb_blocks();
    return array_key_exists($key, $blocks) ? $blocks[$key] : $default;
}

/** Einzeiliger oder mehrzeiliger Text ohne Formatierung. */
function rcb_text(string $key, string $default = ''): string
{
    $value = rcb_block($key, $default);
    $html = nl2br(rcb_h($value), false);
    if (rcb_editor()) {
        return '<span class="rcb-edit" data-rcb-key="' . rcb_h($key) . '" data-rcb-type="text">' . $html . '</span>';
    }
    return $html;
}

/** Formatierter Text (Überschriften, Listen, Links, Bilder). Standardtexte sind vertrauenswürdiges HTML. */
function rcb_rich(string $key, string $defaultHtml = '', string $class = 'rcb-content'): string
{
    $value = rcb_block($key, $defaultHtml);
    if (rcb_editor()) {
        return '<div class="' . rcb_h($class) . ' rcb-edit rcb-edit-rich" data-rcb-key="' . rcb_h($key) . '" data-rcb-type="rich">' . $value . '</div>';
    }
    return trim(strip_tags($value, '<img>')) === '' ? '' : '<div class="' . rcb_h($class) . '">' . $value . '</div>';
}

/** Ist ein Bereich gefüllt (oder wird bearbeitet)? Für optionale Abschnitte. */
function rcb_filled(string $key, string $default = ''): bool
{
    return rcb_editor() !== null || trim(strip_tags(rcb_block($key, $default), '<img>')) !== '';
}

/** Bild-URL eines Bereichs. */
function rcb_img(string $key, string $defaultUrl): string
{
    $value = trim(rcb_block($key, ''));
    return $value !== '' ? $value : $defaultUrl;
}

/** Zusätzliche Attribute, damit ein Bild (oder Hintergrundbild) im Bearbeitungsmodus austauschbar ist. */
function rcb_img_attr(string $key): string
{
    return rcb_editor() ? ' data-rcb-key="' . rcb_h($key) . '" data-rcb-type="image"' : '';
}

/** Neuestes veröffentlichtes Bild einer Medienkategorie, sonst der mitgelieferte Standard. */
function rcb_media_url(string $category, string $fallback): string
{
    static $cache = [];
    if (!array_key_exists($category, $cache)) {
        $rows = rcb_rows('SELECT filename FROM media WHERE published = 1 AND category = ? ORDER BY id DESC LIMIT 1', [$category]);
        $cache[$category] = !empty($rows[0]['filename']) ? '/uploads/' . rawurlencode((string)$rows[0]['filename']) : null;
    }
    return $cache[$category] ?? $fallback;
}

/** Hintergrundbild für Titelbereiche mit dunklem Verlauf. */
function rcb_hero_style(string $url): string
{
    return "background-image:linear-gradient(125deg,rgba(4,29,58,.78),rgba(13,71,119,.55)),url('" . rcb_h($url) . "');background-size:cover;background-position:center";
}

/** Einheitlicher Titelbereich einer Unterseite mit bearbeitbarem Titel, Text und optionalem Bild. */
function rcb_page_hero(string $page, string $title, string $text, ?string $image = null): string
{
    $imageUrl = rcb_img($page . '.hero.bild', $image ?? '');
    $style = $imageUrl !== '' ? ' style="' . rcb_hero_style($imageUrl) . '"' : '';
    return '<section class="rcb-hero"' . $style . rcb_img_attr($page . '.hero.bild') . '><div class="rcb-hero-inner"><h1>'
        . rcb_text($page . '.hero.titel', $title) . '</h1><p>' . rcb_text($page . '.hero.text', $text) . '</p></div></section>';
}

/** 'onroad' / 'offroad' anhand des Streckennamens, sonst ''. */
function rcb_track_kind(string $name): string
{
    if (stripos($name, 'onroad') !== false) {
        return 'onroad';
    }
    return stripos($name, 'offroad') !== false ? 'offroad' : '';
}

/** Bild einer Strecke: im Editor gewähltes Bild, sonst Medienkategorie, sonst mitgeliefertes Foto. */
function rcb_track_image(string $kind): string
{
    return rcb_img('strecke.' . $kind . '.bild', rcb_media_url($kind, '/assets/images/' . $kind . '.jpg'));
}

/** Gekürzter Text für Vorschauen. */
function rcb_excerpt(string $text, int $length = 180): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $length, '…', 'UTF-8');
    }
    return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
}
