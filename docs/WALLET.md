# Wallet, ledger и withdrawal

[Главная](./PROJECT_DOCUMENTATION.md) · [MLM](./MLM.md) · [Services](./SERVICES.md) · [Database](./DATABASE.md) · [API](./API.md)

## Инварианты

- Currency фактически KZT; wallets и financial transactions используют decimal(18,2).
- Balance хранится в `wallets.balance`; reserved withdrawal money — `hold_balance`.
- История хранится в `wallet_transactions`; `affects_balance` явно отделяет деньги от audit-only записей.
- Completed affecting transaction должна соответствовать изменению balance before→after.
- Business bonus дополнительно имеет `bonus_transactions`; это semantic ledger, а wallet transaction — денежный ledger.
- Финансовые services используют DB transactions и row locks. Не редактировать ledger напрямую вне предусмотренных services.

## Wallet types

| Type | Создание | Назначение в реальном коде | Основные operations |
|---|---|---|---|
| `main` | registration/admin first use | доступные/выводимые деньги, bonuses, transfers, cashback | credit referral/binary/status/X2/cashback; debit withdrawal/transfer |
| `bonus` | registration | предполагаемый отдельный bonus balance | реальных credit/debit callers не найдено |
| `deposit` | registration/admin first use | 10% binary, deposit products, 50/50 deposit part | binary credit, purchase/payment debit, release credit |

Unique `(user_id,type)` гарантирует один wallet каждого type.

## Schema

### `wallets`

| Field | Meaning |
|---|---|
| `id`, `user_id` | PK и owner FK |
| `type` | main/bonus/deposit |
| `currency` | default KZT |
| `balance` | доступный ledger balance |
| `hold_balance` | pending withdrawal reserve |
| `status` | active/closed operational state |
| timestamps | audit time |

`Wallet::availableBalance()` возвращает `balance`; hold уже вычтен из balance на withdrawal request, поэтому повторно не subtract-ится.

### `wallet_transactions`

| Field | Meaning |
|---|---|
| `wallet_id`, `user_id` | wallet/owner |
| `type` | semantic operation (`referral_bonus`, `withdrawal_hold`, …) |
| `direction` | `credit`, `debit`, иногда `neutral` |
| `amount` | положительный абсолютный amount |
| `balance_before/after` | snapshot до/после |
| `status` | completed/reversed/voided/cancelled и др. |
| `affects_balance` | меняла ли запись реальный balance |
| `source_type/source_id` | nullable polymorphic source |
| `description` | человекочитаемый комментарий |
| `metadata` | IDs, split, period, actor, idempotency, audit context |

Scope `visible()` исключает reversed/voided/cancelled. API dashboard/admin и notification visibility используют этот scope.

### `bonus_transactions`

| Field | Meaning |
|---|---|
| `user_id` | bonus recipient |
| `wallet_transaction_id` | primary monetary leg; binary primary = main leg |
| `source_user_id/source_order_id` | explicit business origin |
| `bonus_type` | referral, binary, cashback, status_bonus, bonus_x2 |
| `amount` | total bonus, до binary 90/10 split |
| `left_pv/right_pv/matched_pv` | binary/status context |
| `status` | completed/pending/reversed/voided/cancelled |
| `metadata` | rate/base/package/period/split/idempotency/audit |
| `calculated_at` | business calculation time |

### Связанные ledgers

- `binary_bonus_runs`, `binary_bonus_calculations`: period/used/carry/amount и calculation snapshots.
- `user_status_bonuses`, `user_x2_bonuses`: unique award marker и optional bonus transaction.
- `withdrawal_requests`: held amount/status/payment details.
- `partner_transfers`: links sender/recipient wallet transactions.
- `transaction_admin_audits`: old/new wallet transaction payload и reason.
- `admin_action_logs`: balance/package/bonus/delete/rollback audit.

## WalletService

Path: `app/Services/WalletService.php`.

### `createUserWallets(User)`

`firstOrCreate` three wallets with KZT, zero balance/hold, active status. Called by registration/demo; some admin/payment paths lazily `firstOrCreate` required wallet.

### `credit(Wallet $wallet, float|string $amount, string $type, mixed $source = null, array $metadata = [], ?string $description = null)`

1. Normalizes amount and rejects `<=0`.
2. Reads balance before.
3. `balance_after = before + amount`; updates Wallet.
4. Creates completed credit transaction with `affects_balance=true` and morph source.
5. Returns WalletTransaction.

