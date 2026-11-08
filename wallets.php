<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/finance.php';

$userId = current_user_id();
$pdo = database();
$errors = [];
$editingWallet = null;
$walletForm = [
    'id' => '',
    'wallet_name' => '',
    'provider_name' => '',
    'wallet_type' => 'e_wallet',
    'opening_balance' => '0.00',
];

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
            set_flash('success', $action === 'archive' ? 'Wallet archived.' : 'Wallet restored to active wallets.');
            redirect('wallets.php');
        }
    } elseif (in_array($action, ['create', 'update'], true)) {
        $walletForm = [
            'id' => trim((string) ($_POST['wallet_id'] ?? '')),
            'wallet_name' => trim((string) ($_POST['wallet_name'] ?? '')),
            'provider_name' => trim((string) ($_POST['provider_name'] ?? '')),
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
        if (!valid_wallet_amount($walletForm['opening_balance'])) {
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
                             wallet_name = :wallet_name, opening_balance = :opening_balance
                         WHERE id = :wallet_id AND user_id = :user_id'
                    );
                    $statement->execute([
                        'provider_name' => $walletForm['provider_name'],
                        'wallet_type' => $walletForm['wallet_type'],
                        'wallet_name' => $walletForm['wallet_name'],
                        'opening_balance' => $walletForm['opening_balance'],
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

$wallets = user_wallets_with_balances($pdo, $userId);
$activeWallets = array_filter($wallets, static fn (array $wallet): bool => !(bool) $wallet['is_archived']);
$archivedWallets = array_filter($wallets, static fn (array $wallet): bool => (bool) $wallet['is_archived']);
$flash = get_flash();
$pageTitle = 'xinPHP | Wallets';
require __DIR__ . '/includes/header.php';
?>
<body class="min-h-screen bg-sand text-ink">
    <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr]">
        <aside class="bg-brand px-6 py-7 text-white md:min-h-screen">
            <a class="inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="dashboard.php"><span class="grid size-9 place-items-center rounded-xl bg-mint text-lg text-brand-dark">₱</span>xinPHP</a>
            <nav class="mt-10 flex gap-2 overflow-x-auto md:flex-col" aria-label="Main navigation">
                <a class="rounded-xl px-4 py-3 text-sm text-white/70 hover:bg-white/10" href="dashboard.php">Overview</a>
                <a class="rounded-xl bg-white/15 px-4 py-3 text-sm font-bold" href="wallets.php">Wallets</a>
                <a class="rounded-xl px-4 py-3 text-sm text-white/70 hover:bg-white/10" href="transactions.php">Transactions</a>
                <span class="rounded-xl px-4 py-3 text-sm text-white/65">Budgets <small class="ml-1 text-xs">Soon</small></span>
            </nav>
            <form class="mt-8 md:mt-auto" action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="text-sm font-bold text-mint hover:text-white" type="submit">Log out</button></form>
        </aside>
        <main class="px-6 py-10 sm:px-10 lg:px-14">
            <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Your accounts</p><h1 class="mt-2 text-4xl font-extrabold tracking-[-.05em]">Wallets</h1><p class="mt-2 text-sm leading-6 text-muted">Track cash, bank accounts, and e-wallets in one place.</p></div><a class="rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white shadow-lg shadow-brand/15 hover:bg-brand-dark" href="#wallet-form">Add wallet</a></div>
            <?php if ($flash): ?><div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?> mt-7" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
            <?php if ($errors): ?><div class="alert alert-error mt-7" role="alert"><ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <section class="mt-8 grid gap-4 lg:grid-cols-[minmax(0,1fr)_23rem]">
                <div>
                    <h2 class="text-lg font-extrabold">Active wallets</h2>
                    <?php if (!$activeWallets): ?>
                        <div class="mt-4 rounded-2xl border border-dashed border-line bg-white p-8 text-center"><p class="font-bold">No wallets yet</p><p class="mt-2 text-sm text-muted">Add your cash, bank, or e-wallet account to get started.</p></div>
                    <?php else: ?>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <?php foreach ($activeWallets as $wallet): ?>
                                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-muted"><?= e(str_replace('_', ' ', $wallet['wallet_type'])) ?></p><h3 class="mt-1 font-extrabold"><?= e($wallet['wallet_name']) ?></h3><p class="mt-1 text-sm text-muted"><?= e($wallet['provider_name']) ?></p></div><a class="text-sm font-bold text-brand hover:text-brand-dark" href="wallets.php?edit=<?= (int) $wallet['id'] ?>">Edit</a></div><p class="mt-6 text-2xl font-extrabold tracking-tight"><?= format_peso((string) $wallet['current_balance']) ?></p><p class="mt-1 text-xs text-muted">Current balance · PHP</p><form class="mt-5" action="wallets.php" method="post" onsubmit="return confirm('Archive this wallet? Its history will be kept.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="wallet_id" value="<?= (int) $wallet['id'] ?>"><button class="text-xs font-bold text-muted hover:text-ink" type="submit">Archive wallet</button></form></article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <section id="wallet-form" class="rounded-2xl border border-line bg-white p-6 shadow-sm"><h2 class="text-lg font-extrabold"><?= $editingWallet ? 'Edit wallet' : 'Add a wallet' ?></h2><p class="mt-1 text-sm text-muted">Balances are shown in Philippine Peso.</p><form class="mt-6" action="wallets.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $editingWallet ? 'update' : 'create' ?>"><?php if ($editingWallet): ?><input type="hidden" name="wallet_id" value="<?= e($walletForm['id']) ?>"><?php endif; ?><div class="form-field"><label for="wallet_name">Wallet name</label><input id="wallet_name" name="wallet_name" value="<?= e($walletForm['wallet_name']) ?>" placeholder="My GCash" required></div><div class="form-field mt-5"><label for="provider_name">Provider</label><input id="provider_name" name="provider_name" value="<?= e($walletForm['provider_name']) ?>" list="wallet-providers" placeholder="GCash or your bank" required><datalist id="wallet-providers"><?php foreach (WALLET_PROVIDERS as $provider): ?><option value="<?= e($provider) ?>"><?php endforeach; ?></datalist></div><div class="form-field mt-5"><label for="wallet_type">Wallet type</label><select class="w-full rounded-xl border border-line px-4 py-3.5 text-sm outline-none focus:border-brand focus:ring-4 focus:ring-brand/10" id="wallet_type" name="wallet_type" required><?php foreach (WALLET_TYPES as $value => $label): ?><option value="<?= e($value) ?>" <?= $walletForm['wallet_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="form-field mt-5"><label for="opening_balance">Opening balance</label><input id="opening_balance" name="opening_balance" type="text" inputmode="decimal" value="<?= e($walletForm['opening_balance']) ?>" placeholder="0.00" required></div><button class="mt-6 w-full rounded-xl bg-brand px-4 py-3.5 text-sm font-bold text-white hover:bg-brand-dark" type="submit"><?= $editingWallet ? 'Save changes' : 'Add wallet' ?></button><?php if ($editingWallet): ?><a class="mt-3 block text-center text-sm font-bold text-muted hover:text-ink" href="wallets.php">Cancel editing</a><?php endif; ?></form></section>
            </section>
            <?php if ($archivedWallets): ?><section class="mt-10"><h2 class="text-lg font-extrabold">Archived wallets</h2><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($archivedWallets as $wallet): ?><article class="rounded-2xl border border-line bg-white/60 p-5"><p class="font-bold"><?= e($wallet['wallet_name']) ?></p><p class="mt-1 text-sm text-muted"><?= e($wallet['provider_name']) ?> · <?= format_peso((string) $wallet['current_balance']) ?></p><form class="mt-4" action="wallets.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reactivate"><input type="hidden" name="wallet_id" value="<?= (int) $wallet['id'] ?>"><button class="text-sm font-bold text-brand hover:text-brand-dark" type="submit">Restore wallet</button></form></article><?php endforeach; ?></div></section><?php endif; ?>
        </main>
    </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
