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
$budgetMonthInput = (string) ($_GET['budget_month'] ?? $today->format('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $budgetMonthInput)) $budgetMonthInput = $today->format('Y-m');
$budgetOverviewMonth = $budgetMonthInput . '-01';
$budgetOverview = monthly_budget_progress($pdo, $userId, $budgetOverviewMonth);

$pageTitle = 'xinPHP | Dashboard';
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
            <button class="sidebar-toggle mt-6 inline-flex w-full items-center justify-between rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10 md:hidden" type="button" aria-expanded="false" aria-controls="dashboard-navigation"><span>Navigation</span><span class="sidebar-toggle-arrow transition-transform" aria-hidden="true">⌄</span></button>
            <nav id="dashboard-navigation" class="mt-3 hidden gap-2 pb-2 md:mt-10 md:flex md:flex-1 md:flex-col md:pb-0"
                aria-label="Main navigation">
                <a class="shrink-0 rounded-xl bg-white/15 px-4 py-3 text-sm font-bold"
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
            <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Your overview</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-[-.05em]">Welcome back,
                <?= e(ucwords(strtolower($_SESSION['user_name'] ?? 'there'))) ?>.</h1>
            <p class="mt-3 max-w-xl leading-7 text-muted">Here is a clear view of your money for
                <?= e($today->format('F Y')) ?>.</p>
            <section class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Financial summary">
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-muted">Total balance</p>
                    <p class="mt-4 text-3xl font-extrabold tracking-tight"><?= format_peso($summary['total_balance']) ?>
                    </p>
                    <p class="mt-2 text-xs text-muted">Across active wallets</p>
                </article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-muted">Income</p>
                    <p class="mt-4 text-3xl font-extrabold tracking-tight text-brand">
                        <?= format_peso($summary['monthly_income']) ?></p>
                    <p class="mt-2 text-xs text-muted">This month</p>
                </article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-muted">Expenses</p>
                    <p class="mt-4 text-3xl font-extrabold tracking-tight text-rose-700">
                        <?= format_peso($summary['monthly_expenses']) ?></p>
                    <p class="mt-2 text-xs text-muted">This month</p>
                </article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-muted">Monthly savings</p>
                    <p
                        class="mt-4 text-3xl font-extrabold tracking-tight <?= (float) $summary['monthly_savings'] < 0 ? 'text-rose-700' : 'text-brand' ?>">
                        <?= format_peso($summary['monthly_savings']) ?></p>
                    <p class="mt-2 text-xs text-muted">Income minus expenses</p>
                </article>
            </section>
            <section class="mt-10">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-extrabold tracking-tight">Monthly Budget Overview</h2>
                        <p class="mt-1 text-sm text-muted">Expense limits and spending progress.</p>
                    </div>
                    <div class="flex items-end gap-3">
                        <form method="get" action="dashboard.php"><label
                                class="block text-xs font-bold text-muted">Month<input
                                    class="mt-1 block rounded-xl border border-line bg-white px-3 py-2 text-sm font-normal text-ink"
                                    name="budget_month" type="month" value="<?= e($budgetMonthInput) ?>"></label></form>
                        <a class="rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-dark"
                            href="budgets.php">Manage Budgets</a>
                    </div>
                </div>
                <?php if (!$budgetOverview): ?>
                <div class="mt-5 rounded-2xl border border-dashed border-line bg-white p-8 text-center">
                    <p class="font-bold">No budgets for this month</p>
                    <p class="mt-2 text-sm text-muted">Create category spending limits from the Budgets page.</p><a
                        class="mt-4 inline-block text-sm font-bold text-brand hover:text-brand-dark"
                        href="budgets.php">Manage Budgets</a>
                </div>
                <?php else: ?><div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <?php foreach ($budgetOverview as $budget): ?><?php $allocated=(float)$budget['amount']; $spent=(float)$budget['spent']; $remaining=$allocated-$spent; $percentage=$allocated>0?($spent/$allocated)*100:0; $state=$percentage>=100?'exceeded':($percentage>=80?'approaching':'within'); $bar=$state==='exceeded'?'bg-rose-500':($state==='approaching'?'bg-amber-500':'bg-brand'); ?>
                    <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-extrabold"><?= e($budget['category_name']) ?></h3>
                                <p class="mt-1 text-sm text-muted">Allocated
                                    <?= format_peso((string)$budget['amount']) ?> · Spent
                                    <?= format_peso((string)$budget['spent']) ?></p>
                            </div><span
                                class="shrink-0 text-xs font-bold <?= $state==='exceeded'?'text-rose-700':($state==='approaching'?'text-amber-700':'text-brand') ?>"><?= $state==='exceeded'?'Exceeded':($state==='approaching'?'Approaching':'Within budget') ?></span>
                        </div>
                        <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-line">
                            <div class="h-full rounded-full <?= $bar ?>" style="width:<?= min($percentage,100) ?>%">
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm"><span
                                class="text-muted"><?= number_format($percentage,0) ?>% used</span><strong
                                class="<?= $remaining<0?'text-rose-700':'text-brand' ?>"><?= $remaining<0?'Over by '.format_peso((string)abs($remaining)):format_peso((string)$remaining).' remaining' ?></strong>
                        </div>
                    </article><?php endforeach; ?></div><?php endif; ?>
            </section>
            <section class="mt-10">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-extrabold tracking-tight">Recent transactions</h2>
                        <p class="mt-1 text-sm text-muted">Your five latest income and expense entries.</p>
                    </div><a class="text-sm font-bold text-brand hover:text-brand-dark" href="transactions.php">View all
                        transactions</a>
                </div><?php if (!$recentTransactions): ?><div
                    class="mt-5 rounded-2xl border border-dashed border-line bg-white p-8 text-center">
                    <p class="font-bold">No transactions yet</p>
                    <p class="mt-2 text-sm text-muted">Add your first income or expense to see it here.</p><a
                        class="mt-4 inline-block text-sm font-bold text-brand hover:text-brand-dark"
                        href="transactions.php#transaction-form">Add transaction</a>
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
                            </tr>
                        </thead>
                        <tbody><?php foreach ($recentTransactions as $transaction): ?><tr
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
                            </tr><?php endforeach; ?></tbody>
                    </table>
                </div><?php endif; ?>
            </section>
        </main>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; ?>
