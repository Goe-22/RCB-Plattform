<?php
declare(strict_types=1);

/**
 * Darstellung von Terminen/Veranstaltungen mit Nennung, Ausschreibung und Klassen.
 * Wird von termine.php und der Startseite genutzt.
 */

/** Deutscher Monatsname. */
function rcb_month(int $month): string
{
    return ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][$month] ?? '';
}

/** Datumsangabe für Kalenderblatt: ['16.–17.', 'Mai 2026'] bzw. ['31.05.–01.06.', '2026']. */
function rcb_event_date_parts(?string $start, ?string $end): array
{
    if (!$start) {
        return ['', 'Termin folgt'];
    }
    try {
        $from = new DateTimeImmutable($start);
        $to = $end ? new DateTimeImmutable($end) : null;
    } catch (Throwable $e) {
        return ['', ''];
    }
    if (!$to || $to <= $from) {
        return [$from->format('d.'), rcb_month((int)$from->format('n')) . ' ' . $from->format('Y')];
    }
    if ($from->format('Y-m') === $to->format('Y-m')) {
        return [$from->format('d.') . '–' . $to->format('d.'), rcb_month((int)$from->format('n')) . ' ' . $from->format('Y')];
    }
    return [$from->format('d.m.') . '–' . $to->format('d.m.'), $to->format('Y')];
}

/** Kurzer Anbietername für Nennungslinks, z. B. „myRCM“ oder „rck-challenge.de“. */
function rcb_link_label(string $url): string
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $known = ['myrcm.ch' => 'myRCM', 'rck-challenge.de' => 'RCK-Challenge', 'rcracing.de' => 'rcracing.de', 'dmc-online.com' => 'DMC'];
    return $known[$host] ?? $host;
}

/** Ist das eine sichere http(s)-Adresse oder ein Pfad auf dieser Website? */
function rcb_safe_url(string $url): bool
{
    if ($url === '') {
        return false;
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return true;
    }
    return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/** Link-Ziel zum Regelwerk einer Klasse, falls der Name einer Reglement-Klasse entspricht. */
function rcb_rule_class_link(string $name): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (rcb_rule_classes(rcb_setting('reglement_klassen', '')) as $class) {
            if (!$class['published']) {
                continue;
            }
            $titles = [$class['title'], rcb_block('reglement.' . $class['key'] . '.titel', '')];
            foreach ($titles as $title) {
                $norm = rcb_norm_class($title);
                if ($norm !== '') {
                    $map[$norm] = '/reglement.php?klasse=' . rawurlencode($class['key']);
                }
            }
        }
    }
    return $map[rcb_norm_class($name)] ?? '';
}

function rcb_norm_class(string $name): string
{
    $name = strtolower(trim((string)preg_replace('/\s*\(.*?\)\s*/', ' ', $name)));
    return (string)preg_replace('/[^a-z0-9äöüß]+/u', '', $name);
}

/** Klassen eines Termins aus classes_json: [['name'=>…, 'guest'=>bool, 'url'=>…], …]. */
function rcb_event_classes(array $event): array
{
    $list = json_decode((string)($event['classes_json'] ?? ''), true);
    $out = [];
    foreach (is_array($list) ? $list : [] as $item) {
        if (is_array($item) && trim((string)($item['name'] ?? '')) !== '') {
            $out[] = ['name' => trim((string)$item['name']), 'guest' => !empty($item['guest']), 'url' => (string)($item['url'] ?? '')];
        }
    }
    return $out;
}

/** Streckenseite zu einer Strecken-ID (Onroad/Offroad), sonst ''. */
function rcb_event_track_url(int $trackId): string
{
    static $tracks = null;
    if ($trackId < 1) {
        return '';
    }
    if ($tracks === null) {
        $tracks = [];
        foreach (rcb_rows('SELECT id, name FROM tracks') as $row) {
            $tracks[(int)$row['id']] = (string)$row['name'];
        }
    }
    $kind = rcb_track_kind($tracks[$trackId] ?? '');
    return $kind !== '' && rcb_page_published($kind) ? '/' . $kind . '.php' : '';
}

/** Ergebnisse (veröffentlicht) je Termin-ID. */
function rcb_event_result_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = array_map('intval', array_column(rcb_rows('SELECT DISTINCT event_id FROM results WHERE published = 1 AND event_id IS NOT NULL'), 'event_id'));
    }
    return $ids;
}

/**
 * Terminkarte. $variant: 'full' (Terminseite), 'compact' (Startseite), 'past' (Archiv).
 */
