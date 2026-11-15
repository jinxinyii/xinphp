<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/finance.php';

$userId = current_user_id();
$pdo = database();
$errors = [];
$editingWallet = null;
$adjustingWallet = null;
$walletForm = [
    'id' => '',
    'wallet_name' => '',
    'provider_name' => '',
    'wallet_type' => 'e_wallet',
    'opening_balance' => '0.00',
];

function wallet_amount_to_cents(string $amount): int
{
    $negative = str_starts_with($amount, '-');
    $amount = ltrim($amount, '-');
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
    $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

    return $negative ? -$cents : $cents;
}

function wallet_cents_to_amount(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $absolute = abs($cents);

    return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
}

function valid_wallet_amount(string $amount): bool
{
    return (bool) preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/', $amount);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form expired. Please try again.';
    } elseif (in_array($action, ['archive', 'reactivate'], true)) {
        $walletId = filter_var($_POST['wallet_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $wallet = $walletId ? find_owned_wallet($pdo, $walletId, $userId) : null;

        if (!$wallet) {
            $errors[] = 'That wallet was not found.';
        } else {
            $statement = $pdo->prepare('UPDATE wallets SET is_archived = :is_archived WHERE id = :wallet_id AND user_id = :user_id');
            $statement->execute([
                'is_archived' => $action === 'archive' ? 1 : 0,
                'wallet_id' => $walletId,
                'user_id' => $userId,
            ]);
            set_flash('success', $action === 'archive' ? 'Wallet archived.' : 'Wallet restored to active Wallets.');
            redirect('wallets.php');
        }
    } elseif ($action === 'adjust_balance') {
        $walletId = filter_var($_POST['wallet_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $targetBalance = trim((string) ($_POST['target_balance'] ?? ''));
        $reason = normalize_financial_text((string) ($_POST['reason'] ?? ''));

        if (!preg_match('/^\\d{1,12}(?:\\.\\d{1,2})?$/', $targetBalance)) {
            $errors[] = 'Enter a valid current balance that is zero or greater.';
        }
        if (mb_strlen($reason) > 255) {
            $errors[] = 'The adjustment reason must be 255 characters or fewer.';
        }

        $adjustingWallet = $walletId ? find_owned_wallet($pdo, $walletId, $userId) : null;

        if (!$adjustingWallet) {
            $errors[] = 'That wallet was not found.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT id FROM wallets WHERE id = :wallet_id AND user_id = :user_id FOR UPDATE');
                $lock->execute(['wallet_id' => $walletId, 'user_id' => $userId]);

                if (!$lock->fetch()) {
                    throw new RuntimeException('That wallet was not found.');
                }

                $currentBalance = wallet_current_balance($pdo, (int) $walletId, $userId);
                if ($currentBalance === null) {
                    throw new RuntimeException('Unable to calculate the wallet balance.');
                }

                $deltaCents = wallet_amount_to_cents($targetBalance) - wallet_amount_to_cents($currentBalance);
                $delta = wallet_cents_to_amount($deltaCents);

                if ($deltaCents !== 0) {
                    $insert = $pdo->prepare(
                        'INSERT INTO wallet_balance_adjustments
                            (user_id, wallet_id, amount_change, adjustment_date, reason)
                         VALUES (:user_id, :wallet_id, :amount_change, CURRENT_DATE(), :reason)'
                    );
                    $insert->execute([
                        'user_id' => $userId,
                        'wallet_id' => $walletId,
                        'amount_change' => $delta,
                        'reason' => $reason !== '' ? $reason : null,
                    ]);
                }

                $pdo->commit();
                set_flash('success', $deltaCents === 0
                    ? 'Your wallet balance is already up to date.'
                    : 'Current balance adjusted successfully.');
                redirect('wallets.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($exception->getMessage());
                $errors[] = 'We could not adjust your wallet balance. Please try again.';
            }
        }
    } elseif (in_array($action, ['create', 'update'], true)) {
        $walletForm = [
            'id' => trim((string) ($_POST['wallet_id'] ?? '')),
            'wallet_name' => normalize_financial_text((string) ($_POST['wallet_name'] ?? '')),
            'provider_name' => normalize_financial_text((string) ($_POST['provider_name'] ?? '')),
            'wallet_type' => (string) ($_POST['wallet_type'] ?? ''),
            'opening_balance' => trim((string) ($_POST['opening_balance'] ?? '')),
        ];

        if (mb_strlen($walletForm['wallet_name']) < 1 || mb_strlen($walletForm['wallet_name']) > 100) {
            $errors[] = 'Wallet name must be between 1 and 100 characters.';
        }
        if (mb_strlen($walletForm['provider_name']) < 1 || mb_strlen($walletForm['provider_name']) > 100) {
            $errors[] = 'Provider name must be between 1 and 100 characters.';
        }
        if (!array_key_exists($walletForm['wallet_type'], WALLET_TYPES)) {
            $errors[] = 'Choose a valid wallet type.';
        }
        if ($action === 'create' && !valid_wallet_amount($walletForm['opening_balance'])) {
            $errors[] = 'Opening balance must be a valid amount with up to two decimal places.';
        }

        $walletId = $action === 'update'
            ? filter_var($walletForm['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : null;
        $editingWallet = $walletId ? find_owned_wallet($pdo, $walletId, $userId) : null;

        if ($action === 'update' && !$editingWallet) {
            $errors[] = 'That wallet was not found.';
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO wallets (user_id, provider_name, wallet_type, wallet_name, opening_balance, currency_code)
                         VALUES (:user_id, :provider_name, :wallet_type, :wallet_name, :opening_balance, \'PHP\')'
                    );
                    $statement->execute([
                        'user_id' => $userId,
                        'provider_name' => $walletForm['provider_name'],
                        'wallet_type' => $walletForm['wallet_type'],
                        'wallet_name' => $walletForm['wallet_name'],
                        'opening_balance' => $walletForm['opening_balance'],
                    ]);
                    set_flash('success', 'Wallet added successfully.');
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE wallets
                         SET provider_name = :provider_name, wallet_type = :wallet_type,
                             wallet_name = :wallet_name
                         WHERE id = :wallet_id AND user_id = :user_id'
                    );
                    $statement->execute([
                        'provider_name' => $walletForm['provider_name'],
                        'wallet_type' => $walletForm['wallet_type'],
                        'wallet_name' => $walletForm['wallet_name'],
                        'wallet_id' => $walletId,
                        'user_id' => $userId,
                    ]);
                    set_flash('success', 'Wallet updated successfully.');
                }
                redirect('wallets.php');
            } catch (PDOException $exception) {
                $errors[] = $exception->getCode() === '23000'
                    ? 'You already have a wallet with that name.'
                    : 'We could not save your wallet. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $walletId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $editingWallet = $walletId ? find_owned_wallet($pdo, $walletId, $userId) : null;

    if (!$editingWallet) {
        set_flash('error', 'That wallet was not found.');
        redirect('wallets.php');
    }

    $walletForm = [
        'id' => (string) $editingWallet['id'],
        'wallet_name' => $editingWallet['wallet_name'],
        'provider_name' => $editingWallet['provider_name'],
        'wallet_type' => $editingWallet['wallet_type'],
        'opening_balance' => (string) $editingWallet['opening_balance'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['adjust'])) {
    $walletId = filter_var($_GET['adjust'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $adjustingWallet = $walletId ? find_owned_wallet($pdo, $walletId, $userId) : null;

    if (!$adjustingWallet) {
        set_flash('error', 'That wallet was not found.');
        redirect('wallets.php');
    }
}

$adjustmentHistory = [];
$currentAdjustmentBalance = null;
if ($adjustingWallet) {
    $currentAdjustmentBalance = wallet_current_balance($pdo, (int) $adjustingWallet['id'], $userId);
    $historyStatement = $pdo->prepare(
        'SELECT amount_change, adjustment_date, reason
         FROM wallet_balance_adjustments
         WHERE wallet_id = :wallet_id AND user_id = :user_id
         ORDER BY adjustment_date DESC, id DESC
         LIMIT 10'
    );
    $historyStatement->execute(['wallet_id' => $adjustingWallet['id'], 'user_id' => $userId]);
    $adjustmentHistory = $historyStatement->fetchAll();
}

$wallets = user_wallets_with_balances($pdo, $userId);
$activeWallets = array_filter($wallets, static fn(array $wallet): bool => !(bool) $wallet['is_archived']);
$archivedWallets = array_filter($wallets, static fn(array $wallet): bool => (bool) $wallet['is_archived']);
$flash = get_flash();
$pageTitle = 'xinPHP | Wallets';
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
            <button class="sidebar-toggle mt-6 inline-flex w-full items-center justify-between rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10 md:hidden" type="button" aria-expanded="false" aria-controls="wallets-navigation"><span>Navigation</span><span class="sidebar-toggle-arrow transition-transform" aria-hidden="true">⌄</span></button>
            <nav id="wallets-navigation" class="mt-3 hidden gap-2 pb-2 md:mt-10 md:flex md:flex-1 md:flex-col md:pb-0"
                aria-label="Main navigation">
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    href="dashboard.php">
                    Overview
                </a>
                <a class="shrink-0 rounded-xl bg-white/15 px-4 py-3 text-sm font-bold"
                    href="wallets.php">
                    Wallets
                </a>
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
                    href="transfers.php">
                    Transfers
                </a>
                <a class="shrink-0 rounded-xl px-4 py-3 text-sm text-white/70 transition hover:bg-white/10 hover:text-white"
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
        <main class="px-6 py-10 sm:px-10 lg:px-14">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Your accounts</p>
                    <h1 class="mt-2 text-4xl font-extrabold tracking-[-.05em]">Wallets</h1>
                    <p class="mt-2 text-sm leading-6 text-muted">Track cash, bank accounts, and e-wallets in one place.
                    </p>
                </div>
            </div>
            <?php if ($flash): ?><div
                    class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?> mt-7" role="status">
                    <?= e($flash['message']) ?></div><?php endif; ?>
            <?php if ($errors): ?><div class="alert alert-error mt-7" role="alert">
                    <ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li>
                        <?php endforeach; ?></ul>
                </div><?php endif; ?>
            <section class="mt-8 grid gap-4 lg:grid-cols-[minmax(0,1fr)_23rem]">
                <div>
                    <h2 class="text-lg font-extrabold">Active wallets</h2>
                    <?php if (!$activeWallets): ?>
                        <div class="mt-4 rounded-2xl border border-dashed border-line bg-white p-8 text-center">
                            <p class="font-bold">No wallets yet</p>
                            <p class="mt-2 text-sm text-muted">Add your cash, bank, or e-wallet account to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <?php foreach ($activeWallets as $wallet): ?>
                                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-wide text-muted">
                                                <?= e(str_replace('_', ' ', $wallet['wallet_type'])) ?></p>
                                            <h3 class="mt-1 font-extrabold"><?= e($wallet['wallet_name']) ?></h3>
                                            <p class="mt-1 text-sm text-muted"><?= e($wallet['provider_name']) ?></p>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-2.5">
                                            <a class="rounded-full bg-sand px-3 py-1.5 text-xs font-bold text-brand transition hover:bg-line"
                                                href="wallets.php?edit=<?= (int) $wallet['id'] ?>">Edit</a>
                                            <a class="rounded-full bg-brand/10 px-3 py-1.5 text-xs font-bold text-brand transition hover:bg-brand/15"
                                                href="wallets.php?adjust=<?= (int) $wallet['id'] ?>">Adjust Balance</a>
                                        </div>
                                    </div>
                                    <p class="mt-5 text-2xl font-extrabold tracking-tight">
                                        <?= format_peso((string) $wallet['current_balance']) ?></p>
                                    <p class="mt-1 text-xs text-muted">Current Balance · PHP</p>
                                    <form class="mt-5" action="wallets.php" method="post"
                                        onsubmit="return confirm('Archive this wallet? Its history will be kept.');"><input
                                            type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden"
                                            name="action" value="archive"><input type="hidden" name="wallet_id"
                                            value="<?= (int) $wallet['id'] ?>"><button
                                            class="text-xs font-bold text-muted hover:text-ink" type="submit">Archive
                                            Wallet</button></form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <section id="wallet-form" class="rounded-2xl border border-line bg-white p-6 shadow-sm">
                    <?php if ($adjustingWallet): ?>
                        <h2 class="text-lg font-extrabold">ADJUST CURRENT BALANCE</h2>
                        <p class="mt-1 text-sm text-muted"><?= e($adjustingWallet['wallet_name']) ?> · <?= e($adjustingWallet['provider_name']) ?></p>
                        <div class="mt-5 rounded-xl bg-sand p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-muted">Opening Balance · Historical</p>
                            <p class="mt-1 text-lg font-extrabold"><?= format_peso((string) $adjustingWallet['opening_balance']) ?></p>
                            <p class="mt-4 text-xs font-bold uppercase tracking-wide text-muted">Current Balance</p>
                            <p class="mt-1 text-2xl font-extrabold text-brand"><?= format_peso((string) $currentAdjustmentBalance) ?></p>
                        </div>
                        <form class="mt-6" action="wallets.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="adjust_balance">
                            <input type="hidden" name="wallet_id" value="<?= (int) $adjustingWallet['id'] ?>">
                            <div class="form-field">
                                <label for="target_balance">Actual Current Balance</label>
                                <input id="target_balance" name="target_balance" type="text" inputmode="decimal"
                                    placeholder="0.00" value="<?= e((string) ($_POST['target_balance'] ?? $currentAdjustmentBalance)) ?>" required>
                            </div>
                            <div class="form-field mt-5">
                                <label for="reason">Reason (Optional)</label>
                                <input id="reason" name="reason" data-uppercase maxlength="255"
                                    value="<?= e((string) ($_POST['reason'] ?? '')) ?>" placeholder="BALANCE RECONCILIATION">
                            </div>
                            <button class="mt-6 w-full rounded-xl bg-brand px-4 py-3.5 text-sm font-bold text-white hover:bg-brand-dark"
                                type="submit">Save Balance Adjustment</button>
                            <a class="mt-3 block text-center text-sm font-bold text-muted hover:text-ink" href="wallets.php">Cancel</a>
                        </form>
                        <div class="mt-8 border-t border-line pt-5">
                            <h3 class="font-extrabold">Adjustment History</h3>
                            <?php if (!$adjustmentHistory): ?>
                                <p class="mt-3 text-sm text-muted">No balance adjustments yet.</p>
                            <?php else: ?>
                                <div class="mt-3 space-y-3">
                                    <?php foreach ($adjustmentHistory as $adjustment): ?>
                                        <div class="flex items-start justify-between gap-3 text-sm">
                                            <div>
                                                <p class="font-bold"><?= e($adjustment['reason'] ?: 'BALANCE ADJUSTMENT') ?></p>
                                                <p class="text-xs text-muted"><?= e($adjustment['adjustment_date']) ?></p>
                                            </div>
                                            <p class="font-bold tabular-nums <?= (float) $adjustment['amount_change'] < 0 ? 'text-danger' : 'text-brand' ?>">
                                                <?= (float) $adjustment['amount_change'] > 0 ? '+' : '' ?><?= format_peso((string) $adjustment['amount_change']) ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <h2 class="text-lg font-extrabold"><?= $editingWallet ? 'EDIT WALLET' : 'ADD A WALLET' ?></h2>
                    <p class="mt-1 text-sm text-muted">Balances are shown in Philippine Peso.</p>
                    <form class="mt-6" action="wallets.php" method="post"><input type="hidden" name="csrf_token"
                            value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                            value="<?= $editingWallet ? 'update' : 'create' ?>"><?php if ($editingWallet): ?><input
                                type="hidden" name="wallet_id" value="<?= e($walletForm['id']) ?>"><?php endif; ?><div
                            class="form-field"><label for="wallet_name">Wallet Name</label><input id="wallet_name"
                                name="wallet_name" data-uppercase value="<?= e($walletForm['wallet_name']) ?>" placeholder="My GCash"
                                required></div>
                        <div class="form-field mt-5"><label for="provider_name">Provider</label><input
                                id="provider_name" name="provider_name" data-uppercase value="<?= e($walletForm['provider_name']) ?>"
                                list="wallet-providers" placeholder="GCash or your bank" required><datalist
                                id="wallet-providers"><?php foreach (WALLET_PROVIDERS as $provider): ?><option
                                        value="<?= e($provider) ?>"><?php endforeach; ?></datalist></div>
                        <div class="form-field mt-5"><label for="wallet_type">Wallet Type</label><select
                                class="w-full rounded-xl border border-line px-4 py-3.5 text-sm outline-none focus:border-brand focus:ring-4 focus:ring-brand/10"
                                id="wallet_type" name="wallet_type"
                                required><?php foreach (WALLET_TYPES as $value => $label): ?><option
                                        value="<?= e($value) ?>"
                                        <?= $walletForm['wallet_type'] === $value ? 'selected' : '' ?>><?= e($label) ?>
                                    </option><?php endforeach; ?></select></div>
                        <?php if ($editingWallet): ?>
                            <div class="form-field mt-5"><label>Opening Balance · Historical</label>
                                <div class="rounded-xl border border-line bg-sand px-4 py-3.5 text-sm font-bold">
                                    <?= format_peso((string) $editingWallet['opening_balance']) ?>
                                </div>
                                <p class="mt-2 text-xs text-muted">The original opening balance is kept as a permanent record.</p>
                            </div>
                        <?php else: ?>
                            <div class="form-field mt-5"><label for="opening_balance">Opening Balance</label><input
                                    id="opening_balance" name="opening_balance" type="text" inputmode="decimal"
                                    value="<?= e($walletForm['opening_balance']) ?>" placeholder="0.00" required></div>
                        <?php endif; ?>
                        <button
                            class="mt-6 w-full rounded-xl bg-brand px-4 py-3.5 text-sm font-bold text-white hover:bg-brand-dark"
                            type="submit"><?= $editingWallet ? 'Save Changes' : 'Add Wallet' ?></button><?php if ($editingWallet): ?><a
                                class="mt-3 block text-center text-sm font-bold text-muted hover:text-ink"
                                href="wallets.php">Cancel Editing</a><?php endif; ?>
                    </form>
                    <?php endif; ?>
                </section>
            </section>
            <?php if ($archivedWallets): ?><section class="mt-10">
                    <h2 class="text-lg font-extrabold">Archived wallets</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($archivedWallets as $wallet): ?><article
                                class="rounded-2xl border border-line bg-white/60 p-5">
                                <p class="font-bold"><?= e($wallet['wallet_name']) ?></p>
                                <p class="mt-1 text-sm text-muted"><?= e($wallet['provider_name']) ?> ·
                                    <?= format_peso((string) $wallet['current_balance']) ?></p>
                                <form class="mt-4" action="wallets.php" method="post"><input type="hidden" name="csrf_token"
                                        value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                                        value="reactivate"><input type="hidden" name="wallet_id"
                                        value="<?= (int) $wallet['id'] ?>"><button
                                        class="text-sm font-bold text-brand hover:text-brand-dark" type="submit">Restore
                                        Wallet</button></form>
                            </article><?php endforeach; ?></div>
                </section><?php endif; ?>
        </main>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; ?>