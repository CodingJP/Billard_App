<?php

declare(strict_types=1); ?>

<?php if (!$opponent): ?>

    <section class="card wide">
        <h2>Head-to-Head Vergleich</h2>
        <p>Wähle einen Spieler aus, um euren direkten Vergleich zu sehen.</p>

        <?php if (empty($h2hOpponents)): ?>
            <p class="stat-hint">Du hast noch keine bestätigten Spiele gegen andere Spieler.</p>
        <?php else: ?>
            <ul class="item-list avatar-list">
                <?php foreach ($h2hOpponents as $opp): ?>
                    <li>
                        <div class="avatar-row">
                            <img class="avatar" src="<?= esc(avatarUrl($opp['avatar_path'] ?? null)) ?>" alt="">
                            <div>
                                <strong><?= esc($opp['username']) ?></strong>
                                <small><?= (int)$opp['match_count'] ?> Spiel<?= (int)$opp['match_count'] !== 1 ? 'e' : '' ?></small>
                            </div>
                        </div>
                        <a href="index.php?page=h2h&opponent_id=<?= (int)$opp['id'] ?>" class="button-link secondary">Vergleich →</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<?php else: ?>

    <?php $me = currentUserId(); ?>

    <section class="card wide h2h-header">
        <h2>Head-to-Head</h2>
        <div class="h2h-players">
            <div class="h2h-player">
                <img class="avatar avatar-large" src="<?= esc(avatarUrl($_SESSION['avatar_path'] ?? null)) ?>" alt="">
                <strong>Du</strong>
            </div>
            <div class="h2h-score">
                <span class="metric"><?= (int)$h2hStats['my_wins'] ?></span>
                <span class="h2h-vs">vs</span>
                <span class="metric"><?= (int)$h2hStats['their_wins'] ?></span>
            </div>
            <div class="h2h-player">
                <img class="avatar avatar-large" src="<?= esc(avatarUrl($opponent['avatar_path'] ?? null)) ?>" alt="">
                <strong><?= esc($opponent['username']) ?></strong>
            </div>
        </div>
        <?php if ($h2hStats['draws'] > 0): ?>
            <p class="stat-hint" style="text-align:center"><?= (int)$h2hStats['draws'] ?> Unentschieden</p>
        <?php endif; ?>
    </section>

    <section class="card stat-card">
        <p class="stat-label">Deine Siege</p>
        <p class="metric"><?= (int)$h2hStats['my_wins'] ?></p>
        <p class="stat-hint">von <?= (int)$h2hStats['total'] ?> Spielen</p>
    </section>
    <section class="card stat-card">
        <p class="stat-label">Siege <?= esc($opponent['username']) ?></p>
        <p class="metric"><?= (int)$h2hStats['their_wins'] ?></p>
        <p class="stat-hint">von <?= (int)$h2hStats['total'] ?> Spielen</p>
    </section>

    <section class="card wide">
        <h2>Spielverlauf</h2>
        <?php if (empty($h2hGames)): ?>
            <p>Noch keine bestätigten Spiele zwischen euch.</p>
        <?php else: ?>
            <ul class="item-list">
                <?php foreach ($h2hGames as $game): ?>
                    <li>
                        <div>
                            <strong>
                                Du <?= (int)$game['my_score'] ?> : <?= (int)$game['their_score'] ?> <?= esc($opponent['username']) ?>
                            </strong>
                            <small><?= esc($game['played_at']) ?><?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                        </div>
                        <span class="pill <?= $game['my_result'] === 'win' ? 'friend' : ($game['my_result'] === 'loss' ? 'danger' : 'neutral') ?>">
                            <?= $game['my_result'] === 'win' ? 'Sieg' : ($game['my_result'] === 'loss' ? 'Niederlage' : 'Unentschieden') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card wide" style="text-align:center">
        <a href="index.php?page=h2h" class="button-link secondary">← Alle Gegner anzeigen</a>
    </section>

<?php endif; ?>