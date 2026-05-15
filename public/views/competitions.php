<?php

declare(strict_types=1); ?>

<?php if (count($myPendingInvitations) > 0): ?>
    <section class="card wide invite-banner">
        <h2>📬 Einladungen zu Wettkämpfen</h2>
        <ul class="item-list">
            <?php foreach ($myPendingInvitations as $inv): ?>
                <li>
                    <div>
                        <strong><?= esc($inv['name']) ?></strong>
                        <small>Eingeladen von <?= esc($inv['invited_by_name']) ?></small>
                    </div>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="join_competition">
                        <input type="hidden" name="competition_id" value="<?= (int)$inv['id'] ?>">
                        <button type="submit">Beitreten</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card wide">
    <h2>Wettkampf-Übersicht</h2>
    <div class="quick-actions">
        <div class="quick-link quick-link-static">
            <div>
                <p class="stat-label">Gesamt</p>
                <p class="metric"><?= (int)$competitionStatusOverview['total'] ?></p>
            </div>
        </div>
        <div class="quick-link quick-link-static">
            <div>
                <p class="stat-label">Geplant</p>
                <p class="metric"><?= (int)$competitionStatusOverview['planned'] ?></p>
            </div>
        </div>
        <div class="quick-link quick-link-static">
            <div>
                <p class="stat-label">Aktiv</p>
                <p class="metric"><?= (int)$competitionStatusOverview['active'] ?></p>
            </div>
        </div>
    </div>
</section>

<section class="card">
    <h2>Wettkampf erstellen</h2>
    <form method="post" class="form-stack">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_competition">
        <label>Name
            <input type="text" name="name" maxlength="120" required>
        </label>
        <label>Beschreibung (optional)
            <textarea name="description" rows="2" style="resize:vertical"></textarea>
        </label>
        <label>Startdatum
            <input type="date" name="start_date" required>
        </label>
        <label>Status
            <select name="status">
                <option value="planned">Geplant</option>
                <option value="active">Aktiv</option>
                <option value="finished">Beendet</option>
            </select>
        </label>
        <label>Beitritt
            <select name="join_mode">
                <option value="open">Offen – jeder kann beitreten</option>
                <option value="invite">Nur auf Einladung</option>
            </select>
        </label>
        <button type="submit">Wettkampf speichern</button>
    </form>
</section>

