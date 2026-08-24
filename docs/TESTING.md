# Testing

## Test infrastructure

`phpunit.xml` определяет suites `Unit` (`tests/Unit`) и `Feature` (`tests/Feature`). Testing environment принудительно использует SQLite `:memory:`, array cache/session/mail, sync queue, bcrypt cost 4 и отключённые optional observability tools. Application source coverage scope — `app/`.

Проверенный реестр:

- 129 файлов `*Test.php`: 6 Unit + 123 Feature.
- 865 test methods, подтверждены `php artisan test --list-tests`.
- 131 PHP-файл в `tests/` вместе с `tests/TestCase.php` и helper trait `tests/Support/CreatesBinaryBonusEligibility.php`.

При подготовке документации выполнялся только test discovery (`--list-tests`); сами 865 тестов **не запускались**, поэтому их текущий pass/fail status требует отдельной проверки.

## Commands

```bash
composer test
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=BinaryBonusCalculationTest
```

`composer test` сначала выполняет `php artisan config:clear`, затем suite. Frontend test runner в `package.json` **не найден**; ряд Feature tests проверяет frontend source/API contracts косвенно.

## Unit tests

| File | What it verifies |
|---|---|
| `BinaryBonusPercentTest.php` | binary rate берётся из current package |
| `DepositBonusTest.php` | binary split 90% main/10% deposit, 20% deposit cashback and deposit-product debit/cashback |
| `ExampleTest.php` | skeleton sanity assertion |
| `PackageBusinessRulesTest.php` | START/VIP/ELITE business matrix and inactive BUSINESS behavior |
| `ReferralBonusPercentTest.php` | фактическое constant 10% referral правило |
| `StatusThresholdTest.php` | seeded status thresholds/business rewards |

Эти tests небольшие, но фиксируют несколько критичных числовых инвариантов. Они не заменяют DB/transaction integration coverage.

## Feature: binary tree, PV and bonus settlement

| File | What it verifies |
|---|---|
| `BinaryBonusCalculationTest.php` | weak leg, matched PV, amount, split, eligibility and duplicate behavior |
| `BinaryBonusFrontendTest.php` | binary data/labels exposed to frontend |
| `BinaryBonusSoftDeletedPvExclusionTest.php` | deleted buyer PV excluded from bonus |
| `BinaryIncidentReconciliationTest.php` | incident correction/reconciliation plan behavior |
| `BinaryInnerBranchRegressionTest.php` | branch resolution for nested/internal nodes regression |
| `BinaryRecalculationRollbackBatchTest.php` | manifest, fingerprint, dry-run, guarded execute and rollback outputs |
| `BinaryTreeServiceTest.php` | placement/tree relationships and service invariants |
| `BinaryTreeSideResolverTest.php` | L/R side resolution along ancestors |
| `PvAccrualTest.php` | PV propagation to uplines and branch/remaining fields |
| `PvTransactionBinaryBonusTest.php` | binary consumes eligible PV transaction history and records use |
| `ScheduledBinaryCalculationSafetyTest.php` | immutable period/idempotency/financial safety |
| `ScheduledBinaryRecalculationCommandTest.php` | command modes, scheduler source and production restrictions |

`tests/Support/CreatesBinaryBonusEligibility.php` создаёт два personally sponsored active referrals in root L/R branches и при необходимости seeds synthetic bonusable PV transactions. Это shared fixture, а не отдельный test.

## Feature: packages, referral, status and X2

| File | What it verifies |
|---|---|
| `PackageActivationTest.php` | activation effects, PV and package assignment |
| `PackageActivationTransactionTest.php` | activation audit wallet transaction |
| `PackageApiTest.php` | public/dashboard package representations |
| `PackageAutoUpgradeFromPaidOrdersTest.php` | cumulative paid-order thresholds and automatic package assignment |
| `PackagePaymentAvailabilityTest.php` | package payment flag/readiness restrictions |
| `PackageSeederBonusPercentTest.php` | seeded referral/binary percentages |
| `PackageUpgradeTest.php` | allowed upgrades and business effects |
| `PartnerPackageActivityTest.php` | active package/MLM account semantics |
| `PublicRegistrationDisabledTest.php` | default public registration gate |
| `ReferralInviteAccessTest.php` | valid referral link access versus open registration |
| `ReferralRegistrationBonusTest.php` | sponsor/referral registration bonus flow |
| `StatusBonusRepairCommandTest.php` | status definitions/ledger dry-run and force repair protections |
| `StatusBonusTest.php` | ELITE status-award marker, bonus and wallet credit |
| `StatusRecalculationTest.php` | weak-leg threshold status progression |
| `UserStatusTest.php` | user status representation/model behavior |
| `X2BonusServiceTest.php` | direct-referral branch/rank eligibility and awards |
| `X2BonusTest.php` | X2 feature integration and duplicate protection |

Business rule subgroup:

| File | What it verifies |
|---|---|
| `BusinessRules/ElitePackageRulesTest.php` | ELITE activity/turnover split requirements |
| `BusinessRules/ReferralBonusRulesTest.php` | sponsor/referral 10% rule |
| `BusinessRules/RegistrationPackageRulesTest.php` | which packages/flows are valid at registration |
| `BusinessRules/StatusBonusRulesTest.php` | status bonus eligibility and definitions |

