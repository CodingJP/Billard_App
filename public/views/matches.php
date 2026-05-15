<?php

declare(strict_types=1); ?>

<section class="card wide">
    <h2>Einzelspiel melden</h2>
    <form method="post" class="form-grid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="report_game">
        <label>Spieler 1
            <select name="player1_id" required>
                <option value="">Wählen…</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>"
                        <?= (int)$user['id'] === (int)currentUserId() ? 'selected' : '' ?>>
                        <?= esc($user['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Spieler 2
            <select name="player2_id" required>
                <option value="">Wählen…</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>"><?= esc($user['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Punkte Spieler 1
            <input type="number" name="score_player1" min="0" max="9999" required>
        </label>
        <label>Punkte Spieler 2
            <input type="number" name="score_player2" min="0" max="9999" required>
        </label>
        <label>Wettkampf (optional)
            <select name="competition_id">
                <option value="0">Kein Wettkampf</option>
                <?php foreach ($competitions as $competition): ?>
                    <option value="<?= (int)$competition['id'] ?>"><?= esc($competition['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Spieltag
            <input type="date" name="played_at" value="<?= esc(date('Y-m-d')) ?>" max="<?= esc(date('Y-m-d')) ?>" required>
        </label>
        <div style="grid-column: span 3">
            <button type="submit">Ergebnis einreichen</button>
        </div>
    </form>
</section>

<section class="card">
    <h2>Offene Bestätigungen</h2>
    <?php if (count($pendingConfirmations) === 0): ?>
        <p>Aktuell keine ausstehenden Ergebnis-Bestätigungen.</p>
    <?php else: ?>
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
    <?php endif; ?>
</section>

<section class="card">
    <h2>Letzte Spiele</h2>
    <?php if (count($recentGames) === 0): ?>
        <p>Es wurden noch keine Spiele eingetragen.</p>
    <?php else: ?>
        <ul class="item-list">
            <?php foreach ($recentGames as $game): ?>
                <li>
                    <div>
                        <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                        <small>
                            <?php if ($game['winner_name']): ?>
                                Sieger: <?= esc($game['winner_name']) ?>
                            <?php else: ?>
                                Unentschieden
                            <?php endif; ?>
                            <?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?>
                        </small>
                    </div>
                    <span class="pill <?= $game['status'] === 'confirmed' ? 'friend' : ($game['status'] === 'rejected' ? 'danger' : 'neutral') ?>">
                        <?= $game['status'] === 'confirmed' ? 'Bestätigt' : ($game['status'] === 'rejected' ? 'Abgelehnt' : 'Ausstehend') ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($gamesTotalPages > 1): ?>
    <section class="card wide">
        <div class="pagination">
            <?php if ($gamesPage > 1): ?>
                <a class="button-link secondary" href="index.php?page=matches&gp=<?= $gamesPage - 1 ?>">← Zurück</a>
            <?php endif; ?>
            <span class="pagination-info">Seite <?= $gamesPage ?> von <?= $gamesTotalPages ?></span>
            <?php if ($gamesPage < $gamesTotalPages): ?>
                <a class="button-link secondary" href="index.php?page=matches&gp=<?= $gamesPage + 1 ?>">Weiter →</a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>