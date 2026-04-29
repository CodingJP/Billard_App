<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

function avatarUrl(?string $path): string
{
    if (!$path) {
        return 'assets/icons/icon-192.svg';
    }

    return $path;
}

$action = $_POST['action'] ?? null;

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

    $name = trim($_POST['name'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $status = trim($_POST['status'] ?? 'planned');

    if ($name === '' || $startDate === '') {
        flash('error', 'Bitte Name und Startdatum angeben.');
        redirect('index.php?page=competitions');
    }

    $stmt = db()->prepare('INSERT INTO competitions (name, start_date, status, created_by) VALUES (:name, :start_date, :status, :created_by)');
    $stmt->execute([
        'name' => $name,
        'start_date' => $startDate,
        'status' => in_array($status, ['planned', 'active', 'finished'], true) ? $status : 'planned',
        'created_by' => currentUserId(),
    ]);

    flash('success', 'Wettkampf wurde erstellt.');
    redirect('index.php?page=competitions');
}

if ($action === 'join_competition') {
    requireLogin();

    $competitionId = (int)($_POST['competition_id'] ?? 0);
    if ($competitionId <= 0) {
        flash('error', 'Ungültiger Wettkampf.');
        redirect('index.php?page=competitions');
    }

    $stmt = db()->prepare('INSERT IGNORE INTO competition_players (competition_id, user_id) VALUES (:competition_id, :user_id)');
    $stmt->execute([
        'competition_id' => $competitionId,
        'user_id' => currentUserId(),
    ]);

    flash('success', 'Du bist dem Wettkampf beigetreten.');
    redirect('index.php?page=competitions');
}

if ($action === 'report_game') {
    requireLogin();

    $player1 = (int)($_POST['player1_id'] ?? 0);
    $player2 = (int)($_POST['player2_id'] ?? 0);
    $score1 = (int)($_POST['score_player1'] ?? 0);
    $score2 = (int)($_POST['score_player2'] ?? 0);
    $competitionId = (int)($_POST['competition_id'] ?? 0);
    $playedAt = trim($_POST['played_at'] ?? date('Y-m-d'));

    if ($player1 <= 0 || $player2 <= 0 || $player1 === $player2) {
        flash('error', 'Bitte zwei unterschiedliche Spieler waehlen.');
        redirect('index.php?page=matches');
    }

    if ($score1 === $score2) {
        flash('error', 'Unentschieden ist aktuell nicht erlaubt.');
        redirect('index.php?page=matches');
    }

    if (currentUserId() !== $player1 && currentUserId() !== $player2) {
        flash('error', 'Du kannst nur Spiele melden, an denen du beteiligt bist.');
        redirect('index.php?page=matches');
    }

    $winnerId = $score1 > $score2 ? $player1 : $player2;

    $stmt = db()->prepare('INSERT INTO games (
        competition_id, player1_id, player2_id, score_player1, score_player2, winner_id, reported_by, played_at
    ) VALUES (
        :competition_id, :player1_id, :player2_id, :score_player1, :score_player2, :winner_id, :reported_by, :played_at
    )');

    $stmt->execute([
        'competition_id' => $competitionId > 0 ? $competitionId : null,
        'player1_id' => $player1,
        'player2_id' => $player2,
        'score_player1' => $score1,
        'score_player2' => $score2,
        'winner_id' => $winnerId,
        'reported_by' => currentUserId(),
        'played_at' => $playedAt,
    ]);

    flash('success', 'Spiel wurde gemeldet und wartet auf Bestaetigung.');
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

    $update = db()->prepare('UPDATE games SET status = :status, confirmed_by = :confirmed_by WHERE id = :id');
    $update->execute([
        'status' => 'confirmed',
        'confirmed_by' => $me,
        'id' => $gameId,
    ]);

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

    $update = db()->prepare('UPDATE games SET status = :status, confirmed_by = :confirmed_by WHERE id = :id');
    $update->execute([
        'status' => 'rejected',
        'confirmed_by' => $me,
        'id' => $gameId,
    ]);

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
        mkdir($uploadDirFs, 0777, true);
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

    $insert = db()->prepare('INSERT INTO friendships (requester_id, addressee_id, status) VALUES (:requester, :addressee, :status)');
    $insert->execute([
        'requester' => $me,
        'addressee' => $targetId,
        'status' => 'pending',
    ]);

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

$allowedPages = ['auth', 'dashboard', 'matches', 'competitions', 'leaderboard', 'friends', 'profile'];
$page = $_GET['page'] ?? ($loggedIn ? 'dashboard' : 'auth');
if (!in_array($page, $allowedPages, true)) {
    $page = $loggedIn ? 'dashboard' : 'auth';
}

$protectedPages = ['dashboard', 'matches', 'competitions', 'friends', 'profile'];
if (!$loggedIn && in_array($page, $protectedPages, true)) {
    flash('error', 'Bitte zuerst anmelden.');
    redirect('index.php?page=auth');
}

$users = [];
$competitions = [];
$competitionStatusOverview = ['planned' => 0, 'active' => 0, 'finished' => 0, 'total' => 0];
$selectedCompetitionId = isset($_GET['competition_id']) ? max(0, (int)$_GET['competition_id']) : 0;
$selectedCompetition = null;
$selectedCompetitionParticipants = [];
$selectedCompetitionLeaderboard = [];
$selectedCompetitionGames = [];
$roundRobinPairings = [];
$semiFinalPairings = [];
$finalSuggestion = null;
$myCompetitionIds = [];
$leaderboard = [];
$pendingConfirmations = [];
$recentGames = [];
$currentProfile = null;
$friendSuggestions = [];
$incomingFriendRequests = [];
$outgoingFriendRequests = [];
$friends = [];
$friendIdsLookup = [];
$friendsLeaderboard = [];
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
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id = u.id THEN 3 ELSE 0 END) AS points,
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id = u.id THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN g.status = 'confirmed' AND g.winner_id IS NOT NULL AND g.winner_id <> u.id AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS losses,
            SUM(CASE WHEN g.status = 'confirmed' AND (g.player1_id = u.id OR g.player2_id = u.id) THEN 1 ELSE 0 END) AS games_played
         FROM users u
         LEFT JOIN games g ON (g.player1_id = u.id OR g.player2_id = u.id)
         GROUP BY u.id, u.username
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
    }

    $recentGames = db()->query(
        "SELECT g.*, u1.username AS p1, u2.username AS p2, uw.username AS winner_name, c.name AS competition_name
         FROM games g
         JOIN users u1 ON u1.id = g.player1_id
         JOIN users u2 ON u2.id = g.player2_id
         LEFT JOIN users uw ON uw.id = g.winner_id
         LEFT JOIN competitions c ON c.id = g.competition_id
         ORDER BY g.created_at DESC
         LIMIT 15"
    )->fetchAll();

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
    }
} catch (Throwable $e) {
    flash('error', 'Dashboard-Daten konnten nicht geladen werden. Bitte Seite neu laden.');
}

