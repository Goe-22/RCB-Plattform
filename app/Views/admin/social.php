<section class="admin-header">

    <h1>Social Media</h1>

    <p>
        Verbindungen zu unseren sozialen Netzwerken verwalten.
    </p>

</section>

<section class="admin-form">

    <form method="post">

        <h2>Facebook</h2>

        <label for="facebook_url">
            Facebook-Adresse
        </label>

        <input
            type="url"
            id="facebook_url"
            name="facebook_url"
            placeholder="https://www.facebook.com/..."
        >

        <label>
            <input
                type="checkbox"
                name="facebook_enabled"
            >

            Facebook-Link öffentlich anzeigen
        </label>

        <hr>

        <h2>Anzeige</h2>

        <label>
            <input
                type="checkbox"
                name="facebook_footer"
            >

            Facebook im Footer anzeigen
        </label>

        <label>
            <input
                type="checkbox"
                name="facebook_home"
            >

            Facebook-Bereich auf der Startseite anzeigen
        </label>

        <label>
            <input
                type="checkbox"
                name="facebook_tracks"
            >

            Facebook-Link auf den Streckenseiten anzeigen
        </label>

        <label>
            <input
                type="checkbox"
                name="facebook_events"
            >

            Facebook-Link bei Veranstaltungen anzeigen
        </label>

        <button
            type="submit"
            class="button"
        >
            Änderungen speichern
        </button>

    </form>

</section>
