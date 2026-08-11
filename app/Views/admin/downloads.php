<section class="admin-header">

    <h1>Downloads</h1>

    <p>
        Öffentlich bereitgestellte Dateien verwalten.
    </p>

</section>

<section class="admin-form">

    <form
        method="post"
        enctype="multipart/form-data"
    >

        <label for="file">
            Datei auswählen
        </label>

        <input
            type="file"
            id="file"
            name="file"
        >

        <label for="title">
            Bezeichnung
        </label>

        <input
            type="text"
            id="title"
            name="title"
            placeholder="Dateiname / Beschreibung"
        >

        <label for="description">
            Beschreibung
        </label>

        <textarea
            id="description"
            name="description"
            rows="4"
        ></textarea>

        <label>

            <input
                type="checkbox"
                name="published"
            >

            Download öffentlich freigeben

        </label>

        <button
            type="submit"
            class="button"
        >

            Download speichern

        </button>

    </form>

</section>
