<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter a valid email address and password.';
    } else {
        try {
            $statement = database()->prepare(
                "SELECT id,
                        COALESCE(first_name, full_name, '') AS first_name,
                        middle_name,
                        COALESCE(last_name, '') AS last_name,
                        password
                 FROM users
                 WHERE email = :email
                 LIMIT 1"
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = complete_name($user['first_name'], $user['middle_name'], $user['last_name']);
                redirect('dashboard.php');
            }
        } catch (PDOException|RuntimeException $exception) {
            $errors[] = 'We could not log you in right now. Please check the database setup and try again.';
        }
    }
}

$pageTitle = 'xinPHP | Log in';
require __DIR__ . '/includes/header.php';
?>

<body class="min-h-screen bg-sand text-ink antialiased">
    <main class="grid min-h-screen lg:grid-cols-[1.1fr_.9fr]">
        <section
            class="relative isolate overflow-hidden bg-brand px-6 py-7 text-white sm:px-10 lg:flex lg:min-h-screen lg:flex-col lg:px-[clamp(3rem,8vw,8rem)] lg:py-10">
            <div class="absolute -bottom-72 -right-72 -z-10 size-[46rem] rounded-full border border-white/15"></div>
            <div class="absolute -bottom-24 -right-24 -z-10 size-80 rounded-full border-[2.5rem] border-mint/10"></div>

            <a class="inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="index.php"
                aria-label="xinPHP home">
                <span class="grid size-9 place-items-center rounded-xl bg-mint text-lg text-brand-dark">₱</span>
                xinPHP
            </a>

            <div class="max-w-xl py-16 lg:my-auto lg:py-20">
                <p class="mb-4 text-xs font-extrabold uppercase tracking-[.18em] text-mint">Personal budget tracker</p>
                <h1 class="max-w-lg text-5xl font-extrabold leading-[.98] tracking-[-.065em] sm:text-6xl lg:text-7xl">
                    Spend with purpose. Save with ease.</h1>
                <p class="mt-6 max-w-md text-base leading-7 text-white/80 sm:text-lg">A private home for every peso.
                    Track your daily spending, plan your goals, and stay in control of your money.</p>
            </div>

            <div class="hidden flex-wrap gap-3 lg:flex">
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                    <strong class="block text-base">₱ 0.00</strong>
                    <span class="mt-1 block text-xs text-white/75">Start from any amount</span>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                    <strong class="block text-base">Personal &amp; private</strong>
                    <span class="mt-1 block text-xs text-white/75">Made for everyday life</span>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center bg-white px-6 py-12 sm:px-10">
            <div class="w-full max-w-md">
                <h2 class="text-3xl font-extrabold tracking-[-.045em] text-ink">Welcome back</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Log in to continue keeping your budget on track.</p>

                <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>" role="status">
                    <?= e($flash['message']) ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li>
                        <?php endforeach; ?></ul>
                </div>
                <?php endif; ?>
                <form class="mt-8" action="index.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="form-field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="<?= e($email) ?>"
                            placeholder="you@example.com" autocomplete="email" required>
                    </div>
                    <div class="form-field mt-5">
                        <div class="flex items-center justify-between gap-4">
                            <label for="password">Password</label>
                        </div>
                        <div class="relative"><input id="password" name="password" type="password"
                                placeholder="Enter your password" autocomplete="current-password" required><button
                                class="toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-brand hover:text-brand-dark"
                                type="button" aria-controls="password" aria-label="Show password">Show</button></div>
                    </div>
                    <button
                        class="mt-6 w-full rounded-xl bg-brand px-4 py-3.5 font-bold text-white shadow-lg shadow-brand/15 transition hover:-translate-y-0.5 hover:bg-brand-dark focus:outline-none focus:ring-4 focus:ring-brand/20"
                        type="submit">Log in to xinPHP</button>
                </form>

                <div
                    class="my-7 flex items-center gap-3 text-xs text-muted/70 before:h-px before:flex-1 before:bg-line after:h-px after:flex-1 after:bg-line">
                    or</div>
                <p class="text-center text-sm text-muted">New to xinPHP? <a
                        class="font-bold text-brand hover:text-brand-dark" href="register.php">Create your account</a>
                </p>
                <p class="mt-10 text-center text-xs text-muted/75">⌁ Your personal budget stays private and secure</p>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>