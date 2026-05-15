<?php

declare(strict_types=1); ?>

<section class="card">
    <h2>Freunde (<?= count($friends) ?>)</h2>
    <?php if (count($friends) === 0): ?>
        <p>Du hast noch keine bestätigten Freunde.</p>
    <?php else: ?>
        <ul class="item-list avatar-list">
            <?php foreach ($friends as $friend): ?>
                <li>
                    <div class="avatar-row">
                        <img class="avatar" src="<?= esc(avatarUrl($friend['avatar_path'] ?? null)) ?>" alt="">
                        <div>
                            <strong><?= esc($friend['username']) ?></strong>
                            <small>
                                <a href="index.php?page=h2h&opponent_id=<?= (int)$friend['id'] ?>" class="player-link">H2H ansehen</a>
                            </small>
                        </div>
                    </div>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remove_friend">
                        <input type="hidden" name="friend_id" value="<?= (int)$friend['id'] ?>">
                        <button type="submit" class="secondary btn-sm">Entfernen</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Freundesanfragen (<?= count($incomingFriendRequests) ?>)</h2>
    <?php if (count($incomingFriendRequests) === 0): ?>
        <p>Keine offenen Anfragen.</p>
    <?php else: ?>
        <ul class="item-list avatar-list">
            <?php foreach ($incomingFriendRequests as $request): ?>
                <li>
                    <div class="avatar-row">
                        <img class="avatar" src="<?= esc(avatarUrl($request['avatar_path'] ?? null)) ?>" alt="">
                        <div>
                            <strong><?= esc($request['username']) ?></strong>
                            <small>möchte dein Freund werden</small>
                        </div>
                    </div>
                    <div class="inline-actions">
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="accept_friend_request">
                            <input type="hidden" name="friendship_id" value="<?= (int)$request['id'] ?>">
                            <button type="submit">Annehmen</button>
                        </form>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject_friend_request">
                            <input type="hidden" name="friendship_id" value="<?= (int)$request['id'] ?>">
                            <button type="submit" class="secondary">Ablehnen</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card wide">
    <h2>Spieler finden</h2>
    <?php if (count($friendSuggestions) === 0): ?>
        <p>Aktuell keine neuen Vorschläge verfügbar – du kennst schon alle!</p>
    <?php else: ?>
        <ul class="item-list avatar-list">
            <?php foreach ($friendSuggestions as $suggestion): ?>
                <li>
                    <div class="avatar-row">
                        <img class="avatar" src="<?= esc(avatarUrl($suggestion['avatar_path'] ?? null)) ?>" alt="">
                        <div>
                            <strong><?= esc($suggestion['username']) ?></strong>
                            <small>
                                <a href="index.php?page=h2h&opponent_id=<?= (int)$suggestion['id'] ?>" class="player-link">Statistik ansehen</a>
                            </small>
                        </div>
                    </div>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="send_friend_request">
                        <input type="hidden" name="target_user_id" value="<?= (int)$suggestion['id'] ?>">
                        <button type="submit" class="secondary btn-sm">Anfrage senden</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (count($outgoingFriendRequests) > 0): ?>
        <h3>Gesendete Anfragen</h3>
        <ul class="item-list avatar-list">
            <?php foreach ($outgoingFriendRequests as $request): ?>
                <li>
                    <div class="avatar-row">
                        <img class="avatar" src="<?= esc(avatarUrl($request['avatar_path'] ?? null)) ?>" alt="">
                        <div>
                            <strong><?= esc($request['username']) ?></strong>
                            <small>Warte auf Antwort…</small>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>