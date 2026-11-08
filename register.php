<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form expired. Please try again.';
    }
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Your full name must be between 2 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Your password must be at least 8 characters long.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Your password confirmation does not match.';
    }

    if (!$errors) {
        try {
            $statement = database()->prepare('INSERT INTO users (full_name, email, password) VALUES (:full_name, :email, :password)');
            $statement->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            set_flash('success', 'Account created. You can now log in.');
            redirect('index.php');
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000'
                ? 'An account with that email address already exists.'
                : 'We could not create your account right now. Please try again.';
        } catch (RuntimeException $exception) {
            $errors[] = 'We could not connect to the database. Check the database setup and try again.';
        }
    }
}

$pageTitle = 'xinPHP | Create account';
require __DIR__ . '/includes/header.php';
?>
<body class="min-h-screen bg-sand px-5 py-8 text-ink sm:grid sm:place-items-center">
    <main class="mx-auto w-full max-w-md">
        <a class="mb-8 inline-flex items-center gap-3 text-xl font-extrabold tracking-tight text-brand" href="index.php"><span class="grid size-9 place-items-center rounded-xl bg-brand text-lg text-mint">₱</span>xinPHP</a>
        <section class="auth-card">
            <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Start tracking</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-[-.045em]">Create your account</h1>
            <p class="mt-2 text-sm leading-6 text-muted">Set up your private space for every peso.</p>
            <?php if ($errors): ?>
                <div class="alert alert-error mt-6" role="alert"><ul class="list-inside list-disc"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form class="mt-7" action="register.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" type="text" value="<?= e($fullName) ?>" autocomplete="name" required></div>
                <div class="form-field mt-5"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required></div>
                <div class="form-field mt-5"><label for="password">Password</label><div class="relative"><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required><button class="toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-brand hover:text-brand-dark" type="button" aria-controls="password" aria-label="Show password">Show</button></div></div>
                <div class="form-field mt-5"><label for="password_confirmation">Confirm password</label><div class="relative"><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required><button class="toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-brand hover:text-brand-dark" type="button" aria-controls="password_confirmation" aria-label="Show password">Show</button></div></div>
                <button class="mt-7 w-full rounded-xl bg-brand px-4 py-3.5 font-bold text-white shadow-lg shadow-brand/15 transition hover:-translate-y-0.5 hover:bg-brand-dark focus:outline-none focus:ring-4 focus:ring-brand/20" type="submit">Create account</button>
            </form>
            <p class="mt-7 text-center text-sm text-muted">Already have an account? <a class="font-bold text-brand hover:text-brand-dark" href="index.php">Log in</a></p>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