## Feature: wallet, transfers, withdrawals and financial fixes

| File | What it verifies |
|---|---|
| `DepositCheckoutTest.php` | deposit purchase/product payment and cashback ledger |
| `InternalTransferTest.php` | internal main→deposit authorization and two-sided ledger |
| `MlmFinancialFixesTest.php` | collected regressions in financial/MLM corrections |
| `MlmNotificationPersistenceTest.php` | database notifications persist with financial awards |
| `NotificationDispatchTest.php` | registration/bonus/status/withdrawal notification dispatch |
| `PartnerTransfersTest.php` | main wallet partner transfer, balances, idempotency/authorization |
| `UnsafeMlmActionsTest.php` | unsafe mutation endpoints/operations are rejected as designed |
| `WithdrawalApiTest.php` | user/admin withdrawal API contracts and access |
| `WithdrawalTest.php` | request hold, approve/reject and wallet final state |

WalletService does not have a file named `WalletServiceTest`; its real integration coverage is distributed across binary/referral/status/deposit/transfer/withdrawal/admin transaction tests.

## Feature: orders, products and TipTopPay

| File | What it verifies |
|---|---|
| `CartPaymentStrategyTest.php` | card 100%, card/deposit 50/50, deposit 100% selection/constraints |
| `Orders/OrderDeliveryFieldsTest.php` | recipient/phone/city/address/comment validation and storage |
| `Orders/ProductStockOrderTest.php` | stock reservation and insufficient-stock behavior |
| `Orders/UserOrdersPageApiTest.php` | user order list/detail API used by dashboard |
| `ProductOrderApiTest.php` | checkout items/totals/order API flow |
| `ProductPvVisibilityTest.php` | product/order PV exposure rules |
| `TipTopPayConfigTest.php` | config/status flags and credential mapping |
| `TipTopPayOrderPaymentTest.php` | order intent/payment state transitions |
| `TipTopPayPackagePaymentTest.php` | gated package payment intent/completion flow |
| `TipTopPayPaymentTest.php` | core payment model/service behaviors |
| `TipTopPayReadinessTest.php` | admin readiness output and missing configuration |
| `TipTopPayWebhookTest.php` | HMAC callbacks, idempotency and provider status handling |
| `UserPackagePurchaseDisabledTest.php` | package purchase endpoint disabled by default |

