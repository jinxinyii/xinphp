USE xinphp_db;

UPDATE wallets
SET provider_name = UPPER(provider_name),
    wallet_name = UPPER(wallet_name);

UPDATE categories
SET name = UPPER(name);

UPDATE transactions
SET description = UPPER(description),
    notes = CASE WHEN notes IS NULL THEN NULL ELSE UPPER(notes) END;

UPDATE transfers
SET description = UPPER(description),
    notes = CASE WHEN notes IS NULL THEN NULL ELSE UPPER(notes) END;

UPDATE savings_goals
SET goal_name = UPPER(goal_name),
    notes = CASE WHEN notes IS NULL THEN NULL ELSE UPPER(notes) END;

UPDATE savings_contributions
SET notes = CASE WHEN notes IS NULL THEN NULL ELSE UPPER(notes) END;

UPDATE recurring_transactions
SET description = UPPER(description),
    notes = CASE WHEN notes IS NULL THEN NULL ELSE UPPER(notes) END;
