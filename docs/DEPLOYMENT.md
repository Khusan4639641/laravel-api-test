# Environment и deployment

## Что подтверждено репозиторием

- PHP `^8.3`, Laravel `^13.0`, Sanctum `^4.3` из `composer.json`.
- Node build: React 19, Vite 8, TypeScript 6, Tailwind 4 из `package.json`.
- Web entry: `public/index.php`; SPA shell: `resources/views/app.blade.php`; Vite entry: `resources/js/safi/main.tsx`.
- Локальная symlink `public/storage -> storage/app/public` существует в текущем workspace, но сама ссылка не tracked.
- `public/build` существует локально, но не tracked Git; production release должен собирать или доставлять artifacts отдельно.
- `.env.example` defaults: SQLite, database session/cache/queue, log mail, TipTopPay disabled/test mode.
- Application timezone default: `Asia/Tashkent`; scheduler также жёстко использует эту timezone.

Dockerfile, docker-compose, Coolify, Nginx, Apache vhost, PHP-FPM pool, Supervisor/systemd, CI workflow и production deploy script **не найдены в текущей реализации**. Поэтому фактический production topology, release user, web root, zero-downtime strategy, backup/rollback и secret store **требуют дополнительной проверки на сервере**.

## Реально доступные Composer/npm workflows

`composer setup` выполняет:

```text
composer install
copy .env.example -> .env if absent
php artisan key:generate
php artisan migrate --force
npm install --ignore-scripts
npm run build
```

Это bootstrap для нового environment, а не безопасный универсальный production deploy: он выполняет migrations и может создать новый key. На существующем production нельзя запускать его вслепую.

`composer dev` параллельно запускает built-in server, `queue:listen --tries=1 --timeout=0`, Pail и Vite dev server. Это development workflow, не production process manager.

`composer test` очищает config cache и запускает PHPUnit. `npm run build` выполняет `vite build`; `npm run dev` — Vite dev server.

## Deployment flow, который следует из файлов

Репозиторий позволяет восстановить только такой технический контур; автоматизация конкретных шагов отсутствует:

```text
Git checkout/release                         [external process: not found]
  -> composer install --no-dev ...          [operator/deploy tool]
  -> production .env / APP_KEY              [external secret management]
  -> php artisan migrate --force             [changes DB; backup/approval required]
  -> npm install + npm run build             [creates public/build]
  -> php artisan storage:link if missing     [public uploads]
  -> config/route/view cache as selected     [not scripted in repo]
  -> PHP-FPM + web server, document root public/ [external config: not found]
  -> scheduler process                       [external config: not found]
  -> optional queue worker                   [external config: not found]
```

Команды выше описывают необходимые роли компонентов, но не являются подтверждённым production runbook. В частности, exact Composer flags, Node version, maintenance mode, migration ordering, cache warmup, worker restart и rollback procedure требуют проверки инфраструктуры.

## Runtime processes

| Process | Required by code | Repository configuration |
|---|---|---|
| Web server → PHP-FPM | да, для HTTP production | не найдено; document root должен быть `public/` |
| Vite dev server | только development | `npm run dev` |
| Frontend build | да для production artifacts | `npm run build`; build not tracked |
| Scheduler | да для binary settlement 1/15 числа | schedules определены, launcher не найден |
| Queue worker | infrastructure configured, current app jobs absent | dev listener есть; production manager не найден |
| Database | да | driver/host controlled by ENV; actual production engine not inferable |
| Redis/Memcached | optional config only | current `.env.example` uses DB cache/queue; actual production use unknown |