<section class="card">
    <h2>Wettkämpfe</h2>
    <?php if (count($competitions) === 0): ?>
        <p>Noch keine Wettkämpfe vorhanden.</p>
    <?php else: ?>
        <ul class="item-list">
            <?php foreach ($competitions as $competition): ?>
                <li>
                    <div>
                        <strong><?= esc($competition['name']) ?></strong>
                        <small>
                            <?= $competition['status'] === 'planned' ? 'Geplant' : ($competition['status'] === 'active' ? 'Aktiv' : 'Beendet') ?>
                            | Start: <?= esc($competition['start_date']) ?>
                            | <?= esc($competition['creator']) ?>
                            | <?= (int)$competition['participants_count'] ?> Teilnehmer
                            <?= $competition['join_mode'] === 'invite' ? ' | 🔒 Einladung' : '' ?>
                        </small>
                    </div>
                    <div class="inline-actions">
                        <a class="button-link secondary" href="index.php?page=competitions&competition_id=<?= (int)$competition['id'] ?>">Auswertung</a>
                        <?php if (!in_array((int)$competition['id'], $myCompetitionIds, true)): ?>
                            <?php if ($competition['join_mode'] === 'open'): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="join_competition">
                                    <input type="hidden" name="competition_id" value="<?= (int)$competition['id'] ?>">
                                    <button type="submit" class="secondary">Beitreten</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="pill friend">Beigetreten</span>
                            <?php if ((int)$competition['created_by'] !== (int)currentUserId()): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="leave_competition">
                                    <input type="hidden" name="competition_id" value="<?= (int)$competition['id'] ?>">
                                    <button type="submit" class="secondary btn-sm">Verlassen</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card wide">
    <?php if (!$selectedCompetition): ?>
        <h2>Auswertung</h2>
        <p>Wähle bei einem Wettkampf den Button <em>Auswertung</em>, um Statistik, Rangliste und Turnier-Matchings zu sehen.</p>
    <?php else: ?>
        <h2>Auswertung: <?= esc($selectedCompetition['name']) ?></h2>
        <p class="stat-hint">
            Status: <?= esc($selectedCompetition['status']) ?> | Start: <?= esc($selectedCompetition['start_date']) ?>
            <?php if (!empty($selectedCompetition['description'])): ?>
                | <?= esc($selectedCompetition['description']) ?>
            <?php endif; ?>
        </p>

        <?php if ((int)$selectedCompetition['created_by'] === (int)currentUserId()): ?>
            <div class="competition-admin">
                <h3>Admin-Bereich</h3>
                <div class="inline-actions" style="flex-wrap:wrap;gap:0.75rem">
                    <form method="post" style="display:flex;gap:0.5rem;align-items:center">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_competition_status">
                        <input type="hidden" name="competition_id" value="<?= (int)$selectedCompetition['id'] ?>">
                        <select name="status" style="width:auto">
                            <option value="planned" <?= $selectedCompetition['status'] === 'planned' ? 'selected' : '' ?>>Geplant</option>
                            <option value="active" <?= $selectedCompetition['status'] === 'active'  ? 'selected' : '' ?>>Aktiv</option>
                            <option value="finished" <?= $selectedCompetition['status'] === 'finished' ? 'selected' : '' ?>>Beendet</option>
                        </select>
                        <button type="submit" class="secondary btn-sm">Status setzen</button>
                    </form>

                    <?php if ($selectedCompetition['join_mode'] === 'invite' && count($users) > 0): ?>
                        <form method="post" style="display:flex;gap:0.5rem;align-items:center">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="invite_to_competition">
                            <input type="hidden" name="competition_id" value="<?= (int)$selectedCompetition['id'] ?>">
                            <select name="target_user_id" style="width:auto">
                                <?php foreach ($users as $u): ?>
                                    <?php if ((int)$u['id'] !== (int)currentUserId()): ?>
                                        <option value="<?= (int)$u['id'] ?>"><?= esc($u['username']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="secondary btn-sm">Einladen</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <h3>Rangliste im Wettkampf</h3>
        <?php if (count($selectedCompetitionLeaderboard) === 0): ?>
            <p>Keine Ranglistendaten vorhanden.</p>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($selectedCompetitionLeaderboard as $idx => $row): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><?= esc($row['username']) ?></td>
                            <td><strong><?= (int)$row['points'] ?></strong></td>
                            <td><?= (int)$row['wins'] ?></td>
                            <td><?= (int)$row['losses'] ?></td>
                            <td><?= (int)$row['games_played'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3>Teilnehmer (<?= count($selectedCompetitionParticipants) ?>)</h3>
        <?php if (count($selectedCompetitionParticipants) === 0): ?>
            <p>Noch keine Teilnehmer eingetragen.</p>
        <?php else: ?>
            <ul class="item-list avatar-list">
                <?php foreach ($selectedCompetitionParticipants as $participant): ?>
                    <li>
                        <div class="avatar-row">
                            <img class="avatar" src="<?= esc(avatarUrl($participant['avatar_path'] ?? null)) ?>" alt="">
                            <strong><?= esc($participant['username']) ?></strong>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3>Letzte Wettkampfspiele</h3>
        <?php if (count($selectedCompetitionGames) === 0): ?>
            <p>Noch keine Spiele für diesen Wettkampf.</p>
        <?php else: ?>
            <ul class="item-list">
                <?php foreach ($selectedCompetitionGames as $game): ?>
                    <li>
                        <div>
                            <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                            <small>
                                <?php if ($game['winner_name']): ?>
                                    Sieger: <?= esc($game['winner_name']) ?>
                                <?php else: ?>
                                    Unentschieden
                                <?php endif; ?>
                                | <?= esc($game['played_at']) ?>
                            </small>
                        </div>
                        <span class="pill <?= $game['status'] === 'confirmed' ? 'friend' : 'neutral' ?>">
                            <?= $game['status'] === 'confirmed' ? 'Bestätigt' : 'Ausstehend' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3>Turnier-Matchings (Round Robin)</h3>
        <?php if (count($roundRobinPairings) === 0): ?>
            <p>Für Matchings werden mindestens 2 Teilnehmer benötigt.</p>
        <?php else: ?>
            <ul class="item-list compact-list">
                <?php foreach (array_slice($roundRobinPairings, 0, 20) as $pairing): ?>
                    <li><strong><?= esc($pairing['a']) ?> vs <?= esc($pairing['b']) ?></strong></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($roundRobinPairings) > 20): ?>
                <p class="stat-hint">+<?= count($roundRobinPairings) - 20 ?> weitere Paarungen</p>
            <?php endif; ?>
        <?php endif; ?>

        <h3>KO-Phase (Vorschlag)</h3>
        <?php if (count($semiFinalPairings) < 2): ?>
            <p>Für das Halbfinale werden mindestens 4 Spieler mit Ranglistendaten benötigt.</p>
        <?php else: ?>
            <ul class="item-list compact-list">
                <?php foreach ($semiFinalPairings as $semi): ?>
                    <li><strong><?= esc($semi['label']) ?>:</strong> <?= esc($semi['a']) ?> vs <?= esc($semi['b']) ?></li>
                <?php endforeach; ?>
                <?php if ($finalSuggestion): ?>
                    <li><strong>Finale:</strong> <?= esc($finalSuggestion) ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>