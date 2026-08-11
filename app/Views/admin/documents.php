<section class="admin-header">

    <h1>Dokumente</h1>

    <p>
        Ausschreibungen, Reglements und weitere Dokumente verwalten.
    </p>

</section>

<section class="admin-form">

    <form
        method="post"
        enctype="multipart/form-data"
    >

        <h2>Neues Dokument</h2>

        <label for="document">
            PDF auswählen
        </label>

        <input
            type="file"
            id="document"
            name="document"
            accept="application/pdf"
        >

        <label for="title">
            Bezeichnung
        </label>

        <input
            type="text"
            id="title"
            name="title"
            placeholder="z.B. Ausschreibung Vereinslauf"
        >

        <label for="category">
            Kategorie
        </label>

        <select
            id="category"
            name="category"
        >

            <option value="event">
                Veranstaltung
            </option>

            <option value="reglement">
                Reglement
            </option>

            <option value="membership">
                Mitgliedschaft
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

            Dokument öffentlich freigeben

        </label>

        <button
            type="submit"
            class="button"
        >

            Dokument speichern

        </button>

    </form>

</section>
