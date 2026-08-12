<section class="admin-header">

    <h1>Benutzerverwaltung</h1>

    <p>
        Benutzer und zukünftige Berechtigungen verwalten.
    </p>

</section>

<section class="admin-form">

    <form method="post">

        <h2>Neuen Benutzer anlegen</h2>

        <label for="username">
            Benutzername
        </label>

        <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
        >

        <label for="email">
            E-Mail
        </label>

        <input
            type="email"
            id="email"
            name="email"
            autocomplete="email"
        >

        <label for="role">
            Berechtigung
        </label>

        <select
            id="role"
            name="role"
        >

            <option value="admin">
                Administrator
            </option>

            <option value="board">
                Vorstand
            </option>

        </select>

        <p>
            Die Berechtigung „Vorstand“ wird erst aktiviert,
            wenn sie später benötigt wird.
        </p>

        <label for="password">
            Passwort
        </label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="new-password"
        >

        <button
            type="submit"
            class="button"
        >
            Benutzer speichern
        </button>

    </form>

</section>
