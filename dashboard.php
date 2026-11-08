<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/finance.php';

$userId = current_user_id();
$today = new DateTimeImmutable('today');
$monthStart = $today->modify('first day of this month')->format('Y-m-d');
$nextMonthStart = $today->modify('first day of next month')->format('Y-m-d');
$pdo = database();
$summary = financial_dashboard_summary($pdo, $userId, $monthStart, $nextMonthStart);
$recentTransactions = recent_transactions($pdo, $userId);

$pageTitle = 'xinPHP | Dashboard';
require __DIR__ . '/includes/header.php';
?>
<body class="min-h-screen bg-sand text-ink">
    <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr]">
        <aside class="bg-brand px-6 py-7 text-white md:min-h-screen">
            <a class="inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="dashboard.php"><span class="grid size-9 place-items-center rounded-xl bg-mint text-lg text-brand-dark">₱</span>xinPHP</a>
            <nav class="mt-10 flex gap-2 overflow-x-auto md:flex-col" aria-label="Main navigation">
                <a class="rounded-xl bg-white/15 px-4 py-3 text-sm font-bold" href="dashboard.php">Overview</a>
                <a class="rounded-xl px-4 py-3 text-sm text-white/70 hover:bg-white/10" href="wallets.php">Wallets</a>
                <a class="rounded-xl px-4 py-3 text-sm text-white/70 hover:bg-white/10" href="transactions.php">Transactions</a>
                <span class="rounded-xl px-4 py-3 text-sm text-white/65">Budgets <small class="ml-1 text-xs">Soon</small></span>
            </nav>
            <form class="mt-8 md:mt-auto" action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="text-sm font-bold text-mint hover:text-white" type="submit">Log out</button></form>
        </aside>
        <main class="px-6 py-10 sm:px-10 lg:px-14">
            <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Your overview</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-[-.05em]">Welcome back, <?= e(ucwords(strtolower($_SESSION['user_name'] ?? 'there'))) ?>.</h1>
            <p class="mt-3 max-w-xl leading-7 text-muted">Here is a clear view of your money for <?= e($today->format('F Y')) ?>.</p>
            <section class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Financial summary">
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm"><p class="text-sm font-bold text-muted">Total balance</p><p class="mt-4 text-3xl font-extrabold tracking-tight"><?= format_peso($summary['total_balance']) ?></p><p class="mt-2 text-xs text-muted">Across active wallets</p></article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm"><p class="text-sm font-bold text-muted">Income</p><p class="mt-4 text-3xl font-extrabold tracking-tight text-brand"><?= format_peso($summary['monthly_income']) ?></p><p class="mt-2 text-xs text-muted">This month</p></article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm"><p class="text-sm font-bold text-muted">Expenses</p><p class="mt-4 text-3xl font-extrabold tracking-tight text-rose-700"><?= format_peso($summary['monthly_expenses']) ?></p><p class="mt-2 text-xs text-muted">This month</p></article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm"><p class="text-sm font-bold text-muted">Monthly savings</p><p class="mt-4 text-3xl font-extrabold tracking-tight <?= (float) $summary['monthly_savings'] < 0 ? 'text-rose-700' : 'text-brand' ?>"><?= format_peso($summary['monthly_savings']) ?></p><p class="mt-2 text-xs text-muted">Income minus expenses</p></article>
            </section>
            <section class="mt-10"><div class="flex items-end justify-between gap-4"><div><h2 class="text-xl font-extrabold tracking-tight">Recent transactions</h2><p class="mt-1 text-sm text-muted">Your five latest income and expense entries.</p></div><a class="text-sm font-bold text-brand hover:text-brand-dark" href="transactions.php">View all transactions</a></div><?php if (!$recentTransactions): ?><div class="mt-5 rounded-2xl border border-dashed border-line bg-white p-8 text-center"><p class="font-bold">No transactions yet</p><p class="mt-2 text-sm text-muted">Add your first income or expense to see it here.</p><a class="mt-4 inline-block text-sm font-bold text-brand hover:text-brand-dark" href="transactions.php#transaction-form">Add transaction</a></div><?php else: ?><div class="mt-5 overflow-x-auto rounded-2xl border border-line bg-white"><table class="min-w-full text-left text-sm"><thead class="border-b border-line bg-sand text-xs uppercase tracking-wide text-muted"><tr><th class="px-5 py-4">Description</th><th class="px-5 py-4">Category</th><th class="px-5 py-4">Wallet</th><th class="px-5 py-4">Date</th><th class="px-5 py-4">Type</th><th class="px-5 py-4 text-right">Amount</th></tr></thead><tbody><?php foreach ($recentTransactions as $transaction): ?><tr class="border-b border-line last:border-0"><td class="px-5 py-4 font-bold"><?= e($transaction['description']) ?></td><td class="px-5 py-4 text-muted"><?= e($transaction['category_name']) ?></td><td class="px-5 py-4 text-muted"><?= e($transaction['wallet_name']) ?></td><td class="px-5 py-4 text-muted"><?= e($transaction['transaction_date']) ?></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $transaction['type'] === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>"><?= e(ucfirst($transaction['type'])) ?></span></td><td class="px-5 py-4 text-right font-bold <?= $transaction['type'] === 'income' ? 'text-brand' : 'text-rose-700' ?>"><?= $transaction['type'] === 'income' ? '+' : '-' ?><?= format_peso((string) $transaction['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
        </main>
    </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
