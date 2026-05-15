<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/push.php';

function avatarUrl(?string $path): string
{
    return $path ?: 'assets/icons/icon-192.svg';
}

$action = $_POST['action'] ?? null;

if ($action !== null) {
    verifyCsrfToken();
}

if ($action === 'register') {
    try {
        registerUser(
            trim($_POST['username'] ?? ''),
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? '',
            trim($_POST['secret_code'] ?? '')
        );
        flash('success', 'Registrierung erfolgreich. Du kannst dich jetzt anmelden.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('index.php?page=auth');
}

if ($action === 'login') {
    try {
        loginUser(trim($_POST['username_or_email'] ?? ''), $_POST['password'] ?? '');
        flash('success', 'Willkommen in der BillardLiga.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('index.php?page=dashboard');
}

if ($action === 'logout') {
    logoutUser();
    redirect('index.php?page=auth');
}

if ($action === 'create_competition') {
    requireLogin();

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $startDate   = trim($_POST['start_date'] ?? '');
    $status      = trim($_POST['status'] ?? 'planned');
    $joinMode    = trim($_POST['join_mode'] ?? 'open');

    if ($name === '' || $startDate === '') {
        flash('error', 'Bitte Name und Startdatum angeben.');
        redirect('index.php?page=competitions');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        flash('error', 'UngÃ¼ltiges Startdatum.');
        redirect('index.php?page=competitions');
    }

    $stmt = db()->prepare(
        'INSERT INTO competitions (name, description, start_date, status, join_mode, created_by)
         VALUES (:name, :description, :start_date, :status, :join_mode, :created_by)'
    );
    $stmt->execute([
        'name'        => $name,
        'description' => $description !== '' ? $description : null,
        'start_date'  => $startDate,
        'status'      => in_array($status, ['planned', 'active', 'finished'], true) ? $status : 'planned',
        'join_mode'   => in_array($joinMode, ['open', 'invite'], true) ? $joinMode : 'open',
        'created_by'  => currentUserId(),
    ]);

    $compId = (int)db()->lastInsertId();
    db()->prepare('INSERT IGNORE INTO competition_players (competition_id, user_id) VALUES (:cid, :uid)')
        ->execute(['cid' => $compId, 'uid' => currentUserId()]);

    flash('success', 'Wettkampf wurde erstellt. Du wurdest automatisch eingetragen.');
    redirect('index.php?page=competitions');
}

if ($action === 'update_competition_status') {
    requireLogin();

    $competitionId = (int)($_POST['competition_id'] ?? 0);
    $newStatus     = trim($_POST['status'] ?? '');

    if (!in_array($newStatus, ['planned', 'active', 'finished'], true)) {
        flash('error', 'UngÃ¼ltiger Status.');
        redirect('index.php?page=competitions&competition_id=' . $competitionId);
    }

    $check = db()->prepare('SELECT id FROM competitions WHERE id = :id AND created_by = :uid LIMIT 1');
    $check->execute(['id' => $competitionId, 'uid' => currentUserId()]);
    if (!$check->fetch()) {
        flash('error', 'Keine Berechtigung zum Ã„ndern dieses Wettkampfs.');
        redirect('index.php?page=competitions');
    }

    db()->prepare('UPDATE competitions SET status = :status WHERE id = :id')
        ->execute(['status' => $newStatus, 'id' => $competitionId]);

    flash('success', 'Wettkampf-Status aktualisiert.');
    redirect('index.php?page=competitions&competition_id=' . $competitionId);
}

if ($action === 'join_competition') {
    requireLogin();

    $competitionId = (int)($_POST['competition_id'] ?? 0);
    if ($competitionId <= 0) {
        flash('error', 'UngÃ¼ltiger Wettkampf.');
        redirect('index.php?page=competitions');
    }

    $compStmt = db()->prepare('SELECT join_mode, created_by FROM competitions WHERE id = :id LIMIT 1');
    $compStmt->execute(['id' => $competitionId]);
    $comp = $compStmt->fetch();

    if (!$comp) {
        flash('error', 'Wettkampf nicht gefunden.');
        redirect('index.php?page=competitions');
    }

    if ($comp['join_mode'] === 'invite') {
        $invStmt = db()->prepare(
            "SELECT id FROM competition_invitations WHERE competition_id = :cid AND user_id = :uid AND status = 'pending' LIMIT 1"
        );
        $invStmt->execute(['cid' => $competitionId, 'uid' => currentUserId()]);
        if (!$invStmt->fetch()) {
            flash('error', 'Dieser Wettkampf ist nur auf Einladung zugÃ¤nglich.');
            redirect('index.php?page=competitions');
        }
        db()->prepare("UPDATE competition_invitations SET status = 'accepted' WHERE competition_id = :cid AND user_id = :uid")
            ->execute(['cid' => $competitionId, 'uid' => currentUserId()]);
    }

    db()->prepare('INSERT IGNORE INTO competition_players (competition_id, user_id) VALUES (:competition_id, :user_id)')
        ->execute(['competition_id' => $competitionId, 'user_id' => currentUserId()]);

    flash('success', 'Du bist dem Wettkampf beigetreten.');
    redirect('index.php?page=competitions');
}

if ($action === 'leave_competition') {
    requireLogin();

    $competitionId = (int)($_POST['competition_id'] ?? 0);

    $check = db()->prepare('SELECT id FROM competitions WHERE id = :id AND created_by = :uid LIMIT 1');
    $check->execute(['id' => $competitionId, 'uid' => currentUserId()]);
    if ($check->fetch()) {
        flash('error', 'Als Ersteller kannst du den Wettkampf nicht verlassen. Setze ihn auf "Beendet".');
        redirect('index.php?page=competitions');
    }

    db()->prepare('DELETE FROM competition_players WHERE competition_id = :cid AND user_id = :uid')
        ->execute(['cid' => $competitionId, 'uid' => currentUserId()]);

    flash('success', 'Du hast den Wettkampf verlassen.');
    redirect('index.php?page=competitions');
}

if ($action === 'invite_to_competition') {
    requireLogin();

    $competitionId = (int)($_POST['competition_id'] ?? 0);
    $targetId      = (int)($_POST['target_user_id'] ?? 0);

    $check = db()->prepare('SELECT id FROM competitions WHERE id = :id AND created_by = :uid LIMIT 1');
    $check->execute(['id' => $competitionId, 'uid' => currentUserId()]);
    if (!$check->fetch()) {
        flash('error', 'Keine Berechtigung oder Wettkampf nicht gefunden.');
        redirect('index.php?page=competitions&competition_id=' . $competitionId);
    }

    if ($targetId <= 0) {
        flash('error', 'UngÃ¼ltiger Spieler.');
        redirect('index.php?page=competitions&competition_id=' . $competitionId);
    }

    db()->prepare(
        'INSERT IGNORE INTO competition_invitations (competition_id, user_id, invited_by) VALUES (:cid, :uid, :by)'
    )->execute(['cid' => $competitionId, 'uid' => $targetId, 'by' => currentUserId()]);

    flash('success', 'Einladung wurde versendet.');
    redirect('index.php?page=competitions&competition_id=' . $competitionId);
}

if ($action === 'report_game') {
    requireLogin();

    $player1       = (int)($_POST['player1_id'] ?? 0);
    $player2       = (int)($_POST['player2_id'] ?? 0);
    $score1        = (int)($_POST['score_player1'] ?? 0);
    $score2        = (int)($_POST['score_player2'] ?? 0);
    $competitionId = (int)($_POST['competition_id'] ?? 0);
    $playedAt      = trim($_POST['played_at'] ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $playedAt) || $playedAt > date('Y-m-d')) {
        $playedAt = date('Y-m-d');
    }

    if ($player1 <= 0 || $player2 <= 0 || $player1 === $player2) {
        flash('error', 'Bitte zwei unterschiedliche Spieler wÃ¤hlen.');
        redirect('index.php?page=matches');
    }

    if ($score1 < 0 || $score2 < 0) {
        flash('error', 'Punkte dÃ¼rfen nicht negativ sein.');
        redirect('index.php?page=matches');
    }

    if (currentUserId() !== $player1 && currentUserId() !== $player2) {
        flash('error', 'Du kannst nur Spiele melden, an denen du beteiligt bist.');
        redirect('index.php?page=matches');
    }

    // Unentschieden erlaubt: winner_id bleibt NULL
    $winnerId = $score1 !== $score2 ? ($score1 > $score2 ? $player1 : $player2) : null;

    $stmt = db()->prepare(
        'INSERT INTO games
         (competition_id, player1_id, player2_id, score_player1, score_player2, winner_id, reported_by, played_at)
         VALUES
         (:competition_id, :player1_id, :player2_id, :score_player1, :score_player2, :winner_id, :reported_by, :played_at)'
    );
    $stmt->execute([
        'competition_id' => $competitionId > 0 ? $competitionId : null,
        'player1_id'     => $player1,
        'player2_id'     => $player2,
        'score_player1'  => $score1,
        'score_player2'  => $score2,
        'winner_id'      => $winnerId,
        'reported_by'    => currentUserId(),
        'played_at'      => $playedAt,
    ]);

    $opponentId = currentUserId() === $player1 ? $player2 : $player1;
    notifyUser($opponentId, 'SpielbestÃ¤tigung erforderlich', 'Ein gemeldetes Ergebnis wartet auf deine BestÃ¤tigung in der BillardLiga.');

    flash('success', 'Spiel wurde gemeldet und wartet auf BestÃ¤tigung.');
    redirect('index.php?page=matches');
}

if ($action === 'confirm_game') {
    requireLogin();

    $gameId = (int)($_POST['game_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $gameId]);
    $game = $stmt->fetch();

    if (!$game) {
        flash('error', 'Spiel nicht gefunden.');
        redirect('index.php?page=matches');
    }

    $me = currentUserId();
    $isParticipant = $me === (int)$game['player1_id'] || $me === (int)$game['player2_id'];

    if (!$isParticipant || $me === (int)$game['reported_by']) {
        flash('error', 'Nur der andere Spieler darf bestaetigen.');
        redirect('index.php?page=matches');
    }

    db()->prepare('UPDATE games SET status = :status, confirmed_by = :confirmed_by WHERE id = :id')
        ->execute(['status' => 'confirmed', 'confirmed_by' => $me, 'id' => $gameId]);

    notifyUser((int)$game['reported_by'], 'Ergebnis bestÃ¤tigt âœ“', 'Dein eingetragenes Ergebnis wurde bestÃ¤tigt.');

    flash('success', 'Ergebnis wurde bestaetigt.');
    redirect('index.php?page=matches');
}

if ($action === 'reject_game') {
    requireLogin();

    $gameId = (int)($_POST['game_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $gameId]);
    $game = $stmt->fetch();

    if (!$game) {
        flash('error', 'Spiel nicht gefunden.');
        redirect('index.php?page=matches');
    }

    $me = currentUserId();
    $isParticipant = $me === (int)$game['player1_id'] || $me === (int)$game['player2_id'];

    if (!$isParticipant || $me === (int)$game['reported_by']) {
        flash('error', 'Nur der andere Spieler darf ablehnen.');
        redirect('index.php?page=matches');
    }

    db()->prepare('UPDATE games SET status = :status, confirmed_by = :confirmed_by WHERE id = :id')
        ->execute(['status' => 'rejected', 'confirmed_by' => $me, 'id' => $gameId]);

    flash('success', 'Ergebnis wurde abgelehnt.');
    redirect('index.php?page=matches');
}

if ($action === 'upload_avatar') {
    requireLogin();

    if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
        flash('error', 'Bitte ein Bild auswaehlen.');
        redirect('index.php?page=profile');
    }

    $avatar = $_FILES['avatar'];
    if (($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Upload fehlgeschlagen. Bitte erneut versuchen.');
        redirect('index.php?page=profile');
    }

    if (($avatar['size'] ?? 0) > 2 * 1024 * 1024) {
        flash('error', 'Maximale Dateigroesse ist 2 MB.');
        redirect('index.php?page=profile');
    }

    $tmpPath = (string)($avatar['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime]) || @getimagesize($tmpPath) === false) {
        flash('error', 'Nur gueltige Bilddateien (JPG, PNG, WEBP, GIF) sind erlaubt.');
        redirect('index.php?page=profile');
    }

    $uploadDirFs = __DIR__ . '/uploads/avatars';
    if (!is_dir($uploadDirFs)) {
        mkdir($uploadDirFs, 0755, true);
    }

    if (!is_writable($uploadDirFs)) {
        @chmod($uploadDirFs, 0777);
    }

    if (!is_writable($uploadDirFs)) {
        flash('error', 'Upload-Ordner ist nicht beschreibbar. Bitte Rechte fuer public/uploads/avatars pruefen.');
        redirect('index.php?page=profile');
    }

    $fileName = sprintf('u%d_%d.%s', (int)currentUserId(), time(), $allowed[$mime]);
    $destinationFs = $uploadDirFs . '/' . $fileName;
    $destinationWeb = 'uploads/avatars/' . $fileName;

    if (!move_uploaded_file($tmpPath, $destinationFs)) {
        flash('error', 'Bild konnte nicht gespeichert werden.');
        redirect('index.php?page=profile');
    }

    $old = db()->prepare('SELECT avatar_path FROM users WHERE id = :id');
    $old->execute(['id' => currentUserId()]);
    $oldPath = $old->fetchColumn();

    $update = db()->prepare('UPDATE users SET avatar_path = :avatar WHERE id = :id');
    $update->execute([
        'avatar' => $destinationWeb,
        'id' => currentUserId(),
    ]);

    if (is_string($oldPath) && str_starts_with($oldPath, 'uploads/avatars/')) {
        $oldFs = __DIR__ . '/' . $oldPath;
        if (is_file($oldFs)) {
            @unlink($oldFs);
        }
    }

    flash('success', 'Profilbild wurde aktualisiert.');
    redirect('index.php?page=profile');
}

if ($action === 'send_friend_request') {
    requireLogin();

    $targetId = (int)($_POST['target_user_id'] ?? 0);
    $me = (int)currentUserId();

    if ($targetId <= 0 || $targetId === $me) {
        flash('error', 'Ungueltiger Nutzer fuer Freundesanfrage.');
        redirect('index.php?page=friends');
    }

    $exists = db()->prepare(
        'SELECT id, status FROM friendships
         WHERE (requester_id = :me AND addressee_id = :target)
            OR (requester_id = :target2 AND addressee_id = :me2)
         LIMIT 1'
    );
    $exists->execute([
        'me' => $me,
        'target' => $targetId,
        'target2' => $targetId,
        'me2' => $me,
    ]);
    $existing = $exists->fetch();

    if ($existing) {
        flash('error', 'Es besteht bereits eine Freundschaft oder Anfrage.');
        redirect('index.php?page=friends');
    }

    db()->prepare('INSERT INTO friendships (requester_id, addressee_id, status) VALUES (:requester, :addressee, :status)')
        ->execute(['requester' => $me, 'addressee' => $targetId, 'status' => 'pending']);

    notifyUser($targetId, 'Neue Freundesanfrage', 'Du hast eine neue Freundesanfrage in der BillardLiga erhalten.');

    flash('success', 'Freundesanfrage gesendet.');
    redirect('index.php?page=friends');
}

if ($action === 'accept_friend_request' || $action === 'reject_friend_request') {
    requireLogin();

    $friendshipId = (int)($_POST['friendship_id'] ?? 0);
    $status = $action === 'accept_friend_request' ? 'accepted' : 'rejected';

    $stmt = db()->prepare('SELECT id FROM friendships WHERE id = :id AND addressee_id = :me AND status = :pending LIMIT 1');
    $stmt->execute([
        'id' => $friendshipId,
        'me' => currentUserId(),
        'pending' => 'pending',
    ]);

    if (!$stmt->fetch()) {
        flash('error', 'Anfrage nicht gefunden.');
        redirect('index.php?page=friends');
    }

    $update = db()->prepare('UPDATE friendships SET status = :status, responded_at = NOW() WHERE id = :id');
    $update->execute([
        'status' => $status,
        'id' => $friendshipId,
    ]);

    flash('success', $status === 'accepted' ? 'Freundesanfrage angenommen.' : 'Freundesanfrage abgelehnt.');
    redirect('index.php?page=friends');
}

if ($action === 'remove_friend') {
    requireLogin();

    $friendId = (int)($_POST['friend_id'] ?? 0);
    if ($friendId <= 0) {
        flash('error', 'Ungueltiger Freund.');
        redirect('index.php?page=friends');
    }

    $delete = db()->prepare(
        'DELETE FROM friendships
         WHERE status = :status
           AND ((requester_id = :me AND addressee_id = :friend)
             OR (requester_id = :friend2 AND addressee_id = :me2))'
    );
    $delete->execute([
        'status' => 'accepted',
        'me' => currentUserId(),
        'friend' => $friendId,
        'friend2' => $friendId,
        'me2' => currentUserId(),
    ]);

    flash('success', 'Freund wurde entfernt.');
    redirect('index.php?page=friends');
}

$loggedIn = isLoggedIn();

$allowedPages = ['auth', 'dashboard', 'matches', 'competitions', 'leaderboard', 'friends', 'profile', 'h2h'];
$page = $_GET['page'] ?? ($loggedIn ? 'dashboard' : 'auth');
if (!in_array($page, $allowedPages, true)) {
    $page = $loggedIn ? 'dashboard' : 'auth';
}

$protectedPages = ['dashboard', 'matches', 'competitions', 'friends', 'profile', 'h2h'];
if (!$loggedIn && in_array($page, $protectedPages, true)) {
    flash('error', 'Bitte zuerst anmelden.');
    redirect('index.php?page=auth');
}

$users                      = [];
$competitions               = [];
$competitionStatusOverview  = ['planned' => 0, 'active' => 0, 'finished' => 0, 'total' => 0];
$selectedCompetitionId      = isset($_GET['competition_id']) ? max(0, (int)$_GET['competition_id']) : 0;
$selectedCompetition        = null;
$selectedCompetitionParticipants = [];
$selectedCompetitionLeaderboard  = [];
$selectedCompetitionGames        = [];
$roundRobinPairings         = [];
$semiFinalPairings          = [];
$finalSuggestion            = null;
$myCompetitionIds           = [];
$myPendingInvitations       = [];
$leaderboard                = [];
$pendingConfirmations        = [];
$recentGames                = [];
$currentProfile             = null;
$friendSuggestions          = [];
$incomingFriendRequests     = [];
$outgoingFriendRequests     = [];
$friends                    = [];
$friendIdsLookup            = [];
$friendsLeaderboard         = [];
$myStats                    = ['wins' => 0, 'losses' => 0, 'draws' => 0, 'games_played' => 0, 'win_rate' => 0.0];
$myStreak                   = 0;
$myBestOpponent             = null;
$myRecentGames              = [];
$opponent                   = null;
$h2hGames                   = [];
$h2hStats                   = ['my_wins' => 0, 'their_wins' => 0, 'draws' => 0, 'total' => 0];
$h2hOpponents               = [];
$gamesPerPage               = 20;
$gamesPage                  = max(1, (int)($_GET['gp'] ?? 1));
$gamesTotalPages            = 1;
$dashboardStats = [
    'users' => 0,
    'competitions' => 0,
    'confirmed_games' => 0,
    'pending' => 0,
];

try {
    $users = db()->query('SELECT id, username, avatar_path FROM users ORDER BY username ASC')->fetchAll();
    $competitions = db()->query(
        'SELECT c.*, u.username AS creator,
                COUNT(DISTINCT cp.user_id) AS participants_count,
                SUM(CASE WHEN g.status = "confirmed" THEN 1 ELSE 0 END) AS confirmed_games,
                SUM(CASE WHEN g.status = "pending_confirmation" THEN 1 ELSE 0 END) AS pending_games
         FROM competitions c
         JOIN users u ON u.id = c.created_by
         LEFT JOIN competition_players cp ON cp.competition_id = c.id
         LEFT JOIN games g ON g.competition_id = c.id
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    )->fetchAll();

    $competitionStatusOverview['total'] = count($competitions);
    foreach ($competitions as $comp) {
        $statusKey = (string)$comp['status'];
        if (isset($competitionStatusOverview[$statusKey])) {
            $competitionStatusOverview[$statusKey]++;
        }
        if ($selectedCompetitionId > 0 && (int)$comp['id'] === $selectedCompetitionId) {
            $selectedCompetition = $comp;
        }
    }

    $leaderboard = db()->query(
        "SELECT
            u.id,
            u.username,
            u.avatar_path,
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id = u.id THEN 3
                     WHEN g.status = 'confirmed' AND g.winner_id IS NULL AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1
                     ELSE 0 END) AS points,
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id = u.id THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id IS NOT NULL AND g.winner_id <> u.id AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS losses,
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id IS NULL AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS draws,
            SUM(CASE WHEN g.status = 'confirmed' AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS games_played
         FROM users u
         LEFT JOIN games g ON (g.player1_id = u.id OR g.player2_id = u.id)
         GROUP BY u.id, u.username, u.avatar_path
         ORDER BY points DESC, wins DESC, u.username ASC"
    )->fetchAll();

    if ($loggedIn) {
        $myCompStmt = db()->prepare('SELECT competition_id FROM competition_players WHERE user_id = :id');
        $myCompStmt->execute(['id' => currentUserId()]);
        $myCompetitionIds = array_map('intval', array_column($myCompStmt->fetchAll(), 'competition_id'));

        $profileStmt = db()->prepare('SELECT id, username, email, avatar_path, created_at FROM users WHERE id = :id LIMIT 1');
        $profileStmt->execute(['id' => currentUserId()]);
        $currentProfile = $profileStmt->fetch();

        $friendSuggestionsStmt = db()->prepare(
            "SELECT u.id, u.username, u.avatar_path
             FROM users u
             WHERE u.id <> :me
               AND NOT EXISTS (
                   SELECT 1
                   FROM friendships f
                   WHERE (f.requester_id = :me2 AND f.addressee_id = u.id)
                      OR (f.requester_id = u.id AND f.addressee_id = :me3)
               )
             ORDER BY u.username ASC
             LIMIT 10"
        );
        $friendSuggestionsStmt->execute([
            'me' => currentUserId(),
            'me2' => currentUserId(),
            'me3' => currentUserId(),
        ]);
        $friendSuggestions = $friendSuggestionsStmt->fetchAll();

        $incomingStmt = db()->prepare(
            "SELECT f.id, u.id AS user_id, u.username, u.avatar_path
             FROM friendships f
             JOIN users u ON u.id = f.requester_id
             WHERE f.addressee_id = :me AND f.status = 'pending'
             ORDER BY f.created_at DESC"
        );
        $incomingStmt->execute(['me' => currentUserId()]);
        $incomingFriendRequests = $incomingStmt->fetchAll();

        $outgoingStmt = db()->prepare(
            "SELECT f.id, u.id AS user_id, u.username, u.avatar_path
             FROM friendships f
             JOIN users u ON u.id = f.addressee_id
             WHERE f.requester_id = :me AND f.status = 'pending'
             ORDER BY f.created_at DESC"
        );
        $outgoingStmt->execute(['me' => currentUserId()]);
        $outgoingFriendRequests = $outgoingStmt->fetchAll();

        $friendsStmt = db()->prepare(
            "SELECT u.id, u.username, u.avatar_path
             FROM friendships f
             JOIN users u ON u.id = CASE WHEN f.requester_id = :me THEN f.addressee_id ELSE f.requester_id END
             WHERE f.status = 'accepted'
               AND (f.requester_id = :me2 OR f.addressee_id = :me3)
             ORDER BY u.username ASC"
        );
        $friendsStmt->execute([
            'me' => currentUserId(),
            'me2' => currentUserId(),
            'me3' => currentUserId(),
        ]);
        $friends = $friendsStmt->fetchAll();

        foreach ($friends as $friend) {
            $friendIdsLookup[(int)$friend['id']] = true;
        }

        if ($page === 'competitions' && $selectedCompetitionId > 0) {
            $participantsStmt = db()->prepare(
                'SELECT u.id, u.username, u.avatar_path
                 FROM competition_players cp
                 JOIN users u ON u.id = cp.user_id
                 WHERE cp.competition_id = :competition_id
                 ORDER BY u.username ASC'
            );
            $participantsStmt->execute(['competition_id' => $selectedCompetitionId]);
            $selectedCompetitionParticipants = $participantsStmt->fetchAll();

            $competitionLeaderboardStmt = db()->prepare(
                "SELECT
                    u.id,
                    u.username,
                    u.avatar_path,
                    SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id = u.id THEN 3 ELSE 0 END) AS points,
                    SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id = u.id THEN 1 ELSE 0 END) AS wins,
                    SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id IS NOT NULL AND g.winner_id <> u.id AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS losses,
                    SUM(CASE WHEN g.status = 'confirmed' AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS games_played
                 FROM competition_players cp
                 JOIN users u ON u.id = cp.user_id
                 LEFT JOIN games g ON g.competition_id = :competition_id
                    AND (g.player1_id = u.id OR g.player2_id = u.id)
                 WHERE cp.competition_id = :competition_id_2
                 GROUP BY u.id, u.username, u.avatar_path
                 ORDER BY points DESC, wins DESC, u.username ASC"
            );
            $competitionLeaderboardStmt->execute([
                'competition_id' => $selectedCompetitionId,
                'competition_id_2' => $selectedCompetitionId,
            ]);
            $selectedCompetitionLeaderboard = $competitionLeaderboardStmt->fetchAll();

            $competitionGamesStmt = db()->prepare(
                "SELECT g.*, u1.username AS p1, u2.username AS p2, uw.username AS winner_name
                 FROM games g
                 JOIN users u1 ON u1.id = g.player1_id
                 JOIN users u2 ON u2.id = g.player2_id
                 LEFT JOIN users uw ON uw.id = g.winner_id
                 WHERE g.competition_id = :competition_id
                 ORDER BY g.created_at DESC
                 LIMIT 12"
            );
            $competitionGamesStmt->execute(['competition_id' => $selectedCompetitionId]);
            $selectedCompetitionGames = $competitionGamesStmt->fetchAll();

            // Pairings suggestion for group/league style tournaments.
            $participantCount = count($selectedCompetitionParticipants);
            if ($participantCount >= 2) {
                for ($i = 0; $i < $participantCount; $i++) {
                    for ($j = $i + 1; $j < $participantCount; $j++) {
                        $roundRobinPairings[] = [
                            'a' => $selectedCompetitionParticipants[$i]['username'],
                            'b' => $selectedCompetitionParticipants[$j]['username'],
                        ];
                    }
                }
            }

            // Knockout proposal: top 4 from current competition ranking.
            if (count($selectedCompetitionLeaderboard) >= 4) {
                $semiFinalPairings[] = [
                    'label' => 'Halbfinale 1',
                    'a' => $selectedCompetitionLeaderboard[0]['username'],
                    'b' => $selectedCompetitionLeaderboard[3]['username'],
                ];
                $semiFinalPairings[] = [
                    'label' => 'Halbfinale 2',
                    'a' => $selectedCompetitionLeaderboard[1]['username'],
                    'b' => $selectedCompetitionLeaderboard[2]['username'],
                ];
                $finalSuggestion = 'Sieger HF1 vs Sieger HF2';
            }
        }
    }

    if ($loggedIn) {
        $stmt = db()->prepare(
            "SELECT g.*, u1.username AS p1, u2.username AS p2, ur.username AS reporter, c.name AS competition_name
             FROM games g
             JOIN users u1 ON u1.id = g.player1_id
             JOIN users u2 ON u2.id = g.player2_id
             JOIN users ur ON ur.id = g.reported_by
             LEFT JOIN competitions c ON c.id = g.competition_id
             WHERE g.status = 'pending_confirmation'
               AND (:me1 = g.player1_id OR :me2 = g.player2_id)
               AND g.reported_by <> :me3
             ORDER BY g.created_at DESC"
        );
        $me = currentUserId();
        $stmt->execute([
            'me1' => $me,
            'me2' => $me,
            'me3' => $me,
        ]);
        $pendingConfirmations = $stmt->fetchAll();

        // Pending invitations
        $invitationsStmt = db()->prepare(
            "SELECT ci.competition_id, c.name AS competition_name, u.username AS invited_by_name
             FROM competition_invitations ci
             JOIN competitions c ON c.id = ci.competition_id
             JOIN users u ON u.id = ci.invited_by
             WHERE ci.user_id = :me AND ci.status = 'pending'
             ORDER BY ci.created_at DESC"
        );
        $invitationsStmt->execute(['me' => $me]);
        $myPendingInvitations = $invitationsStmt->fetchAll();
    }

    // Paginated recent games (for matches page)
    $totalGamesRow = db()->query("SELECT COUNT(*) AS cnt FROM games")->fetch();
    $totalGames = (int)($totalGamesRow['cnt'] ?? 0);
    $gamesTotalPages = max(1, (int)ceil($totalGames / $gamesPerPage));
    $gamesPage = min($gamesPage, $gamesTotalPages);
    $gamesOffset = ($gamesPage - 1) * $gamesPerPage;

    $pgStmt = db()->prepare(
        "SELECT g.*, u1.username AS p1, u2.username AS p2, uw.username AS winner_name, c.name AS competition_name
         FROM games g
         JOIN users u1 ON u1.id = g.player1_id
         JOIN users u2 ON u2.id = g.player2_id
         LEFT JOIN users uw ON uw.id = g.winner_id
         LEFT JOIN competitions c ON c.id = g.competition_id
         ORDER BY g.created_at DESC
         LIMIT :lim OFFSET :off"
    );
    $pgStmt->bindValue(':lim', $gamesPerPage, PDO::PARAM_INT);
    $pgStmt->bindValue(':off', $gamesOffset, PDO::PARAM_INT);
    $pgStmt->execute();
    $recentGames = $pgStmt->fetchAll();

    $totals = db()->query(
        "SELECT
            (SELECT COUNT(*) FROM users) AS users_total,
            (SELECT COUNT(*) FROM competitions) AS competitions_total,
            (SELECT COUNT(*) FROM games WHERE status = 'confirmed') AS confirmed_total"
    )->fetch();

    $dashboardStats = [
        'users' => (int)($totals['users_total'] ?? 0),
        'competitions' => (int)($totals['competitions_total'] ?? 0),
        'confirmed_games' => (int)($totals['confirmed_total'] ?? 0),
        'pending' => count($pendingConfirmations),
    ];

    if ($loggedIn) {
        $me = (int)currentUserId();
        $friendsLeaderboard = array_values(array_filter(
            $leaderboard,
            static fn(array $row): bool => isset($friendIdsLookup[(int)$row['id']]) || (int)$row['id'] === $me
        ));

        // Profile stats
        if ($page === 'profile') {
            $statsStmt = db()->prepare(
                "SELECT
                    SUM(CASE WHEN winner_id = :me THEN 1 ELSE 0 END) AS wins,
                    SUM(CASE WHEN winner_id IS NOT NULL AND winner_id <> :me2 AND (player1_id = :me3 OR player2_id = :me4) THEN 1 ELSE 0 END) AS losses,
                    SUM(CASE WHEN winner_id IS NULL AND (player1_id = :me5 OR player2_id = :me6) THEN 1 ELSE 0 END) AS draws,
                    SUM(CASE WHEN player1_id = :me7 OR player2_id = :me8 THEN 1 ELSE 0 END) AS games_played
                 FROM games
                 WHERE status = 'confirmed' AND (player1_id = :me9 OR player2_id = :me10)"
            );
            $statsStmt->execute(array_fill_keys([':me', ':me2', ':me3', ':me4', ':me5', ':me6', ':me7', ':me8', ':me9', ':me10'], $me));
            $statsRow = $statsStmt->fetch();
            if ($statsRow) {
                $gp = max(1, (int)$statsRow['games_played']);
                $myStats = [
                    'wins'         => (int)$statsRow['wins'],
                    'losses'       => (int)$statsRow['losses'],
                    'draws'        => (int)$statsRow['draws'],
                    'games_played' => $gp,
                    'win_rate'     => round((int)$statsRow['wins'] / $gp * 100, 1),
                ];
            }

            // Current streak
            $streakStmt = db()->prepare(
                "SELECT winner_id, player1_id, player2_id FROM games
                 WHERE status = 'confirmed' AND (player1_id = :me OR player2_id = :me2)
                 ORDER BY played_at DESC, id DESC LIMIT 20"
            );
            $streakStmt->execute([':me' => $me, ':me2' => $me]);
            $streakGames = $streakStmt->fetchAll();
            $streak = 0;
            $streakType = null;
            foreach ($streakGames as $sg) {
                $won  = (int)$sg['winner_id'] === $me;
                $draw = $sg['winner_id'] === null;
                $type = $won ? 'W' : ($draw ? 'D' : 'L');
                if ($streakType === null) {
                    $streakType = $type;
                }
                if ($type !== $streakType) {
                    break;
                }
                $streak++;
            }
            $myStreak = $streakType === 'W' ? $streak : -$streak;

            // Best opponent (most wins against)
            $bestStmt = db()->prepare(
                "SELECT u.id, u.username,
                    SUM(CASE WHEN g.winner_id = :me THEN 1 ELSE 0 END) AS wins_against
                 FROM games g
                 JOIN users u ON u.id = CASE WHEN g.player1_id = :me2 THEN g.player2_id ELSE g.player1_id END
                 WHERE g.status = 'confirmed' AND (g.player1_id = :me3 OR g.player2_id = :me4)
                 GROUP BY u.id, u.username
                 ORDER BY wins_against DESC, u.username ASC
                 LIMIT 1"
            );
            $bestStmt->execute([':me' => $me, ':me2' => $me, ':me3' => $me, ':me4' => $me]);
            $myBestOpponent = $bestStmt->fetch() ?: null;

            // Recent 10 games
            $recentStmt = db()->prepare(
                "SELECT g.*, u1.username AS p1, u2.username AS p2, uw.username AS winner_name
                 FROM games g
                 JOIN users u1 ON u1.id = g.player1_id
                 JOIN users u2 ON u2.id = g.player2_id
                 LEFT JOIN users uw ON uw.id = g.winner_id
                 WHERE g.status = 'confirmed' AND (g.player1_id = :me OR g.player2_id = :me2)
                 ORDER BY g.played_at DESC, g.id DESC LIMIT 10"
            );
            $recentStmt->execute([':me' => $me, ':me2' => $me]);
            $myRecentGames = $recentStmt->fetchAll();
        }

        // H2H data
        if ($page === 'h2h') {
            $opponentId = (int)($_GET['opponent_id'] ?? 0);
            if ($opponentId > 0 && $opponentId !== $me) {
                $oppStmt = db()->prepare('SELECT id, username, avatar_path FROM users WHERE id = :id LIMIT 1');
                $oppStmt->execute([':id' => $opponentId]);
                $opponent = $oppStmt->fetch() ?: null;

                if ($opponent) {
                    $h2hStmt = db()->prepare(
                        "SELECT g.*, u1.username AS p1, u2.username AS p2
                         FROM games g
                         JOIN users u1 ON u1.id = g.player1_id
                         JOIN users u2 ON u2.id = g.player2_id
                         WHERE g.status = 'confirmed'
                           AND ((g.player1_id = :me AND g.player2_id = :opp)
                             OR (g.player1_id = :opp2 AND g.player2_id = :me2))
                         ORDER BY g.played_at DESC, g.id DESC"
                    );
                    $h2hStmt->execute([':me' => $me, ':opp' => $opponentId, ':opp2' => $opponentId, ':me2' => $me]);
                    $h2hGames = $h2hStmt->fetchAll();

                    foreach ($h2hGames as $hg) {
                        $h2hStats['total']++;
                        if ($hg['winner_id'] === null) {
                            $h2hStats['draws']++;
                        } elseif ((int)$hg['winner_id'] === $me) {
                            $h2hStats['my_wins']++;
                        } else {
                            $h2hStats['their_wins']++;
                        }
                    }
                }
            } else {
                // List all opponents
                $oppsStmt = db()->prepare(
                    "SELECT u.id, u.username, COUNT(*) AS match_count
                     FROM games g
                     JOIN users u ON u.id = CASE WHEN g.player1_id = :me THEN g.player2_id ELSE g.player1_id END
                     WHERE g.status = 'confirmed' AND (g.player1_id = :me2 OR g.player2_id = :me3)
                     GROUP BY u.id, u.username
                     ORDER BY match_count DESC, u.username ASC"
                );
                $oppsStmt->execute([':me' => $me, ':me2' => $me, ':me3' => $me]);
                $h2hOpponents = $oppsStmt->fetchAll();
            }
        }
    }
} catch (Throwable $e) {
    flash('error', 'Dashboard-Daten konnten nicht geladen werden. Bitte Seite neu laden.');
}

$flashes = consumeFlash();
$cssVersion      = (string)@filemtime(__DIR__ . '/assets/css/style.css');
$jsVersion       = (string)@filemtime(__DIR__ . '/assets/js/app.js');
$manifestVersion = (string)@filemtime(__DIR__ . '/manifest.webmanifest');
$vapidPublicKey  = (string)(getenv('VAPID_PUBLIC_KEY') ?: '');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f4b3f">
    <meta name="csrf-token" content="<?= esc(generateCsrfToken()) ?>">
    <?php if ($vapidPublicKey !== ''): ?>
        <meta name="vapid-public-key" content="<?= esc($vapidPublicKey) ?>">
    <?php endif; ?>
    <title>BillardLiga</title>
    <link rel="manifest" href="manifest.webmanifest?v=<?= esc($manifestVersion) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= esc($cssVersion) ?>">
</head>

<body>
    <div class="background-shape"></div>
    <header class="hero">
        <div>
            <p class="eyebrow">Community Plattform</p>
            <h1>BillardLiga</h1>
            <p>Organisiere Wettk&auml;mpfe, melde Einzelspiele und best&auml;tige Ergebnisse fair durch den Gegenspieler.</p>
            <?php if ($loggedIn): ?>
                <p class="user-badge">Eingeloggt als <?= esc((string)($_SESSION['username'] ?? '')) ?></p>
            <?php endif; ?>
        </div>
        <div class="hero-actions">
            <button id="install-pwa" hidden>Als App installieren</button>
        </div>
    </header>

    <nav class="main-nav">
        <div class="main-nav-inner">
            <?php if (!$loggedIn): ?>
                <a href="index.php?page=auth" class="<?= $page === 'auth' ? 'active' : '' ?>">Anmeldung</a>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
                <a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="index.php?page=matches" class="<?= $page === 'matches' ? 'active' : '' ?>">
                    Einzelspiele<?php if (count($pendingConfirmations) > 0): ?><span class="nav-badge"><?= count($pendingConfirmations) ?></span><?php endif; ?>
                </a>
                <a href="index.php?page=competitions" class="<?= $page === 'competitions' ? 'active' : '' ?>">
                    Wettkampf<?php if (count($myPendingInvitations) > 0): ?><span class="nav-badge"><?= count($myPendingInvitations) ?></span><?php endif; ?>
                </a>
                <a href="index.php?page=h2h" class="<?= $page === 'h2h' ? 'active' : '' ?>">H2H</a>
                <a href="index.php?page=friends" class="<?= $page === 'friends' ? 'active' : '' ?>">
                    Freunde<?php if (count($incomingFriendRequests) > 0): ?><span class="nav-badge"><?= count($incomingFriendRequests) ?></span><?php endif; ?>
                </a>
                <a href="index.php?page=profile" class="<?= $page === 'profile' ? 'active' : '' ?>">Profil</a>
            <?php endif; ?>
            <a href="index.php?page=leaderboard" class="<?= $page === 'leaderboard' ? 'active' : '' ?>">Rangliste</a>
            <?php if ($loggedIn && $vapidPublicKey !== ''): ?>
                <button id="enable-push" class="secondary nav-push" hidden>&#128276; Benachrichtigungen</button>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
                <form method="post" class="nav-logout-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="secondary nav-logout">Abmelden</button>
                </form>
            <?php endif; ?>
        </div>
    </nav>

    <main class="grid">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash <?= esc($flash['type']) ?>"><?= esc($flash['message']) ?></div>
        <?php endforeach; ?>

        <?php require __DIR__ . '/views/' . $page . '.php'; ?>
    </main>

    <script src="assets/js/app.js?v=<?= esc($jsVersion) ?>"></script>
</body>

</html>