SET @avatar_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'avatar_path'
);

SET @avatar_sql := IF(
    @avatar_exists = 0,
    'ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER password_hash',
    'SELECT 1'
);
PREPARE stmt_avatar FROM @avatar_sql;
EXECUTE stmt_avatar;
DEALLOCATE PREPARE stmt_avatar;

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
