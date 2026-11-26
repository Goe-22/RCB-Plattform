<?php
declare(strict_types=1);

/*
 * Benachrichtigung per E-Mail, wenn andere Admins etwas ändern.
 * Jeder Admin wählt unter „Mein Zugang“: aus, gesammelt (ca. 10 Minuten nach der letzten Änderung) oder täglich.
 * Grundlage ist das Protokoll (Tabelle logs). Geprüft wird höchstens einmal pro Minute bei einem beliebigen Seitenaufruf.
 */

/** Auswahl für „Mein Zugang“. */
function rcb_notify_modes(): array
{
    return [
        '' => 'Aus – keine E-Mails',
        'bundle' => 'Gesammelt – etwa 10 Minuten nach der letzten Änderung',
        'daily' => 'Einmal täglich – Zusammenfassung des Tages',
    ];
}

/** Lesbare Bezeichnungen für Protokolleinträge. Nicht aufgeführte Aktionen erscheinen mit ihrem technischen Namen. */
function rcb_notify_labels(): array
{
    return [
        'content_published' => 'Seiteninhalte veröffentlicht',
        'content_drafts_discarded' => 'Entwürfe verworfen',
        'page_saved' => 'Seite gespeichert',
        'page_deleted' => 'Seite gelöscht',
        'nav_order_changed' => 'Menü-Reihenfolge geändert',
        'event_saved' => 'Termin gespeichert',
        'event_deleted' => 'Termin gelöscht',
        'event_settings' => 'Einstellungen für vergangene Termine geändert',
        'news_saved' => 'Neuigkeit gespeichert',
        'news_deleted' => 'Neuigkeit gelöscht',
        'result_saved' => 'Ergebnis gespeichert',
        'result_deleted' => 'Ergebnis gelöscht',
        'track_saved' => 'Strecke gespeichert',
        'track_deleted' => 'Strecke gelöscht',
        'sponsor_saved' => 'Sponsor gespeichert',
        'sponsor_deleted' => 'Sponsor gelöscht',
        'media_uploaded' => 'Bild/Video hochgeladen',
        'media_deleted' => 'Bild/Video gelöscht',
        'file_uploaded' => 'Datei hochgeladen',
        'file_deleted' => 'Datei gelöscht',
        'rule_class_added' => 'Reglement-Klasse angelegt',
        'rule_class_deleted' => 'Reglement-Klasse gelöscht',
        'membership_config' => 'Mitgliedschaft geändert',
        'membership_request_deleted' => 'Mitgliedsantrag gelöscht',
        'design_saved' => 'Design geändert',
        'design_reset' => 'Design zurückgesetzt',
        'settings_saved' => 'Einstellungen geändert',
        'site_updates_cleared' => 'Automatische Hinweise entfernt',
        'auto_updates_toggled' => 'Automatische Hinweise ein-/ausgeschaltet',
        'invite_created' => 'Admin-Einladung erstellt',
        'invite_accepted' => 'Einladung angenommen (neuer Admin)',
        'user_updated' => 'Admin-Rechte geändert',
        'user_toggled' => 'Admin aktiviert/deaktiviert',
        'user_deleted' => 'Admin gelöscht',
        'reset_link_created' => 'Passwort-Link erstellt',
        'token_revoked' => 'Einladung/Link widerrufen',
        'database_backup_downloaded' => 'Datenbank-Backup heruntergeladen',
    ];
}

/** Aktionen, die keine Benachrichtigung auslösen (An-/Abmelden, Zwischenspeichern, Passwort-Vorgänge). */
function rcb_notify_ignored(): array
{
    return ['login', 'logout', 'login_failed', 'content_draft_saved', 'password_reset_requested', 'password_reset', 'password_recovered', 'own_account_updated', 'notify_settings', 'initial_setup'];
}

