<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/membership.php';

$email = rcb_setting('contact_email', 'rcb.zwickau@gmail.com');
$config = rcb_membership_config_from_json(rcb_setting('membership_config', ''));
$fees = rcb_fee_options($config);
$rules = rcb_visible_rules($config);
$formErrors = [];
$form = ['kind' => 'aufnahme', 'member_no' => '', 'last_name' => '', 'first_name' => '', 'birth_date' => '', 'street' => '', 'zip' => '', 'city' => '', 'phone' => '', 'mobile' => '', 'email' => '', 'family' => 'nein', 'family_name' => '', 'fee' => '', 'monthly' => '', 'guardian' => '', 'message' => ''];
$ruleAnswers = array_fill(0, count($rules), '');

/** Zur Seite hochgeladene Datei, deren Titel den Namen enthält (z. B. „Satzung“) – für Links im Formular. */
function rcb_member_file_for(string $name): ?array
{
    foreach (rcb_page_files('mitglied') as $file) {
        if ($name !== '' && mb_stripos((string)$file['title'], $name) !== false) {
            return $file;
        }
    }
    return null;
}
function rcb_bank_html(array $config): string
{
    if (!$config['show_bank_page'] || trim($config['bank_iban']) === '') {
        return '';
    }
    return '<h3>Bankverbindung für den Beitrag</h3><p>' . rcb_h($config['bank_holder']) . '<br>IBAN: ' . rcb_h($config['bank_iban'])
        . ($config['bank_bic'] !== '' ? '<br>BIC: ' . rcb_h($config['bank_bic']) : '')
        . ($config['bank_purpose'] !== '' ? '<br>Verwendungszweck: ' . rcb_h($config['bank_purpose']) : '') . '</p>';
}

