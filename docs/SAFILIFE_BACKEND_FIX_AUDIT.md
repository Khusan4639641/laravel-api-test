# SAFI LIFE Backend Fix Audit

Дата аудита: 2026-06-03

Цель этапа: зафиксировать расхождения бизнес-логики SAFI LIFE с ТЗ и добавить тесты, которые описывают правильное ожидаемое поведение. Бизнес-логика на этом этапе не исправлялась.

## Проверенные backend-файлы

- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Requests/Auth/RegisterRequest.php`
- `app/Models/Package.php`
- `database/seeders/PackageSeeder.php`
- `app/Services/PackageService.php`
- `app/Http/Controllers/Api/PackageActivationController.php`
- `app/Http/Controllers/Api/PackageUpgradeController.php`
- `app/Services/BonusService.php`
- `app/Services/ReferralService.php`
- `app/Services/BinaryTreeService.php`
- `app/Services/PvService.php`
- `app/Services/StatusService.php`
- `app/Services/StatusBonusService.php`
- `app/Services/WalletService.php`
- `app/Http/Controllers/Api/Admin/PartnerController.php`

## Проверенные frontend-файлы

- `resources/js/safi/pages/RegisterPage.tsx`
- `resources/js/safi/pages/dashboard/PackageStatus.tsx`
- `resources/js/safi/pages/admin/AdminPartnerDetail.tsx`
- `resources/js/safi/pages/admin/AdminPackages.tsx`
- `resources/js/safi/pages/MarketingPlanPage.tsx`
- `resources/js/safi/pages/BusinessPage.tsx`
- `resources/js/safi/pages/dashboard/Overview.tsx`

## Расхождения

| Требование | Текущая реализация | Где | Расхождение | Добавленные тесты | Что исправить дальше |
|---|---|---|---|---|---|
| При первой регистрации доступны только START и VIP. ELITE доступен только upgrade с VIP. | `Package::PUBLIC_CODES` содержит `START`, `VIP`, `ELITE`. `RegisterRequest` принимает любой существующий `package_id`. `AuthController::resolvePackage()` разрешает любой active package из `PUBLIC_CODES`. | `app/Models/Package.php`, `app/Http/Requests/Auth/RegisterRequest.php`, `app/Http/Controllers/Api/AuthController.php` | ELITE можно выбрать как стартовый пакет. | `RegistrationPackageRulesTest::test_register_does_not_allow_elite_as_starting_package` | Разделить starter packages и upgrade packages. Для регистрации разрешить только START/VIP. |
| Выбор пакета при регистрации не должен сразу начислять PV/бонусы без оплаты. | `AuthController::register()` после создания пользователя вызывает `PackageService::upgradePackage()` если передан `package_id`. | `app/Http/Controllers/Api/AuthController.php`, `app/Services/PackageService.php` | Регистрация бесплатно активирует пакет, меняет `current_package_id`, начисляет PV и referral bonus спонсору. | `RegistrationPackageRulesTest::test_register_with_package_choice_does_not_activate_package_or_accrue_bonus_without_payment` | Сделать выбранный пакет pending/intent либо убрать активацию из регистрации. Начисления запускать только после подтвержденной оплаты/активации. |
| START: цена 60 000, активность 100 PV, товарооборот 100 PV. VIP: цена 180 000, активность 300 PV, товарооборот 300 PV. ELITE: цена 300 000, активность 500 PV, добавляет 200 PV. | `PackageSeeder` записывает `pv` равным цене: START 60000, VIP 180000, ELITE 300000. Существующие package tests также используют `pv = price`. | `database/seeders/PackageSeeder.php`, `tests/Feature/PackageActivationTest.php`, `tests/Feature/PackageUpgradeTest.php` | PV смешан с тенге. | `RegistrationPackageRulesTest::test_seeded_package_values_match_business_tz` | Обновить seeders, тестовые фикстуры и расчеты на PV 100/300/500. |
| 1 PV = 500 ₸, но PV не равно тенге и не является балансом. | `PackageService` начисляет `package->pv`, но сидер хранит в `pv` тенге-значения. `BonusService` рассчитывает деньги от переданного `baseAmount`, а не от PV напрямую. | `database/seeders/PackageSeeder.php`, `app/Services/PackageService.php`, `app/Services/BonusService.php`, `app/Services/PvService.php` | В данных пакета PV фактически равно цене, что ломает статусы/товарооборот. | `RegistrationPackageRulesTest::test_seeded_package_values_match_business_tz` | Ввести явное разделение price, activity PV, turnover PV, bonusable amount. |
| ELITE первые 200 PV идут только в товарооборот, без referral bonus. | `PackageService::upgradeExistingPackage()` начисляет referral bonus от `paymentAmount` при VIP -> ELITE. Нет признака non-bonusable ELITE volume. | `app/Services/PackageService.php`, `app/Services/BonusService.php` | Спонсору начисляется referral bonus за ELITE upgrade volume, который по ТЗ должен быть исключен. | `ElitePackageRulesTest::test_first_two_hundred_elite_pv_does_not_create_referral_bonus` | Разделить ELITE turnover PV и bonusable amount. Для первых 200 PV ELITE не создавать referral bonus. |
| ELITE первые 200 PV идут только в товарооборот, без binary bonus. | `PvService::accruePvUpTree()` добавляет весь PV в `remaining_left_pv`/`remaining_right_pv`. `BonusService::calculateBinaryBonus()` использует remaining PV без исключения ELITE non-bonusable volume. | `app/Services/PvService.php`, `app/Services/BonusService.php` | После ELITE upgrade первые 200 PV могут стать базой бинарного бонуса. | `ElitePackageRulesTest::test_first_two_hundred_elite_pv_does_not_create_binary_bonus` | Хранить bonusable/non-bonusable branch PV отдельно или помечать источник PV, чтобы binary bonus не использовал первые 200 PV ELITE. |
| Referral bonus = 10% от корректной базы заказа. Кейс: база 135 000 ₸ -> бонус 13 500 ₸, не 18 000 ₸. | `BonusService::accrueReferralBonus()` корректно считает 10% от переданной базы, но package flows передают полную цену пакета или разницу upgrade. Нет модели/поля bonusable order base. | `app/Services/BonusService.php`, `app/Services/PackageService.php`, `app/Http/Controllers/Api/PackageActivationController.php` | Для package activation referral bonus считается от full package price, поэтому VIP 180 000 дает 18 000 вместо ожидаемой базы 135 000. | `ReferralBonusRulesTest::test_referral_bonus_is_ten_percent_of_correct_order_base`, `ReferralBonusRulesTest::test_package_purchase_referral_bonus_uses_bonusable_base_not_full_package_price` | Передавать в `BonusService` корректную bonusable base из заказа/оплаты, а не full package price. |
| Бронзовый директор: путевка + 100 000 ₸. Компенсация 400 000 ₸ только при отказе. | В `StatusService::STATUS_DEFINITIONS` bronze has `amount = 400000`, `is_cash_bonus = true`. `StatusBonusService` сразу создает cash bonus на всю сумму. | `app/Services/StatusService.php`, `database/seeders/StatusBonusDefinitionSeeder.php`, `app/Services/StatusBonusService.php` | 400 000 начисляется как основной бонус, без выбора отказа от поездки. | `StatusBonusRulesTest::test_bronze_director_default_reward_is_trip_plus_one_hundred_thousand`, `StatusBonusRulesTest::test_bronze_director_cash_compensation_is_only_paid_after_trip_refusal` | Добавить модель выбора reward option/refusal. По умолчанию начислять 100 000 + reward text, компенсацию только после отказа. |
| Серебряный директор: зарубежная поездка + 250 000 ₸. Компенсация 750 000 ₸ только при отказе. | В `StatusService::STATUS_DEFINITIONS` silver has `amount = 750000`, `is_cash_bonus = true`. `StatusBonusService` сразу создает cash bonus. | `app/Services/StatusService.php`, `database/seeders/StatusBonusDefinitionSeeder.php`, `app/Services/StatusBonusService.php` | 750 000 начисляется как основной бонус, без выбора отказа. | `StatusBonusRulesTest::test_silver_director_default_reward_is_foreign_trip_plus_two_hundred_fifty_thousand`, `StatusBonusRulesTest::test_silver_director_cash_compensation_is_only_paid_after_trip_refusal` | Разделить default trip reward cash amount и refusal compensation amount. |
| Если SUPER ADMIN вручную назначил пакет, должны пройти PV/товарооборот, referral, binary и прочие начисления по ТЗ. | `PartnerController::package()` только делает `forceFill(['current_package_id' => ...])`. `PackageService` не используется. | `app/Http/Controllers/Api/Admin/PartnerController.php` | Ручное назначение пакета не меняет PV, не обновляет binary volume, не начисляет referral bonus. | `AdminManualPackageAssignmentTest::test_manual_package_assignment_updates_partner_pv_turnover`, `::test_manual_package_assignment_accrues_referral_bonus_to_sponsor`, `::test_manual_package_assignment_updates_binary_volume` | Перевести manual assignment на отдельный service action с явным audit reason/manual source и теми же бизнес-начислениями, если это подтвержденное ручное присвоение. |
| Frontend registration должен показывать только START/VIP для первого входа и не создавать впечатление бесплатной активации. | `RegisterPage` грузит все public packages из API и отправляет `package_id` в `/api/register`. | `resources/js/safi/pages/RegisterPage.tsx` | Если API возвращает ELITE, UI позволяет выбрать ELITE. Сам submit отправляет package_id на регистрацию. | Backend tests добавлены; frontend tests не добавлялись на этом этапе. | После backend-фикса отфильтровать starter packages на API или UI. Текст формы должен объяснять, что пакет выбирается/оплачивается отдельно. |
| Dashboard package flow должен отделять оплату/заявку от активации. | `PackageStatus` вызывает `/api/packages/{package}/activate` или `/upgrade` напрямую и показывает "отправлен", но backend сразу начисляет. | `resources/js/safi/pages/dashboard/PackageStatus.tsx`, `app/Http/Controllers/Api/PackageActivationController.php`, `app/Http/Controllers/Api/PackageUpgradeController.php` | Нет отдельного payment/approval state в тестируемом package flow. | Покрыто косвенно registration/elite/referral tests. | Добавить paid order/package activation workflow. |

## Добавленные тесты

### `tests/Feature/BusinessRules/RegistrationPackageRulesTest.php`

- `test_register_does_not_allow_elite_as_starting_package`
- `test_register_allows_start_as_starting_package_choice`
- `test_register_allows_vip_as_starting_package_choice`
- `test_register_with_package_choice_does_not_activate_package_or_accrue_bonus_without_payment`
- `test_seeded_package_values_match_business_tz`

### `tests/Feature/BusinessRules/ReferralBonusRulesTest.php`

- `test_referral_bonus_is_ten_percent_of_correct_order_base`
- `test_package_purchase_referral_bonus_uses_bonusable_base_not_full_package_price`

### `tests/Feature/BusinessRules/ElitePackageRulesTest.php`

- `test_elite_upgrade_adds_first_two_hundred_pv_to_turnover`
- `test_first_two_hundred_elite_pv_does_not_create_referral_bonus`
- `test_first_two_hundred_elite_pv_does_not_create_binary_bonus`

### `tests/Feature/BusinessRules/StatusBonusRulesTest.php`

- `test_bronze_director_default_reward_is_trip_plus_one_hundred_thousand`
- `test_bronze_director_cash_compensation_is_only_paid_after_trip_refusal`
- `test_silver_director_default_reward_is_foreign_trip_plus_two_hundred_fifty_thousand`
- `test_silver_director_cash_compensation_is_only_paid_after_trip_refusal`

### `tests/Feature/Admin/AdminManualPackageAssignmentTest.php`

- `test_super_admin_can_manually_assign_package`
- `test_manual_package_assignment_updates_partner_pv_turnover`
- `test_manual_package_assignment_accrues_referral_bonus_to_sponsor`
- `test_manual_package_assignment_updates_binary_volume`

## Исходный результат целевого запуска новых тестов на этапе аудита

Команда:

```bash
php artisan test tests/Feature/BusinessRules tests/Feature/Admin/AdminManualPackageAssignmentTest.php
```

Результат на текущей реализации:

- 18 tests
- 5 passed
- 13 failed

Ожидаемо падают тесты по:

- запрету ELITE на первой регистрации;
- отсутствию бесплатной активации пакета при регистрации;
- PV values в `PackageSeeder`;
- bonusable base для referral bonus;
- исключению первых 200 PV ELITE из referral/binary bonus;
- bronze/silver status bonus default amounts;
- ручному назначению пакета супер-админом.

## Обновление после этапа 2

На этапе 2 исправлены правила регистрации и матрица START/VIP/ELITE:

- `PackageSeeder` приведен к `price` 60 000 / 180 000 / 300 000 и `activity_pv` 100 / 300 / 500.
- Добавлено отдельное `turnover_pv`: START 100, VIP 300, ELITE 200.
- `/api/public/packages` продолжает возвращать START/VIP/ELITE.
- Добавлен `/api/public/registration-packages`, который возвращает только START/VIP.
- `/api/register` валидирует только START/VIP как стартовый выбор и не активирует пакет/не начисляет PV/бонусы при регистрации.
- `/api/packages/{package}/activate` не разрешает ELITE как первый пакет.
- VIP -> ELITE upgrade теперь дает `additional_pv = 200`.

После этапа 2 целевые тесты по регистрации и пакетам проходят. Полный `php artisan test` всё еще падает на 10 тестах, относящихся к следующим этапам:

- referral bonus base для VIP;
- исключение первых 200 PV ELITE из referral/binary bonus;
- bronze/silver status bonus compensation logic;
- manual package assignment by super admin.

## Обновление после этапа 3

На этапе 3 исправлены referral bonus base и исключение первых 200 PV ELITE из бонусируемого объема:

- START activation платит referral bonus от полной цены пакета.
- VIP activation платит referral bonus от базы 135 000 ₸, поэтому сумма бонуса 13 500 ₸ вместо 18 000 ₸.
- VIP -> ELITE upgrade добавляет 200 PV в товарооборот партнера и upstream branch PV, но не добавляет эти PV в `remaining_left_pv`/`remaining_right_pv`.
- VIP -> ELITE upgrade не создает referral bonus и не создает `referral_bonus` wallet transaction.
- Binary bonus calculation больше не использует первые 200 ELITE PV как bonusable volume.

После этапа 3 целевые тесты по referral/ELITE проходят. Полный `php artisan test` всё еще падает на 7 тестах, относящихся к следующим этапам:

- manual package assignment by super admin;
- bronze/silver status bonus compensation logic.

## Обновление после этапа 4

На этапе 4 исправлены статусные бонусы Bronze/Silver:

- `status_bonus_definitions` получил отдельные поля `cash_amount`, `compensation_amount`, `compensation_available`.
- Bronze Director теперь автоматически начисляет `100000.00` и хранит доступную компенсацию `400000.00` только как опцию отказа.
- Silver Director теперь автоматически начисляет `250000.00` и хранит доступную компенсацию `750000.00` только как опцию отказа.
- `StatusBonusService` больше не создает automatic status bonus transactions на суммы compensation.
- Public/admin status payload содержит новые поля для корректного отображения rewards во frontend.

После этапа 4 целевые тесты по статусным бонусам проходят. Полный `php artisan test` всё еще падает на 3 тестах, относящихся к следующему этапу:

- manual package assignment by super admin.

## Следующий этап исправлений

1. Переписать admin manual package assignment на явный service action:
   - присвоение пакета с source/audit reason;
   - начисление PV/товарооборота;
   - referral bonus;
   - binary volume update.
2. После исправлений обновить/переписать старые тесты, которые всё еще закрепляют прежнее неверное поведение.
