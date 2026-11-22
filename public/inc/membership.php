<?php
declare(strict_types=1);

/**
 * Digitaler Aufnahmeantrag / Änderungsmeldung (nach dem Papierformular vom 12.04.2023).
 * Gemeinsame Definitionen für die öffentliche Seite, die E-Mail und den Adminbereich.
 */

/** Beitragsstufen laut Aufnahmeantrag: Schlüssel => [Bezeichnung, Jahresbeitrag in €]. */
function rcb_fee_options(): array
{
    return [
        'erwachsen' => ['Erwachsene aktive Mitglieder', 90],
        'kinder' => ['Kinder (7–12 Jahre)', 15],
        'jugend1' => ['Jugend 1 (13–15 Jahre)', 25],
        'jugend2' => ['Jugend 2 (16–18 Jahre)', 50],
        'ermaessigt' => ['Schüler, Geringverdiener, geringfügig Beschäftigte (über 18 Jahre, mit Nachweis)', 45],
        'frei' => ['Kinder unter 8 Jahre (beitragsfrei)', 0],
    ];
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
 * Leere Angaben werden ausgelassen.
 */
function rcb_membership_summary(array $row): array
{
    $d = rcb_membership_details($row);
    $isChange = ($d['kind'] ?? '') === 'aenderung';
    $fees = rcb_fee_options();
    $yesNo = static fn($v) => $v === 'ja' ? 'Ja' : ($v === 'nein' ? 'Nein' : '');
    $age = rcb_age((string)($row['birth_date'] ?? ''));
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
        ['Jahresbeitrag', isset($fees[$d['fee'] ?? '']) ? $fees[$d['fee']][0] . ' – ' . $fees[$d['fee']][1] . ' €' : ''],
        ['Zahlungsweise', !empty($d['monthly']) ? 'monatlich (12 Monate à 7,50 €)' : (isset($fees[$d['fee'] ?? '']) && $fees[$d['fee']][1] > 0 ? 'jährlich' : '')],
        ['Gesetzliche(r) Vertreter', (string)($d['guardian'] ?? '')],
        ['Satzung gelesen und verstanden', $yesNo($d['satzung'] ?? '')],
        ['Bahnordnung gelesen und verstanden', $yesNo($d['bahnordnung'] ?? '')],
        ['Geländeordnung gelesen und verstanden', $yesNo($d['gelaendeordnung'] ?? '')],
        ['Bemerkung', trim((string)($row['message'] ?? ''))],
    ];
    return array_values(array_filter($lines, static fn($line) => $line[1] !== ''));
}
