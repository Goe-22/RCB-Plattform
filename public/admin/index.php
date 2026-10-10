<?php
declare(strict_types=1);

/*
 * RCB Plattform – Admin-Anmeldung
 * Hinweis: Die echte Anmeldung mit serverseitiger Prüfung und Session-Schutz
 * wird im nächsten Schritt ergänzt. Diese Datei stellt zunächst die Oberfläche bereit.
 */
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin-Anmeldung | RC Bande Zwickau</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-page">
    <main class="login-card">
        <div class="login-mark" aria-hidden="true">RCB</div>
        <p class="eyebrow">RC BANDE ZWICKAU E.V.</p>
        <h1>Adminbereich</h1>
        <p class="intro">Melde dich an, um die Vereinsplattform zu verwalten.</p>

        <form action="login.php" method="post" autocomplete="on">
            <label for="username">Benutzername oder E-Mail</label>
            <input id="username" name="username" type="text" autocomplete="username" required>

            <label for="password">Passwort</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">Anmelden</button>
        </form>

        <a class="back-link" href="../../index.html">← Zur öffentlichen Webseite</a>
        <p class="security-note">Geschützter Verwaltungsbereich · Zugang nur für berechtigte Personen</p>
    </main>
</body>
</html>
