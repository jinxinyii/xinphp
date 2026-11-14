# XinPHP

A personal finance and budget-tracking web application built with **PHP**, **MySQL**, **PDO**, **JavaScript**, and **Tailwind CSS v4**.

XinPHP is designed to help users organize their wallets, record income and expenses, transfer money between wallets, and monitor monthly budgets from one dashboard. The project is currently under development.

## Features

- **Financial dashboard** – View financial summaries and recent activity.
- **Wallet management** – Organize balances across cash, e-wallets, and bank accounts.
- **Transaction management** – Record and manage income and expenses.
- **Wallet transfers** – Track money moved between wallets separately from income and expenses.
- **Budget management** – Set and monitor monthly budgets.
- **User accounts** – Register and sign in to a personal account.

Feature availability may change as development continues.

## Technology stack

- PHP
- MySQL
- PDO for database access
- HTML and JavaScript
- Tailwind CSS v4
- XAMPP for local development

## Requirements

- Windows, macOS, or Linux
- PHP with the PDO MySQL extension enabled
- MySQL or MariaDB
- Apache or another PHP-compatible web server
- Node.js and npm for building Tailwind CSS
- A modern web browser

## Local installation

### 1. Get the project

Clone the repository or download the source code, then place the project folder in your local web server's document root.

For XAMPP on Windows, the usual location is:

```text
C:\xampp\htdocs\xinphp
```

### 2. Start XAMPP

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

### 3. Create the database

1. Open `http://localhost/phpmyadmin/`.
2. Select the **Import** tab.
3. Choose `database/schema.sql`.
4. Start the import.

The default database name is `xinphp_db`.

### 4. Configure the database connection

XinPHP reads the following environment variables and uses the listed local defaults when they are not set:

| Variable | Default |
|---|---|
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `xinphp_db` |
| `DB_USER` | `root` |
| `DB_PASSWORD` | Empty |

Update the values to match your local MySQL configuration. Do not commit real database passwords or other secrets to GitHub. Keep local environment files out of version control.

### 5. Install frontend dependencies

From the project directory, install the npm dependencies:

```powershell
npm install
```

### 6. Build Tailwind CSS

To build the stylesheet once:

```powershell
npm run build:css
```

To rebuild the stylesheet automatically while developing:

```powershell
npm run watch:css
```

The generated stylesheet is written to `assets/css/output.css`.

### 7. Open XinPHP

Visit:

```text
http://localhost/xinphp/
```

## Database migrations

`database/schema.sql` is the schema to use for a fresh installation.

If you are upgrading a database created from an earlier XinPHP schema, review the migration files in `database/migrations/` and apply only the migration appropriate for that database version.

- `001_refine_financial_schema.sql` is an upgrade migration. It is not a fresh-install script and should not be run more than once on the same database.
- `002_add_user_profile_fields.sql` adds profile fields while retaining the legacy `full_name` column for existing accounts. For an existing populated database, fill in the required profile information before applying any later migration that makes those fields mandatory.

**Back up your database before applying migrations.** Do not import the fresh-install schema over a database that contains data you want to keep.

## Project structure

```text
xinphp/
├── assets/
│   ├── css/
│   │   └── output.css
│   ├── images/
│   └── js/
│       └── app.js
├── config/
│   └── database.php
├── database/
│   ├── migrations/
│   └── schema.sql
├── includes/
│   ├── auth.php
│   ├── finance.php
│   ├── footer.php
│   └── header.php
├── src/
│   └── input.css
├── budgets.php
├── dashboard.php
├── index.php
├── logout.php
├── register.php
├── transactions.php
├── transfers.php
├── wallets.php
├── package.json
└── README.md
```

## Financial calculation notes

- Wallet balances account for the opening balance, income, expenses, incoming transfers, and outgoing transfers.
- Transfers between a user's own wallets are tracked separately; they should not be counted as income or expenses.
- Monthly expense totals include expense transactions within the selected month.

## Security considerations

XinPHP handles personal financial information. Before deploying it to a public hosting service:

- Use unique, strong database credentials and keep secrets out of the repository.
- Use HTTPS.
- Ensure database queries use prepared statements.
- Enforce account ownership checks on every operation involving a user's financial records.
- Protect state-changing requests against CSRF and escape output before displaying user-provided values.
- Disable public error display and log errors privately.
- Back up the database regularly.
- Test access controls with separate user accounts before allowing other people to use the application.

A local development setup is not automatically production-ready. Review the hosting provider's PHP, MySQL, HTTPS, configuration, and backup capabilities before deploying real financial data.

## Development status

XinPHP is an ongoing project. Features, database structure, and deployment instructions may change as development progresses.

## License

No license has been specified yet. Unless a license is added to this repository, the default copyright rules apply and others should not assume they have permission to reuse, modify, or distribute the project.
