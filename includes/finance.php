<?php
declare(strict_types=1);

/** Financial helpers shared by future wallet, transaction, and dashboard pages. */

const WALLET_PROVIDERS = [
    'GCash', 'Maya', 'GoTyme', 'GrabPay', 'ShopeePay',
    'BDO', 'BPI', 'Metrobank', 'UnionBank', 'Landbank',
    'Security Bank', 'RCBC', 'PNB', 'China Bank',
    'Cash', 'Savings Account', 'Other Bank', 'Custom Wallet',
];

const WALLET_TYPES = [
    'cash' => 'Cash',
    'bank' => 'Bank account',
    'e_wallet' => 'E-wallet',
    'other' => 'Other',
];

function format_peso(string|float|int $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/**
 * Returns a wallet only when it belongs to the authenticated user.
 * Never query a private record by ID alone.
 *
 * @return array<string, mixed>|null
 */
function find_owned_wallet(PDO $pdo, int $walletId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, provider_name, wallet_type, wallet_name, opening_balance, currency_code, is_archived
         FROM wallets
         WHERE id = :wallet_id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['wallet_id' => $walletId, 'user_id' => $userId]);

    return $statement->fetch() ?: null;
}

/** @return array<string, mixed>|null */
function find_owned_category(PDO $pdo, int $categoryId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, name, type, color, is_archived
         FROM categories
         WHERE id = :category_id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['category_id' => $categoryId, 'user_id' => $userId]);

    return $statement->fetch() ?: null;
}

/** @return array<string, mixed>|null */
function find_owned_transaction(PDO $pdo, int $transactionId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, wallet_id, category_id, type, amount, transaction_date, description, notes
         FROM transactions
         WHERE id = :transaction_id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['transaction_id' => $transactionId, 'user_id' => $userId]);

    return $statement->fetch() ?: null;
}

/**
 * Calculates a wallet balance from the opening balance, income/expenses, and
 * dedicated transfer records. Transfers are excluded from income/expense reports.
 */
function wallet_current_balance(PDO $pdo, int $walletId, int $userId): ?string
{
    $statement = $pdo->prepare(
        "SELECT CAST(w.opening_balance
            + COALESCE((SELECT SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END)
                FROM transactions t WHERE t.wallet_id = w.id AND t.user_id = w.user_id), 0)
            + COALESCE((SELECT SUM(CASE WHEN tr.destination_wallet_id = w.id THEN tr.amount ELSE -tr.amount END)
                FROM transfers tr
                WHERE tr.user_id = w.user_id
                    AND (tr.source_wallet_id = w.id OR tr.destination_wallet_id = w.id)), 0)
            AS DECIMAL(14,2)) AS current_balance
        FROM wallets w
        WHERE w.id = :wallet_id AND w.user_id = :user_id
        LIMIT 1"
    );
    $statement->execute(['wallet_id' => $walletId, 'user_id' => $userId]);
    $wallet = $statement->fetch();

    return $wallet ? (string) $wallet['current_balance'] : null;
}

/**
 * Returns every wallet owned by a user along with one consistently calculated
 * balance. Archived wallets can be included for the archive section.
 *
 * @return list<array<string, mixed>>
 */
function user_wallets_with_balances(PDO $pdo, int $userId, bool $includeArchived = true): array
{
    $statement = $pdo->prepare(
        "SELECT w.id, w.provider_name, w.wallet_type, w.wallet_name, w.opening_balance,
                w.currency_code, w.is_archived,
                CAST(w.opening_balance + COALESCE(transaction_totals.net_amount, 0)
                    + COALESCE(transfer_totals.net_amount, 0) AS DECIMAL(14,2)) AS current_balance
         FROM wallets w
         LEFT JOIN (
             SELECT wallet_id, user_id,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) AS net_amount
             FROM transactions
             WHERE user_id = :transaction_user_id
             GROUP BY wallet_id, user_id
         ) transaction_totals ON transaction_totals.wallet_id = w.id AND transaction_totals.user_id = w.user_id
         LEFT JOIN (
             SELECT wallet_id, user_id, SUM(amount_change) AS net_amount
             FROM (
                 SELECT destination_wallet_id AS wallet_id, user_id, amount AS amount_change
                 FROM transfers WHERE user_id = :incoming_transfer_user_id
                 UNION ALL
                 SELECT source_wallet_id AS wallet_id, user_id, -amount AS amount_change
                 FROM transfers WHERE user_id = :outgoing_transfer_user_id
             ) transfer_changes
             GROUP BY wallet_id, user_id
         ) transfer_totals ON transfer_totals.wallet_id = w.id AND transfer_totals.user_id = w.user_id
         WHERE w.user_id = :wallet_user_id
           AND (:include_archived = 1 OR w.is_archived = 0)
         ORDER BY w.is_archived ASC, w.wallet_name ASC"
    );
    $statement->execute([
        'transaction_user_id' => $userId,
        'incoming_transfer_user_id' => $userId,
        'outgoing_transfer_user_id' => $userId,
        'wallet_user_id' => $userId,
        'include_archived' => $includeArchived ? 1 : 0,
    ]);

    return $statement->fetchAll();
}

