<?php
declare(strict_types=1);

/**
 * Verzeichnis aller Seiten der Website (ohne Datenbankzugriff, auch vom Adminbereich genutzt).
 *
 * slug => [Bezeichnung, Adresse, im Menü?, darf verborgen werden?]
 * Startseite, Impressum und Datenschutz müssen immer erreichbar bleiben.
 */
function rcb_site_pages(): array
{
    return [
        'home' => ['Startseite', '/', true, false],
        'verein' => ['Verein', '/verein.php', true, true],
        'termine' => ['Termine', '/termine.php', true, true],
        'reglement' => ['Reglement', '/reglement.php', true, true],
        'ergebnisse' => ['Ergebnisse', '/ergebnisse.php', true, true],
        'live' => ['Live-Timing', '/live.php', true, true],
        'mitglied' => ['Mitglied werden', '/mitglied-werden.php', true, true],
        'kontakt' => ['Kontakt', '/kontakt.php', true, true],
        'onroad' => ['Onroad', '/onroad.php', false, true],
        'offroad' => ['Offroad', '/offroad.php', false, true],
        'downloads' => ['Downloads', '/downloads.php', false, true],
        'impressum' => ['Impressum', '/impressum.php', false, false],
        'datenschutz' => ['Datenschutz', '/datenschutz.php', false, false],
        'haftungsausschluss' => ['Haftungsausschluss', '/haftungsausschluss.php', false, true],
    ];
}

/** Seitenkennung zu einem Skriptpfad (z. B. /verein.php), sonst ''. */
function rcb_page_slug_for_path(string $path): string
{
    $path = $path === '/index.php' ? '/' : $path;
    foreach (rcb_site_pages() as $slug => [, $url]) {
        if ($url === $path) {
            return $slug;
        }
    }
    return '';
}

/** Menüpunkte mit Untermenü (keine eigene Seite): Kennung => [Bezeichnung, [Unterseiten]]. */
function rcb_nav_groups(): array
{
    return ['strecken' => ['Strecken', ['onroad', 'offroad']]];
}

/** Standard-Reihenfolge des Menüs. */
function rcb_default_nav(): array
{
    return ['home', 'verein', 'strecken', 'termine', 'reglement', 'ergebnisse', 'live', 'mitglied', 'kontakt'];
}

/**
 * Menüreihenfolge aus der Einstellung nav_order (JSON-Liste).
 * Einträge: Seitenkennungen, Gruppen (z. B. „strecken“) und eigene Seiten als „seite:<adresse>“.
 * $customInMenu: Adressen eigener Seiten, die im Menü stehen sollen.
 */
function rcb_nav_order(string $storedJson, array $customInMenu = []): array
{
    $valid = rcb_default_nav();
    foreach ($customInMenu as $slug) {
        $valid[] = 'seite:' . $slug;
    }
    $stored = json_decode($storedJson, true);
    $order = [];
    foreach (is_array($stored) ? $stored : [] as $item) {
        // Frühere Einträge: Übersichtsseite „strecken“ bzw. Onroad/Offroad einzeln → Menüpunkt „Strecken“.
        $item = in_array($item, ['onroad', 'offroad'], true) ? 'strecken' : $item;
        if (is_string($item) && in_array($item, $valid, true) && !in_array($item, $order, true)) {
            $order[] = $item;
        }
    }
    foreach ($valid as $item) {
        if (in_array($item, $order, true)) {
            continue;
        }
        // Fehlende Standardpunkte an ihrer üblichen Stelle einfügen, eigene Seiten hinten anhängen.
        $defaultPos = array_search($item, rcb_default_nav(), true);
        $insertAt = count($order);
        if ($defaultPos !== false && $defaultPos > 0) {
            $before = rcb_default_nav()[$defaultPos - 1];
            $found = array_search($before, $order, true);
            if ($found !== false) {
                $insertAt = $found + 1;
            }
        }
        array_splice($order, $insertAt, 0, [$item]);
    }
    $order = array_values(array_diff($order, ['home']));
    array_unshift($order, 'home');
    return $order;
}

/** Verborgene Seiten aus der Einstellung pages_hidden (JSON) und live_enabled. */
function rcb_hidden_pages(string $hiddenJson, string $liveEnabled): array
{
    $hidden = json_decode($hiddenJson, true);
    $hidden = array_values(array_filter(is_array($hidden) ? $hidden : [], static function ($slug): bool {
        $pages = rcb_site_pages();
        return is_string($slug) && isset($pages[$slug]) && $pages[$slug][3];
    }));
    if ($liveEnabled !== '1' && !in_array('live', $hidden, true)) {
        $hidden[] = 'live';
    }
    if ($liveEnabled === '1') {
        $hidden = array_values(array_diff($hidden, ['live']));
    }
    return $hidden;
}

/**
 * Reglement-Klassen: mitgelieferte Klassen (Kennung => Titel).
 * Ihr Regelwerk steht als Standardtext in reglement.php.
 */
function rcb_default_rule_classes(): array
{
    return [
        '555' => '555 Runden Team-Rennen',
        'tt02' => 'TT02 Cup',
        'einsteiger' => 'Einsteiger',
        'tw175' => 'TW 17,5t Stock',
        'fwd' => 'FWD Cup',
        'ore8' => 'ORE8 Cup (Buggy Elektro)',
        'or8' => 'OR8 Cup (Buggy Nitro)',
    ];
}

/**
 * Liste der Reglement-Klassen in Reihenfolge: [['key'=>…, 'title'=>…, 'published'=>bool], …].
 * Gespeichert als JSON in der Einstellung reglement_klassen; ohne Eintrag gelten die mitgelieferten Klassen.
 */
function rcb_rule_classes(string $storedJson): array
{
    $stored = json_decode($storedJson, true);
    if (!is_array($stored)) {
        $list = [];
        foreach (rcb_default_rule_classes() as $key => $title) {
            $list[] = ['key' => (string)$key, 'title' => $title, 'published' => true];
        }
        return $list;
    }
    $list = [];
    foreach ($stored as $item) {
        if (is_array($item) && isset($item['key']) && preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', (string)$item['key'])) {
            $list[] = ['key' => (string)$item['key'], 'title' => (string)($item['title'] ?? $item['key']), 'published' => !empty($item['published'])];
        }
    }
    return $list;
}
