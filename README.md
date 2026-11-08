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
