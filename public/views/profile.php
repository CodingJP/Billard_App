<?php

declare(strict_types=1); ?>

<section class="card">
    <h2>Profil</h2>
    <?php if ($currentProfile): ?>
        <div class="profile-header">
            <img class="avatar avatar-large" src="<?= esc(avatarUrl($currentProfile['avatar_path'] ?? null)) ?>" alt="Profilbild">
            <div>
                <p class="stat-label">Benutzername</p>
                <p class="profile-value"><?= esc($currentProfile['username']) ?></p>
                <p class="stat-label">E-Mail</p>
                <p class="profile-value"><?= esc($currentProfile['email']) ?></p>
                <p class="stat-label">Dabei seit</p>
                <p class="profile-value"><?= esc(date('d.m.Y', strtotime($currentProfile['created_at']))) ?></p>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Profilbild hochladen</h2>
    <form method="post" enctype="multipart/form-data" class="form-stack">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_avatar">
        <label>Bilddatei (JPG, PNG, WEBP, GIF, max 2 MB)
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </label>
        <button type="submit">Profilbild speichern</button>
    </form>
</section>

<section class="card stat-card">
    <p class="stat-label">Siege</p>
    <p class="metric"><?= (int)($myStats['wins'] ?? 0) ?></p>
    <p class="stat-hint"><?= (float)($myStats['win_rate'] ?? 0) ?>% Gewinnquote</p>
</section>
<section class="card stat-card">
    <p class="stat-label">Niederlagen</p>
    <p class="metric"><?= (int)($myStats['losses'] ?? 0) ?></p>
    <p class="stat-hint"><?= (int)($myStats['games_played'] ?? 0) ?> Spiele gesamt</p>
</section>
<section class="card stat-card">
    <p class="stat-label">Unentschieden</p>
    <p class="metric"><?= (int)($myStats['draws'] ?? 0) ?></p>
    <p class="stat-hint">Bestätigte Spiele</p>
</section>
<section class="card stat-card">
    <p class="stat-label">Aktuelle Serie</p>
    <p class="metric"><?= $myStreak > 0 ? '+' . $myStreak : ($myStreak < 0 ? $myStreak : '–') ?></p>
    <p class="stat-hint"><?= $myStreak > 0 ? 'Siege in Folge' : ($myStreak < 0 ? 'Niederlagen in Folge' : 'Kein Lauf') ?></p>
</section>

<?php if ($myBestOpponent): ?>
    <section class="card wide">
        <h2>Meistgespielter Gegner</h2>
        <div class="avatar-row">
            <img class="avatar" src="<?= esc(avatarUrl($myBestOpponent['avatar_path'] ?? null)) ?>" alt="">
            <div>
                <strong><?= esc($myBestOpponent['username']) ?></strong>
                <p class="stat-hint"><?= (int)$myBestOpponent['match_count'] ?> gemeinsame Spiele</p>
            </div>
            <a href="index.php?page=h2h&opponent_id=<?= (int)$myBestOpponent['id'] ?>" class="button-link secondary" style="margin-left:auto">H2H ansehen</a>
        </div>
    </section>
<?php endif; ?>

<section class="card wide">
    <h2>Letzte 10 Spiele</h2>
    <?php if (empty($myRecentGames)): ?>
        <p>Noch keine bestätigten Spiele vorhanden.</p>
    <?php else: ?>
        <ul class="item-list">
            <?php foreach ($myRecentGames as $game): ?>
                <li>
                    <div>
                        <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                        <small><?= esc($game['played_at']) ?><?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                    </div>
                    <span class="pill <?= $game['my_result'] === 'Sieg' ? 'friend' : ($game['my_result'] === 'Niederlage' ? 'danger' : 'neutral') ?>">
                        <?= esc($game['my_result']) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if (count($friends) > 0): ?>
    <section class="card wide">
        <h2>Head-to-Head gegen Freunde</h2>
        <ul class="item-list avatar-list">
            <?php foreach ($friends as $friend): ?>
                <li>
                    <div class="avatar-row">
                        <img class="avatar" src="<?= esc(avatarUrl($friend['avatar_path'] ?? null)) ?>" alt="">
                        <strong><?= esc($friend['username']) ?></strong>
                    </div>
                    <a href="index.php?page=h2h&opponent_id=<?= (int)$friend['id'] ?>" class="button-link secondary btn-sm">Vergleich anzeigen</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>