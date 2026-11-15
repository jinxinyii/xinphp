<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/finance.php';

$userId = current_user_id();
$pdo = database();
$errors = [];
$flash = get_flash();
$editingTransaction = null;
$openTransactionDialog = false;
$transactionForm = [
    'id' => '',
    'type' => 'expense',
    'description' => '',
    'amount' => '',
    'wallet_id' => '',
    'category_id' => '',
    'transaction_date' => date('Y-m-d'),
    'notes' => '',
];

function is_valid_transaction_date(string $date): bool
{
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateErrors = DateTimeImmutable::getLastErrors();

    return $dateObject instanceof DateTimeImmutable
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $dateObject->format('Y-m-d') === $date;
}

function is_valid_positive_amount(string $amount): bool
{
    return (bool) preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amount) && (float) $amount > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form expired. Please try again.';
    } elseif ($action === 'create_category') {
        $categoryName = normalize_financial_text((string) ($_POST['category_name'] ?? ''));
        $categoryType = (string) ($_POST['category_type'] ?? '');

        if (mb_strlen($categoryName) < 1 || mb_strlen($categoryName) > 100) {
            $errors[] = 'Category name must be between 1 and 100 characters.';
        }
        if (!in_array($categoryType, ['income', 'expense'], true)) {
            $errors[] = 'Choose income or expense for the category.';
        }
        if (!$errors) {
            try {
                $statement = $pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (:user_id, :name, :type)');
                $statement->execute(['user_id' => $userId, 'name' => $categoryName, 'type' => $categoryType]);
                set_flash('success', 'Category added. You can now use it in a transaction.');
                redirect('transactions.php#category-tools');
            } catch (PDOException $exception) {
                $errors[] = $exception->getCode() === '23000'
                    ? 'You already have a category with that name and type.'
                    : 'We could not save your category. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $transactionId = filter_var($_POST['transaction_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $transaction = $transactionId ? find_owned_transaction($pdo, $transactionId, $userId) : null;

        if (!$transaction) {
            $errors[] = 'That transaction was not found.';
        } else {
            try {
                $statement = $pdo->prepare('DELETE FROM transactions WHERE id = :transaction_id AND user_id = :user_id');
                $statement->execute(['transaction_id' => $transactionId, 'user_id' => $userId]);
                set_flash('success', 'Transaction deleted. Your wallet balance was recalculated.');
                redirect('transactions.php');
            } catch (PDOException $exception) {
                $errors[] = 'This transaction cannot be deleted because it is used by another financial record.';
            }
        }
    } elseif (in_array($action, ['create', 'update'], true)) {
        $openTransactionDialog = true;
        $transactionForm = [
            'id' => trim((string) ($_POST['transaction_id'] ?? '')),
            'type' => (string) ($_POST['transaction_type'] ?? ''),
            'description' => normalize_financial_text((string) ($_POST['description'] ?? '')),
            'amount' => trim((string) ($_POST['amount'] ?? '')),
            'wallet_id' => trim((string) ($_POST['wallet_id'] ?? '')),
            'category_id' => trim((string) ($_POST['category_id'] ?? '')),
            'transaction_date' => trim((string) ($_POST['transaction_date'] ?? '')),
            'notes' => normalize_financial_text((string) ($_POST['notes'] ?? '')),
        ];

        if (!in_array($transactionForm['type'], ['income', 'expense'], true)) {
            $errors[] = 'Choose income or expense.';
        }
        if (mb_strlen($transactionForm['description']) < 1 || mb_strlen($transactionForm['description']) > 150) {
            $errors[] = 'Description must be between 1 and 150 characters.';
        }
        if (!is_valid_positive_amount($transactionForm['amount'])) {
            $errors[] = 'Amount must be greater than zero and use at most two decimal places.';
        }
        if (!is_valid_transaction_date($transactionForm['transaction_date'])) {
            $errors[] = 'Enter a valid transaction date.';
        }
        if (mb_strlen($transactionForm['notes']) > 5000) {
            $errors[] = 'Notes cannot exceed 5,000 characters.';
        }

        $walletId = filter_var($transactionForm['wallet_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $categoryId = filter_var($transactionForm['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $wallet = $walletId ? find_owned_wallet($pdo, $walletId, $userId) : null;
        $category = $categoryId ? find_owned_category($pdo, $categoryId, $userId) : null;

        if (!$wallet || (bool) $wallet['is_archived']) {
            $errors[] = 'Choose one of your active wallets.';
        }
        if (!$category || (bool) $category['is_archived'] || $category['type'] !== $transactionForm['type']) {
            $errors[] = 'Choose one of your active categories that matches the transaction type.';
        }

        $transactionId = $action === 'update'
            ? filter_var($transactionForm['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : null;
        $editingTransaction = $transactionId ? find_owned_transaction($pdo, $transactionId, $userId) : null;

        if ($action === 'update' && !$editingTransaction) {
            $errors[] = 'That transaction was not found.';
        }

        if (!$errors) {
            if ($action === 'create') {
                $statement = $pdo->prepare(
                    'INSERT INTO transactions (user_id, wallet_id, category_id, category_type, type, amount, transaction_date, description, notes)
                     VALUES (:user_id, :wallet_id, :category_id, :category_type, :type, :amount, :transaction_date, :description, :notes)'
                );
                $statement->execute([
                    'user_id' => $userId,
                    'wallet_id' => $walletId,
                    'category_id' => $categoryId,
                    'category_type' => $category['type'],
                    'type' => $transactionForm['type'],
                    'amount' => $transactionForm['amount'],
                    'transaction_date' => $transactionForm['transaction_date'],
                    'description' => $transactionForm['description'],
                    'notes' => $transactionForm['notes'] !== '' ? $transactionForm['notes'] : null,
                ]);
                set_flash('success', 'Transaction added. Your wallet balance was recalculated.');
            } else {
                $statement = $pdo->prepare(
                    'UPDATE transactions SET wallet_id = :wallet_id, category_id = :category_id, category_type = :category_type,
                     type = :type, amount = :amount, transaction_date = :transaction_date, description = :description, notes = :notes
                     WHERE id = :transaction_id AND user_id = :user_id'
                );
                $statement->execute([
                    'wallet_id' => $walletId,
                    'category_id' => $categoryId,
                    'category_type' => $category['type'],
                    'type' => $transactionForm['type'],
                    'amount' => $transactionForm['amount'],
                    'transaction_date' => $transactionForm['transaction_date'],
                    'description' => $transactionForm['description'],
                    'notes' => $transactionForm['notes'] !== '' ? $transactionForm['notes'] : null,
                    'transaction_id' => $transactionId,
                    'user_id' => $userId,
                ]);
                set_flash('success', 'Transaction updated. Your wallet balance was recalculated.');
            }
            redirect('transactions.php');
        }
    }
}

$walletStatement = $pdo->prepare('SELECT id, wallet_name FROM wallets WHERE user_id = :user_id AND is_archived = 0 ORDER BY wallet_name');
$walletStatement->execute(['user_id' => $userId]);
$walletOptions = $walletStatement->fetchAll();
$categoryStatement = $pdo->prepare('SELECT id, name, type FROM categories WHERE user_id = :user_id AND is_archived = 0 ORDER BY type, name');
$categoryStatement->execute(['user_id' => $userId]);
$categoryOptions = $categoryStatement->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $openTransactionDialog = true;
    $transactionId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $editingTransaction = $transactionId ? find_owned_transaction($pdo, $transactionId, $userId) : null;
    if (!$editingTransaction) {
        set_flash('error', 'That transaction was not found.');
        redirect('transactions.php');
    }
    $transactionForm = [
        'id' => (string) $editingTransaction['id'],
        'type' => $editingTransaction['type'],
        'description' => $editingTransaction['description'],
        'amount' => (string) $editingTransaction['amount'],
        'wallet_id' => (string) $editingTransaction['wallet_id'],
        'category_id' => (string) $editingTransaction['category_id'],
        'transaction_date' => $editingTransaction['transaction_date'],
        'notes' => (string) ($editingTransaction['notes'] ?? ''),
    ];
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'type' => (string) ($_GET['type'] ?? ''),
    'wallet_id' => filter_var($_GET['wallet_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'category_id' => filter_var($_GET['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to' => (string) ($_GET['date_to'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? 'newest'),
];
$filters['type'] = in_array($filters['type'], ['income', 'expense'], true) ? $filters['type'] : '';
$filters['sort'] = $filters['sort'] === 'oldest' ? 'oldest' : 'newest';

$where = ['t.user_id = :user_id'];
$parameters = ['user_id' => $userId];
if ($filters['search'] !== '') {
    $where[] = 't.description LIKE :search';
    $parameters['search'] = '%' . mb_substr($filters['search'], 0, 150) . '%';
}
if ($filters['type'] !== '') {
    $where[] = 't.type = :type';
    $parameters['type'] = $filters['type'];
}
if ($filters['wallet_id']) {
    $where[] = 't.wallet_id = :wallet_id';
    $parameters['wallet_id'] = $filters['wallet_id'];
}
if ($filters['category_id']) {
    $where[] = 't.category_id = :category_id';
    $parameters['category_id'] = $filters['category_id'];
}
if (is_valid_transaction_date($filters['date_from'])) {
    $where[] = 't.transaction_date >= :date_from';
    $parameters['date_from'] = $filters['date_from'];
}
if (is_valid_transaction_date($filters['date_to'])) {
    $where[] = 't.transaction_date <= :date_to';
    $parameters['date_to'] = $filters['date_to'];
}
$whereSql = implode(' AND ', $where);

$countStatement = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE {$whereSql}");
$countStatement->execute($parameters);
$totalTransactions = (int) $countStatement->fetchColumn();
$perPage = 10;
$totalPages = max(1, (int) ceil($totalTransactions / $perPage));
$page = min(max((int) ($_GET['page'] ?? 1), 1), $totalPages);
$offset = ($page - 1) * $perPage;
$orderBy = $filters['sort'] === 'oldest' ? 'ASC' : 'DESC';
$historyStatement = $pdo->prepare(
    "SELECT t.id, t.type, t.amount, t.transaction_date, t.description, t.notes, c.name AS category_name, w.wallet_name
     FROM transactions t
     INNER JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
     INNER JOIN wallets w ON w.id = t.wallet_id AND w.user_id = t.user_id
     WHERE {$whereSql}
     ORDER BY t.transaction_date {$orderBy}, t.id {$orderBy}
     LIMIT {$perPage} OFFSET {$offset}"
);
$historyStatement->execute($parameters);
$transactions = $historyStatement->fetchAll();
$pageTitle = 'xinPHP | Transactions';
require __DIR__ . '/includes/header.php';
?>

<body class="min-h-screen bg-sand text-ink">
    <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr]">
        <aside class="bg-brand px-6 py-7 text-white md:flex md:min-h-screen md:flex-col">
            <a class="inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="dashboard.php">
                <span class="grid size-9 place-items-center rounded-xl bg-mint text-lg text-brand-dark">
                    ₱
                </span>
                xinPHP
            </a>
            <button class="sidebar-toggle mt-6 inline-flex w-full items-center justify-between rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10 md:hidden" type="button" aria-expanded="false" aria-controls="transactions-navigation"><span>Navigation</span><span class="sidebar-toggle-arrow transition-transform" aria-hidden="true">⌄</span></button>
            <nav id="transactions-navigation" class="mt-3 hidden gap-2 pb-2 md:mt-10 md:flex md:flex-1 md:flex-col md:pb-0"
                aria-label="Main navigation">
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    href="dashboard.php">
                    Overview
                </a>
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    href="wallets.php">
                    Wallets
                </a>
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    href="transfers.php">
                    Transfers
                </a>
                <a class="shrink-0 rounded-xl bg-white/15 px-4 py-3 text-sm font-bold"
                    href="transactions.php">
                    Transactions
                </a>
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    href="budgets.php">
                    Budgets
                </a>
                <form class="border-t border-white/15 pt-2 md:hidden" action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="w-full rounded-xl px-4 py-3 text-left text-sm font-bold text-white/70 transition hover:bg-white/10 hover:text-white" type="submit">Log out</button>
                </form>
            </nav>
            <form class="mt-6 hidden border-t border-white/15 pt-4 md:mt-auto md:block md:pt-6" action="logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button
                    class="mt-2 w-full rounded-xl px-4 py-3 text-left text-sm font-bold text-white/70 transition hover:bg-white/10 hover:text-white"
                    type="submit">
                    Log out
                </button>
            </form>
        </aside>
        <main class="transactions-main px-4 pb-28 pt-7 sm:px-10 sm:py-10 lg:px-14 md:pb-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Money activity</p>
                    <h1 class="mt-2 text-4xl font-extrabold tracking-[-.05em]">Transactions</h1>
                    <p class="mt-2 text-sm text-muted">Record and review every income and expense.</p>
                </div>
                <button class="desktop-transaction-cta hidden rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white transition hover:bg-brand-dark md:inline-flex" type="button" data-open-transaction-dialog>+ Add transaction</button>
            </div>
            <?php if ($flash): ?><div
                    class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?> mt-7" role="status">
                    <?= e($flash['message']) ?></div><?php endif; ?><?php if ($errors): ?><div
                    class="alert alert-error mt-7" role="alert">
                    <ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li>
                        <?php endforeach; ?></ul>
                </div><?php endif; ?>
            <section id="category-tools" class="mt-7 rounded-2xl border border-line bg-white p-5 shadow-sm">
                <details class="rounded-xl bg-sand p-4">
                    <summary class="cursor-pointer text-sm font-bold text-brand">Manage custom categories</summary>
                    <form class="mt-4 flex flex-col items-stretch gap-3 sm:flex-row sm:items-end" action="transactions.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create_category">
                        <label class="flex-1 text-sm font-bold">Category name<input class="mt-1 block w-full rounded-lg border border-line px-3 py-2.5 font-normal" name="category_name" data-uppercase maxlength="100" required></label>
                        <label class="text-sm font-bold sm:w-40">Type<select class="mt-1 block w-full rounded-lg border border-line px-3 py-2.5 font-normal" name="category_type">
                                <option value="expense">Expense</option>
                                <option value="income">Income</option>
                            </select></label>
                        <button class="rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white" type="submit">Add category</button>
                    </form>
                </details>
            </section>
            <dialog id="transaction-form" class="transaction-dialog fixed inset-x-0 bottom-0 top-auto m-0 max-h-[92dvh] w-full max-w-none overflow-hidden rounded-t-3xl border border-line bg-white p-0 shadow-2xl backdrop:bg-ink/50 md:inset-0 md:m-auto md:max-h-[88dvh] md:max-w-2xl md:rounded-2xl" data-open-on-load="<?= $openTransactionDialog ? 'true' : 'false' ?>">
                <div class="transaction-dialog-shell flex max-h-[92dvh] flex-col md:max-h-[88dvh]">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4 sm:px-7">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[.15em] text-brand">Quick entry</p>
                            <h2 class="mt-1 text-xl font-extrabold"><?= $editingTransaction ? 'Edit transaction' : 'Add transaction' ?></h2>
                        </div>
                        <button class="grid size-10 place-items-center rounded-full bg-sand text-xl font-bold text-muted hover:text-ink" type="button" data-close-transaction-dialog aria-label="Close transaction form">×</button>
                    </div>
                    <?php if (!$walletOptions || !$categoryOptions): ?><p class="mx-5 mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 sm:mx-7">You need at least one active wallet and category before adding a transaction.</p><?php endif; ?>
                    <form id="transaction-entry-form" class="flex min-h-0 flex-1 flex-col" action="transactions.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="<?= $editingTransaction ? 'update' : 'create' ?>">
                        <?php if ($editingTransaction): ?><input type="hidden" name="transaction_id" value="<?= e($transactionForm['id']) ?>"><?php endif; ?>
                        <div class="transaction-dialog-body min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-7">
                            <fieldset>
                                <legend class="mb-2 block text-sm font-bold">Transaction type</legend>
                                <div class="transaction-type-options grid grid-cols-2 gap-2">
                                    <label class="transaction-type-option flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border border-line px-3 text-sm font-bold"><input name="transaction_type" type="radio" value="expense" <?= $transactionForm['type'] === 'expense' ? 'checked' : '' ?>> Expense</label>
                                    <label class="transaction-type-option flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border border-line px-3 text-sm font-bold"><input name="transaction_type" type="radio" value="income" <?= $transactionForm['type'] === 'income' ? 'checked' : '' ?>> Income</label>
                                </div>
                            </fieldset>
                            <div class="form-field">
                                <label for="amount">Amount (PHP)</label>
                                <div class="transaction-amount-wrap relative"><span class="transaction-amount-prefix pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg font-bold text-muted">₱</span><input class="transaction-amount-input !pl-10 !py-4 !text-xl !font-extrabold" id="amount" name="amount" type="text" inputmode="decimal" autocomplete="off" value="<?= e($transactionForm['amount']) ?>" placeholder="0.00" required></div>
                            </div>
                            <div class="form-field"><label for="description">Description</label><input id="description" name="description" data-uppercase maxlength="150" value="<?= e($transactionForm['description']) ?>" placeholder="e.g. Grocery shopping" required></div>
                            <div class="transaction-field-grid grid gap-5 sm:grid-cols-2">
                                <div class="form-field"><label for="category_id">Category</label><select class="w-full rounded-xl border border-line px-4 py-3.5 text-sm outline-none focus:border-brand focus:ring-4 focus:ring-brand/10" id="category_id" name="category_id" data-category-select required>
                                        <option value="">Select category</option><?php foreach ($categoryOptions as $category): ?><option data-category-type="<?= e($category['type']) ?>" value="<?= (int) $category['id'] ?>" <?= $transactionForm['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="form-field"><label for="wallet_id">Wallet</label><select class="w-full rounded-xl border border-line px-4 py-3.5 text-sm outline-none focus:border-brand focus:ring-4 focus:ring-brand/10" id="wallet_id" name="wallet_id" required>
                                        <option value="">Select wallet</option><?php foreach ($walletOptions as $wallet): ?><option value="<?= (int) $wallet['id'] ?>" <?= $transactionForm['wallet_id'] === (string) $wallet['id'] ? 'selected' : '' ?>><?= e($wallet['wallet_name']) ?></option><?php endforeach; ?>
                                    </select></div>
                            </div>
                            <div class="form-field"><label for="transaction_date">Transaction date</label><input id="transaction_date" name="transaction_date" type="date" value="<?= e($transactionForm['transaction_date']) ?>" required></div>
                            <details class="rounded-xl bg-sand p-4">
                                <summary class="cursor-pointer text-sm font-bold text-brand">Additional details (optional)</summary>
                                <div class="form-field mt-4"><label for="notes">Notes</label><textarea class="min-h-24 w-full rounded-xl border border-line px-4 py-3.5 text-sm outline-none focus:border-brand focus:ring-4 focus:ring-brand/10" id="notes" name="notes" data-uppercase maxlength="5000"><?= e($transactionForm['notes']) ?></textarea></div>
                            </details>
                        </div>
                        <div class="transaction-dialog-footer flex gap-3 border-t border-line bg-white px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-7">
                            <button class="flex-1 rounded-xl bg-brand px-5 py-3.5 text-sm font-bold text-white transition hover:bg-brand-dark" type="submit" <?= (!$walletOptions || !$categoryOptions) ? 'disabled' : '' ?>><?= $editingTransaction ? 'Save changes' : 'Save transaction' ?></button>
                            <button class="rounded-xl border border-line px-5 py-3.5 text-sm font-bold text-muted hover:text-ink" type="button" data-close-transaction-dialog>Cancel</button>
                        </div>
                    </form>
                </div>
            </dialog>
            <button class="mobile-transaction-cta fixed bottom-5 right-5 z-30 inline-flex items-center gap-2 rounded-full bg-brand px-5 py-4 text-sm font-extrabold text-white shadow-xl shadow-brand/25 transition hover:bg-brand-dark md:hidden" type="button" data-open-transaction-dialog><span class="text-xl leading-none">+</span> Add transaction</button>
            <section class="mt-10">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-extrabold">Transaction history</h2>
                        <p class="mt-1 text-sm text-muted"><?= $totalTransactions ?>
                            result<?= $totalTransactions === 1 ? '' : 's' ?></p>
                    </div>
                </div>
                <form class="mt-5 grid gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
                    method="get" action="transactions.php"><input
                        class="rounded-xl border border-line px-3 py-2 text-sm" name="search"
                        value="<?= e($filters['search']) ?>" placeholder="Search description"><select
                        class="rounded-xl border border-line px-3 py-2 text-sm" name="type">
                        <option value="">All types</option>
                        <option value="income" <?= $filters['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select><select class="rounded-xl border border-line px-3 py-2 text-sm" name="wallet_id">
                        <option value="">All wallets</option><?php foreach ($walletOptions as $wallet): ?><option
                                value="<?= (int) $wallet['id'] ?>"
                                <?= $filters['wallet_id'] === (int) $wallet['id'] ? 'selected' : '' ?>>
                                <?= e($wallet['wallet_name']) ?></option><?php endforeach; ?>
                    </select><select class="rounded-xl border border-line px-3 py-2 text-sm" name="category_id">
                        <option value="">All categories</option><?php foreach ($categoryOptions as $category): ?><option
                                value="<?= (int) $category['id'] ?>"
                                <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?></option><?php endforeach; ?>
                    </select><input class="rounded-xl border border-line px-3 py-2 text-sm" name="date_from" type="date"
                        value="<?= e($filters['date_from']) ?>"><input
                        class="rounded-xl border border-line px-3 py-2 text-sm" name="date_to" type="date"
                        value="<?= e($filters['date_to']) ?>"><select
                        class="rounded-xl border border-line px-3 py-2 text-sm" name="sort">
                        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest first
                        </option>
                        <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first
                        </option>
                    </select>
                    <div class="flex gap-2"><button class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white"
                            type="submit">Filter</button><a
                            class="rounded-xl border border-line px-4 py-2 text-sm font-bold text-muted"
                            href="transactions.php">Clear</a></div>
                </form>
                <?php if (!$transactions): ?><div
                        class="mt-5 rounded-2xl border border-dashed border-line bg-white p-8 text-center">
                        <p class="font-bold">No transactions found</p>
                        <p class="mt-2 text-sm text-muted">Add your first income or expense, or adjust your filters.</p>
                    </div><?php else: ?><div class="mt-5 overflow-x-auto rounded-2xl border border-line bg-white">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-line bg-sand text-xs uppercase tracking-wide text-muted">
                                <tr>
                                    <th class="px-5 py-4">Description</th>
                                    <th class="px-5 py-4">Category</th>
                                    <th class="px-5 py-4">Wallet</th>
                                    <th class="px-5 py-4">Date</th>
                                    <th class="px-5 py-4">Type</th>
                                    <th class="px-5 py-4 text-right">Amount</th>
                                    <th class="px-5 py-4"></th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($transactions as $transaction): ?><tr
                                        class="border-b border-line last:border-0">
                                        <td class="px-5 py-4 font-bold"><?= e($transaction['description']) ?></td>
                                        <td class="px-5 py-4 text-muted"><?= e($transaction['category_name']) ?></td>
                                        <td class="px-5 py-4 text-muted"><?= e($transaction['wallet_name']) ?></td>
                                        <td class="px-5 py-4 text-muted"><?= e($transaction['transaction_date']) ?></td>
                                        <td class="px-5 py-4"><span
                                                class="rounded-full px-2.5 py-1 text-xs font-bold <?= $transaction['type'] === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>"><?= e(ucfirst($transaction['type'])) ?></span>
                                        </td>
                                        <td
                                            class="px-5 py-4 text-right font-bold <?= $transaction['type'] === 'income' ? 'text-brand' : 'text-rose-700' ?>">
                                            <?= $transaction['type'] === 'income' ? '+' : '-' ?><?= format_peso((string) $transaction['amount']) ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex justify-end gap-3"><a
                                                    class="font-bold text-brand hover:text-brand-dark"
                                                    href="transactions.php?edit=<?= (int) $transaction['id'] ?>">Edit</a>
                                                <form action="transactions.php" method="post"
                                                    onsubmit="return confirm('Delete this transaction? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input
                                                        type="hidden" name="action" value="delete"><input type="hidden"
                                                        name="transaction_id" value="<?= (int) $transaction['id'] ?>"><button
                                                        class="font-bold text-rose-700" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div><?php if ($totalPages > 1): ?><div class="mt-5 flex items-center justify-between text-sm"><span
                                class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
                            <div class="flex gap-2"><?php $query = $_GET;
                                                    if ($page > 1): $query['page'] = $page - 1; ?><a
                                        class="rounded-lg border border-line px-3 py-2 font-bold"
                                        href="transactions.php?<?= e(http_build_query($query)) ?>">Previous</a><?php endif;
                                                                                                            if ($page < $totalPages): $query['page'] = $page + 1; ?><a
                                        class="rounded-lg border border-line px-3 py-2 font-bold"
                                        href="transactions.php?<?= e(http_build_query($query)) ?>">Next</a><?php endif; ?></div>
                        </div><?php endif; ?><?php endif; ?>
            </section>
        </main>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; ?>