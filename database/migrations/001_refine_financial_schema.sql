USE xinphp_db;

ALTER TABLE wallets
    CHANGE COLUMN provider provider_name VARCHAR(100) NOT NULL,
    CHANGE COLUMN name wallet_name VARCHAR(100) NOT NULL,
    ADD COLUMN currency_code CHAR(3) NOT NULL DEFAULT 'PHP' AFTER opening_balance,
    ADD CONSTRAINT chk_wallet_currency CHECK (CHAR_LENGTH(currency_code) = 3),
    ADD CONSTRAINT chk_wallet_type CHECK (wallet_type IN ('cash', 'bank', 'e_wallet', 'other'));

ALTER TABLE wallets DROP FOREIGN KEY fk_wallet_user;
ALTER TABLE wallets ADD CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE categories
    MODIFY COLUMN type VARCHAR(10) NOT NULL,
    ADD UNIQUE KEY uq_category_id_user_type (id, user_id, type),
    ADD CONSTRAINT chk_category_type CHECK (type IN ('income', 'expense'));

ALTER TABLE categories DROP FOREIGN KEY fk_category_user;
ALTER TABLE categories ADD CONSTRAINT fk_category_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE transactions
    DROP FOREIGN KEY fk_transaction_user,
    DROP FOREIGN KEY fk_transaction_wallet_owner,
    DROP FOREIGN KEY fk_transaction_category_owner;

ALTER TABLE transactions
    MODIFY COLUMN category_id INT UNSIGNED NOT NULL,
    ADD COLUMN category_type VARCHAR(10) NOT NULL AFTER category_id,
    MODIFY COLUMN type VARCHAR(10) NOT NULL,
    ADD UNIQUE KEY uq_transaction_id_user (id, user_id),
    ADD CONSTRAINT chk_transaction_type CHECK (type IN ('income', 'expense')),
    ADD CONSTRAINT chk_transaction_amount CHECK (amount > 0);

ALTER TABLE transactions
    ADD CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_transaction_wallet_owner FOREIGN KEY (wallet_id, user_id)
        REFERENCES wallets(id, user_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_transaction_category_type_owner FOREIGN KEY (category_id, user_id, category_type)
        REFERENCES categories(id, user_id, type) ON DELETE RESTRICT;

ALTER TABLE budgets
    DROP FOREIGN KEY fk_budget_user,
    DROP FOREIGN KEY fk_budget_category_owner;

ALTER TABLE budgets
    ADD COLUMN category_type VARCHAR(10) NOT NULL DEFAULT 'expense' AFTER category_id,
    ADD CONSTRAINT chk_budget_amount CHECK (amount > 0),
    ADD CONSTRAINT chk_budget_month CHECK (DAYOFMONTH(budget_month) = 1),
    ADD CONSTRAINT chk_budget_expense_category CHECK (category_type = 'expense');

ALTER TABLE budgets
    ADD CONSTRAINT fk_budget_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_budget_category_type_owner FOREIGN KEY (category_id, user_id, category_type)
        REFERENCES categories(id, user_id, type) ON DELETE RESTRICT;

ALTER TABLE savings_goals
    CHANGE COLUMN name goal_name VARCHAR(100) NOT NULL,
    CHANGE COLUMN is_completed status VARCHAR(12) NOT NULL DEFAULT 'active',
    ADD CONSTRAINT chk_goal_target_amount CHECK (target_amount > 0),
    ADD CONSTRAINT chk_goal_status CHECK (status IN ('active', 'completed', 'cancelled'));

ALTER TABLE savings_goals DROP FOREIGN KEY fk_goal_user;
ALTER TABLE savings_goals ADD CONSTRAINT fk_goal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

RENAME TABLE goal_contributions TO savings_contributions;

ALTER TABLE savings_contributions
    DROP FOREIGN KEY fk_goal_contribution_user,
    DROP FOREIGN KEY fk_goal_contribution_goal_owner;

ALTER TABLE savings_contributions
    CHANGE COLUMN goal_id savings_goal_id INT UNSIGNED NOT NULL,
    CHANGE COLUMN contributed_on contribution_date DATE NOT NULL,
    ADD COLUMN wallet_id INT UNSIGNED NULL AFTER savings_goal_id,
    ADD CONSTRAINT chk_savings_contribution_amount CHECK (amount > 0);

ALTER TABLE savings_contributions
    ADD CONSTRAINT fk_savings_contribution_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_savings_contribution_goal_owner FOREIGN KEY (savings_goal_id, user_id)
        REFERENCES savings_goals(id, user_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_savings_contribution_wallet_owner FOREIGN KEY (wallet_id, user_id)
        REFERENCES wallets(id, user_id) ON DELETE RESTRICT;

ALTER TABLE recurring_transactions
    DROP FOREIGN KEY fk_recurring_user,
    DROP FOREIGN KEY fk_recurring_wallet_owner,
    DROP FOREIGN KEY fk_recurring_category_owner;

ALTER TABLE recurring_transactions
    ADD COLUMN category_type VARCHAR(10) NOT NULL AFTER category_id,
    MODIFY COLUMN type VARCHAR(10) NOT NULL,
    ADD UNIQUE KEY uq_recurring_id_user (id, user_id),
    ADD CONSTRAINT chk_recurring_type CHECK (type IN ('income', 'expense')),
    ADD CONSTRAINT chk_recurring_category_type CHECK (category_type = type),
    ADD CONSTRAINT chk_recurring_amount CHECK (amount > 0),
    ADD CONSTRAINT chk_recurring_frequency CHECK (frequency IN ('weekly', 'monthly', 'yearly'));

ALTER TABLE recurring_transactions
    ADD CONSTRAINT fk_recurring_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_recurring_wallet_owner FOREIGN KEY (wallet_id, user_id)
        REFERENCES wallets(id, user_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_recurring_category_type_owner FOREIGN KEY (category_id, user_id, category_type)
        REFERENCES categories(id, user_id, type) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    source_wallet_id INT UNSIGNED NOT NULL,
    destination_wallet_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    transfer_date DATE NOT NULL,
    description VARCHAR(150) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_transfer_user_date (user_id, transfer_date),
    KEY idx_transfer_source_wallet_date (user_id, source_wallet_id, transfer_date),
    KEY idx_transfer_destination_wallet_date (user_id, destination_wallet_id, transfer_date),
    CONSTRAINT chk_transfer_amount CHECK (amount > 0),
    CONSTRAINT chk_transfer_wallets_differ CHECK (source_wallet_id <> destination_wallet_id),
    CONSTRAINT fk_transfer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_source_wallet_owner FOREIGN KEY (source_wallet_id, user_id)
        REFERENCES wallets(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_destination_wallet_owner FOREIGN KEY (destination_wallet_id, user_id)
        REFERENCES wallets(id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurring_transaction_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    recurring_transaction_id INT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL,
    occurrence_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recurring_run_occurrence (recurring_transaction_id, occurrence_date),
    UNIQUE KEY uq_recurring_run_transaction_user (transaction_id, user_id),
    KEY idx_recurring_run_user_date (user_id, occurrence_date),
    CONSTRAINT fk_recurring_run_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_recurring_run_template_owner FOREIGN KEY (recurring_transaction_id, user_id)
        REFERENCES recurring_transactions(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_recurring_run_transaction_owner FOREIGN KEY (transaction_id, user_id)
        REFERENCES transactions(id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
