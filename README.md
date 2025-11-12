# SchoolManagement

Professional-ready PHP 8.2 application that bundles a Docker-based dev stack, MariaDB, automated quality checks, and linting tools. It is designed as a shadow repository you can mirror into production later.

## What's Inside

- PHP 8.2 + Apache workspace container with custom `php.ini` and PDO MySQL extension
- MariaDB 11 service with health checks and persistent volume mounts
- Sample HTML/CSS/PHP landing page that demonstrates PDO prepared statements
- Composer-managed tooling: PHPStan (strict rules), PHP_CodeSniffer/PHPCBF, PHP-CS-Fixer, Dotenv, and Roave Security Advisories
- Ready-to-use environment management via `.env` and `config/bootstrap.php`

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose v2)
- PHP 8.2+ and Composer (for running checks locally outside the container)
- GNU Make (optional, if you plan to add helper scripts later)

## First Run

1. **Install dependencies**
   ```bash
   composer install
   ```
2. **Copy environment template**
   ```bash
   cp .env.example .env
   ```
   Adjust credentials if needed.
   > `.env` is intentionally git-ignored; each contributor should create their own copy from `.env.example`.
3. **Start the stack**
   ```bash
   ./scripts/up-open.sh
   ```
   The helper script runs `docker compose up -d --build`, waits for the app container to pass its health check, and opens the homepage automatically. Prefer the script for day-to-day use, or fall back to `docker compose up --build` if you need interactive logs.
4. Visit `http://localhost:49200` for the starter page and `http://localhost:49201` for phpMyAdmin (the URLs reflect `HOST_HTTP_PORT` and `HOST_PHPMYADMIN_PORT` in `.env`).

## Services

| Service    | Image                     | Ports        | Notes                                                              |
|------------|---------------------------|--------------|--------------------------------------------------------------------|
| app        | php:8.2-apache            | 49200 → 80   | mounts the repo, loads custom `php.ini`, logs frontend URL         |
| db         | mariadb:11.4              | 49202 → 3306 | seeded via runtime migrator, credentials from `.env`               |
| phpmyadmin | phpmyadmin/phpmyadmin:5.2 | 49201 → 80   | web UI for MariaDB; startup logs print the management console URL  |

### Environment Variables

`docker-compose.yml` reads `.env` automatically. Core variables:

| Key              | Usage (default)          |
|------------------|--------------------------|
| `APP_NAME`       | Display name in UI       |
| `APP_ENV`        | Surface on landing page  |
| `APP_URL`        | Base URL shown in UI     |
| `APP_TIMEZONE`   | Propagated to PHP config |
| `DB_HOST`        | MariaDB host (`mariadb`) |
| `DB_PORT`        | MariaDB port (`3306`)    |
| `DB_DATABASE`    | Schema (`app`)           |
| `DB_USERNAME`    | Database user (`app`)    |
| `DB_PASSWORD`    | Database password        |
| `DB_ROOT_PASSWORD` | Root password for MariaDB |
| `HOST_HTTP_PORT` | Host port for the app container (`49200`) |
| `HOST_PHPMYADMIN_PORT` | Host port for phpMyAdmin (`49201`) |

## Composer Scripts

| Script            | Command                           | Purpose                                         |
|-------------------|-----------------------------------|-------------------------------------------------|
| `composer check`  | `validate`, `lint`, `analyse` | One-stop quality gate                         |
| `composer lint`   | `phpcs --standard=phpcs.xml`      | PSR-12 + custom sniffs                          |
| `composer lint-fix` | `phpcbf` + `php-cs-fixer fix`   | Auto-fix coding standards                       |
| `composer analyse`| `phpstan analyse`                 | Max-level static analysis with strict rules     |

Roave Security Advisories prevents installing packages with known vulnerabilities. `phpstan/extension-installer` automatically wires strict/deprecation rules.

## Local Quality Checks

Run before committing:

```bash
composer check
```

To auto-fix coding style issues:

```bash
composer lint-fix
```

Need to focus on one tool? Use `composer lint` or `composer analyse` independently.

## Database Usage

The sample page (`public/index.php`) calls `App\Database\Migrator` to migrate the schema and seed demo messages with prepared statements:

```25:40:src/Database/Migrator.php
        $this->connection->exec(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS samples (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    message VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
```

Update the migration or replace it with bespoke SQL/Migrations as your schema grows. No ORM is bundled; PDO prepared statements are the default access pattern.

## Troubleshooting & Tips

- **Composer memory limit**: set `COMPOSER_MEMORY_LIMIT=-1` when installing large dependencies.
- **File permissions in container**: adjust `USER_ID`/`GROUP_ID` build args in `docker/php/Dockerfile` to match your host UID/GID if needed.
- **MariaDB data reset**: remove the `docker/mysql-data` directory (it's bind-mounted) to start fresh.
- **Hot reload**: the app container bind-mounts your working tree, so PHP and asset changes are picked up immediately—just refresh the browser.
- **Health checks**: container readiness is validated via `curl` (app) and `mariadb-admin ping` (database). Tail logs with `docker compose logs -f app phpmyadmin db`.
- **Security scanning**: consider layering tools like `enlightn/security-checker` or `trivy` for container scans in the future.
- **Backups & migrations**: integrate a migration manager (e.g., Phinx) when schema changes become frequent.

## Next Ideas

- Introduce a secrets manager (Vault, Doppler, GitHub OIDC) before production.
- Publish a devcontainer (`.devcontainer/`) for VS Code Codespaces or GitHub Codespaces.
- Configure dependabot/renovate to keep Composer dependencies current.

## License

MIT © 2025 SchoolManagement
