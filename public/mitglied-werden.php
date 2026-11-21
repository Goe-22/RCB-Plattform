<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

$email = rcb_setting('contact_email', 'rcb.zwickau@gmail.com');
$formErrors = [];
$form = ['first_name' => '', 'last_name' => '', 'birth_date' => '', 'email' => '', 'phone' => '', 'address' => '', 'interest' => '', 'vehicle' => '', 'message' => ''];

/** Geheimnis für den Zeitstempel im Formular (Schutz gegen Spam-Bots), wird einmalig erzeugt. */
function rcb_form_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $secret = rcb_setting('form_secret', '');
    if ($secret === '') {
        $new = bin2hex(random_bytes(32));
        rcb_db_write(static fn(PDO $db) => $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute(['form_secret', $new]));
        $secret = rcb_setting('form_secret', '');
    }
    return $secret;
}
function rcb_form_token(): string
{
    $time = (string)time();
    return $time . '.' . hash_hmac('sha256', 'mitglied|' . $time, rcb_form_secret());
}
function rcb_clean_line(string $value, int $max): string
{
    $value = trim(preg_replace('/[\r\n\t]+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (['first_name' => 120, 'last_name' => 120, 'birth_date' => 20, 'email' => 190, 'phone' => 60, 'address' => 300, 'interest' => 60, 'vehicle' => 300] as $field => $max) {
        $form[$field] = rcb_clean_line((string)($_POST[$field] ?? ''), $max);
    }
    $message = trim(str_replace("\r", '', (string)($_POST['message'] ?? '')));
    $form['message'] = function_exists('mb_substr') ? mb_substr($message, 0, 3000) : substr($message, 0, 3000);

    // Spamschutz: verstecktes Feld muss leer sein, Formular frühestens nach 3 Sekunden und höchstens nach 1 Tag abgeschickt.
    [$time, $sig] = array_pad(explode('.', (string)($_POST['token'] ?? ''), 2), 2, '');
    $tokenOk = ctype_digit($time) && hash_equals(hash_hmac('sha256', 'mitglied|' . $time, rcb_form_secret()), $sig)
        && time() - (int)$time >= 3 && time() - (int)$time <= 86400;
    $isBot = (string)($_POST['website'] ?? '') !== '' || !$tokenOk;

    if ($form['first_name'] === '' || $form['last_name'] === '') { $formErrors[] = 'Bitte Vor- und Nachnamen angeben.'; }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) { $formErrors[] = 'Bitte eine gültige E-Mail-Adresse angeben, damit wir dir antworten können.'; }
    if ($form['birth_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['birth_date'])) { $formErrors[] = 'Bitte das Geburtsdatum über die Datumsauswahl eintragen.'; }
    if (!in_array($form['interest'], ['', 'Onroad', 'Offroad', 'Onroad und Offroad', 'Weiß ich noch nicht'], true)) { $form['interest'] = ''; }
    if (empty($_POST['privacy'])) { $formErrors[] = 'Bitte der Verarbeitung deiner Angaben zustimmen (Datenschutz).'; }

    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . 'rcb-mitglied');
    $recent = rcb_rows('SELECT COUNT(*) AS n FROM membership_requests WHERE ip_hash = ? AND created_at >= ?', [$ipHash, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)($recent[0]['n'] ?? 0) >= 3) { $formErrors[] = 'Es wurden gerade schon mehrere Anfragen gesendet. Bitte versuche es später noch einmal oder schreib uns eine E-Mail.'; }

    if ($isBot && !$formErrors) {
        // Bots bekommen dieselbe Erfolgsmeldung, aber nichts wird gespeichert oder versendet.
        header('Location: /mitglied-werden.php?gesendet=1#anfrage', true, 303);
        exit;
    }
    if (!$formErrors) {
        $mailSent = false;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $siteName = rcb_setting('site_name', 'RC Bande Zwickau e.V.');
            $base = rcb_setting('site_url', '');
            $host = (string)(parse_url($base, PHP_URL_HOST) ?: preg_replace('/[^a-z0-9.-]/', '', strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'))));
            $lines = [
                'Neue Mitgliedsanfrage über die Webseite', '',
                'Name: ' . $form['first_name'] . ' ' . $form['last_name'],
                'E-Mail: ' . $form['email'],
                'Telefon: ' . ($form['phone'] ?: '–'),
                'Geburtsdatum: ' . ($form['birth_date'] !== '' ? date('d.m.Y', strtotime($form['birth_date'])) : '–'),
                'Anschrift: ' . ($form['address'] ?: '–'),
                'Interesse: ' . ($form['interest'] ?: '–'),
                'Fahrzeug / Klasse: ' . ($form['vehicle'] ?: '–'), '',
                'Nachricht:', $form['message'] !== '' ? $form['message'] : '–', '',
                'Antworten: einfach auf diese E-Mail antworten.',
                $base !== '' ? 'Alle Anfragen im Admin: ' . $base . '/admin/index.php?page=requests' : '',
            ];
            $headers = 'From: ' . mb_encode_mimeheader($siteName, 'UTF-8') . ' <noreply@' . preg_replace('/^www\./', '', $host) . ">\r\n"
                . 'Reply-To: ' . $form['email'] . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
            $mailSent = @mail($email, mb_encode_mimeheader('Neue Mitgliedsanfrage: ' . $form['first_name'] . ' ' . $form['last_name'], 'UTF-8'), implode("\n", $lines), $headers);
            if (!$mailSent) { error_log('RCB: Mitgliedsanfrage konnte nicht per E-Mail versendet werden.'); }
        }
        $saved = rcb_db_write(static fn(PDO $db) => $db->prepare('INSERT INTO membership_requests (first_name, last_name, birth_date, email, phone, address, interest, vehicle, message, status, mail_sent, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$form['first_name'], $form['last_name'], $form['birth_date'], $form['email'], $form['phone'], $form['address'], $form['interest'], $form['vehicle'], $form['message'], 'neu', $mailSent ? 1 : 0, $ipHash, date('Y-m-d H:i:s')]));
        if ($saved || $mailSent) {
            header('Location: /mitglied-werden.php?gesendet=1#anfrage', true, 303);
            exit;
        }
        $formErrors[] = 'Die Anfrage konnte gerade nicht gesendet werden. Bitte schreib uns direkt eine E-Mail.';
    }
}

rcb_header('join');
echo rcb_page_hero('mitglied', 'Mitglied werden', 'Du möchtest regelmäßig fahren, den Verein kennenlernen oder dich im Vereinsleben einbringen? Wir freuen uns auf deine Anfrage.');
$downloads = rcb_page_downloads('mitglied', 'Aufnahmeantrag & Unterlagen');
$sent = isset($_GET['gesendet']);
?>
<section class="rcb-section"><div class="rcb-wrap">
<div class="rcb-grid rcb-join-grid">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('mitglied.ablauf', '<h2>So geht es weiter</h2><ol><li>Schick uns unten eine Online-Anfrage oder schreib uns eine E-Mail.</li><li>Komm zu einem Training vorbei und lerne uns und die Strecken kennen.</li><li>Für die Aufnahme füllst du den Aufnahmeantrag aus, unterschreibst ihn und gibst ihn bei uns ab.</li></ol>') ?></div></article>
<article class="rcb-card"><div class="rcb-card-body">
<?php if ($downloads !== ''): ?><?= $downloads ?>
<?php else: ?><h2>Aufnahmeantrag</h2><?= rcb_rich('mitglied.antrag.fehlt', '<p>Den Aufnahmeantrag bekommst du beim Training oder auf Anfrage per E-Mail.</p>') ?><?php if (rcb_admin_viewer()): ?><p class="rcb-admin-hint">Admin-Hinweis: Lade den Antrag im Admin unter <a href="/admin/index.php?page=files&amp;seite=mitglied">📎 Dateien</a> hoch und ordne ihn „Mitglied werden“ zu – dann erscheint er hier als Download.</p><?php endif; ?>
<?php endif; ?>
<?php if ($email !== ''): ?><p style="margin-top:16px"><strong>E-Mail:</strong> <a href="mailto:<?= rcb_h($email) ?>"><?= rcb_h($email) ?></a></p><?php endif; ?>
</div></article>
</div>

<article class="rcb-card rcb-join-form" id="anfrage"><div class="rcb-card-body">
<?php if ($sent): ?>
<div class="rcb-form-success" role="status"><h2>Danke für deine Anfrage!</h2><p>Sie ist bei uns angekommen. Wir melden uns so bald wie möglich per E-Mail bei dir.</p><?php if ($downloads !== ''): ?><p>Wenn du schon sicher bist: Den Aufnahmeantrag findest du oben zum Herunterladen.</p><?php endif; ?></div>
<?php else: ?>
<?= rcb_rich('mitglied.formular.intro', '<h2>Online-Anfrage</h2><p>Schreib uns kurz, wer du bist und was dich interessiert. Die Anfrage ist noch keine verbindliche Anmeldung – für die Aufnahme brauchen wir den unterschriebenen Aufnahmeantrag.</p>') ?>
<?php if ($formErrors): ?><div class="rcb-form-errors" role="alert"><ul><?php foreach ($formErrors as $err): ?><li><?= rcb_h($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/mitglied-werden.php#anfrage" class="rcb-form" novalidate>
<input type="hidden" name="token" value="<?= rcb_h(rcb_form_token()) ?>">
<div class="rcb-hp" aria-hidden="true"><label for="website">Webseite</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="rcb-form-row"><div><label for="first_name">Vorname *</label><input id="first_name" name="first_name" required maxlength="120" autocomplete="given-name" value="<?= rcb_h($form['first_name']) ?>"></div>
<div><label for="last_name">Nachname *</label><input id="last_name" name="last_name" required maxlength="120" autocomplete="family-name" value="<?= rcb_h($form['last_name']) ?>"></div></div>
<div class="rcb-form-row"><div><label for="email">E-Mail *</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= rcb_h($form['email']) ?>"></div>
<div><label for="phone">Telefon</label><input id="phone" name="phone" type="tel" maxlength="60" autocomplete="tel" value="<?= rcb_h($form['phone']) ?>"></div></div>
<div class="rcb-form-row"><div><label for="birth_date">Geburtsdatum</label><input id="birth_date" name="birth_date" type="date" autocomplete="bday" value="<?= rcb_h($form['birth_date']) ?>"></div>
<div><label for="interest">Was interessiert dich?</label><select id="interest" name="interest"><?php foreach (['' => 'Bitte wählen', 'Onroad' => 'Onroad', 'Offroad' => 'Offroad', 'Onroad und Offroad' => 'Onroad und Offroad', 'Weiß ich noch nicht' => 'Weiß ich noch nicht'] as $value => $label): ?><option value="<?= rcb_h($value) ?>"<?= $form['interest'] === $value ? ' selected' : '' ?>><?= rcb_h($label) ?></option><?php endforeach; ?></select></div></div>
<label for="address">Anschrift</label><input id="address" name="address" maxlength="300" autocomplete="street-address" placeholder="Straße, PLZ Ort" value="<?= rcb_h($form['address']) ?>">
<label for="vehicle">Fahrzeug / Klasse</label><input id="vehicle" name="vehicle" maxlength="300" placeholder="z. B. Tamiya TT02, noch kein eigenes Auto …" value="<?= rcb_h($form['vehicle']) ?>">
<label for="message">Nachricht</label><textarea id="message" name="message" rows="5" maxlength="3000"><?= rcb_h($form['message']) ?></textarea>
<label class="rcb-check"><input type="checkbox" name="privacy" value="1" required<?= !empty($_POST['privacy']) ? ' checked' : '' ?>> <span>Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Anfrage gespeichert und an den Vorstand weitergegeben werden. Mehr dazu in der <a href="/datenschutz.php" target="_blank">Datenschutzerklärung</a>. *</span></label>
<button class="rcb-btn" type="submit">Anfrage senden</button>
<p class="rcb-form-note">* Pflichtfelder. Bitte keine Bankdaten hier eintragen – die stehen nur im unterschriebenen Aufnahmeantrag.</p>
</form>
<?php endif; ?>
</div></article>
</div></section>
<?php rcb_footer(); ?>