Scheduler details and financial safeguards: [COMMANDS.md](./COMMANDS.md#scheduler).

## Environment variables

Ниже перечислены важные variables, прочитанные из `.env.example` и `config/*.php`. Реальные значения и secrets в документации не приводятся.

### APP / logging

| Variable | Purpose / current default |
|---|---|
| `APP_NAME` | application/mail/UI name; example `Laravel` |
| `APP_ENV` | environment gates production safeguards/demo command; example `local` |
| `APP_KEY=***` | Laravel encryption/signing key; required and secret |
| `APP_DEBUG` | detailed errors; example true, production must be false |
| `APP_URL` | generated URLs, public storage and payment redirect base |
| `APP_TIMEZONE` | default `Asia/Tashkent` in config; absent from example |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | config defaults ru, but `.env.example` explicitly sets en |
| `APP_FAKER_LOCALE` | factory locale |
| `APP_PREVIOUS_KEYS=***` | previous encryption keys for controlled rotation |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | maintenance coordination |
| `BCRYPT_ROUNDS` | password hashing cost; example 12 |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | logging target/verbosity |
| `LOG_SLACK_WEBHOOK_URL=***`, `PAPERTRAIL_URL=***` and related | optional Laravel logging integrations; business-specific use not found |

### Database

| Variable | Purpose |
|---|---|
| `DB_CONNECTION` | example SQLite; production value requires checking |
| `DB_URL=***` | optional complete connection URL |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD=***` | server database connection |
| `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION`, `DB_TIMEZONE` | driver tuning |
| `DB_FOREIGN_KEYS`, `DB_SSLMODE`, `DB_ENCRYPT`, `DB_TRUST_SERVER_CERTIFICATE`, `MYSQL_ATTR_SSL_CA` | driver/transport safeguards |

### Cache, session, queue and Redis

| Variable group | Purpose |
|---|---|
| `CACHE_STORE`, `CACHE_PREFIX` | example database cache; scheduler overlap mutex depends on cache |
| `DB_CACHE_CONNECTION`, `DB_CACHE_TABLE`, lock variables | database cache storage |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_DOMAIN/PATH`, secure/http-only/same-site variables | session configuration; API frontend currently uses bearer tokens |
| `QUEUE_CONNECTION` | example database; no application jobs found |
| `DB_QUEUE_*`, `QUEUE_FAILED_DRIVER` | database queue/retry/failed jobs |
| `REDIS_CLIENT`, `REDIS_URL=***`, `REDIS_HOST/PORT/USERNAME/PASSWORD=***`, DB/prefix/retry variables | optional Redis cache/queue/database connections |
| `MEMCACHED_*`, `BEANSTALKD_QUEUE_*`, `SQS_*` | optional standard Laravel drivers; current project-specific use not found |

### Mail and notifications

| Variable | Purpose |
|---|---|
| `MAIL_MAILER` | example `log`; actual delivery requires another configured transport |
| `MAIL_URL=***`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD=***` | SMTP connection |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | sender identity |
| `POSTMARK_API_KEY=***`, `RESEND_API_KEY=***`, AWS SES variables | optional supported mail transports; runtime choice depends on `MAIL_MAILER` |

### Filesystem / AWS

| Variable | Purpose |
|---|---|
| `FILESYSTEM_DISK` | default local/private disk; services explicitly use public/private where needed |
| `AWS_ACCESS_KEY_ID=***`, `AWS_SECRET_ACCESS_KEY=***` | S3/SES/SQS/DynamoDB credentials |
| `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | S3 and AWS driver settings |

Product/news/avatar images are written to the `public` disk; support attachments use protected storage abstraction. Public uploads require `public/storage` link or equivalent web delivery.

### Safi feature/business flags

| Variable | Default | Effect |
|---|---:|---|
| `SAFI_PUBLIC_REGISTRATION_ENABLED` | false | allow unsponsored public registration |
| `SAFI_USER_PACKAGE_CHANGES_ENABLED` | false | enable direct user activate/upgrade endpoints |
| `SAFI_USER_PACKAGE_PURCHASES_ENABLED` | false | enable user package TipTopPay intent |

Эти variables используются `config/safi.php`, но отсутствуют в `.env.example`; absent value означает false. Withdrawal payout period 14 days and methods `ip_account`, `card_account` hard-coded in the same config, не ENV.

### TipTopPay

| Variable | Purpose |
|---|---|
| `TIPTOPPAY_ENABLED` | global integration gate; example false |
| `TIPTOPPAY_TEST_MODE` | readiness/widget mode marker; example true |
| `TIPTOPPAY_PUBLIC_TERMINAL_ID` | public widget terminal id |
| `TIPTOPPAY_PAYMENT_SCHEMA` | default `Single` |
| `TIPTOPPAY_CURRENCY` | default KZT |
| `TIPTOPPAY_SUCCESS_URL`, `TIPTOPPAY_FAIL_URL` | browser redirects, default under `APP_URL` |
| `TIPTOPPAY_API_PASSWORD=***` | secret readiness credential; no outbound charge client found |
| `TIPTOPPAY_WEBHOOK_SECRET=***` | HMAC callback validation secret |

**HIGH:** blank webhook secret currently causes signature check to accept callback. Production readiness must treat it as mandatory; see [SERVICES.md](./SERVICES.md#webhook-authentication).

### Frontend

`VITE_APP_NAME` is present in `.env.example`, although current SPA title comes from Blade and no meaningful runtime read was found in `resources/js/safi`. Any variable prefixed `VITE_` is compiled into browser assets and must never contain a secret.

## Production readiness checklist grounded in code

1. Confirm supported PHP extensions and Node/npm versions on target; lockfiles should govern exact dependencies.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, stable `APP_KEY`, correct HTTPS `APP_URL`.
3. Back up DB before `php artisan migrate --force`; migrations were not executed by this documentation work.
4. Set explicit DB/cache/session/queue connections and ensure corresponding framework tables exist.
5. Set mail transport if registration/status/withdrawal emails must leave the server; `MAIL_MAILER=log` only logs.
6. Configure all TipTopPay settings, especially webhook secret, and validate callback reachability over HTTPS.
7. Build frontend and verify `public/build/manifest.json`; do not rely on a local untracked build.
8. Ensure writable `storage/` and `bootstrap/cache/`, public storage link, upload size limits and backup policy.
9. Configure scheduler launcher and monitor the 1st/15th financial command; prevent duplicate scheduler hosts or rely on a shared mutex-capable cache.
10. Decide whether a queue worker is required; current notifications are synchronous, but framework default is database queue.
11. Verify Nginx/Apache/PHP-FPM, TLS, proxy headers, request limits, CSP and log rotation externally; configs are not in repo.
12. Smoke-test `/up`, public catalog, login, role redirects, uploads, TipTopPay test flow and read-only financial reports before enabling traffic.

## Rollback and disaster recovery

Application release rollback procedure **не найден**. Database migrations may not be safely reversible after writes, and financial commands have their own ledgers. A production runbook must distinguish:

- code/artifact rollback;
- schema migration rollback (only after migration-specific review);
- database restore;
- financial incident reconciliation via the guarded binary rollback command.

Never use `migrate:fresh`, production seeding or ad-hoc wallet SQL as a deployment rollback. Binary incident procedure is documented in [COMMANDS.md](./COMMANDS.md#php-artisan-safibinary-recalculationrollback-batch).
