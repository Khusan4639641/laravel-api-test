# Artisan commands, scheduler и operations

В проекте 9 custom command classes в `app/Console/Commands` и одна closure-команда `inspire` в `routes/console.php`. Ниже описан фактический write behavior; команды не запускались при подготовке документации.

## Command catalog

### `php artisan safi:binary-recalculate-all`

Path: `app/Console/Commands/BinaryRecalculateAllCommand.php`

Signature:

```text
safi:binary-recalculate-all
  --dry-run   count active partners, no writes
  --force     permit manual production run
  --scheduled identify scheduler-approved run
```

`handle()` выбирает source `scheduler|command`, в production запрещает ручной write без `--force`, затем вызывает `ScheduledBinaryBonusService::calculateForAllPartners(now Asia/Tashkent)`. Читает active MLM partner users, packages, tree/PV history and existing binary runs. Для eligible пользователей может записать `binary_bonus_runs`, `binary_bonus_calculations`, `bonus_transactions`, два `wallet_transactions`, balances и database notification.

Повторный запуск в том же half-month period не должен дублировать начисление: unique `(user_id, period_start, period_end)` для run, row lock and service duplicate checks. Ошибка отдельного пользователя учитывается в summary; команда возвращает failure, если failed > 0.

**HIGH RISK:** финансовое начисление. Сначала `--dry-run`; manual production use requires explicit `--force`, backup/reconciliation and period verification.

### `php artisan safi:binary-recalculation:rollback-batch`

Path: `app/Console/Commands/BinaryRecalculationRollbackBatchCommand.php`

Options:

```text
--started-at=UTC --ended-at=UTC --run-ids=1,2
--scope=entire-batch|adjustments-only
--dry-run
--manifest=<path>
--reconciliation=<approved-json>
--confirm-fingerprint=<fingerprint>
--report=<path>
--force
--control-user-id=69
```

Без `--force` команда вызывает `BinaryRecalculationRollbackService::discover()`, формирует immutable manifest, fingerprint, operations, blockers, control-user result and JSON report under storage (или explicit path), не меняя DB. Force-mode запрещён вместе с dry-run, требует manifest and matching fingerprint; если manifest связан с reconciliation JSON, проверяется checksum. Завершённый fingerprint определяется через `hasCompletedRollback()` и повторно не применяется.

Execution может удалить/restore selected binary runs/calculations/bonus notifications, replay wallet effects, adjust user/PV state and, в зависимости от scope/reconciliation, обработать incident adjustments/transfers. Все операции выполняются сервисом по approved manifest.

**HIGH RISK:** incident-level изменение финансового ledger. Не запускать force без сохранённого dry-run report, independent approval, database backup and reconciliation checksum.

### `php artisan safi:cleanup-deleted-user-effects`

Path: `app/Console/Commands/CleanupDeletedUserEffectsCommand.php`

Option: `--dry-run`.

Находит soft-deleted/archived users, считает unreleased identities, active binary nodes, non-voided PV, related completed bonuses, package audit transactions, pending withdrawals, active orders and open wallet balances. При отсутствии `--dry-run` вызывает `PartnerDeletionService::voidDeletedUserOperationalData()`.

Write flow может release identities, deactivate nodes, void PV/bonuses/package ledgers, cancel/restore operational rows and recalculate affected uplines. Повтор предназначен быть mostly idempotent через void/status markers, но это не безвредная read-команда.

**HIGH RISK:** write mode является default; production guard и обязательный `--force` **не найдены**. Всегда начинать с `--dry-run` and backup.

### `php artisan safi:recalculate-branch-pv`

Path: `app/Console/Commands/RecalculateBranchPvCommand.php`

Options: `--chunk=200`, `--force-production`.

Для active `user` вызывает `DashboardBranchVolumeService::syncCachedBranchVolumes()`, пересчитывая cached `users.left_pv`, `right_pv`, `total_pv` по active binary downline. Не изменяет `remaining_left_pv/right_pv`, не создаёт bonus/wallet transactions. Production запрещён без `--force-production` и прямо требует backup.

Повторный запуск должен сходиться к тем же cached values, если дерево/PV не менялись. **MEDIUM RISK:** массовая перезапись dashboard/MLM indicators может выявить/создать расхождение с historical remaining PV.

### `php artisan mlm:recalculate-statuses`

Path: `app/Console/Commands/RecalculateStatusesCommand.php`

Параметров нет. Вызывает `StatusService::recalculateAll()` для **всех** non-deleted active accounts; метод не фильтрует `role=user`. StatusService определяет status по weak leg PV и при повышении вызывает `StatusBonusService` и `X2BonusService`. Для staff accounts без дерева weak leg обычно нулевой, однако сам широкий scope важен для оператора.

Unique markers защищают уже присуждённые status/X2 awards, но новый достигнутый threshold способен создать `bonus_transactions`, credit main wallet и notification. Dry-run, force confirmation и production guard **не найдены**.

**HIGH RISK:** название выглядит как пересчёт статусов, но команда способна начислять деньги.

### `php artisan safi:release-deleted-user-identities`

Path: `app/Console/Commands/ReleaseDeletedUserIdentitiesCommand.php`

Option: `--chunk=200`.

Для soft-deleted/archived users вызывает `PartnerDeletionService::releaseDeletedUserIdentity()`: освобождает unique login/email/phone/referral identifiers, записывая заменённые значения/deletion metadata. Active account дополнительно пропускается. Повтор пропускает уже released identity по metadata.

**MEDIUM RISK:** необратимо с точки зрения прежних login identifiers; dry-run/production guard отсутствуют.

