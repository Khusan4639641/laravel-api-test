# Demo Binary Tree Seeder

Команда создаёт локальное/testing demo-дерево для проверки:

- `/admin/structure`
- `/dashboard/structure`
- `/admin/partners`
- поиска партнёров
- left/right counts
- tree rendering
- partner list rendering

## Запуск Локально

```bash
php artisan safi:seed-demo-tree --count=1000 --roots=10 --fresh-demo
```

Параметры:

- `--count=1000` — общее количество demo users.
- `--roots=10` — количество независимых root users без sponsor/binary parent. Минимум 10.
- `--password=password` — пароль для demo users и локального super admin.
- `--fresh-demo` — удалить только demo users перед созданием нового дерева.

## Логины

Admin:

```text
admin@safilife.test / password
```

Demo root users:

```text
demo_root_001@safilife.test / password
demo_root_002@safilife.test / password
...
demo_root_010@safilife.test / password
```

Demo downline users:

```text
demo_user_0011@safilife.test / password
demo_user_0012@safilife.test / password
...
```

## Что Создаёт

При запуске по умолчанию:

- 1000 demo users с `role=user`.
- 10 independent root partners с `sponsor_id=null` и `binary parent=null`.
- 990 downline partners, распределённых по бинарному дереву под root users.
- Минимум 4 уровня глубины для default `--count=1000 --roots=10`.
- START package для большинства пользователей, часть VIP/ELITE для визуального теста.
- `status=user`, `account_status=active`.
- Main/bonus/deposit wallets с нулевыми балансами.
- Локальный super admin, если отсутствует или требует обновления.

## Безопасная Очистка

Флаг `--fresh-demo` удаляет только demo users с email pattern:

```text
demo_%@safilife.test
```

Команда не удаляет:

- `admin@safilife.test`
- реальных пользователей
- пользователей без demo email marker

Связанные demo records очищаются через cascade/delete только для demo users.

## Production Warning

НЕ запускать на production.

Команда заблокирована вне `local/testing`. Если environment `production`, выполнение останавливается с ошибкой:

```text
This command is disabled in production.
```

Не использовать `migrate:fresh` на production.
