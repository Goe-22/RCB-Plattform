<section class="admin-header">

    <h1>Reglement</h1>

    <p>
        Vereinsklassen und Reglemente verwalten.
        Jede Klasse kann separat veröffentlicht werden.
    </p>

</section>

<section class="admin-form">

    <form method="post">

        <h2>Klasse 1</h2>

        <label for="class1_title">
            Bezeichnung
        </label>

        <input
            type="text"
            id="class1_title"
            name="class1_title"
            value="Klasse 1"
        >

        <label for="class1_description">
            Beschreibung
        </label>

        <textarea
            id="class1_description"
            name="class1_description"
            rows="6"
        ></textarea>

        <label for="class1_rules">
            Reglement
        </label>

        <textarea
            id="class1_rules"
            name="class1_rules"
            rows="12"
        ></textarea>

        <label>

            <input
                type="checkbox"
                name="class1_published"
            >

            Klasse 1 öffentlich anzeigen

        </label>

        <hr>

        <h2>Klasse 2</h2>

        <label for="class2_title">
            Bezeichnung
        </label>

        <input
            type="text"
            id="class2_title"
            name="class2_title"
            value="Klasse 2"
        >

        <label for="class2_description">
            Beschreibung
        </label>

        <textarea
            id="class2_description"
            name="class2_description"
            rows="6"
        ></textarea>

        <label for="class2_rules">
            Reglement
        </label>

        <textarea
            id="class2_rules"
            name="class2_rules"
            rows="12"
        ></textarea>

        <label>

            <input
                type="checkbox"
                name="class2_published"
            >

            Klasse 2 öffentlich anzeigen

        </label>

        <hr>

        <h2>Reglement-Seite</h2>

        <label>

            <input
                type="checkbox"
                name="reglement_published"
            >

            Reglement öffentlich anzeigen

        </label>

        <button
            type="submit"
            class="button"
        >

            Änderungen speichern

        </button>

    </form>

</section>
