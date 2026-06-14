SELECT 'users' AS tbl, COUNT(*) AS cnt FROM users
UNION ALL SELECT 'budget_months', COUNT(*) FROM budget_months
UNION ALL SELECT 'incomes', COUNT(*) FROM incomes
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses
UNION ALL SELECT 'categories', COUNT(*) FROM categories;
