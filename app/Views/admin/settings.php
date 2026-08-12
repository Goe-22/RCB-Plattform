<section class="admin-header">

    <h1>Systemeinstellungen</h1>

    <p>
        Grundeinstellungen der Webseite.
    </p>

</section>

<section class="admin-form">

    <form method="post">

        <h2>Webseite</h2>

        <label for="site_name">
            Webseitenname
        </label>

        <input
            type="text"
            id="site_name"
            name="site_name"
            value="RC Bande Zwickau e.V."
        >

        <label for="site_description">
            Kurzbeschreibung
        </label>

        <textarea
            id="site_description"
            name="site_description"
            rows="4"
        ></textarea>

        <h2>Öffentliche Bereiche</h2>

        <label>
            <input
                type="checkbox"
                name="site_enabled"
                checked
            >

            Webseite öffentlich aktiv
        </label>

        <label>
            <input
                type="checkbox"
                name="live_enabled"
            >

            Live-Bereich aktivieren
        </label>

        <label>
            <input
                type="checkbox"
                name="results_enabled"
                checked
            >

            Ergebnisse aktivieren
        </label>

        <label>
            <input
                type="checkbox"
                name="news_enabled"
            >

            News aktivieren
        </label>

        <h2>Social Media</h2>

        <label>
            <input
                type="checkbox"
                name="facebook_enabled"
            >

            Facebook aktivieren
        </label>

        <button
            type="submit"
            class="button"
        >
            Einstellungen speichern
        </button>

    </form>

</section>