function rcb_event_card(array $event, string $variant = 'full'): string
{
    [$day, $monthYear] = rcb_event_date_parts($event['event_date'] ?? null, $event['end_date'] ?? null);
    $meta = [];
    if (!empty($event['start_time'])) {
        $meta[] = rcb_h('ab ' . substr((string)$event['start_time'], 0, 5) . ' Uhr');
    }
    if ((string)($event['location'] ?? '') !== '') {
        // Bei Veranstaltungen auf unseren Strecken auf die Streckenseite verlinken.
        $trackUrl = rcb_event_track_url((int)($event['track_id'] ?? 0));
        $meta[] = $trackUrl !== '' ? '<a href="' . rcb_h($trackUrl) . '">' . rcb_h($event['location']) . '</a>' : rcb_h($event['location']);
    }
    $registration = (string)($event['registration_url'] ?? '');
    $announcementUrl = (string)($event['announcement_url'] ?? '');
    $announcementText = trim((string)($event['announcement_text'] ?? ''));
    $classes = rcb_event_classes($event);
    $hasResult = in_array((int)$event['id'], rcb_event_result_ids(), true);

    $actions = '';
    if ($variant !== 'past' && rcb_safe_url($registration)) {
        $actions .= '<a class="rcb-btn rcb-btn-nennung" href="' . rcb_h($registration) . '" target="_blank" rel="noopener noreferrer"><span class="rcb-btn-icon" aria-hidden="true">✎</span><span>Jetzt nennen<small>bei ' . rcb_h(rcb_link_label($registration)) . ' ↗</small></span></a>';
    }
    if ($variant !== 'compact' && rcb_safe_url($announcementUrl)) {
        $isPdf = str_ends_with(strtolower((string)parse_url($announcementUrl, PHP_URL_PATH)), '.pdf');
        $actions .= '<a class="rcb-btn rcb-btn-doc" href="' . rcb_h($announcementUrl) . '" target="_blank" rel="noopener"><span class="rcb-btn-icon" aria-hidden="true">📄</span><span>Ausschreibung<small>' . ($isPdf ? 'PDF öffnen' : 'öffnen ↗') . '</small></span></a>';
    }
    $resultsUrl = (string)($event['results_url'] ?? '');
    if ($variant === 'past' && rcb_safe_url($resultsUrl)) {
        $actions .= '<a class="rcb-btn rcb-btn-result" href="' . rcb_h($resultsUrl) . '" target="_blank" rel="noopener noreferrer"><span class="rcb-btn-icon" aria-hidden="true">🏁</span><span>Ergebnisse<small>bei ' . rcb_h(rcb_link_label($resultsUrl)) . ' ↗</small></span></a>';
    }
    if ($hasResult) {
        $actions .= '<a class="rcb-btn rcb-btn-light" href="/ergebnisse.php#bericht-' . (int)$event['id'] . '">Rennbericht</a>';
    }
    if ($variant === 'compact') {
        $actions .= '<a class="rcb-btn rcb-btn-light" href="/termine.php#termin-' . (int)$event['id'] . '">Details</a>';
    }

    $html = '<article class="rcb-event rcb-event-' . rcb_h($variant) . '" id="termin-' . (int)$event['id'] . '">';
    $html .= '<div class="rcb-event-date"><span class="rcb-event-day">' . rcb_h($day) . '</span><span class="rcb-event-month">' . rcb_h($monthYear) . '</span></div>';
    $html .= '<div class="rcb-event-body"><h3>' . rcb_h($event['title']) . '</h3>';
    if ($meta) {
        $html .= '<p class="rcb-event-meta">' . implode(' · ', $meta) . '</p>';
    }
    if ($classes) {
        $html .= '<ul class="rcb-event-classes">';
        foreach ($classes as $class) {
            $rules = rcb_rule_class_link($class['name']);
            $html .= '<li><span class="rcb-event-class">' . rcb_h($class['name']) . '</span>'
                . ($class['guest'] ? ' <span class="rcb-tag">Gastklasse</span>' : '')
                . ($variant !== 'compact' && $rules !== '' ? ' <a href="' . rcb_h($rules) . '">Reglement</a>' : '')
                . ($variant === 'full' && rcb_safe_url($class['url']) ? ' <a class="rcb-class-nennung" href="' . rcb_h($class['url']) . '" target="_blank" rel="noopener noreferrer">✎ Nennung ↗</a>' : '')
                . '</li>';
        }
        $html .= '</ul>';
    }
    if ($variant !== 'compact' && (string)($event['description'] ?? '') !== '') {
        $html .= '<p>' . nl2br(rcb_h($event['description'])) . '</p>';
    }
    if ($variant === 'full' && $announcementText !== '') {
        $html .= '<details class="rcb-event-announcement"><summary><span aria-hidden="true">📄</span> Ausschreibung lesen</summary><div class="rcb-content">' . nl2br(rcb_h($announcementText)) . '</div></details>';
    }
    $html .= '</div>';
    if ($actions !== '') {
        $html .= '<div class="rcb-event-actions">' . $actions . '</div>';
    }
    return $html . '</article>';
}


/** War der Termin ein Rennen? (Klassen ausgeschrieben, Nennung, Ergebnis-Link oder Rennbericht vorhanden) */
function rcb_event_is_race(array $event): bool
{
    return rcb_event_classes($event) !== [] || (string)($event['registration_url'] ?? '') !== ''
        || (string)($event['results_url'] ?? '') !== '' || in_array((int)$event['id'], rcb_event_result_ids(), true);
}