$flashes = consumeFlash();
$cssVersion = (string)@filemtime(__DIR__ . '/assets/css/style.css');
$jsVersion = (string)@filemtime(__DIR__ . '/assets/js/app.js');
$manifestVersion = (string)@filemtime(__DIR__ . '/manifest.webmanifest');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f4b3f">
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
            <p>Organisiere Wettkaempfe, melde Einzelspiele und bestaetige Ergebnisse fair durch den Gegenspieler.</p>
            <?php if ($loggedIn): ?>
                <p class="user-badge">Eingeloggt als <?= esc((string)($_SESSION['username'] ?? '')) ?></p>
            <?php endif; ?>
        </div>
        <div class="hero-actions">
            <button id="install-pwa" hidden>Als App installieren</button>
            <?php if ($loggedIn): ?>
                <form method="post">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="secondary">Abmelden</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <nav class="main-nav">
        <div class="main-nav-inner">
            <?php if (!$loggedIn): ?>
                <a href="index.php?page=auth" class="<?= $page === 'auth' ? 'active' : '' ?>">Anmeldung</a>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
                <a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="index.php?page=matches" class="<?= $page === 'matches' ? 'active' : '' ?>">Einzelspiele</a>
                <a href="index.php?page=competitions" class="<?= $page === 'competitions' ? 'active' : '' ?>">Wettkaempfe</a>
                <a href="index.php?page=friends" class="<?= $page === 'friends' ? 'active' : '' ?>">Freunde</a>
                <a href="index.php?page=profile" class="<?= $page === 'profile' ? 'active' : '' ?>">Profil</a>
            <?php endif; ?>
            <a href="index.php?page=leaderboard" class="<?= $page === 'leaderboard' ? 'active' : '' ?>">Rangliste</a>
        </div>
    </nav>

    <main class="grid">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash <?= esc($flash['type']) ?>"><?= esc($flash['message']) ?></div>
        <?php endforeach; ?>

        <?php if ($page === 'auth'): ?>
            <?php if ($loggedIn): ?>
                <section class="card wide">
                    <h2>Du bist bereits angemeldet</h2>
                    <p>Nutze das Menue oben, um zu Spielen, Wettkaempfen oder deiner Rangliste zu wechseln.</p>
                </section>
            <?php else: ?>
            <section class="card">
                <h2>Anmelden</h2>
                <form method="post" class="form-stack">
                    <input type="hidden" name="action" value="login">
                    <label>Benutzername oder E-Mail
                        <input type="text" name="username_or_email" required>
                    </label>
                    <label>Passwort
                        <input type="password" name="password" required>
                    </label>
                    <button type="submit">Einloggen</button>
                </form>
            </section>

            <section class="card">
                <h2>Registrierung mit Secret-Code</h2>
                <form method="post" class="form-stack">
                    <input type="hidden" name="action" value="register">
                    <label>Benutzername
                        <input type="text" name="username" minlength="3" required>
                    </label>
                    <label>E-Mail
                        <input type="email" name="email" required>
                    </label>
                    <label>Passwort
                        <input type="password" name="password" minlength="8" required>
                    </label>
                    <label>Secret-Code
                        <input type="text" name="secret_code" required>
                    </label>
                    <button type="submit">Account erstellen</button>
                </form>
            </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($page === 'dashboard' && $loggedIn): ?>
            <section class="card stat-card">
                <p class="stat-label">Mitglieder</p>
                <p class="metric"><?= (int)$dashboardStats['users'] ?></p>
                <p class="stat-hint">Aktive Spieler in deiner Liga</p>
            </section>
            <section class="card stat-card">
                <p class="stat-label">Wettkaempfe</p>
                <p class="metric"><?= (int)$dashboardStats['competitions'] ?></p>
                <p class="stat-hint">Laufende und geplante Formate</p>
            </section>
            <section class="card stat-card">
                <p class="stat-label">Bestaetigte Spiele</p>
                <p class="metric"><?= (int)$dashboardStats['confirmed_games'] ?></p>
                <p class="stat-hint">Wertungen, die in die Rangliste eingehen</p>
            </section>
            <section class="card stat-card">
                <p class="stat-label">Offene Bestaetigungen</p>
                <p class="metric"><?= (int)$dashboardStats['pending'] ?></p>
                <p class="stat-hint">Warten auf Gegenspieler-Freigabe</p>
            </section>
            <section class="card wide">
                <h2>Schnellzugriff</h2>
                <div class="quick-actions">
                    <a href="index.php?page=matches" class="quick-link">Neues Einzelspiel eintragen</a>
                    <a href="index.php?page=competitions" class="quick-link">Wettkampf erstellen oder beitreten</a>
                    <a href="index.php?page=leaderboard" class="quick-link">Rangliste ansehen</a>
                </div>
            </section>
            <section class="card wide">
                <h2>Aktivitaet</h2>
                <?php if (count($recentGames) === 0): ?>
                    <p>Noch keine Spiele vorhanden. Trage dein erstes Match ueber Einzelspiele ein.</p>
                <?php else: ?>
                    <ul class="item-list">
                        <?php foreach (array_slice($recentGames, 0, 5) as $game): ?>
                            <li>
                                <div>
                                    <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                                    <small>Status: <?= esc($game['status']) ?><?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($page === 'matches' && $loggedIn): ?>
            <section class="card wide">
                <h2>Einzelspiel melden</h2>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="report_game">
                    <label>Spieler 1
                        <select name="player1_id" required>
                            <option value="">Waehlen...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>"><?= esc($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Spieler 2
                        <select name="player2_id" required>
                            <option value="">Waehlen...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>"><?= esc($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Punkte Spieler 1
                        <input type="number" name="score_player1" min="0" required>
                    </label>
                    <label>Punkte Spieler 2
                        <input type="number" name="score_player2" min="0" required>
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
                        <input type="date" name="played_at" value="<?= esc(date('Y-m-d')) ?>" required>
                    </label>
                    <button type="submit">Ergebnis einreichen</button>
                </form>
            </section>

            <section class="card">
                <h2>Offene Bestaetigungen</h2>
                <?php if (count($pendingConfirmations) === 0): ?>
                    <p>Aktuell keine ausstehenden Ergebnis-Bestaetigungen.</p>
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
                                        <input type="hidden" name="action" value="confirm_game">
                                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                                        <button type="submit">Bestaetigen</button>
                                    </form>
                                    <form method="post">
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
        <?php endif; ?>

        <?php if ($page === 'leaderboard'): ?>
        <section class="card wide">
            <h2>Rangliste</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Spieler</th>
                        <th>Beziehung</th>
                        <th>Punkte</th>
                        <th>Siege</th>
                        <th>Niederlagen</th>
                        <th>Spiele</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= esc($row['username']) ?></td>
                            <td>
                                <?php if ($loggedIn && (int)$row['id'] === (int)currentUserId()): ?>
                                    <span class="pill self">Du</span>
                                <?php elseif ($loggedIn && isset($friendIdsLookup[(int)$row['id']])): ?>
                                    <span class="pill friend">Freund</span>
                                <?php else: ?>
                                    <span class="pill neutral">Liga</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$row['points'] ?></td>
                            <td><?= (int)$row['wins'] ?></td>
                            <td><?= (int)$row['losses'] ?></td>
                            <td><?= (int)$row['games_played'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ($loggedIn): ?>
        <section class="card wide">
            <h2>Freunde-Rangliste</h2>
            <?php if (count($friendsLeaderboard) === 0): ?>
                <p>Keine Freunde vorhanden. Fuege Freunde hinzu, um die separate Rangliste zu sehen.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Spieler</th>
                            <th>Punkte</th>
                            <th>Siege</th>
                            <th>Niederlagen</th>
                            <th>Spiele</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($friendsLeaderboard as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?= esc($row['username']) ?>
                                    <?php if ((int)$row['id'] === (int)currentUserId()): ?>
                                        <span class="pill self">Du</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$row['points'] ?></td>
                                <td><?= (int)$row['wins'] ?></td>
                                <td><?= (int)$row['losses'] ?></td>
                                <td><?= (int)$row['games_played'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($page === 'competitions' && $loggedIn): ?>
        <section class="card wide">
            <h2>Wettkampf-Uebersicht</h2>
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
                <input type="hidden" name="action" value="create_competition">
                <label>Name
                    <input type="text" name="name" required>
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
                <button type="submit">Wettkampf speichern</button>
            </form>
        </section>

        <section class="card">
            <h2>Wettkaempfe</h2>
            <?php if (count($competitions) === 0): ?>
                <p>Noch keine Wettkaempfe vorhanden.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($competitions as $competition): ?>
                        <li>
                            <div>
                                <strong><?= esc($competition['name']) ?></strong>
                                <small>
                                    <?= esc($competition['status']) ?> | Start: <?= esc($competition['start_date']) ?> | Von: <?= esc($competition['creator']) ?>
                                    | Teilnehmer: <?= (int)$competition['participants_count'] ?>
                                    | Bestaetigte Spiele: <?= (int)$competition['confirmed_games'] ?>
                                </small>
                            </div>
                            <div class="inline-actions">
                                <a class="button-link secondary" href="index.php?page=competitions&competition_id=<?= (int)$competition['id'] ?>">Auswertung</a>
                                <?php if (!in_array((int)$competition['id'], $myCompetitionIds, true)): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="join_competition">
                                    <input type="hidden" name="competition_id" value="<?= (int)$competition['id'] ?>">
                                    <button type="submit" class="secondary">Beitreten</button>
                                </form>
                                <?php else: ?>
                                    <span class="pill friend">Beigetreten</span>
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
                <p>Waehle bei einem Wettkampf den Button Auswertung, um Statistik, Rangliste und Turnier-Matchings zu sehen.</p>
            <?php else: ?>
                <h2>Auswertung: <?= esc($selectedCompetition['name']) ?></h2>
                <p class="stat-hint">Status: <?= esc($selectedCompetition['status']) ?> | Start: <?= esc($selectedCompetition['start_date']) ?></p>

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
                                <th>Siege</th>
                                <th>Niederlagen</th>
                                <th>Spiele</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selectedCompetitionLeaderboard as $idx => $row): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><?= esc($row['username']) ?></td>
                                    <td><?= (int)$row['points'] ?></td>
                                    <td><?= (int)$row['wins'] ?></td>
                                    <td><?= (int)$row['losses'] ?></td>
                                    <td><?= (int)$row['games_played'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h3>Teilnehmer</h3>
                <?php if (count($selectedCompetitionParticipants) === 0): ?>
                    <p>Noch keine Teilnehmer eingetragen.</p>
                <?php else: ?>
                    <ul class="item-list avatar-list">
                        <?php foreach ($selectedCompetitionParticipants as $participant): ?>
                            <li>
                                <div class="avatar-row">
                                    <img class="avatar" src="<?= esc(avatarUrl($participant['avatar_path'] ?? null)) ?>" alt="Avatar von <?= esc($participant['username']) ?>">
                                    <strong><?= esc($participant['username']) ?></strong>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3>Letzte Wettkampfspiele</h3>
                <?php if (count($selectedCompetitionGames) === 0): ?>
                    <p>Noch keine Spiele fuer diesen Wettkampf.</p>
                <?php else: ?>
                    <ul class="item-list">
                        <?php foreach ($selectedCompetitionGames as $game): ?>
                            <li>
                                <div>
                                    <strong><?= esc($game['p1']) ?> <?= (int)$game['score_player1'] ?> : <?= (int)$game['score_player2'] ?> <?= esc($game['p2']) ?></strong>
                                    <small>Status: <?= esc($game['status']) ?> | Sieger: <?= esc($game['winner_name'] ?? '-') ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3>Turnier-Matchings (Vorschlag)</h3>
                <?php if (count($roundRobinPairings) === 0): ?>
                    <p>Fuer Matchings werden mindestens 2 Teilnehmer benoetigt.</p>
                <?php else: ?>
                    <ul class="item-list compact-list">
                        <?php foreach (array_slice($roundRobinPairings, 0, 20) as $pairing): ?>
                            <li>
                                <strong><?= esc($pairing['a']) ?> vs <?= esc($pairing['b']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($roundRobinPairings) > 20): ?>
                        <p class="stat-hint">+<?= count($roundRobinPairings) - 20 ?> weitere Paarungen.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <h3>KO-Phase (Halbfinale/Finale Vorschlag)</h3>
                <?php if (count($semiFinalPairings) < 2): ?>
                    <p>Fuer Halbfinale werden mindestens 4 Spieler mit Rangliste benoetigt.</p>
                <?php else: ?>
                    <ul class="item-list compact-list">
                        <?php foreach ($semiFinalPairings as $semi): ?>
                            <li>
                                <strong><?= esc($semi['label']) ?>:</strong>
                                <span><?= esc($semi['a']) ?> vs <?= esc($semi['b']) ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($finalSuggestion): ?>
                            <li>
                                <strong>Finale:</strong>
                                <span><?= esc($finalSuggestion) ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($page === 'matches' && $loggedIn): ?>
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
                                <small>Status: <?= esc($game['status']) ?> | Sieger: <?= esc($game['winner_name'] ?? '-') ?><?= $game['competition_name'] ? ' | ' . esc($game['competition_name']) : '' ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($page === 'friends' && $loggedIn): ?>
        <section class="card">
            <h2>Freunde</h2>
            <?php if (count($friends) === 0): ?>
                <p>Du hast noch keine bestaetigten Freunde.</p>
            <?php else: ?>
                <ul class="item-list avatar-list">
                    <?php foreach ($friends as $friend): ?>
                        <li>
                            <div class="avatar-row">
                                <img class="avatar" src="<?= esc(avatarUrl($friend['avatar_path'] ?? null)) ?>" alt="Avatar von <?= esc($friend['username']) ?>">
                                <div>
                                    <strong><?= esc($friend['username']) ?></strong>
                                    <small>Befreundet</small>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="remove_friend">
                                <input type="hidden" name="friend_id" value="<?= (int)$friend['id'] ?>">
                                <button type="submit" class="secondary">Entfernen</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Freundesanfragen erhalten</h2>
            <?php if (count($incomingFriendRequests) === 0): ?>
                <p>Keine offenen Anfragen.</p>
            <?php else: ?>
                <ul class="item-list avatar-list">
                    <?php foreach ($incomingFriendRequests as $request): ?>
                        <li>
                            <div class="avatar-row">
                                <img class="avatar" src="<?= esc(avatarUrl($request['avatar_path'] ?? null)) ?>" alt="Avatar von <?= esc($request['username']) ?>">
                                <div>
                                    <strong><?= esc($request['username']) ?></strong>
                                    <small>moechte dein Freund werden</small>
                                </div>
                            </div>
                            <div class="inline-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="accept_friend_request">
                                    <input type="hidden" name="friendship_id" value="<?= (int)$request['id'] ?>">
                                    <button type="submit">Annehmen</button>
                                </form>
                                <form method="post">
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
                <p>Aktuell keine neuen Vorschlaege verfuegbar.</p>
            <?php else: ?>
                <ul class="item-list avatar-list">
                    <?php foreach ($friendSuggestions as $suggestion): ?>
                        <li>
                            <div class="avatar-row">
                                <img class="avatar" src="<?= esc(avatarUrl($suggestion['avatar_path'] ?? null)) ?>" alt="Avatar von <?= esc($suggestion['username']) ?>">
                                <div>
                                    <strong><?= esc($suggestion['username']) ?></strong>
                                    <small>Spielerprofil</small>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="send_friend_request">
                                <input type="hidden" name="target_user_id" value="<?= (int)$suggestion['id'] ?>">
                                <button type="submit">Anfrage senden</button>
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
                                <img class="avatar" src="<?= esc(avatarUrl($request['avatar_path'] ?? null)) ?>" alt="Avatar von <?= esc($request['username']) ?>">
                                <div>
                                    <strong><?= esc($request['username']) ?></strong>
                                    <small>Warte auf Antwort</small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($page === 'profile' && $loggedIn): ?>
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
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Profilbild hochladen</h2>
            <form method="post" enctype="multipart/form-data" class="form-stack">
                <input type="hidden" name="action" value="upload_avatar">
                <label>Bilddatei (JPG, PNG, WEBP, GIF, max 2 MB)
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" required>
                </label>
                <button type="submit">Profilbild speichern</button>
            </form>
        </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js?v=<?= esc($jsVersion) ?>"></script>
</body>
</html>