/** Prüft fällige Benachrichtigungen und verschickt sie. Darf bei jedem Seitenaufruf aufgerufen werden. */
function rcb_notify_dispatch(PDO $db): void
{
    $now = time();
    $get = static function (string $key, string $default = '') use ($db): string {
        $s = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $v === false || $v === null ? $default : (string)$v;
    };
    $set = static function (string $key, string $value) use ($db): void {
        $u = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        $u->execute([$value, $key]);
        if ($u->rowCount() === 0) {
            $s = $db->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
            $s->execute([$key]);
            if ((int)$s->fetchColumn() === 0) {
                $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute([$key, $value]);
            }
        }
    };
    if ($now - (int)$get('notify_last_check', '0') < 60) {
        return;
    }
    $set('notify_last_check', (string)$now);

    $recipients = $db->query("SELECT id, username, display_name, email, notify_mode, notify_last_log_id, notify_last_sent FROM users WHERE active = 1 AND notify_mode IN ('bundle','daily') AND email <> ''")->fetchAll(PDO::FETCH_ASSOC);
    if (!$recipients) {
        return;
    }
    $ignored = rcb_notify_ignored();
    $placeholders = implode(',', array_fill(0, count($ignored), '?'));
    $labels = rcb_notify_labels();
    $base = rtrim($get('site_url', ''), '/');
    $siteName = $get('site_name', 'RC Bande Zwickau e.V.');
    $quietBefore = date('Y-m-d H:i:s', $now - 600);

    foreach ($recipients as $r) {
        $stmt = $db->prepare("SELECT l.id, l.action, l.details, l.created_at, l.logged_at, l.user_id, u.username, u.display_name
            FROM logs l LEFT JOIN users u ON u.id = l.user_id
            WHERE l.id > ? AND (l.user_id IS NULL OR l.user_id <> ?) AND l.action NOT IN ($placeholders)
            ORDER BY l.id ASC LIMIT 300");
        $stmt->execute(array_merge([(int)$r['notify_last_log_id'], (int)$r['id']], $ignored));
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$entries) {
            continue;
        }
        $newest = end($entries);
        $lastSent = (int)$r['notify_last_sent'];
        if ($r['notify_mode'] === 'bundle') {
            // Erst senden, wenn seit der letzten Änderung 10 Minuten Ruhe ist – so kommt pro Bearbeitung eine Mail.
            if ((string)($newest['logged_at'] ?? '') === '' || (string)$newest['logged_at'] > $quietBefore) {
                continue;
            }
        } elseif ($lastSent > $now - 86400 + 600) {
            continue; // täglich: frühestens 24 Stunden nach der letzten Zusammenfassung
        }

        $byUser = [];
        foreach ($entries as $e) {
            $who = trim((string)($e['display_name'] ?? '')) !== '' ? (string)$e['display_name'] : ((string)($e['username'] ?? '') !== '' ? (string)$e['username'] : 'System');
            $when = (string)($e['logged_at'] ?: $e['created_at']);
            $what = $labels[$e['action']] ?? $e['action'];
            $details = trim((string)$e['details']);
            $byUser[$who][] = '  • ' . date('d.m. H:i', strtotime($when) ?: $now) . ' – ' . $what . ($details !== '' ? ': ' . $details : '');
        }
        $names = array_keys($byUser);
        $subject = count($entries) . ' Änderung' . (count($entries) === 1 ? '' : 'en') . ' an der Webseite von ' . implode(', ', $names);
        $hello = trim((string)$r['display_name']) !== '' ? (string)$r['display_name'] : (string)$r['username'];
        $body = "Hallo " . $hello . ",\n\nan der Webseite von " . $siteName . " wurde Folgendes geändert:\n\n";
        foreach ($byUser as $who => $lines) {
            $body .= $who . ":\n" . implode("\n", $lines) . "\n\n";
        }
        if ($base !== '') {
            $body .= "Webseite ansehen: " . $base . "/\nProtokoll im Admin: " . $base . "/admin/index.php?page=logs\n\n";
        }
        $body .= "Diese E-Mail kommt, weil du unter „Mein Zugang“ Benachrichtigungen eingeschaltet hast. Dort kannst du sie auch wieder ausschalten.\n";
        $host = (string)(parse_url($base !== '' ? $base : 'http://localhost', PHP_URL_HOST) ?: 'localhost');
        $headers = "From: " . mb_encode_mimeheader($siteName, 'UTF-8') . " <noreply@" . preg_replace('/^www\./', '', $host) . ">\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
        $sent = @mail((string)$r['email'], mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
        if (!$sent) {
            error_log('RCB: Änderungs-Benachrichtigung konnte nicht gesendet werden an Benutzer ' . $r['id']);
        }
        // Auch bei Fehlschlag weiterzählen, damit nicht bei jedem Aufruf erneut versucht wird.
        $db->prepare('UPDATE users SET notify_last_log_id = ?, notify_last_sent = ? WHERE id = ?')->execute([(int)$newest['id'], $now, (int)$r['id']]);
    }
}
