<?php

declare(strict_types=1); ?>

<section class="card wide">
    <h2>Rangliste</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Spieler</th>
                <?php if ($loggedIn): ?><th>Relation</th><?php endif; ?>
                <th>Punkte</th>
                <th>S</th>
                <th>N</th>
                <th>U</th>
                <th>Spiele</th>
                <th>Quote</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leaderboard as $index => $row): ?>
                <tr <?= $loggedIn && (int)$row['id'] === (int)currentUserId() ? 'class="row-self"' : '' ?>>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <?php if ($loggedIn): ?>
                            <a href="index.php?page=h2h&opponent_id=<?= (int)$row['id'] ?>" class="player-link"><?= esc($row['username']) ?></a>
                        <?php else: ?>
                            <?= esc($row['username']) ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($loggedIn): ?>
                        <td>
                            <?php if ((int)$row['id'] === (int)currentUserId()): ?>
                                <span class="pill self">Du</span>
                            <?php elseif (isset($friendIdsLookup[(int)$row['id']])): ?>
                                <span class="pill friend">Freund</span>
                            <?php else: ?>
                                <span class="pill neutral">Liga</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><strong><?= (int)$row['points'] ?></strong></td>
                    <td><?= (int)$row['wins'] ?></td>
                    <td><?= (int)$row['losses'] ?></td>
                    <td><?= (int)$row['draws'] ?></td>
                    <td><?= (int)$row['games_played'] ?></td>
                    <td>
                        <?php if ((int)$row['games_played'] > 0): ?>
                            <?= number_format(100 * (int)$row['wins'] / (int)$row['games_played'], 0) ?>%
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($loggedIn): ?>
    <section class="card wide">
        <h2>Freunde-Rangliste</h2>
        <?php if (count($friendsLeaderboard) === 0): ?>
            <p>Füge Freunde hinzu, um eine separate Freundes-Rangliste zu sehen.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Spieler</th>
                        <th>Punkte</th>
                        <th>S</th>
                        <th>N</th>
                        <th>Spiele</th>
                        <th>Quote</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($friendsLeaderboard as $index => $row): ?>
                        <tr <?= (int)$row['id'] === (int)currentUserId() ? 'class="row-self"' : '' ?>>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <a href="index.php?page=h2h&opponent_id=<?= (int)$row['id'] ?>" class="player-link"><?= esc($row['username']) ?></a>
                                <?php if ((int)$row['id'] === (int)currentUserId()): ?>
                                    <span class="pill self">Du</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int)$row['points'] ?></strong></td>
                            <td><?= (int)$row['wins'] ?></td>
                            <td><?= (int)$row['losses'] ?></td>
                            <td><?= (int)$row['games_played'] ?></td>
                            <td>
                                <?php if ((int)$row['games_played'] > 0): ?>
                                    <?= number_format(100 * (int)$row['wins'] / (int)$row['games_played'], 0) ?>%
                                <?php else: ?>
                                    –
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>