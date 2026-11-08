<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();

$pageTitle = 'xinPHP | Dashboard';
require __DIR__ . '/includes/header.php';
?>

<body class="min-h-screen bg-sand text-ink">
    <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr]">
        <aside class="bg-brand px-6 py-7 text-white md:min-h-screen">
            <a class="inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="dashboard.php"><span
                    class="grid size-9 place-items-center rounded-xl bg-mint text-lg text-brand-dark">₱</span>xinPHP</a>
            <nav class="mt-10 flex gap-2 overflow-x-auto md:flex-col" aria-label="Main navigation">
                <a class="rounded-xl bg-white/15 px-4 py-3 text-sm font-bold" href="dashboard.php">Overview</a>
                <a class="rounded-xl px-4 py-3 text-sm text-white/70 hover:bg-white/10" href="wallets.php">Wallets</a>
                <a class="rounded-xl px-4 py-3 text-sm text-white/70 hover:bg-white/10"
                    href="transactions.php">Transactions</a>
                <span class="rounded-xl px-4 py-3 text-sm text-white/65">Budgets <small
                        class="ml-1 text-xs">Soon</small></span>
            </nav>
            <form class="mt-8 md:mt-auto" action="logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="text-sm font-bold text-mint hover:text-white" type="submit">Log out</button>
            </form>
        </aside>
        <main class="px-6 py-10 sm:px-10 lg:px-14">
            <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Your overview</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-[-.05em]">Welcome back,
                <?= e(ucwords(strtolower($_SESSION['user_name'] ?? 'there'))) ?>.</h1>
            <p class="mt-3 max-w-xl leading-7 text-muted">Your dashboard is ready. Start adding income and expenses to
                see a clear picture of your budget.</p>
            <section class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Budget overview">
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-muted">This month</p>
                    <p class="mt-4 text-3xl font-extrabold tracking-tight">₱0.00</p>
                    <p class="mt-2 text-xs text-muted">No transactions yet</p>
                </article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-muted">Income</p>
                    <p class="mt-4 text-3xl font-extrabold tracking-tight text-brand">₱0.00</p>
                    <p class="mt-2 text-xs text-muted">Add your first income soon</p>
                </article>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
                    <p class="text-sm font-bold text-muted">Expenses</p>
                    <p class="mt-4 text-3xl font-extrabold tracking-tight">₱0.00</p>
                    <p class="mt-2 text-xs text-muted">Stay within your plan</p>
                </article>
            </section>
        </main>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; ?>