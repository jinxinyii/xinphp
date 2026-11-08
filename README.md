# XinPHP

A personal PHP budget-tracker starter application built with PHP, MySQL, PDO, and Tailwind CSS v4.

## Local setup

1. Start **Apache** and **MySQL** in XAMPP.
2. In phpMyAdmin, import `database/schema.sql` to create the `xinphp_db` database and `users` table.
3. Configure database environment variables if your local MySQL setup differs from the XAMPP defaults:
   - `DB_HOST` (default: `127.0.0.1`)
   - `DB_PORT` (default: `3306`)
   - `DB_NAME` (default: `xinphp_db`)
   - `DB_USER` (default: `root`)
   - `DB_PASSWORD` (default: empty)
4. Build the stylesheet with the official Tailwind CLI:

   ```powershell
   npx @tailwindcss/cli -i ./src/input.css -o ./assets/css/output.css --watch
   ```

   The project also includes equivalent npm scripts when `npm` is available:

   ```powershell
   npm run build:css
   # or: npm run watch:css
   ```

5. Open `http://localhost/xinphp/` in your browser.

Never commit real database passwords. Use environment variables or an ignored `.env` file for local credentials.

## Database imports and migrations

For a fresh installation, open phpMyAdmin, select the **Import** tab, choose `database/schema.sql`, and click **Import**.

For a database created from XinPHP's earlier schema, import `database/migrations/001_refine_financial_schema.sql` once. It is an upgrade script, not a fresh-install script; do not run it twice. Back up a populated database before applying any future migration.

The separate `002_add_user_profile_fields.sql` migration keeps the old `full_name` column so existing accounts are not lost. On a populated database, manually fill each account's first name, last name, age, and birthdate before a later migration makes those new columns mandatory.

## Useful reporting queries

Wallet balance (opening balance + income - expenses + transfers in - transfers out):

```sql
SELECT w.id, w.wallet_name,
       w.opening_balance
       + COALESCE((SELECT SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END)
                   FROM transactions t WHERE t.wallet_id = w.id AND t.user_id = w.user_id), 0)
       + COALESCE((SELECT SUM(CASE WHEN tr.destination_wallet_id = w.id THEN tr.amount ELSE -tr.amount END)
                   FROM transfers tr
                   WHERE tr.user_id = w.user_id
                     AND (tr.source_wallet_id = w.id OR tr.destination_wallet_id = w.id)), 0) AS current_balance
FROM wallets w
WHERE w.user_id = :user_id;
```

Monthly expense total:

```sql
SELECT COALESCE(SUM(amount), 0) AS monthly_expenses
FROM transactions
WHERE user_id = :user_id
  AND type = 'expense'
  AND transaction_date >= :month_start
  AND transaction_date < :next_month_start;
```
