<?php

declare(strict_types=1); ?>

<section class="card stat-card">
    <p class="stat-label">Mitglieder</p>
    <p class="metric"><?= (int)$dashboardStats['users'] ?></p>
    <p class="stat-hint">Aktive Spieler in deiner Liga</p>
</section>
<section class="card stat-card">
    <p class="stat-label">Wettkämpfe</p>
    <p class="metric"><?= (int)$dashboardStats['competitions'] ?></p>
    <p class="stat-hint">Laufende und geplante Formate</p>
</section>
<section class="card stat-card">
    <p class="stat-label">Bestätigte Spiele</p>
    <p class="metric"><?= (int)$dashboardStats['confirmed_games'] ?></p>
    <p class="stat-hint">Wertungen, die in die Rangliste eingehen</p>
</section>
<section class="card stat-card">
    <p class="stat-label">Offene Bestätigungen</p>
    <p class="metric"><?= (int)$dashboardStats['pending'] ?></p>
    <p class="stat-hint">Warten auf Gegenspieler-Freigabe</p>
</section>

<section class="card wide">
    <h2>Schnellzugriff</h2>
    <div class="quick-actions">
        <a href="index.php?page=matches" class="quick-link">Neues Einzelspiel eintragen</a>
        <a href="index.php?page=competitions" class="quick-link">Wettkampf erstellen oder beitreten</a>
        <a href="index.php?page=leaderboard" class="quick-link">Rangliste ansehen</a>
        <a href="index.php?page=h2h" class="quick-link">Head-to-Head Vergleich</a>
    </div>
</section>

<?php if (count($pendingConfirmations) > 0): ?>
    <section class="card wide">
        <h2>⚠️ Deine offenen Bestätigungen</h2>
        <ul class="item-list">
            <?php foreach ($pendingConfirmations as $game): ?>
                <li>
                    <div>
                        <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                        <small>Gemeldet von <?= esc($game['reporter']) ?><?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                    </div>
                    <div class="inline-actions">
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="confirm_game">
                            <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                            <button type="submit">Bestätigen</button>
                        </form>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject_game">
                            <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                            <button type="submit" class="secondary">Ablehnen</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card wide">
    <h2>Letzte Aktivität</h2>
    <?php if (count($recentGames) === 0): ?>
        <p>Noch keine Spiele vorhanden. Trage dein erstes Match über Einzelspiele ein.</p>
    <?php else: ?>
        <ul class="item-list">
            <?php foreach (array_slice($recentGames, 0, 6) as $game): ?>
                <li>
                    <div>
                        <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                        <?php if ($game['winner_name']): ?>
                            <small>Sieger: <?= esc($game['winner_name']) ?><?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                        <?php else: ?>
                            <small>Unentschieden<?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="pill <?= $game['status'] === 'confirmed' ? 'friend' : ($game['status'] === 'rejected' ? 'danger' : 'neutral') ?>">
                        <?= $game['status'] === 'confirmed' ? 'Bestätigt' : ($game['status'] === 'rejected' ? 'Abgelehnt' : 'Ausstehend') ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>