### `php artisan safi:status-bonuses:repair`

Path: `app/Console/Commands/RepairStatusBonusesCommand.php`

Options:

```text
--user-id=<id> | --all
--dry-run | --force
--seed-definitions
--repair-ledger
--only-missing
--status=<code>
```

Требует ровно одну target selection: user-id или all. Write запрещён без `--force`; dry-run reports eligibility/missing markers/ledger. `--seed-definitions` idempotently создаёт/обновляет `status_bonus_definitions`. `--repair-ledger` может восстановить отсутствующие bonus/wallet entries при существующем marker. Service проверяет ELITE eligibility, weak-leg thresholds and existing `user_status_bonuses`.

**HIGH RISK:** force mode может начислить/восстановить деньги. Безопасный порядок: dry-run → проверить definitions and each action → backup → targeted force → ledger reconciliation.

### `php artisan safi:recalculate-mlm`

Path: `app/Console/Commands/SafiRecalculateMlmCommand.php`

Options: `--user=<id>` или `--all`; без target command fails.

Вызывает `PartnerDeletionService::recalculateAffectedUplines()` или `recalculateAllActiveUsers()`. Перестраивает branch PV, remaining PV, total/status caches из active non-deleted data; по описанию команды новые бонусы не создаёт. Production guard, dry-run and backup enforcement отсутствуют.

**HIGH RISK:** массово перезаписывает MLM state, включая remaining volumes/status; до запуска необходим snapshot и понимание отличий от `safi:recalculate-branch-pv`.

### `php artisan safi:seed-demo-tree`

Path: `app/Console/Commands/SeedDemoTreeCommand.php`

Options: `--count=1000`, `--roots=10`, `--password=password`, `--fresh-demo`.

Работает только в `local`/`testing`, явно запрещена в production. Создаёт/обновляет local super admin, seeds START/VIP/ELITE packages, demo users/profiles/wallets and binary nodes. Без `--fresh-demo` refuses existing demo dataset; с option удаляет только распознанных demo users before rebuild.

**HIGH RISK outside disposable environment:** создаёт credentials and large test dataset; `--fresh-demo` удаляет demo identities/data. Production environment guard присутствует.

### `php artisan inspire`

Framework/demo closure в `routes/console.php`; только печатает quote, данных не меняет.

## Scheduler

`routes/console.php` содержит два schedule entries:

| Schedule | Command | Frequency/timezone | Purpose | Production impact |
|---|---|---|---|---|
| `monthlyOn(1, 03:00)` | `safi:binary-recalculate-all --scheduled` | 1-е число, 03:00 Asia/Tashkent | first half-month binary settlement | writes bonus/wallet/PV-use ledgers |
| `monthlyOn(15, 03:00)` | та же | 15-е число, 03:00 Asia/Tashkent | second half-month binary settlement | writes bonus/wallet/PV-use ledgers |

Обе используют `withoutOverlapping()` и один mutex name `safi-binary-recalculate-all`. Чтобы schedule исполнялся, внешний cron/systemd/container process должен регулярно запускать `php artisan schedule:run` либо `schedule:work`; такой OS configuration **не найден в репозитории**.

## Queue workers

`QUEUE_CONNECTION=database` указан в `.env.example`, а framework migrations создают `jobs`, `job_batches`, `failed_jobs`. Однако классы в `app/Jobs`, dispatch calls и queued notifications/listeners **не найдены в текущей реализации**. Все шесть Notification classes выполняются синхронно, поскольку не реализуют `ShouldQueue`.

`composer dev` всё равно запускает `php artisan queue:listen --tries=1 --timeout=0`. Supervisor/systemd/Coolify config для production worker **не найден**.

## High-risk operations

| Operation | Tables/state | Duplicate/repeat protection | Risk |
|---|---|---|---|
| Scheduled binary calculate | runs, calculations, bonuses, wallets, notifications | period unique key + locks + service checks | HIGH |
| Binary rollback batch | те же + reconciliation effects | approved fingerprint/checksum + completed rollback check | HIGH |
| Deleted-user cleanup | users/tree/PV/bonuses/wallets/orders/withdrawals | status/void markers; no mandatory approval | HIGH |
| Status recalculation | users/status awards/bonuses/wallets | unique status/X2 markers | HIGH |
| Status bonus repair | definitions/markers/bonus/wallet ledger | dry-run + explicit force + marker checks | HIGH |
| MLM recalculation | users PV/remaining/status cache | deterministic recalculation, no command guard | HIGH |
| Branch PV recalc | user PV caches | deterministic; production force flag | MEDIUM |
| Identity release | deleted user identity fields/meta | release metadata | MEDIUM |
| Demo tree seed | demo users/tree/wallets/packages | environment check; fresh-demo explicit | HIGH in non-disposable data |

## Operator checklist

Перед любой HIGH RISK command:

1. Проверить `APP_ENV`, target IDs/period/timezone and current DB connection.
2. Сделать recoverable database backup outside this application.
3. Использовать dry-run, если он предусмотрен.
4. Сохранить output/manifest/fingerprint and obtain independent approval for money-changing operations.
5. После write сверить `wallets.balance`, affecting `wallet_transactions`, `bonus_transactions`, marker/run tables and notifications.
6. Не запускать повторно только потому, что CLI output был потерян; сначала проверить immutable rows.

Эта документация не является разрешением на запуск команд. Полный финансовый ledger описан в [WALLET.md](./WALLET.md), binary periods/calculation — в [MLM.md](./MLM.md#binary-bonus).
