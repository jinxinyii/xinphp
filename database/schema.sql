CREATE DATABASE IF NOT EXISTS xinphp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE xinphp_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    birthdate DATE NOT NULL,
    full_name VARCHAR(100) NULL COMMENT 'Legacy compatibility column; new registrations do not use it.',
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider_name VARCHAR(100) NOT NULL,
    wallet_type VARCHAR(50) NOT NULL,
    wallet_name VARCHAR(100) NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NOT NULL DEFAULT 'PHP',
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wallet_user_name (user_id, wallet_name),
    UNIQUE KEY uq_wallet_id_user (id, user_id),
    KEY idx_wallet_user_archived (user_id, is_archived),
    CONSTRAINT chk_wallet_currency CHECK (CHAR_LENGTH(currency_code) = 3),
    CONSTRAINT chk_wallet_type CHECK (wallet_type IN ('cash', 'bank', 'e_wallet', 'other')),
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(10) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#0f5c46',
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_user_type_name (user_id, type, name),
    UNIQUE KEY uq_category_id_user (id, user_id),
    UNIQUE KEY uq_category_id_user_type (id, user_id, type),
    KEY idx_category_user_type_archived (user_id, type, is_archived),
    CONSTRAINT chk_category_type CHECK (type IN ('income', 'expense')),
    CONSTRAINT fk_category_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    wallet_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    category_type VARCHAR(10) NOT NULL,
    type VARCHAR(10) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    transaction_date DATE NOT NULL,
    description VARCHAR(150) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transaction_id_user (id, user_id),
    KEY idx_transaction_user_date (user_id, transaction_date),
    KEY idx_transaction_user_type_date (user_id, type, transaction_date),
    KEY idx_transaction_user_wallet_date (user_id, wallet_id, transaction_date),
    KEY idx_transaction_user_category_date (user_id, category_id, transaction_date),
    CONSTRAINT chk_transaction_type CHECK (type IN ('income', 'expense')),
    CONSTRAINT chk_transaction_amount CHECK (amount > 0),
    CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transaction_wallet_owner FOREIGN KEY (wallet_id, user_id) REFERENCES wallets(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_transaction_category_type_owner FOREIGN KEY (category_id, user_id, category_type) REFERENCES categories(id, user_id, type) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    CONSTRAINT fk_transfer_source_wallet_owner FOREIGN KEY (source_wallet_id, user_id) REFERENCES wallets(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_destination_wallet_owner FOREIGN KEY (destination_wallet_id, user_id) REFERENCES wallets(id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    category_type VARCHAR(10) NOT NULL DEFAULT 'expense',
    budget_month DATE NOT NULL COMMENT 'Always use the first day of the month.',
    amount DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_budget_user_category_month (user_id, category_id, budget_month),
    KEY idx_budget_user_month (user_id, budget_month),
    CONSTRAINT chk_budget_amount CHECK (amount > 0),
    CONSTRAINT chk_budget_month CHECK (DAYOFMONTH(budget_month) = 1),
    CONSTRAINT chk_budget_expense_category CHECK (category_type = 'expense'),
    CONSTRAINT fk_budget_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_budget_category_type_owner FOREIGN KEY (category_id, user_id, category_type) REFERENCES categories(id, user_id, type) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS savings_goals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    goal_name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(14,2) NOT NULL,
    target_date DATE NULL,
    notes TEXT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_goal_id_user (id, user_id),
    KEY idx_goal_user_status (user_id, status),
    CONSTRAINT chk_goal_target_amount CHECK (target_amount > 0),
    CONSTRAINT chk_goal_status CHECK (status IN ('active', 'completed', 'cancelled')),
    CONSTRAINT fk_goal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS savings_contributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    savings_goal_id INT UNSIGNED NOT NULL,
    wallet_id INT UNSIGNED NULL,
    amount DECIMAL(14,2) NOT NULL,
    contribution_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_savings_contribution_user_goal_date (user_id, savings_goal_id, contribution_date),
    CONSTRAINT chk_savings_contribution_amount CHECK (amount > 0),
    CONSTRAINT fk_savings_contribution_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_savings_contribution_goal_owner FOREIGN KEY (savings_goal_id, user_id) REFERENCES savings_goals(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_savings_contribution_wallet_owner FOREIGN KEY (wallet_id, user_id) REFERENCES wallets(id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurring_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    wallet_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    category_type VARCHAR(10) NOT NULL,
    type VARCHAR(10) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    description VARCHAR(150) NOT NULL,
    notes TEXT NULL,
    frequency VARCHAR(10) NOT NULL,
    start_date DATE NOT NULL,
    next_run_date DATE NOT NULL,
    end_date DATE NULL,
    last_processed_on DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recurring_id_user (id, user_id),
    KEY idx_recurring_due (is_active, next_run_date),
    KEY idx_recurring_user_active (user_id, is_active),
    CONSTRAINT chk_recurring_type CHECK (type IN ('income', 'expense')),
    CONSTRAINT chk_recurring_category_type CHECK (category_type = type),
    CONSTRAINT chk_recurring_amount CHECK (amount > 0),
    CONSTRAINT chk_recurring_frequency CHECK (frequency IN ('weekly', 'monthly', 'yearly')),
    CONSTRAINT fk_recurring_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_recurring_wallet_owner FOREIGN KEY (wallet_id, user_id) REFERENCES wallets(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_recurring_category_type_owner FOREIGN KEY (category_id, user_id, category_type) REFERENCES categories(id, user_id, type) ON DELETE RESTRICT
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
    CONSTRAINT fk_recurring_run_template_owner FOREIGN KEY (recurring_transaction_id, user_id) REFERENCES recurring_transactions(id, user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_recurring_run_transaction_owner FOREIGN KEY (transaction_id, user_id) REFERENCES transactions(id, user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