/** Geheimnis für Zeitstempel und Druck-Links, wird einmalig erzeugt. */
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
/** Unratbarer Link-Schlüssel zur Druckansicht eines gerade gesendeten Antrags. */
function rcb_antrag_key(int $id, string $createdAt): string
{
    return $id . '.' . substr(hash_hmac('sha256', 'antrag|' . $id . '|' . $createdAt, rcb_form_secret()), 0, 40);
}
function rcb_clean_line(string $value, int $max): string
{
    $value = trim(preg_replace('/[\r\n\t]+/', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}
/** Antrag zu einem Druck-Link (24 Stunden gültig), sonst null. */
function rcb_antrag_from_key(string $key): ?array
{
    if (!preg_match('/^(\d{1,10})\.[a-f0-9]{40}$/', $key, $m)) {
        return null;
    }
    $row = rcb_rows('SELECT * FROM membership_requests WHERE id = ? LIMIT 1', [(int)$m[1]])[0] ?? null;
    if (!$row || !hash_equals(rcb_antrag_key((int)$row['id'], (string)$row['created_at']), $key) || strtotime((string)$row['created_at']) < time() - 86400) {
        return null;
    }
    return $row;
}

// ---------------------------------------------------------------- Druckansicht des ausgefüllten Antrags
if (isset($_GET['antrag'])) {
    $row = rcb_antrag_from_key((string)$_GET['antrag']);
    rcb_header('join');
    if (!$row) {
        echo '<section class="rcb-section"><div class="rcb-wrap" style="max-width:760px"><h1>Antrag nicht mehr abrufbar</h1><p>Der Link zum Ausdrucken gilt 24 Stunden. Du kannst den Antrag als <a href="/mitglied-werden.php">PDF herunterladen</a> und von Hand ausfüllen – deine Online-Angaben liegen uns trotzdem vor.</p></div></section>';
        rcb_footer();
        exit;
    }
    $d = rcb_membership_details($row);
    $minor = (rcb_age((string)$row['birth_date']) ?? 99) < 18;
    $logo = rcb_media_url('logo', '/assets/images/rcb-logo.png');
    ?>
<section class="rcb-section rcb-print-page"><div class="rcb-wrap" style="max-width:860px">
<p class="rcb-no-print"><button type="button" class="rcb-btn" data-print>🖨 Antrag drucken</button> <a class="rcb-btn rcb-btn-light" href="/mitglied-werden.php">Zurück</a></p>
<article class="rcb-antrag-doc">
<header><div><h1><?= ($d['kind'] ?? '') === 'aenderung' ? 'Änderungsmeldung' : 'Aufnahmeantrag' ?></h1><p>RC Bande Zwickau e.V. · Online ausgefüllt am <?= rcb_h(date('d.m.Y', strtotime((string)$row['created_at']))) ?></p></div><img src="<?= rcb_h($logo) ?>" alt="RC Bande Zwickau e.V."></header>
<table><?php foreach (rcb_membership_summary($row) as [$label, $value]): ?><tr><th><?= rcb_h($label) ?></th><td><?= nl2br(rcb_h($value)) ?></td></tr><?php endforeach; ?></table>
<p>Durch meine Unterschrift erkenne ich die Satzung, Gelände- und Platzordnungen sowie Beitragsordnung als für mich verbindlich an. Außerdem bestätige ich, dass ich die Informationen zum Datenschutz / zu den Persönlichkeitsrechten gelesen und verstanden habe.</p>
<?php if ($minor && $config['show_guardian_text'] && $config['guardian_text'] !== ''): ?><p><?= rcb_h($config['guardian_text']) ?></p><?php endif; ?>
<div class="rcb-sign-row"><div><span></span>Datum, Ort</div><div><span></span>Unterschrift Mitglied</div></div>
<?php if ($minor): ?><div class="rcb-sign-row"><div><span></span>Datum, Ort</div><div><span></span>Unterschrift gesetzliche(r) Vertreter</div></div><?php endif; ?>
<?php if ($config['show_bank_print'] && trim($config['bank_iban']) !== ''): ?><div class="rcb-bank"><strong>Bankverbindung:</strong> <?= rcb_h($config['bank_holder']) ?> · IBAN <?= rcb_h($config['bank_iban']) ?><?= $config['bank_bic'] !== '' ? ' · BIC ' . rcb_h($config['bank_bic']) : '' ?><?php if ($config['bank_purpose'] !== ''): ?><br>Verwendungszweck: <?= rcb_h($config['bank_purpose']) ?><?php endif; ?></div><?php endif; ?>
<div class="rcb-board"><strong>Wird vom Vorstand ausgefüllt</strong><div class="rcb-sign-row"><div><span></span>Mitgliedernummer</div><div><span></span>Unterschrift Vorstand</div></div><div class="rcb-sign-row"><div><span></span>bei Ablehnung, Grund angeben</div><div><span></span>Unterschrift Vorstand</div></div></div>
<?php if ($config['show_print_footer'] && $config['print_footer'] !== ''): ?><p class="rcb-small"><?= rcb_h($config['print_footer']) ?></p><?php endif; ?>
</article></div></section>
<?php
    rcb_footer();
    exit;
}

// ---------------------------------------------------------------- Antrag absenden
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $config['form_enabled']) {
    foreach (['member_no' => 20, 'last_name' => 120, 'first_name' => 120, 'birth_date' => 10, 'street' => 190, 'zip' => 10, 'city' => 120, 'phone' => 60, 'mobile' => 60, 'email' => 190, 'family_name' => 190, 'guardian' => 300] as $field => $max) {
        $form[$field] = rcb_clean_line((string)($_POST[$field] ?? ''), $max);
    }
    $form['kind'] = $config['allow_change'] && ($_POST['kind'] ?? '') === 'aenderung' ? 'aenderung' : 'aufnahme';
    $form['family'] = $config['show_family'] && ($_POST['family'] ?? '') === 'ja' ? 'ja' : 'nein';
    if (!$config['show_family']) { $form['family_name'] = ''; }
    foreach ($rules as $i => $rule) {
        $answer = (string)($_POST['rule'][$i] ?? '');
        $ruleAnswers[$i] = in_array($answer, ['ja', 'nein'], true) ? $answer : '';
    }
    $form['fee'] = isset($fees[(string)($_POST['fee'] ?? '')]) ? (string)$_POST['fee'] : '';
    $form['monthly'] = $config['monthly_enabled'] && !empty($_POST['monthly']) ? '1' : '';
    if (!$config['allow_change'] || !$config['show_member_no']) { $form['member_no'] = ''; }
    if (!$config['show_phone']) { $form['phone'] = ''; }
    if (!$config['show_mobile']) { $form['mobile'] = ''; }
    if (!$config['show_guardian']) { $form['guardian'] = ''; }
    $form['message'] = $config['show_message'] ? mb_substr(trim(str_replace("\r", '', (string)($_POST['message'] ?? ''))), 0, 3000) : '';
    $isChange = $form['kind'] === 'aenderung';

    // Spamschutz: verstecktes Feld leer, Formular frühestens nach 3 Sekunden und höchstens nach 1 Tag abgeschickt.
    [$time, $sig] = array_pad(explode('.', (string)($_POST['token'] ?? ''), 2), 2, '');
    $tokenOk = ctype_digit($time) && hash_equals(hash_hmac('sha256', 'mitglied|' . $time, rcb_form_secret()), $sig)
        && time() - (int)$time >= 3 && time() - (int)$time <= 86400;
    $isBot = (string)($_POST['website'] ?? '') !== '' || !$tokenOk;

    $age = rcb_age($form['birth_date']);
    if ($form['last_name'] === '' || $form['first_name'] === '') { $formErrors[] = 'Bitte Name und Vorname angeben.'; }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) { $formErrors[] = 'Bitte eine gültige E-Mail-Adresse angeben.'; }
    if ($form['birth_date'] !== '' && ($age === null || $age > 120)) { $formErrors[] = 'Bitte den Geburtstag über die Datumsauswahl eintragen.'; }
    if ($form['zip'] !== '' && !preg_match('/^\d{5}$/', $form['zip'])) { $formErrors[] = 'Die Postleitzahl muss 5 Ziffern haben.'; }
    if (!$isChange) {
        if ($form['birth_date'] === '') { $formErrors[] = 'Bitte den Geburtstag angeben.'; }
        if ($form['street'] === '' || $form['zip'] === '' || $form['city'] === '') { $formErrors[] = 'Bitte die vollständige Anschrift angeben (Straße, Postleitzahl, Ort).'; }
        if ($form['fee'] === '' && $fees) { $formErrors[] = 'Bitte den passenden Jahresbeitrag auswählen.'; }
        if ($config['show_guardian'] && $age !== null && $age < 18 && $form['guardian'] === '') { $formErrors[] = 'Bei Minderjährigen bitte den Namen des/der gesetzlichen Vertreter(s) angeben.'; }
        if ($config['show_accept'] && $config['accept_text'] !== '' && empty($_POST['accept'])) { $formErrors[] = 'Bitte bestätigen, dass du Satzung, Gelände-, Platz- und Beitragsordnung anerkennst.'; }
    }
    if ($form['family'] === 'ja' && $form['family_name'] === '') { $formErrors[] = 'Bitte den Namen des Familienmitglieds angeben, das schon Mitglied ist.'; }
    if ($form['monthly'] !== '' && $config['monthly_fee'] !== '' && $form['fee'] !== $config['monthly_fee']) { $formErrors[] = 'Die monatliche Zahlung gibt es nur beim Beitrag „' . ($fees[$config['monthly_fee']][0] ?? '') . '“.'; }
    if (empty($_POST['privacy'])) { $formErrors[] = 'Bitte der Verarbeitung deiner Angaben zustimmen (Datenschutz).'; }

    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . 'rcb-mitglied');
    $recent = rcb_rows('SELECT COUNT(*) AS n FROM membership_requests WHERE ip_hash = ? AND created_at >= ?', [$ipHash, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)($recent[0]['n'] ?? 0) >= 3) { $formErrors[] = 'Es wurden gerade schon mehrere Anträge gesendet. Bitte versuche es später noch einmal oder schreib uns eine E-Mail.'; }

    if ($isBot && !$formErrors) {
        // Bots bekommen eine Erfolgsmeldung, aber nichts wird gespeichert oder versendet.
        header('Location: /mitglied-werden.php?gesendet=1#antrag', true, 303);
        exit;
    }
    if (!$formErrors) {
        $details = array_intersect_key($form, array_flip(['kind', 'member_no', 'street', 'zip', 'city', 'mobile', 'family', 'family_name', 'fee', 'monthly', 'guardian']));
        // Wortlaut von Beitrag und Ordnungen festhalten, falls er später im Admin geändert wird.
        $details['fee_label'] = $form['fee'] !== '' ? $fees[$form['fee']][0] : '';
        $details['fee_price'] = $form['fee'] !== '' ? $fees[$form['fee']][1] : '';
        $details['monthly_label'] = $form['monthly'] !== '' ? $config['monthly_label'] : '';
        $details['rules'] = array_map(static fn($name, $answer) => ['name' => $name, 'answer' => $answer], $rules, $ruleAnswers);
        if (!$config['show_family']) { unset($details['family'], $details['family_name']); }
        $createdAt = date('Y-m-d H:i:s');
        $row = ['first_name' => $form['first_name'], 'last_name' => $form['last_name'], 'birth_date' => $form['birth_date'], 'email' => $form['email'], 'phone' => $form['phone'], 'message' => $form['message'], 'details' => json_encode($details, JSON_UNESCAPED_UNICODE)];
        $summary = rcb_membership_summary($row);

        $mailSent = false;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $siteName = rcb_setting('site_name', 'RC Bande Zwickau e.V.');
            $base = rcb_setting('site_url', '');
            $host = (string)(parse_url($base, PHP_URL_HOST) ?: preg_replace('/[^a-z0-9.-]/', '', strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'))));
            $title = $isChange ? 'Änderungsmeldung' : 'Aufnahmeantrag';
            $lines = [$title . ' über die Webseite', ''];
            foreach ($summary as [$label, $value]) { $lines[] = $label . ': ' . $value; }
            $lines[] = '';
            $lines[] = 'Antworten: einfach auf diese E-Mail antworten.';
            if ($base !== '') { $lines[] = 'Alle Anträge im Admin: ' . $base . '/admin/index.php?page=requests'; }
            $headers = 'From: ' . mb_encode_mimeheader($siteName, 'UTF-8') . ' <noreply@' . preg_replace('/^www\./', '', $host) . ">\r\n"
                . 'Reply-To: ' . $form['email'] . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
            $mailSent = @mail($email, mb_encode_mimeheader($title . ': ' . $form['first_name'] . ' ' . $form['last_name'], 'UTF-8'), implode("\n", $lines), $headers);
            if (!$mailSent) { error_log('RCB: Aufnahmeantrag konnte nicht per E-Mail versendet werden.'); }
        }
        $newId = 0;
        $address = trim($form['street'] . ', ' . trim($form['zip'] . ' ' . $form['city']), ', ');
        $saved = rcb_db_write(static function (PDO $db) use ($form, $row, $address, $mailSent, $ipHash, $createdAt, $isChange, &$newId): void {
            $db->prepare('INSERT INTO membership_requests (first_name, last_name, birth_date, email, phone, address, interest, vehicle, message, details, status, mail_sent, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$form['first_name'], $form['last_name'], $form['birth_date'], $form['email'], $form['phone'], $address, $isChange ? 'Änderungsmeldung' : 'Aufnahmeantrag', '', $form['message'], $row['details'], 'neu', $mailSent ? 1 : 0, $ipHash, $createdAt]);
            $newId = (int)$db->lastInsertId();
        });
        if ($saved || $mailSent) {
            header('Location: /mitglied-werden.php?gesendet=' . ($saved && $newId > 0 ? rcb_antrag_key($newId, $createdAt) : '1') . '#antrag', true, 303);
            exit;
        }
        $formErrors[] = 'Der Antrag konnte gerade nicht gesendet werden. Bitte lade das PDF herunter oder schreib uns eine E-Mail.';
    }
}

// ---------------------------------------------------------------- Seite
rcb_header('join');
echo rcb_page_hero('mitglied', 'Mitglied werden', 'Du möchtest regelmäßig fahren, den Verein kennenlernen oder dich im Vereinsleben einbringen? Wir freuen uns auf dich.');
$downloads = rcb_page_downloads('mitglied', 'Aufnahmeantrag als PDF');
$sentKey = (string)($_GET['gesendet'] ?? '');
$printable = $sentKey !== '' && rcb_antrag_from_key($sentKey) !== null;
$check = static fn(string $field, string $value) => $form[$field] === $value ? ' checked' : '';
?>
<section class="rcb-section"><div class="rcb-wrap">
<div class="rcb-grid rcb-join-grid">
<article class="rcb-card"><div class="rcb-card-body"><?= rcb_rich('mitglied.ablauf', '<h2>So wirst du Mitglied</h2><ol><li>Komm zu einem Training vorbei und lerne uns und die Strecken kennen.</li><li>Fülle unten den Aufnahmeantrag online aus – oder lade ihn als PDF herunter.</li><li>Drucke den Antrag aus, unterschreibe ihn (bei Minderjährigen zusätzlich die Eltern) und gib ihn beim Vorstand ab.</li></ol>') ?></div></article>
<article class="rcb-card"><div class="rcb-card-body">
<?php if ($downloads !== ''): ?><?= $downloads ?>
<?php else: ?><h2>Aufnahmeantrag als PDF</h2><p>Den Antrag zum Ausdrucken bekommst du beim Training oder auf Anfrage per E-Mail.</p><?php if (rcb_admin_viewer()): ?><p class="rcb-admin-hint">Admin-Hinweis: Lade das PDF im Admin unter <a href="/admin/index.php?page=files&amp;seite=mitglied">📎 Dateien</a> hoch und ordne es „Mitglied werden“ zu.</p><?php endif; ?>
<?php endif; ?>
<?= rcb_bank_html($config) ?><?php if (rcb_admin_viewer()): ?><p class="rcb-admin-hint">Admin: Bankverbindung, Beiträge und Formular änderst du unter <a href="/admin/index.php?page=membership">🪪 Mitgliedschaft</a>.</p><?php endif; ?>
<?php if ($email !== ''): ?><p><strong>Fragen?</strong> <a href="mailto:<?= rcb_h($email) ?>"><?= rcb_h($email) ?></a></p><?php endif; ?>
</div></article>
</div>

<?php if ($config['form_enabled'] || $sentKey !== ''): ?>
<article class="rcb-card rcb-join-form" id="antrag"><div class="rcb-card-body">
<?php if ($sentKey !== ''): ?>
<div class="rcb-form-success" role="status"><h2>Danke – dein Antrag ist angekommen!</h2>
<p>Wir haben deine Angaben erhalten und melden uns per E-Mail bei dir.</p>
<?php if ($printable): ?><p><strong>Wichtig:</strong> Für die Aufnahme brauchen wir den Antrag mit deiner Unterschrift (bei Minderjährigen zusätzlich die der Eltern). Drucke ihn dir jetzt fertig ausgefüllt aus und gib ihn beim nächsten Training ab.</p>
<p><a class="rcb-btn" href="/mitglied-werden.php?antrag=<?= rcb_h($sentKey) ?>">🖨 Ausgefüllten Antrag drucken</a></p><p class="rcb-form-note">Der Druck-Link funktioniert 24 Stunden.</p><?php endif; ?></div>
<?php else: ?>
<?= rcb_rich('mitglied.antrag.intro', '<h2>Aufnahmeantrag online ausfüllen</h2><p>Fülle den Antrag hier aus und schick ihn ab. Danach kannst du ihn fertig ausgefüllt ausdrucken – unterschrieben gibst du ihn beim Vorstand ab. Auch Änderungen deiner Mitgliedsdaten (z. B. neue Anschrift) kannst du hier melden.</p>') ?>
<?php if ($formErrors): ?><div class="rcb-form-errors" role="alert"><ul><?php foreach ($formErrors as $err): ?><li><?= rcb_h($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/mitglied-werden.php#antrag" class="rcb-form" novalidate>
<input type="hidden" name="token" value="<?= rcb_h(rcb_form_token()) ?>">
<div class="rcb-hp" aria-hidden="true"><label for="website">Webseite</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>

<?php if ($config['allow_change']): ?><fieldset><legend>Was möchtest du tun?</legend>
<label class="rcb-check"><input type="radio" name="kind" value="aufnahme"<?= $check('kind', 'aufnahme') ?>> <span>Ich beantrage hiermit die Aufnahme in die RC Bande Zwickau e.V.</span></label>
<label class="rcb-check"><input type="radio" name="kind" value="aenderung"<?= $check('kind', 'aenderung') ?>> <span>Ich möchte die Änderung meiner Mitgliedsdaten melden</span></label>
<?php if ($config['show_member_no']): ?><label for="member_no">Mitgliedernummer <small>(nur bei Änderung, falls bekannt)</small></label><input id="member_no" name="member_no" maxlength="20" inputmode="numeric" value="<?= rcb_h($form['member_no']) ?>" style="max-width:220px"><?php endif; ?>
</fieldset><?php endif; ?>

<fieldset><legend>Persönliche Angaben</legend>
<div class="rcb-form-row"><div><label for="last_name">Name *</label><input id="last_name" name="last_name" required maxlength="120" autocomplete="family-name" value="<?= rcb_h($form['last_name']) ?>"></div>
<div><label for="first_name">Vorname *</label><input id="first_name" name="first_name" required maxlength="120" autocomplete="given-name" value="<?= rcb_h($form['first_name']) ?>"></div></div>
<div class="rcb-form-row"><div><label for="birth_date">Geburtstag *</label><input id="birth_date" name="birth_date" type="date" autocomplete="bday" value="<?= rcb_h($form['birth_date']) ?>"></div><div></div></div>
<label for="street">Straße, Hausnummer *</label><input id="street" name="street" maxlength="190" autocomplete="street-address" value="<?= rcb_h($form['street']) ?>">
<div class="rcb-form-row rcb-form-row-zip"><div><label for="zip">Postleitzahl *</label><input id="zip" name="zip" maxlength="5" inputmode="numeric" autocomplete="postal-code" value="<?= rcb_h($form['zip']) ?>"></div>
<div><label for="city">Ort *</label><input id="city" name="city" maxlength="120" autocomplete="address-level2" value="<?= rcb_h($form['city']) ?>"></div></div>
<?php if ($config['show_phone'] || $config['show_mobile']): ?><div class="rcb-form-row"><?php if ($config['show_phone']): ?><div><label for="phone">Telefon</label><input id="phone" name="phone" type="tel" maxlength="60" autocomplete="tel" value="<?= rcb_h($form['phone']) ?>"></div><?php endif; ?>
<?php if ($config['show_mobile']): ?><div><label for="mobile">Mobil</label><input id="mobile" name="mobile" type="tel" maxlength="60" autocomplete="tel" value="<?= rcb_h($form['mobile']) ?>"></div><?php endif; ?></div><?php endif; ?>
<label for="email">E-Mail *</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= rcb_h($form['email']) ?>">
<p class="rcb-form-note">* Pflichtfelder<?= $config['allow_change'] ? ' für die Aufnahme. Bei einer Änderungsmeldung genügen Name, Vorname, E-Mail und die geänderten Angaben.' : '.' ?></p>
</fieldset>

<?php if ($config['show_family']): ?><fieldset><legend>Familie</legend>
<p class="rcb-q">Sind bereits andere Familienmitglieder Mitglied der RC Bande Zwickau?</p>
<div class="rcb-inline"><label class="rcb-check"><input type="radio" name="family" value="ja"<?= $check('family', 'ja') ?>> <span>Ja</span></label><label class="rcb-check"><input type="radio" name="family" value="nein"<?= $check('family', 'nein') ?>> <span>Nein</span></label></div>
<label for="family_name">Name des Familienmitglieds</label><input id="family_name" name="family_name" maxlength="190" value="<?= rcb_h($form['family_name']) ?>">
</fieldset><?php endif; ?>

<?php if ($fees): ?><fieldset><legend>Jahresbeitrag *</legend>
<div class="rcb-fee-list"><?php foreach ($fees as $key => [$label, $price]): ?>
<label class="rcb-check rcb-fee"><input type="radio" name="fee" value="<?= rcb_h($key) ?>"<?= $check('fee', $key) ?>> <span><?= rcb_h($label) ?></span><strong><?= rcb_h(rcb_price_label($price)) ?></strong></label>
<?php endforeach; ?></div>
<?php if ($config['monthly_enabled']): ?><label class="rcb-check"><input type="checkbox" name="monthly" value="1"<?= $form['monthly'] !== '' ? ' checked' : '' ?>> <span><?= rcb_h($config['monthly_label']) ?><?= isset($fees[$config['monthly_fee']]) ? ' (nur bei „' . rcb_h($fees[$config['monthly_fee']][0]) . '“)' : '' ?></span></label><?php endif; ?>
<?php if ($config['show_fee_note'] && $config['fee_note'] !== ''): ?><p class="rcb-form-note"><?= nl2br(rcb_h($config['fee_note'])) ?></p><?php endif; ?>
</fieldset><?php endif; ?>

<?php if ($config['show_guardian']): ?><fieldset><legend>Bei Minderjährigen</legend>
<label for="guardian">Name des/der gesetzlichen Vertreter(s)</label><input id="guardian" name="guardian" maxlength="300" placeholder="z. B. Maria und Thomas Muster" value="<?= rcb_h($form['guardian']) ?>">
<?php if ($config['show_guardian_text'] && $config['guardian_text'] !== ''): ?><p class="rcb-form-note"><?= rcb_h($config['guardian_text']) ?></p><?php endif; ?>
</fieldset><?php endif; ?>

<?php if ($rules): ?><fieldset><legend>Vereinsordnungen</legend>
<?php foreach ($rules as $i => $rule): $ruleFile = rcb_member_file_for($rule); ?>
<div class="rcb-yesno"><span><?= rcb_h($rule) ?> gelesen und verstanden<?php if ($ruleFile): ?> · <a href="<?= rcb_h($ruleFile['path']) ?>" target="_blank" rel="noopener">lesen</a><?php endif; ?></span><label class="rcb-check"><input type="radio" name="rule[<?= $i ?>]" value="ja"<?= $ruleAnswers[$i] === 'ja' ? ' checked' : '' ?>> <span>Ja</span></label><label class="rcb-check"><input type="radio" name="rule[<?= $i ?>]" value="nein"<?= $ruleAnswers[$i] === 'nein' ? ' checked' : '' ?>> <span>Nein</span></label></div>
<?php endforeach; ?>
<?php if ($config['show_rules_note'] && $config['rules_note'] !== ''): ?><p class="rcb-form-note"><?= nl2br(rcb_h($config['rules_note'])) ?></p><?php endif; ?>
</fieldset><?php endif; ?>

<?php if ($config['show_message']): ?><label for="message">Bemerkung</label><textarea id="message" name="message" rows="4" maxlength="3000"><?= rcb_h($form['message']) ?></textarea><?php endif; ?>

<?php if ($config['show_accept'] && $config['accept_text'] !== ''): ?><label class="rcb-check"><input type="checkbox" name="accept" value="1"<?= !empty($_POST['accept']) ? ' checked' : '' ?>> <span><?= rcb_h($config['accept_text']) ?> *</span></label><?php endif; ?>
<label class="rcb-check"><input type="checkbox" name="privacy" value="1"<?= !empty($_POST['privacy']) ? ' checked' : '' ?>> <span>Ich bin einverstanden, dass meine Angaben zur Bearbeitung des Antrags gespeichert und an den Vorstand weitergegeben werden. Mehr dazu in der <a href="/datenschutz.php" target="_blank">Datenschutzerklärung</a>. *</span></label>
<button class="rcb-btn" type="submit">Antrag absenden</button>
<p class="rcb-form-note">Bitte keine eigenen Bankdaten eintragen<?= $config['show_bank_page'] && trim($config['bank_iban']) !== '' ? ' – den Beitrag überweist du an die oben genannte Bankverbindung' : '' ?>.</p>
</form>
<?php endif; ?>
</div></article>
<?php endif; ?>
</div></section>
<?php rcb_footer(); ?>
