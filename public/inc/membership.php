<?php
declare(strict_types=1);

/**
 * Digitaler Aufnahmeantrag / Änderungsmeldung (nach dem Papierformular vom 12.04.2023).
 * Alle Inhalte (Beiträge, Vereinsordnungen, Texte, Bankverbindung) werden im Adminbereich
 * unter „Mitgliedschaft“ gepflegt und in der Einstellung membership_config gespeichert.
 */

/** Standardinhalte – gelten, solange im Admin nichts gespeichert wurde. */
function rcb_membership_defaults(): array
{
    return [
        'form_enabled' => true,
        // Jeder Punkt lässt sich im Admin einzeln ein- und ausblenden.
        'allow_change' => true,
        'show_member_no' => true,
        'show_phone' => true,
        'show_mobile' => true,
        'show_family' => true,
        'show_fees' => true,
        'show_fee_note' => true,
        'show_guardian' => true,
        'show_guardian_text' => true,
        'show_rules' => true,
        'show_rules_note' => true,
        'show_message' => true,
        'show_accept' => true,
        'show_bank_page' => true,
        'show_bank_print' => true,
        'show_print_footer' => true,
        'fees' => [
            ['id' => 'erwachsen', 'label' => 'Erwachsene aktive Mitglieder', 'price' => '90'],
            ['id' => 'kinder', 'label' => 'Kinder (7–12 Jahre)', 'price' => '15'],
            ['id' => 'jugend1', 'label' => 'Jugend 1 (13–15 Jahre)', 'price' => '25'],
            ['id' => 'jugend2', 'label' => 'Jugend 2 (16–18 Jahre)', 'price' => '50'],
            ['id' => 'ermaessigt', 'label' => 'Schüler, Geringverdiener, geringfügig Beschäftigte (über 18 Jahre, mit Nachweis)', 'price' => '45'],
            ['id' => 'frei', 'label' => 'Kinder unter 8 Jahre (beitragsfrei)', 'price' => '0'],
        ],
        'monthly_enabled' => true,
        'monthly_label' => 'Monatliche Zahlung: 12 Monate à 7,50 €',
        'monthly_fee' => 'erwachsen',
        'fee_note' => 'Kinder unter 8 Jahre sind von der Beitragszahlung befreit. Der ermäßigte Beitrag gilt nur mit Nachweis.',
        'rules' => ['Satzung', 'Bahnordnung', 'Geländeordnung'],
        'rules_note' => 'Die Ordnungen findest du oben zum Herunterladen oder bekommst sie beim Training.',
        'accept_text' => 'Ich erkenne die Satzung, Gelände- und Platzordnungen sowie die Beitragsordnung als für mich verbindlich an.',
        'guardian_text' => 'Mit der Unterschrift erklären sich die gesetzlichen Vertreter bereit, für Forderungen des Vereins aus dem Mitgliedschaftsverhältnis einzutreten.',
        'bank_holder' => 'RC Bande Zwickau e.V.',
        'bank_iban' => 'DE90 8705 5000 1020 0383 10',
        'bank_bic' => 'WELADED1ZWI',
        'bank_purpose' => 'Mitgliedernummer(n), Beitragsjahr (z. B. 50, 52 // 2026)',
        'print_footer' => 'Vereinsregister Chemnitz · VR 4910 RC-Bande Zwickau e.V.',
    ];
}

/** Gespeicherte Einstellungen (JSON) über die Standardinhalte legen. */
function rcb_membership_config_from_json(string $json): array
{
    $config = rcb_membership_defaults();
    $stored = json_decode($json, true);
    if (is_array($stored)) {
        foreach ($config as $key => $default) {
            if (!array_key_exists($key, $stored)) {
                continue;
            }
            $config[$key] = is_bool($default) ? (bool)$stored[$key] : (is_array($default) ? (array)$stored[$key] : (string)$stored[$key]);
        }
    }
    // Einheitliche Form: Beitragsstufen und Ordnungen haben jeweils einen Schalter „sichtbar“.
    $config['fees'] = array_values(array_map(static fn($f) => [
        'id' => (string)($f['id'] ?? ''), 'label' => (string)($f['label'] ?? ''), 'price' => (string)($f['price'] ?? '0'),
        'visible' => !array_key_exists('visible', (array)$f) || (bool)$f['visible'],
    ], array_filter($config['fees'], 'is_array')));
    $config['rules'] = array_values(array_map(static fn($r) => is_array($r)
        ? ['name' => (string)($r['name'] ?? ''), 'visible' => !array_key_exists('visible', $r) || (bool)$r['visible']]
        : ['name' => (string)$r, 'visible' => true], $config['rules']));
    return $config;
}

/** Sichtbare Vereinsordnungen (Namen) für das Formular. */
function rcb_visible_rules(array $config): array
{
    if (!$config['show_rules']) {
        return [];
    }
    return array_values(array_map(static fn($r) => $r['name'], array_filter($config['rules'], static fn($r) => $r['visible'] && trim($r['name']) !== '')));
}

