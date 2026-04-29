CREATE DATABASE IF NOT EXISTS billard_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE billard_app;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS friendships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    addressee_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_friendships_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_friendships_addressee FOREIGN KEY (addressee_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_friendship_different_users CHECK (requester_id <> addressee_id),
    UNIQUE KEY uq_friend_request (requester_id, addressee_id)
) ENGINE=InnoDB;

CREATE INDEX idx_friendships_addressee_status ON friendships (addressee_id, status);
CREATE INDEX idx_friendships_requester_status ON friendships (requester_id, status);

CREATE TABLE IF NOT EXISTS competitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('planned', 'active', 'finished') NOT NULL DEFAULT 'planned',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_competitions_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS competition_players (
    competition_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (competition_id, user_id),
    CONSTRAINT fk_competition_players_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_competition_players_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id INT UNSIGNED DEFAULT NULL,
    player1_id INT UNSIGNED NOT NULL,
    player2_id INT UNSIGNED NOT NULL,
    score_player1 INT UNSIGNED NOT NULL,
    score_player2 INT UNSIGNED NOT NULL,
    winner_id INT UNSIGNED DEFAULT NULL,
    reported_by INT UNSIGNED NOT NULL,
    confirmed_by INT UNSIGNED DEFAULT NULL,
    status ENUM('pending_confirmation', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending_confirmation',
    played_at DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_games_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL,
    CONSTRAINT fk_games_player1 FOREIGN KEY (player1_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_games_player2 FOREIGN KEY (player2_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_games_winner FOREIGN KEY (winner_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_games_reported_by FOREIGN KEY (reported_by) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_games_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_different_players CHECK (player1_id <> player2_id)
) ENGINE=InnoDB;

CREATE INDEX idx_games_status ON games (status);
CREATE INDEX idx_games_players ON games (player1_id, player2_id);
CREATE INDEX idx_games_competition ON games (competition_id);
