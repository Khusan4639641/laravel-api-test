import { FormEvent, useMemo, useState } from 'react';
import { ArrowRightLeft, CheckCircle2 } from 'lucide-react';
import AsyncPartnerSelect from '../AsyncPartnerSelect';
import { ApiError, createPartnerTransfer, TransferPartner } from '../../lib/api';

interface PartnerTransferFormProps {
  availableBalance: number;
  onSuccess: () => Promise<void> | void;
}

export default function PartnerTransferForm({ availableBalance, onSuccess }: PartnerTransferFormProps) {
  const [recipient, setRecipient] = useState<TransferPartner | null>(null);
  const [amount, setAmount] = useState('');
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  const [idempotencyKey, setIdempotencyKey] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const amountNumber = useMemo(() => Number(amount), [amount]);
  const isAmountValid = Number.isFinite(amountNumber) && amountNumber > 0 && amountNumber <= availableBalance;
  const canSubmit = Boolean(recipient) && isAmountValid && !isSubmitting;

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setMessage('');
    setError('');

    if (!recipient) {
      setError('Выберите партнёра');
      return;
    }

    if (!Number.isFinite(amountNumber) || amountNumber <= 0) {
      setError('Сумма перевода должна быть больше нуля');
      return;
    }

    if (amountNumber > availableBalance) {
      setError('Недостаточно средств');
      return;
    }

    setIdempotencyKey(makeIdempotencyKey());
    setIsConfirmOpen(true);
  };

  const confirmTransfer = async () => {
    if (!recipient) {
      return;
    }

    setIsSubmitting(true);
    setError('');
    setMessage('');

    try {
      await createPartnerTransfer({
        recipient_user_id: recipient.id,
        amount: amountNumber,
        comment,
        idempotency_key: idempotencyKey || makeIdempotencyKey(),
      });
      setMessage('Перевод выполнен');
      setRecipient(null);
      setAmount('');
      setComment('');
      setIdempotencyKey('');
      setIsConfirmOpen(false);
      await onSuccess();
    } catch (caughtError) {
      setError(caughtError instanceof ApiError ? caughtError.message : 'Не удалось выполнить перевод');
      setIdempotencyKey('');
      setIsConfirmOpen(false);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
      <h2 className="mb-7 flex items-center gap-3 font-serif text-3xl font-semibold text-safi-green">
        <ArrowRightLeft className="h-6 w-6 text-safi-gold" />
        Перевод партнёру
      </h2>

      {(message || error) && (
        <div className={`mb-5 rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
          {message && !error && (
            <span className="inline-flex items-center gap-2">
              <CheckCircle2 className="h-4 w-4" />
              {message}
            </span>
          )}
          {error}
        </div>
      )}

      <form className="space-y-6" onSubmit={submit}>
        <label className="block">
          <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Получатель</span>
          <AsyncPartnerSelect value={recipient} onChange={setRecipient} disabled={isSubmitting} />
        </label>

        <label className="block">
          <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Сумма перевода</span>
          <input
            type="number"
            min="1"
            max={availableBalance || undefined}
            step="0.01"
            value={amount}
            disabled={isSubmitting}
            onChange={(event) => setAmount(event.target.value)}
            className="w-full rounded-2xl border border-safi-border bg-safi-cream px-5 py-4 text-xl font-extrabold text-safi-green outline-none focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25 disabled:cursor-not-allowed disabled:opacity-70"
          />
          <span className="mt-2 block text-xs font-bold text-safi-muted">Доступно: {formatMoney(availableBalance)}</span>
        </label>

        <label className="block">
          <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Комментарий</span>
          <textarea
            value={comment}
            disabled={isSubmitting}
            onChange={(event) => setComment(event.target.value)}
            placeholder="Комментарий к переводу"
            rows={3}
            className="w-full resize-none rounded-2xl border border-safi-border bg-safi-cream px-5 py-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25 disabled:cursor-not-allowed disabled:opacity-70"
          />
        </label>

        <button
          type="submit"
          disabled={!canSubmit}
          className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-safi-green bg-safi-green px-5 py-4 text-xs font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)] transition-colors hover:bg-safi-green-hover disabled:cursor-not-allowed disabled:opacity-60"
        >
          <ArrowRightLeft className="h-5 w-5" />
          {isSubmitting ? 'Переводим...' : 'Перевести'}
        </button>
      </form>

      {isConfirmOpen && recipient && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-safi-green/45 px-4 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-[28px] border border-safi-border bg-white p-6 shadow-[0_28px_80px_rgba(11,23,18,0.28)]">
            <h3 className="font-serif text-2xl font-semibold text-safi-green">Подтвердите перевод</h3>
            <p className="mt-4 text-sm font-bold leading-7 text-safi-muted">
              Перевести {formatMoney(amountNumber)} партнёру {recipient.name}?
            </p>
            <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                disabled={isSubmitting}
                onClick={() => {
                  setIdempotencyKey('');
                  setIsConfirmOpen(false);
                }}
                className="rounded-full border border-safi-border px-5 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:bg-safi-cream disabled:opacity-60"
              >
                Отмена
              </button>
              <button
                type="button"
                disabled={isSubmitting}
                onClick={confirmTransfer}
                className="rounded-full border border-safi-green bg-safi-green px-5 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-white transition-colors hover:bg-safi-green-hover disabled:opacity-60"
              >
                Подтвердить перевод
              </button>
            </div>
          </div>
        </div>
      )}
    </article>
  );
}

function formatMoney(amount: number) {
  return `${amount.toLocaleString('ru-RU')} ₸`;
}

function makeIdempotencyKey() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}
