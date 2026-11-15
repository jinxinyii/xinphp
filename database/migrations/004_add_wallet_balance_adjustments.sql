USE xinphp_db;

CREATE TABLE IF NOT EXISTS wallet_balance_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    wallet_id INT UNSIGNED NOT NULL,
    amount_change DECIMAL(14,2) NOT NULL,
    adjustment_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wallet_adjustment_user_date (user_id, adjustment_date),
    KEY idx_wallet_adjustment_wallet_date (wallet_id, user_id, adjustment_date),
    CONSTRAINT chk_wallet_adjustment_nonzero CHECK (amount_change <> 0),
    CONSTRAINT fk_wallet_adjustment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_wallet_adjustment_wallet_owner FOREIGN KEY (wallet_id, user_id) REFERENCES wallets(id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
