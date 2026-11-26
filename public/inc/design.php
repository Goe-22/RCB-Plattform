<?php
declare(strict_types=1);

/*
 * Gestaltung der Webseite (Admin → Design):
 * Vereinsfarben, Schriftgröße, Hintergründe pro Seite und ausblendbare Abschnitte.
 * Gespeichert als JSON in der Einstellung „design“. Genutzt von Webseite und Admin.
 */

function rcb_design_defaults(): array
{
    return [
        'red' => '#c9272c',
        'blue' => '#062b55',
        'font' => 'normal',
        'backgrounds' => [],
        'hidden' => [],
    ];
}

function rcb_design_from_json(string $json): array
{
    $design = rcb_design_defaults();
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $design;
    }
    foreach (['red', 'blue'] as $key) {
        if (isset($data[$key]) && is_string($data[$key]) && preg_match('/^#[0-9a-f]{6}$/i', $data[$key])) {
            $design[$key] = strtolower($data[$key]);
        }
    }
    if (isset($data['font']) && array_key_exists((string)$data['font'], rcb_design_font_sizes())) {
        $design['font'] = (string)$data['font'];
    }
    if (isset($data['backgrounds']) && is_array($data['backgrounds'])) {
        foreach ($data['backgrounds'] as $page => $bg) {
            if (!is_string($page) || !is_array($bg) || !array_key_exists((string)($bg['type'] ?? ''), rcb_design_background_types())) {
                continue;
            }
            $image = (string)($bg['image'] ?? '');
            if ($image !== '' && !preg_match('#^/(uploads|assets/images)/[A-Za-z0-9._%-]+$#', $image)) {
                $image = '';
            }
            $design['backgrounds'][$page] = ['type' => (string)$bg['type'], 'image' => $image];
        }
    }
    if (isset($data['hidden']) && is_array($data['hidden'])) {
        $known = rcb_design_all_section_ids();
        $design['hidden'] = array_values(array_intersect($known, array_map('strval', $data['hidden'])));
    }
    return $design;
}

/** Schriftgröße der ganzen Seite: Schlüssel => [Anzeige, CSS-Wert für html{font-size}] */
function rcb_design_font_sizes(): array
{
    return ['normal' => ['Normal', '100%'], 'gross' => ['Groß', '112.5%'], 'sehr-gross' => ['Sehr groß', '125%']];
}

/** Hintergrund des Inhaltsbereichs: Schlüssel => Anzeige */
function rcb_design_background_types(): array
{
    return ['standard' => 'Standard (hellgrau)', 'weiss' => 'Weiß', 'hellblau' => 'Hellblau', 'warm' => 'Warmes Hellgrau', 'bild' => 'Bild (dezent aufgehellt)'];
}

/** Abschnitte, die sich pro Seite ausblenden lassen: Seite => [Abschnitt-ID => Bezeichnung] */
function rcb_design_sections(): array
{
    $track = ['steckbrief' => 'Steckbrief (Kasten rechts)', 'galerie' => 'Bilder & Videos', 'anfahrt' => 'Anfahrt & Besuch'];
    return [
        'home' => ['intro' => 'Begrüßungstext', 'neuigkeiten' => 'Neuigkeiten', 'strecken' => 'Unsere Strecken (Kacheln)', 'termine' => 'Kommende Termine', 'sponsoren' => 'Unsere Unterstützer'],
        'onroad' => $track,
        'offroad' => $track,
        'termine' => ['vergangen' => 'Vergangene Termine'],
        'ergebnisse' => ['veranstaltungen' => 'Ergebnisse unserer Veranstaltungen (automatisch)', 'archiv' => 'Ergebnis-Liste (von Hand gepflegt)'],
        'kontakt' => ['bildquelle' => 'Bildquelle unter den Kästen'],
    ];
}

function rcb_design_all_section_ids(): array
{
    $ids = [];
    foreach (rcb_design_sections() as $page => $sections) {
        foreach (array_keys($sections) as $id) {
            $ids[] = $page . '.' . $id;
        }
    }
    return $ids;
}

/** Hex-Farbe aufhellen (+) oder abdunkeln (−), $amount von −1 bis 1. */
function rcb_design_shade(string $hex, float $amount): string
{
    $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
    $out = '#';
    foreach ($rgb as $c) {
        $c = $amount < 0 ? $c * (1 + $amount) : $c + (255 - $c) * $amount;
        $out .= sprintf('%02x', (int)round(max(0, min(255, $c))));
    }
    return $out;
}

/** Kontrast zu Weiß (WCAG), z. B. 4.5 = gut lesbar. */
function rcb_design_contrast_white(string $hex): float
{
    $lum = 0.0;
    foreach (array_combine(['r', 'g', 'b'], sscanf(ltrim($hex, '#'), '%02x%02x%02x')) as $ch => $c) {
        $c /= 255;
        $c = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $lum += ['r' => 0.2126, 'g' => 0.7152, 'b' => 0.0722][$ch] * $c;
    }
    return 1.05 / ($lum + 0.05);
}

/** CSS für die gewählten Farben, Schriftgröße und den Hintergrund der aktuellen Seite. */
function rcb_design_css(array $design, string $pageKey): string
{
    // Bei den Vereinsfarben die bisher abgestimmten Abstufungen verwenden, sonst berechnen.
    $isRed = $design['red'] === '#c9272c';
    $isBlue = $design['blue'] === '#062b55';
    $redDark = $isRed ? '#a81d22' : rcb_design_shade($design['red'], -0.18);
    $dark = $isBlue ? '#041d3a' : rcb_design_shade($design['blue'], -0.33);
    $mid = $isBlue ? '#0d4777' : rcb_design_shade($design['blue'], 0.12);
    $tint = $isBlue ? '#e8eef5' : rcb_design_shade($design['blue'], 0.9);
    $rgba = static function (string $hex, float $a): string { [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x'); return "rgba($r,$g,$b,$a)"; };
    $css = ':root{--rcb-red:' . $design['red'] . ';--rcb-red-dark:' . $redDark . ';--rcb-blue:' . $design['blue'] . ';--rcb-blue-dark:' . $dark
        . ';--rcb-blue-mid:' . $mid . ';--rcb-tint:' . $tint . ';--rcb-ov1:' . $rgba($dark, .78) . ';--rcb-ov2:' . $rgba($mid, .55) . ';--rcb-ov3:' . $rgba($dark, .88) . '}';
    $font = rcb_design_font_sizes()[$design['font']][1] ?? '100%';
    if ($font !== '100%') {
        $css .= 'html{font-size:' . $font . '}';
    }
    $bgKey = isset($design['backgrounds'][$pageKey]) ? $pageKey : (str_starts_with($pageKey, 'reglement:') ? 'reglement' : '');
    $bg = $bgKey !== '' ? $design['backgrounds'][$bgKey] ?? null : null;
    if ($bg) {
        $value = match ($bg['type']) {
            'weiss' => '#fff',
            'hellblau' => 'var(--rcb-tint)',
            'warm' => '#f6f3ee',
            'bild' => $bg['image'] !== '' ? 'linear-gradient(rgba(255,255,255,.88),rgba(255,255,255,.88)),url(\'' . $bg['image'] . '\') center/cover fixed' : '',
            default => '',
        };
        if ($value !== '') {
            $css .= 'main{background:' . $value . '}';
        }
    }
    return $css;
}
