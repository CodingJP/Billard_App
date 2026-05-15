<?php

declare(strict_types=1); ?>

<?php if ($loggedIn): ?>
    <section class="card wide">
        <h2>Du bist bereits angemeldet</h2>
        <p>Nutze das Menü oben, um zu Spielen, Wettkämpfen oder deiner Rangliste zu wechseln.</p>
    </section>
<?php else: ?>

    <section class="card">
        <h2>Anmelden</h2>
        <form method="post" class="form-stack">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="login">
            <label>Benutzername oder E-Mail
                <input type="text" name="username_or_email" autocomplete="username" required>
            </label>
            <label>Passwort
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Einloggen</button>
        </form>
    </section>

    <section class="card">
        <h2>Registrierung mit Secret-Code</h2>
        <form method="post" class="form-stack">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="register">
            <label>Benutzername
                <input type="text" name="username" minlength="3" maxlength="40" autocomplete="username" required>
            </label>
            <label>E-Mail
                <input type="email" name="email" autocomplete="email" required>
            </label>
            <label>Passwort (min. 8 Zeichen)
                <input type="password" name="password" minlength="8" autocomplete="new-password" required>
            </label>
            <label>Secret-Code
                <input type="text" name="secret_code" required>
            </label>
            <button type="submit">Account erstellen</button>
        </form>
    </section>

<?php endif; ?>