/** @return array{total_balance: string, monthly_income: string, monthly_expenses: string, monthly_savings: string} */
function financial_dashboard_summary(PDO $pdo, int $userId, string $monthStart, string $nextMonthStart): array
{
    $statement = $pdo->prepare(
        "SELECT CAST(
            COALESCE((SELECT SUM(w.opening_balance) FROM wallets w WHERE w.user_id = :balance_user_id AND w.is_archived = 0), 0)
            + COALESCE((SELECT SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END) FROM transactions t INNER JOIN wallets w ON w.id = t.wallet_id AND w.user_id = t.user_id WHERE t.user_id = :transaction_balance_user_id AND w.is_archived = 0), 0)
            + COALESCE((SELECT SUM(CASE WHEN tr.destination_wallet_id = active_wallet.id THEN tr.amount ELSE -tr.amount END) FROM transfers tr INNER JOIN wallets active_wallet ON active_wallet.user_id = tr.user_id AND active_wallet.is_archived = 0 AND (active_wallet.id = tr.source_wallet_id OR active_wallet.id = tr.destination_wallet_id) WHERE tr.user_id = :transfer_balance_user_id), 0)
            AS DECIMAL(14,2)) AS total_balance,
            CAST(COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS DECIMAL(14,2)) AS monthly_income,
            CAST(COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS DECIMAL(14,2)) AS monthly_expenses
         FROM transactions t WHERE t.user_id = :monthly_user_id AND t.transaction_date >= :month_start AND t.transaction_date < :next_month_start"
    );
    $statement->execute(['balance_user_id' => $userId, 'transaction_balance_user_id' => $userId, 'transfer_balance_user_id' => $userId, 'monthly_user_id' => $userId, 'month_start' => $monthStart, 'next_month_start' => $nextMonthStart]);
    $summary = $statement->fetch() ?: [];
    $income = (string) ($summary['monthly_income'] ?? '0.00');
    $expenses = (string) ($summary['monthly_expenses'] ?? '0.00');

    return ['total_balance' => (string) ($summary['total_balance'] ?? '0.00'), 'monthly_income' => $income, 'monthly_expenses' => $expenses, 'monthly_savings' => number_format((float) $income - (float) $expenses, 2, '.', '')];
}

/** @return list<array<string, mixed>> */
function recent_transactions(PDO $pdo, int $userId, int $limit = 5): array
{
    $limit = max(1, min($limit, 20));
    $statement = $pdo->prepare("SELECT t.id, t.type, t.amount, t.transaction_date, t.description, c.name AS category_name, w.wallet_name FROM transactions t INNER JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id INNER JOIN wallets w ON w.id = t.wallet_id AND w.user_id = t.user_id WHERE t.user_id = :user_id ORDER BY t.transaction_date DESC, t.id DESC LIMIT {$limit}");
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

/** @return list<array<string, mixed>> */
function monthly_budget_progress(PDO $pdo, int $userId, string $monthStart): array
{
    $nextMonth = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
    $statement = $pdo->prepare(
        "SELECT b.id, b.amount, c.name AS category_name, CAST(COALESCE(SUM(t.amount), 0) AS DECIMAL(14,2)) AS spent
         FROM budgets b
         INNER JOIN categories c ON c.id = b.category_id AND c.user_id = b.user_id
         LEFT JOIN transactions t ON t.category_id = b.category_id AND t.user_id = b.user_id
             AND t.type = 'expense' AND t.transaction_date >= :month_start AND t.transaction_date < :next_month
         WHERE b.user_id = :user_id AND b.budget_month = :budget_month
         GROUP BY b.id, b.amount, c.name ORDER BY c.name"
    );
    $statement->execute(['month_start' => $monthStart, 'next_month' => $nextMonth, 'user_id' => $userId, 'budget_month' => $monthStart]);

    return $statement->fetchAll();
}
