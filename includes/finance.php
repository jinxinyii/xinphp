<?php
declare(strict_types=1);

/** Financial helpers shared by future wallet, transaction, and dashboard pages. */

const WALLET_PROVIDERS = [
    'GCash', 'Maya', 'GoTyme', 'GrabPay', 'ShopeePay',
    'BDO', 'BPI', 'Metrobank', 'UnionBank', 'Landbank',
    'Security Bank', 'RCBC', 'PNB', 'China Bank',
    'Cash', 'Savings Account', 'Other Bank', 'Custom Wallet',
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