/** Beitragsstufen als Schlüssel => [Bezeichnung, Preis-Text]. */
function rcb_fee_options(?array $config = null): array
{
    $config ??= rcb_membership_defaults();
    if (array_key_exists('show_fees', $config) && !$config['show_fees']) {
        return [];
    }
    $options = [];
    foreach ($config['fees'] as $fee) {
        if (array_key_exists('visible', $fee) && !$fee['visible']) {
            continue;
        }
        $options[(string)$fee['id']] = [(string)$fee['label'], (string)$fee['price']];
    }
    return $options;
}

/** Preis für die Anzeige: „90 €“, „7,50 €“ oder „frei“. */
function rcb_price_label(string $price): string
{
    $value = (float)str_replace(',', '.', $price);
    if ($value <= 0) {
        return 'frei';
    }
    return (floor($value) == $value ? number_format($value, 0, ',', '.') : number_format($value, 2, ',', '.')) . ' €';
}

/** Alter in Jahren zu einem Datum JJJJ-MM-TT, sonst null. */
function rcb_age(string $birthDate): ?int
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    return $date ? $date->diff(new DateTimeImmutable('today'))->y : null;
}

function rcb_de_date(string $isoDate): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
    return $date ? $date->format('d.m.Y') : $isoDate;
}

/** Zusätzliche Angaben eines Antrags (JSON-Spalte details). */
function rcb_membership_details(array $row): array
{
    $details = json_decode((string)($row['details'] ?? ''), true);
    return is_array($details) ? $details : [];
}

/**
 * Alle Angaben eines Antrags als Liste [Bezeichnung, Wert] – für E-Mail, Adminbereich und Druckansicht.
 * Beitrag und Ordnungen werden beim Absenden mit ihrem damaligen Wortlaut gespeichert,
 * damit spätere Änderungen im Admin alte Anträge nicht verfälschen.
 */
function rcb_membership_summary(array $row): array
{
    $d = rcb_membership_details($row);
    $isChange = ($d['kind'] ?? '') === 'aenderung';
    $yesNo = static fn($v) => $v === 'ja' ? 'Ja' : ($v === 'nein' ? 'Nein' : '');
    $age = rcb_age((string)($row['birth_date'] ?? ''));

    $feeText = '';
    if (($d['fee_label'] ?? '') !== '') {
        $feeText = $d['fee_label'] . ' – ' . rcb_price_label((string)($d['fee_price'] ?? '0'));
    } elseif (isset(rcb_fee_options()[$d['fee'] ?? ''])) {
        [$label, $price] = rcb_fee_options()[$d['fee']];
        $feeText = $label . ' – ' . rcb_price_label($price);
    }
    $payment = '';
    if (!empty($d['monthly'])) {
        $payment = ($d['monthly_label'] ?? '') !== '' ? (string)$d['monthly_label'] : 'monatlich (12 Monate à 7,50 €)';
    } elseif ($feeText !== '' && !str_ends_with($feeText, 'frei')) {
        $payment = 'jährlich';
    }

    $lines = [
        ['Art', $isChange ? 'Änderung meiner Mitgliedsdaten' : 'Antrag auf Aufnahme in die RC Bande Zwickau e.V.'],
        ['Mitgliedernummer', (string)($d['member_no'] ?? '')],
        ['Name, Vorname', trim((string)$row['last_name'] . ', ' . (string)$row['first_name'], ', ')],
        ['Geburtstag', (string)($row['birth_date'] ?? '') !== '' ? rcb_de_date((string)$row['birth_date']) . ($age !== null ? ' (' . $age . ' Jahre)' : '') : ''],
        ['Straße, Hausnummer', (string)($d['street'] ?? '')],
        ['PLZ, Ort', trim((string)($d['zip'] ?? '') . ' ' . (string)($d['city'] ?? ''))],
        ['Telefon', (string)($row['phone'] ?? '')],
        ['Mobil', (string)($d['mobile'] ?? '')],
        ['E-Mail', (string)($row['email'] ?? '')],
        ['Andere Familienmitglieder bereits Mitglied', $yesNo($d['family'] ?? '')],
        ['Name des Familienmitglieds', ($d['family'] ?? '') === 'ja' ? (string)($d['family_name'] ?? '') : ''],
        ['Jahresbeitrag', $feeText],
        ['Zahlungsweise', $payment],
        ['Gesetzliche(r) Vertreter', (string)($d['guardian'] ?? '')],
    ];
    if (isset($d['rules']) && is_array($d['rules'])) {
        foreach ($d['rules'] as $rule) {
            $lines[] = [(string)($rule['name'] ?? '') . ' gelesen und verstanden', $yesNo($rule['answer'] ?? '') ?: '–'];
        }
    } else {
        foreach (['satzung' => 'Satzung', 'bahnordnung' => 'Bahnordnung', 'gelaendeordnung' => 'Geländeordnung'] as $key => $name) {
            $lines[] = [$name . ' gelesen und verstanden', $yesNo($d[$key] ?? '')];
        }
    }
    $lines[] = ['Bemerkung', trim((string)($row['message'] ?? ''))];
    return array_values(array_filter($lines, static fn($line) => $line[1] !== ''));
}
