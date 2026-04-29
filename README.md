# BillardLiga (PHP + MySQL + PWA)

Webplattform fuer Billardspieler mit:

- Registrierung nur mit Secret-Code aus `.env`
- Login/Logout
- Rangliste auf Basis bestaetigter Spiele
- Wettkaempfe erstellen und beitreten
- Einzelspiele gegeneinander melden
- Ergebnis-Bestaetigung durch den anderen Spieler
- Freundesystem (Anfrage, annehmen, entfernen)
- Profilseite mit Avatar-Upload
- PWA-Unterstuetzung (installierbar, Offline-Cache)

## 1) Voraussetzungen

- PHP 8.1+
- MySQL 8+

Alternativ ohne lokale Installation:

- Docker + Docker Compose

## 2) Einrichtung

1. Datei kopieren:

   ```bash
   cp .env.example .env
   ```

2. In `.env` Werte setzen (vor allem `REGISTER_SECRET` und DB-Zugang).

3. Datenbank-Schema importieren:

   ```bash
   mysql -u root -p < sql/schema.sql
   ```

4. Lokalen PHP-Server starten:

   ```bash
   php -S localhost:8000 -t public
   ```

5. Im Browser aufrufen:

   ```
   http://localhost:8000/index.php
   ```

## 2b) Einrichtung mit Docker (empfohlen, wenn nichts lokal installiert ist)

1. Docker-Umgebung kopieren:

   ```bash
   cp .env.docker .env
   ```

2. Secret-Code setzen (`REGISTER_SECRET`) in `.env`.

3. Container starten:

   ```bash
   docker compose up --build
   ```

4. App im Browser oeffnen:

   ```
   http://localhost:8080/index.php
   ```

Hinweis: Das SQL-Schema wird beim ersten Start automatisch importiert.

Bei bestehenden Datenbanken bitte danach einmal die Migration ausfuehren:

```bash
docker compose exec -T db mysql -ubillard -pbillard -D billard_app < sql/migrations/2026-04-29-friends-and-avatar.sql
```

## 3) Hinweise

- Spiele zaehlen erst fuer die Rangliste, wenn der Gegenspieler das Ergebnis bestaetigt.
- Jeder Spieler kann nur Spiele bestaetigen/ablehnen, die er nicht selbst gemeldet hat.
- Punkte-System in der Rangliste: Sieg = 3 Punkte, Niederlage = 0.

## 4) Projektstruktur

- `public/index.php`: UI und Aktionen
- `public/assets/css/style.css`: Styling
- `public/assets/js/app.js`: PWA-Install-Flow + Service Worker Registration
- `public/sw.js`: Offline-Caching
- `src/config.php`: `.env` laden + Session
- `src/db.php`: PDO-Verbindung
- `src/auth.php`: Registrierung/Login/Logout
- `src/helpers.php`: Utilities
- `sql/schema.sql`: Tabellen
