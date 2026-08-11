<section class="admin-header">

    <h1>Onroad verwalten</h1>

    <p>
        Inhalte und öffentliche Anzeige der Onroad-Strecke.
    </p>

</section>

<section class="admin-form">

    <form method="post">

        <h2>Allgemeine Informationen</h2>

        <label for="title">
            Streckenname
        </label>

        <input
            type="text"
            id="title"
            name="title"
            value="Onroad-Strecke"
        >

        <label for="description">
            Beschreibung
        </label>

        <textarea
            id="description"
            name="description"
            rows="6"
        ></textarea>

        <label for="surface">
            Belag
        </label>

        <input
            type="text"
            id="surface"
            name="surface"
        >

        <label for="length">
            Streckenlänge
        </label>

        <input
            type="text"
            id="length"
            name="length"
        >

        <label for="classes">
            Klassen
        </label>

        <textarea
            id="classes"
            name="classes"
            rows="4"
        ></textarea>

        <h2>Einrichtungen</h2>

        <label>
            <input type="checkbox" name="electricity">
            Strom vorhanden
        </label>

        <label>
            <input type="checkbox" name="toilet">
            WC vorhanden
        </label>

        <label>
            <input type="checkbox" name="paddock">
            Fahrerlager vorhanden
        </label>

        <label>
            <input type="checkbox" name="guest_drivers">
            Gastfahrer erlaubt
        </label>

        <h2>Öffentliche Anzeige</h2>

        <label>
            <input type="checkbox" name="published">
            Strecke öffentlich anzeigen
        </label>

        <label>
            <input type="checkbox" name="show_status">
            Streckenstatus öffentlich anzeigen
        </label>

        <label for="status">
            Status
        </label>

        <select
            id="status"
            name="status"
        >

            <option value="open">
                🟢 Geöffnet
            </option>

            <option value="closed">
                🔴 Geschlossen
            </option>

            <option value="members">
                🟡 Nur Vereinsmitglieder
            </option>

            <option value="event">
                ⚫ Veranstaltung
            </option>

        </select>

        <button
            type="submit"
            class="button"
        >
            Änderungen speichern
        </button>

    </form>

</section>
