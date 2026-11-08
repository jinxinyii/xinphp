<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/finance.php';

$userId = current_user_id(); $pdo = database(); $errors = []; $flash = get_flash();
$selectedMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$form = ['id'=>'','category_id'=>'','amount'=>'','month'=>$selectedMonth]; $editing = null;
function owned_budget(PDO $pdo, int $id, int $userId): ?array { $s=$pdo->prepare('SELECT id, category_id, amount, budget_month FROM budgets WHERE id=:id AND user_id=:user_id'); $s->execute(['id'=>$id,'user_id'=>$userId]); return $s->fetch() ?: null; }
function budget_amount_valid(string $amount): bool { return (bool) preg_match('/^\d{1,12}(?:\.\d{1,2})?$/',$amount) && (float)$amount > 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action=(string)($_POST['action']??'');
    if (!verify_csrf_token($_POST['csrf_token']??null)) $errors[]='Your form expired. Please try again.';
    elseif ($action==='delete') { $id=filter_var($_POST['budget_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id || !owned_budget($pdo,$id,$userId)) $errors[]='That budget was not found.'; else { $s=$pdo->prepare('DELETE FROM budgets WHERE id=:id AND user_id=:user_id'); $s->execute(['id'=>$id,'user_id'=>$userId]); set_flash('success','Budget deleted.'); redirect('budgets.php?month='.urlencode($selectedMonth)); } }
    elseif (in_array($action,['create','update'],true)) {
        $form=['id'=>(string)($_POST['budget_id']??''),'category_id'=>(string)($_POST['category_id']??''),'amount'=>trim((string)($_POST['amount']??'')),'month'=>(string)($_POST['budget_month']??'')];
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$form['month'])) $errors[]='Choose a valid budget month.';
        if (!budget_amount_valid($form['amount'])) $errors[]='Budget amount must be greater than zero.';
        $categoryId=filter_var($form['category_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $category=$categoryId ? find_owned_category($pdo,$categoryId,$userId) : null;
        if (!$category || $category['type']!=='expense' || $category['is_archived']) $errors[]='Choose one of your active expense categories.';
        $id=$action==='update' ? filter_var($form['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : null;
        if ($action==='update' && (!$id || !owned_budget($pdo,$id,$userId))) $errors[]='That budget was not found.';
        if (!$errors) try { if ($action==='create') { $s=$pdo->prepare("INSERT INTO budgets (user_id,category_id,category_type,budget_month,amount) VALUES (:user_id,:category_id,'expense',:month,:amount)"); $s->execute(['user_id'=>$userId,'category_id'=>$categoryId,'month'=>$form['month'].'-01','amount'=>$form['amount']]); set_flash('success','Monthly budget added.'); } else { $s=$pdo->prepare("UPDATE budgets SET category_id=:category_id, category_type='expense', budget_month=:month, amount=:amount WHERE id=:id AND user_id=:user_id"); $s->execute(['category_id'=>$categoryId,'month'=>$form['month'].'-01','amount'=>$form['amount'],'id'=>$id,'user_id'=>$userId]); set_flash('success','Budget updated.'); } redirect('budgets.php?month='.urlencode($form['month'])); } catch (PDOException $e) { $errors[]=$e->getCode()==='23000'?'A budget already exists for this category and month.':'We could not save your budget.'; }
    }
}
$cs=$pdo->prepare("SELECT id,name FROM categories WHERE user_id=:user_id AND type='expense' AND is_archived=0 ORDER BY name"); $cs->execute(['user_id'=>$userId]); $categories=$cs->fetchAll();
if (isset($_GET['edit'])) { $id=filter_var($_GET['edit'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $editing=$id?owned_budget($pdo,$id,$userId):null; if (!$editing) { set_flash('error','That budget was not found.'); redirect('budgets.php?month='.urlencode($selectedMonth)); } $form=['id'=>(string)$editing['id'],'category_id'=>(string)$editing['category_id'],'amount'=>(string)$editing['amount'],'month'=>substr($editing['budget_month'],0,7)]; }
$start=$selectedMonth.'-01'; $end=(new DateTimeImmutable($start))->modify('first day of next month')->format('Y-m-d');
$s=$pdo->prepare("SELECT b.id,b.amount,c.name,CAST(COALESCE(SUM(t.amount),0) AS DECIMAL(14,2)) spent FROM budgets b JOIN categories c ON c.id=b.category_id AND c.user_id=b.user_id LEFT JOIN transactions t ON t.category_id=b.category_id AND t.user_id=b.user_id AND t.type='expense' AND t.transaction_date>=:start AND t.transaction_date<:end WHERE b.user_id=:user_id AND b.budget_month=:month GROUP BY b.id,b.amount,c.name ORDER BY c.name"); $s->execute(['start'=>$start,'end'=>$end,'user_id'=>$userId,'month'=>$start]); $budgets=$s->fetchAll();
$pageTitle='xinPHP | Budgets'; require __DIR__.'/includes/header.php';
?>

<body class="min-h-screen bg-sand text-ink">
    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Spending limits</p>
                <h1 class="mt-2 text-4xl font-extrabold tracking-[-.05em]">Monthly budgets</h1>
                <p class="mt-2 text-sm text-muted">Set limits for expense categories and monitor progress.</p>
            </div><a class="rounded-xl border border-line px-4 py-3 text-sm font-bold" href="dashboard.php">Back to
                dashboard</a>
        </div><?php if($flash): ?><div
            class="alert <?= $flash['type']==='success'?'alert-success':'alert-error' ?> mt-6">
            <?= e($flash['message']) ?></div><?php endif; ?><?php if($errors): ?><div class="alert alert-error mt-6">
            <ul class="list-inside list-disc"><?php foreach($errors as $error): ?><li><?= e($error) ?></li>
                <?php endforeach; ?></ul>
        </div><?php endif; ?><form class="mt-7 flex items-end gap-3" method="get"><label
                class="text-sm font-bold">Review month<input
                    class="mt-1 block rounded-xl border border-line px-3 py-2 font-normal" type="month" name="month"
                    value="<?= e($selectedMonth) ?>"></label><button
                class="rounded-xl border border-line px-4 py-2 text-sm font-bold">View</button></form>
        <section class="mt-6 rounded-2xl border border-line bg-white p-6 shadow-sm">
            <h2 class="font-extrabold"><?= $editing?'Edit budget':'Add budget' ?></h2>
            <form class="mt-5 grid gap-4 sm:grid-cols-3" method="post"><input type="hidden" name="csrf_token"
                    value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                    value="<?= $editing?'update':'create' ?>"><?php if($editing): ?><input type="hidden"
                    name="budget_id" value="<?= e($form['id']) ?>"><?php endif; ?><label
                    class="text-sm font-bold">Month<input
                        class="mt-1 block w-full rounded-xl border border-line px-3 py-2 font-normal" type="month"
                        name="budget_month" value="<?= e($form['month']) ?>" required></label><label
                    class="text-sm font-bold">Expense category<select
                        class="mt-1 block w-full rounded-xl border border-line px-3 py-2 font-normal" name="category_id"
                        required>
                        <option value="">Select</option><?php foreach($categories as $c): ?><option
                            value="<?= (int)$c['id'] ?>" <?= $form['category_id']===(string)$c['id']?'selected':'' ?>>
                            <?= e($c['name']) ?></option><?php endforeach; ?>
                    </select></label><label class="text-sm font-bold">Amount (PHP)<input
                        class="mt-1 block w-full rounded-xl border border-line px-3 py-2 font-normal" name="amount"
                        value="<?= e($form['amount']) ?>" inputmode="decimal" required></label><button
                    class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white hover:bg-brand-dark"
                    <?= !$categories?'disabled':'' ?>><?= $editing?'Save changes':'Add budget' ?></button><?php if($editing): ?><a
                    class="inline-flex items-center justify-center rounded-xl bg-rose-100 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-200 text-muted"
                    href="budgets.php?month=<?= e($selectedMonth) ?>">Cancel</a><?php endif; ?></form>
        </section>
        <section class="mt-10">
            <h2 class="text-xl font-extrabold"><?= e((new DateTimeImmutable($start))->format('F Y')) ?> progress</h2>
            <?php if(!$budgets): ?><div
                class="mt-4 rounded-2xl border border-dashed border-line bg-white p-8 text-center">
                <p class="font-bold">No budgets for this month</p>
                <p class="mt-2 text-sm text-muted">Create an expense category in Transactions, then add a budget.</p>
            </div><?php else: ?><div class="mt-4 grid gap-4 md:grid-cols-2">
                <?php foreach($budgets as $b): ?><?php $a=(float)$b['amount'];$spent=(float)$b['spent'];$pct=$a?($spent/$a)*100:0;$remain=$a-$spent;$state=$pct>=100?'exceeded':($pct>=80?'approaching':'within');$bar=$state==='exceeded'?'bg-rose-500':($state==='approaching'?'bg-amber-500':'bg-brand'); ?>
                <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                    <div class="flex justify-between gap-3">
                        <div>
                            <h3 class="font-extrabold"><?= e($b['name']) ?></h3>
                            <p class="mt-1 text-sm text-muted">Budget <?= format_peso((string)$b['amount']) ?> · Spent
                                <?= format_peso((string)$b['spent']) ?></p>
                        </div><span
                            class="text-xs font-bold <?= $state==='exceeded'?'text-rose-700':($state==='approaching'?'text-amber-700':'text-brand') ?>"><?= $state==='exceeded'?'Exceeded':($state==='approaching'?'Approaching':'Within budget') ?></span>
                    </div>
                    <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-line">
                        <div class="h-full <?= $bar ?>" style="width:<?= min($pct,100) ?>%"></div>
                    </div>
                    <div class="mt-3 flex justify-between text-sm"><span><?= number_format($pct,0) ?>%
                            used</span><strong
                            class="<?= $remain<0?'text-rose-700':'text-brand' ?>"><?= $remain<0?'Over by '.format_peso((string)abs($remain)):format_peso((string)$remain).' remaining' ?></strong>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <a class="inline-flex items-center justify-center rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white transition hover:bg-brand/90"
                            href="budgets.php?month=<?= e($selectedMonth) ?>&edit=<?= (int)$b['id'] ?>">
                            Edit
                        </a>
                        <form method="post" onsubmit="return confirm('Delete this budget?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="budget_id" value="<?= (int)$b['id'] ?>">
                            <button
                                class="inline-flex items-center justify-center rounded-xl bg-rose-100 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-200"
                                type="submit">
                                Delete
                            </button>
                        </form>
                    </div>
                </article><?php endforeach; ?></div><?php endif; ?>
        </section>
    </main><?php require __DIR__.'/includes/footer.php'; ?>