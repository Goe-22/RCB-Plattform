<section class="admin-header">

    <h1>Wartung / Arbeitseinsätze</h1>

    <p>
        Vereinsarbeiten, Wartung und gemeinsame Arbeitseinsätze verwalten.
    </p>

</section>

<section class="admin-form">

    <form method="post">

        <label for="title">
            Titel
        </label>

        <input
            type="text"
            id="title"
            name="title"
            placeholder="z.B. Streckenpflege"
        >

        <label for="date">
            Datum
        </label>

        <input
            type="date"
            id="date"
            name="date"
        >

        <label for="time">
            Uhrzeit
        </label>

        <input
            type="time"
            id="time"
            name="time"
        >

        <label for="location">
            Ort
        </label>

        <input
            type="text"
            id="location"
            name="location"
        >

        <label for="description">
            Beschreibung
        </label>

        <textarea
            id="description"
            name="description"
            rows="7"
        ></textarea>

        <label for="contact">
            Ansprechpartner
        </label>

        <input
            type="text"
            id="contact"
            name="contact"
        >

        <h2>Öffentliche Anzeige</h2>

        <label>
            <input
                type="checkbox"
                name="published"
            >

            Arbeitseinsatz öffentlich anzeigen
        </label>

        <button
            type="submit"
            class="button"
        >
            Änderungen speichern
        </button>

    </form>

</section>
