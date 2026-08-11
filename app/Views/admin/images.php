<section class="admin-header">

    <h1>Bilderverwaltung</h1>

    <p>
        Bilder hochladen, verwalten und für die Webseite freigeben.
    </p>

</section>

<section class="admin-form">

    <form
        method="post"
        enctype="multipart/form-data"
    >

        <h2>Neues Bild</h2>

        <label for="image">
            Bild auswählen
        </label>

        <input
            type="file"
            id="image"
            name="image"
            accept="image/jpeg,image/png,image/webp"
        >

        <label for="title">
            Bezeichnung
        </label>

        <input
            type="text"
            id="title"
            name="title"
            placeholder="z.B. Onroad Strecke"
        >

        <label for="description">
            Beschreibung
        </label>

        <textarea
            id="description"
            name="description"
            rows="4"
        ></textarea>

        <label for="category">
            Verwendung
        </label>

        <select
            id="category"
            name="category"
        >

            <option value="home">
                Startseite
            </option>

            <option value="verein">
                Verein
            </option>

            <option value="onroad">
                Onroad
            </option>

            <option value="offroad">
                Offroad
            </option>

            <option value="event">
                Veranstaltung
            </option>

            <option value="other">
                Sonstiges
            </option>

        </select>

        <label>

            <input
                type="checkbox"
                name="published"
            >

            Bild öffentlich freigeben

        </label>

        <button
            type="submit"
            class="button"
        >

            Bild speichern

        </button>

    </form>

</section>