### `debit(Wallet $wallet, float|string $amount, string $type, mixed $source = null, array $metadata = [], ?string $description = null)`

Same, but validates balance sufficient, subtracts and writes debit. Insufficient balance → `InvalidArgumentException`; no partial mutation when caller transaction rolls back.

### `recordNonBalanceOperation(Wallet $wallet, float|string $amount, string $type, mixed $source = null, array $metadata = [], ?string $description = null, string $direction = 'neutral')`

Positive amount; no wallet balance update. Transaction before=after, `affects_balance=false`, direction default neutral. Package assignment, order payment confirmation и withdrawal approval используют такой audit, чтобы operation была видна без повторного денежного effect.

## Bonus → wallet flow

### Referral

```text
base × 10%
  → WalletService.credit(main, referral_bonus)
  → wallet transaction (morph source = BonusTransaction,
                        metadata.referral_user_id = referral)
  → BonusTransaction(type=referral, primary tx ID)
  → notification
```

### Binary

```text
total binary amount
  ├─ 90% → credit(main, binary_bonus_main)       ← primary tx
  └─ 10% → credit(deposit, binary_bonus_deposit)
       ↓
BonusTransaction metadata stores both tx IDs/split
BinaryBonusRun links BonusTransaction
BinaryBonusCalculation stores exact legs
```

### Status/X2/cashback

- status cash → main `status_bonus`;
- X2 cash → main `x2_bonus`, semantic BonusTransaction `bonus_x2`;
- deposit purchase cashback → main `deposit_purchase_cashback`, semantic `cashback`;
- noncash status/X2 creates marker without wallet row.

## Transfers

### Partner-to-partner

Routes: POST `/api/dashboard/partner-transfers` и alias `/api/dashboard/wallet/transfers`.

```text
Sender main wallet (locked)
  → debit partner_transfer_out
  → PartnerTransfer(UUID, amount, idempotency key)
  → Recipient main wallet (locked)
  → credit partner_transfer_in
  → link both transaction IDs
  → database notification sender + recipient
```

Constraints: positive amount, active recipient role=user, not self, sufficient sender balance. Optional idempotency key unique per sender; without it retry is not deduplicated. Both wallets lock in deterministic ID order.

### Internal main→deposit

POST `/api/dashboard/wallets/internal-transfer`, но controller допускает только admin/super_admin.

`InternalWalletTransferService` accepts only `from=main`, `to=deposit`; debit/credit pair with shared UUID in metadata. Reverse deposit→main отсутствует. Эта операция не создаёт `partner_transfers`.

## Withdrawal

### User request

Routes: POST `/api/withdrawals` и `/api/dashboard/withdrawals`. Request: `amount>0`, payment method `ip_account|card_account`; aliases `card`, `bank_account`, `business_account` normalized. Payout period is 14 days from config.

```text
StoreWithdrawalRequest
  → locate user main wallet
  → WithdrawalService::requestWithdrawal
     → lock wallet
     → assert balance >= amount
     → balance_after = balance_before - amount
     → hold_after = hold_before + amount
     → WithdrawalRequest(status=pending, fee=0, net=amount)
     → WalletTransaction(
          type=withdrawal_hold,
          direction=debit,
          affects_balance=true,
          source=WithdrawalRequest,
          metadata hold_before/after
        )
     → WithdrawalRequestedNotification(mail)
```

Средства уже удалены из доступного balance и находятся в hold. Повторный withdrawal проверяет уменьшенный balance.

### Admin/accountant processing

List permission: admin/accountant/super_admin. Approve/reject permission: accountant/super_admin.

#### Approve

1. Lock pending withdrawal и wallet; user must still be active.
2. Assert `hold_balance >= amount`.
3. Decrease hold only; available balance does not change again.
4. Status approved, `processed_at=now`.
5. Creates neutral nonbalance `withdrawal_approved` transaction, source withdrawal.

Внешний bank/card payout API не найден. Approve означает запись решения в приложении; фактическая отправка денег за пределами системы требует отдельного operational process.

#### Reject

1. Lock pending withdrawal/wallet.
2. Decrease hold and restore amount to balance.
3. Status rejected, optional admin reason, processed time.
4. Creates affecting credit `withdrawal_rejected` with before/after/hold metadata.

Only pending status can transition. Повтор approve/reject вызывает validation error и не меняет balance.

## Deposit purchase

