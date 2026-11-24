<?php
declare(strict_types=1);

/* Streckenangaben (Ausstattung, Gäste, Status) – genutzt von Webseite und Admin. */

/** Auswählbare Ausstattung einer Strecke (Schlüssel => Anzeige). */
function rcb_track_equipment_options(): array
{
    return [
        'strom' => 'Strom',
        'fahrerlager' => 'Fahrerlager',
        'fahrerlager_ueberdacht' => 'Überdachtes Fahrerlager',
        'fahrerstand_ueberdacht' => 'Überdachter Fahrerstand',
        'zeitnahme' => 'Zeitnahme (Transponder)',
        'flutlicht' => 'Flutlicht',
        'toilette' => 'Toilette',
        'parkplatz' => 'Parkplätze',
        'sitzplaetze' => 'Tische & Sitzplätze',
    ];
}

/** Gewählte Ausstattung; ältere Einträge (Strom/Toilette/Fahrerlager-Häkchen) werden übernommen. */
function rcb_track_equipment(array $track): array
{
    $raw = trim((string)($track['equipment_json'] ?? ''));
    if ($raw !== '') {
        $list = json_decode($raw, true);
        return is_array($list) ? array_values(array_intersect(array_keys(rcb_track_equipment_options()), $list)) : [];
    }
    $list = [];
    if ((int)($track['electricity'] ?? 0) === 1) { $list[] = 'strom'; }
    if ((int)($track['paddock'] ?? 0) === 1) { $list[] = 'fahrerlager'; }
    if ((int)($track['toilet'] ?? 0) === 1) { $list[] = 'toilette'; }
    return $list;
}

/** Wer darf fahren? (Schlüssel => Text auf der Webseite; leer = nicht anzeigen) */
function rcb_track_guest_modes(): array
{
    return [
        '' => '',
        'absprache' => 'Gäste nach vorheriger Absprache',
        'training' => 'Gäste bei offenen Trainings und Veranstaltungen',
        'veranstaltung' => 'Gäste nur bei Veranstaltungen',
        'mitglieder' => 'Nur für Mitglieder',
    ];
}

/** Streckenstatus: Schlüssel => [Text, CSS-Klasse] */
function rcb_track_status_labels(): array
{
    return ['open' => ['Geöffnet', 'open'], 'closed' => ['Geschlossen', 'closed'], 'maintenance' => ['Wartung / Umbau', 'maintenance'], 'winter' => ['Winterpause', 'closed']];
}


/** Ist das ein erlaubter PayPal-Link (paypal.me oder paypal.com, nur https)? */
function rcb_track_pay_url_ok(string $url): bool
{
    return (bool)preg_match('#^https://(www\.)?(paypal\.me|paypal\.com)/[^\s"<>]*$#i', $url);
}

/** Bezahl-Links einer Strecke: Liste von ['label' => …, 'url' => …], höchstens zwei. */
function rcb_track_pay_links(array $track): array
{
    $list = json_decode((string)($track['pay_json'] ?? ''), true);
    $out = [];
    foreach (is_array($list) ? $list : [] as $item) {
        $url = trim((string)($item['url'] ?? ''));
        if ($url !== '' && rcb_track_pay_url_ok($url)) {
            $label = trim((string)($item['label'] ?? ''));
            $out[] = ['label' => $label !== '' ? $label : 'Tagesgebühr mit PayPal bezahlen', 'url' => $url];
        }
    }
    return array_slice($out, 0, 2);
}