Potential issue documented elsewhere — order MLM effects occur before provider success — has test coverage around order/payment behavior, but exact desired business rule still requires product-owner confirmation. See [SERVICES.md](./SERVICES.md#tiptoppayservice).

## Feature: admin and accounting

### Nested `tests/Feature/Admin`

| File | What it verifies |
|---|---|
| `AdminManualPackageAssignmentTest.php` | manual START/VIP/ELITE assignment, correct PV, referral effects |
| `AdminManualStatusAssignmentTest.php` | manual status assignment authorization/audit |
| `AdminOrdersApiTest.php` | backoffice order list/show/status operations |
| `AdminPackageAssignmentTransactionTest.php` | audit transaction emitted for manual package assignment |
| `AdminPartnerBalanceAdjustmentTest.php` | super-admin balance set/adjust ledger |
| `AdminPartnerDetailBalanceTest.php` | detail response wallet balances |
| `AdminPartnerTransactionsTest.php` | per-partner ledger response |
| `BinaryBonusManualCalculationTest.php` | guarded manual partner binary calculation |

### Top-level admin files

| File | What it verifies |
|---|---|
| `AccountantAccessTest.php` | accountant read/report/withdrawal permissions and restrictions |
| `AdminApiTest.php` | general admin API auth/contracts |
| `AdminBinaryRecalculateButtonTest.php` | frontend action is wired to binary API |
| `AdminBonusForceRecalculationTest.php` | forced/recalculation authorization and effects |
| `AdminBonusesPaginationTest.php` | bonuses/ledger pagination |
| `AdminNewsImageUploadTest.php` | news image validation/storage |
| `AdminOverviewZeroStateTest.php` | empty-data overview response |
| `AdminPartnerBulkCreateTest.php` | bulk validation/creation behavior |
| `AdminPartnerCreateParityTest.php` | single/bulk create business parity |
| `AdminPartnerCreateStructureTest.php` | created partner sponsor/binary tree placement |
| `AdminPartnerCreateTest.php` | create validation, defaults and authorization |
| `AdminPartnerDeleteApiTest.php` | delete preview/endpoint response |
| `AdminPartnerDeleteTest.php` | deletion service business effects |
| `AdminPartnerIdentityEditTest.php` | super-admin identity mutation and uniqueness |
| `AdminPartnerManagementTest.php` | status/package/block/note/password management |
| `AdminPartnerPlacementSideChainTest.php` | selected branch same-side-chain placement rule |
| `AdminPartnerSoftDeleteIdentityTest.php` | deleted identity release/meta |
| `AdminPartnerSoftDeleteStatsTest.php` | deletion preview operational counts |
| `AdminPartnersApiTest.php` | partner list/show response |
| `AdminPartnersPaginationSearchTest.php` | partner filters/search/pagination |
| `AdminProductsFormFrontendTest.php` | admin product form/source contract |
| `AdminStructureApiTest.php` | tree API representation |
| `AdminStructureBranchPvTest.php` | displayed branch PV source/calculation |
| `AdminTransactionMutationTest.php` | wallet transaction edit/delete delta and audit |
| `AdminTransactionsAdminBonusesDateTimeFrontendTest.php` | date/time rendering/source integration |
| `AdminTransactionsPaginationTest.php` | ledger pagination |
| `AdminTransactionsSearchTest.php` | ledger search/filters |
| `AdminTransactionsSummaryTest.php` | amount/type/status summaries |
| `ReportsApiTest.php` | accountant/super-admin report aggregates |
| `SettingsLanguageTest.php` | localized system settings read/write |

## Feature: dashboard, authentication and authorization

| File | What it verifies |
|---|---|
| `Auth/RegistrationPhoneRequiredTest.php` | phone required/validation on registration |
| `DashboardEarningsSummaryTest.php` | bonus/transfer/withdrawal totals returned to UI |
| `DashboardOverviewBalanceTest.php` | wallet balances in overview |
| `DashboardOverviewBranchPvTest.php` | branch PV in overview |
| `DashboardPackageChangeDisabledTest.php` | direct user package mutation disabled by default |
| `DashboardStatusProgressTest.php` | next status/weak-leg progress response |
| `DashboardStructureListTest.php` | structure tree/list payload |
| `DashboardStructurePartnersListTest.php` | descendant partner pagination/search |
| `DashboardTransactionsTest.php` | own transaction visibility and filters |
| `DirectAccessPermissionTest.php` | direct API access is denied beyond role permissions |
| `ForgotPasswordRequestFlowTest.php` | public request and staff reset/cancel lifecycle |
| `MenuApiTest.php` | role-specific menu/redirect response |
| `RolePermissionsTest.php` | config-driven role/API access matrix |
| `ProfileAvatarFrontendTest.php` | avatar frontend integration/source |
| `ProfileAvatarUploadTest.php` | image validation, replacement and URL response |

## Feature: support

| File | What it verifies |
|---|---|
| `SupportChatTest.php` | ticket initial message, replies, close/reopen and attachments |
| `SupportTicketAccessTest.php` | user ownership and staff access |
| `SupportTicketPermissionTest.php` | role permission matrix for support endpoints |

## Feature: localization, public pages and frontend contracts

| File | What it verifies |
|---|---|
| `FaqLanguageTest.php` | localized FAQ response |
| `FrontendContentTest.php` | expected SPA source/content/routes |
| `FrontendStatusProgressDisplayTest.php` | status progress UI consumes API fields |
| `LanguageMiddlewareTest.php` | Accept-Language locale selection |
| `LocaleTranslationTest.php` | translations across supported locales |
| `MarketingCalculatorFrontendTest.php` | marketing calculator package/status data wiring |
| `PublicLegalPagesTest.php` | legal routes/settings content |
| `PublicNewsLanguageTest.php` | localized news output |
| `PublicProductsLanguageTest.php` | localized product output |
| `SystemLabelLocalizationTest.php` | package/status/order/product system labels |

`ExampleTest.php` at Feature level is the Laravel skeleton HTTP sanity test.

## Seeder coverage

`Seeders/DemoBinaryTreeSeederTest.php` verifies local/testing demo tree generation and guard behavior. Package/status/product/bonus definitions are also exercised indirectly by the business Feature/Unit tests. Not every one of the 15 seeders has a dedicated file.

## Coverage by business area

| Area | Evidence | Main gaps |
|---|---|---|
| Binary/PV/tree | dedicated service, calculation, transaction, scheduler, rollback and deletion tests | production-size performance/concurrency load tests not found |
| Package/referral | activation/upgrade/manual/auto/referral tests | real provider end-to-end package purchase disabled by default |
| Status/X2 | thresholds, recalc, repair, service/integration tests | compensation payout workflow absent from code |
| Wallet/withdrawal | indirect wallet flows + withdrawal/admin/transfer mutation tests | no external payout provider integration test |
| Orders/payments | stock, delivery, strategy, provider intent/webhook tests | no browser/E2E widget automation |
| Admin/roles | broad endpoint and frontend contract coverage | no separate policy tests because Policies absent |
| Frontend | PHP source/contract checks | no Vitest/Jest/React Testing Library/Playwright suite |
| Deployment | none | no deployment/smoke/infrastructure tests found |

## Safe execution notes

- Test configuration uses in-memory SQLite and `RefreshDatabase` patterns; still verify `phpunit.xml` is loaded before running commands.
- Do not override testing DB variables with a production connection.
- `composer test` clears config cache in the working environment.
- Tests may create files on fake/local disks and artifacts under `storage/framework/testing`; review cleanup in CI.
- A test name documents intended behavior, not proof of current correctness until the suite passes on the target revision.