Deposit products могут быть оплачены только 100% deposit. Service lock-ит stock и wallet:

1. Paid/completed Order with provider `deposit`, card=0, PV=0.
2. Product stock decrement.
3. Deposit debit `deposit_purchase`.
4. Cashback 20% main credit.
5. BonusTransaction and notification.

Для regular 50/50 TipTopPay flow deposit half debit происходит при создании payment intent, один раз. Fail/refund/cancel release-ит deposit через credit и сохраняет transaction IDs в payment/order metadata.

## Audit-only operations

Типичные `affects_balance=false`:

- package activation/upgrade/manual assignment (`package_*`);
- `order_payment` после TipTopPay success;
- `package_auto_upgrade` amount 0 создаётся напрямую как audit;
- `withdrawal_approved`;
- отдельные административные operational markers.

`direction` у таких строк может быть neutral или business-facing debit/credit, но summary должен учитывать `affects_balance`, иначе operation turnover и real money будут смешаны.

## Admin financial mutation

### Manual partner balance

PATCH `/api/admin/partners/{user}/balance`, super_admin only. Modes:

- `set`: target balance ≥0;
- `adjust`: signed delta; resulting balance must not be negative.

Creates one `manual_adjustment` affecting transaction if delta !=0 and an `AdminActionLog` even for no-op. Self-update flag пишется metadata.

### Wallet transaction edit/delete

- PATCH transaction: route permission admin/accountant/super_admin, FormRequest restricts admin/super_admin; amount >0 + reason.
- DELETE: FormRequest super_admin only + reason.

`AdminWalletTransactionService` adjusts/reverses current balance, linked bonus and notification; writes `transaction_admin_audits`. Potential issue: edit delta path lacks negative final balance guard.

### Bonus edit/delete

Routes use `admin.bonuses.manage` (admin/super_admin), but service itself asserts **super_admin**. Binary adjustment updates both wallet legs using 90/10 split and linked run. Delete reverses active ledger and marks bonus/run voided. Reason required.

## Reconciliation/rollback

Binary rollback command does not simply subtract a total. It:

- discovers historical recalculations/new runs in exact batch window;
- snapshots users/runs/calculations/bonuses/wallet tx/notifications;
- builds per-wallet chronological replay plan;
- validates blockers/inconsistencies and manifest fingerprint;
- on force restores historical values or deletes new artifacts;
- replays balance before/after for affected ledger;
- applies PV deltas and optional incident reconciliation;
- logs completed fingerprint so повторный rollback is no-op.

Use only through documented manifest workflow: [COMMANDS.md](./COMMANDS.md#php-artisan-safibinary-recalculationrollback-batch).

## Transaction type index

Types explicitly handled/found include:

| Category | Types |
|---|---|
| bonuses | `referral_bonus`, `binary_bonus_main`, `binary_bonus_deposit`, `status_bonus`, `x2_bonus`, `cashback`, `deposit_purchase_cashback` |
| withdrawal | `withdrawal_hold`, `withdrawal_approved`, `withdrawal_rejected`, compatibility `payout_completed` |
| transfer | `partner_transfer_out`, `partner_transfer_in`, internal transfer debit/credit types |
| package/payment | package activation/upgrade/manual source types, `package_auto_upgrade`, `order_payment` |
| admin | `manual_adjustment`, `manual_credit`, bonus/wallet adjustment and reversal types |
| deposit/order | `deposit_purchase`, 50/50 deposit debit/release types |

Metadata keys vary by flow; authoritative exact payload is service code and Resource returns raw metadata.

## Operational controls

Before changing a wallet/bonus transaction in production:

1. Capture user wallet rows and full chronological visible+voided ledger.
2. Capture linked `bonus_transactions`, binary runs/calculations, notifications and source order/withdrawal/transfer.
3. Verify `balance` against replay, including `affects_balance` and status.
4. Use application service/approved command, never direct SQL for one row only.
5. Re-run reconciliation and inspect admin audits.

Every financial mutation in this document is **HIGH RISK**.

## Не найдено / требует проверки

- Outbound bank/card payout после withdrawal approval — не найдено.
- Generic wallet-to-wallet service/user deposit→main transfer — не найдено.
- Real use of `bonus` wallet — не найдено.
- Fees: schema has fee/net, current withdrawal service uses fee 0/net=amount.
- External accounting reconciliation/report export protocol — требует дополнительной проверки.
