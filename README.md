# Budgetman

Personal budgeting app rebuilt from [budget-manager](https://github.com/daflores9096/budget-manager) with a layered architecture inspired by [book-readings](https://github.com/daflores9096/book-readings).

**Stack:** React + Vite frontend, PHP 8.3 API (Controllers / Services / Repositories), MySQL 8, Docker Compose.

---

## Reuse existing database (important)

Budgetman is configured to attach the **existing Docker volume** from budget-manager (`budget-manager_db_data`). Your data is preserved as long as you:

1. **Do not** run `docker compose down -v`
2. Keep the same `MYSQL_DATABASE`, `MYSQL_USER`, and `MYSQL_PASSWORD` as when the volume was first created
3. **Stop budget-manager** before starting budgetman (only one MySQL container may use the volume at a time)

```powershell
# In budget-manager folder
docker compose down

# In budgetman folder
copy .env.example .env
docker compose up --build -d
```

Default URLs:

| Service | URL |
|---------|-----|
| Web app | http://localhost:48080 |
| API health | http://localhost:48080/api/health |

Initial admin for new databases:

| User | Password |
|------|----------|
| `admin` | `Admin.00` |

---

## Project layout

```
budgetman/
├── api/
│   ├── bootstrap.php          # Autoloader
│   ├── public/index.php       # Thin entrypoint
│   └── src/
│       ├── Controllers/
│       ├── Services/
│       ├── Repositories/
│       ├── Middleware/
│       ├── Routes/
│       └── Core/
├── frontend/                  # React + Vite + Tailwind
├── database/                  # schema_tables.sql (init on NEW volumes only)
├── docker/
└── docker-compose.yml
```

---

## Frontend development

```powershell
docker compose up -d
cd frontend
npm install
npm run dev
```

Open http://localhost:5173 — Vite proxies `/api` to `VITE_DEV_API_PROXY` (default `http://127.0.0.1:48080`).

---

## Auth

Session-based auth is preserved (HttpOnly cookie `bm_session` + optional Bearer token). Existing users and sessions remain compatible.

---

## Smoke test

```powershell
Invoke-RestMethod "http://localhost:48080/api/health" -Method Get
```

---

## Troubleshooting

| Symptom | What to try |
|---------|-------------|
| DB volume not found | Ensure `EXISTING_DB_VOLUME=budget-manager_db_data` in `.env` and the volume exists: `docker volume ls` |
| Port already in use | Change `WEB_PORT` in `.env` |
| Two MySQL containers | Stop budget-manager: `docker compose down` in that project |
| Stale API after PHP changes | `docker compose build api web && docker compose up -d` |
