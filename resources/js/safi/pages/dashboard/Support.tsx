import { FormEvent, useCallback, useEffect, useState } from 'react';
import { FileUp, Filter, Mail, MessageSquare, Phone } from 'lucide-react';
import { Badge } from '../../components/dashboard/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { ApiError, createDashboardSupportTicket, getApiErrorState, getArray, getDashboardSupportTickets, getString } from '../../lib/api';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function Support() {
  const [supportTickets, setSupportTickets] = useState<Array<{ id: string; date: string; subject: string; category: string; status: string; lastReply: string }>>([]);
  const [form, setForm] = useState({ subject: '', category: 'Вопрос по бонусам', message: '' });
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const loadTickets = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const response = await getDashboardSupportTickets();
      setSupportTickets(getArray(response, ['support_tickets']).map((item, index) => {
        const ticket = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        return {
          id: getString(ticket, ['id']) || String(index + 1),
          date: getString(ticket, ['created_at']) || '',
          subject: getString(ticket, ['subject']) || '-',
          category: getString(ticket, ['category']) || '-',
          status: normalizeStatus(getString(ticket, ['status']) || 'open'),
          lastReply: getString(ticket, ['last_reply_at', 'replied_at']) || '-',
        };
      }));
    } catch (caughtError) {
      setSupportTickets([]);
      setLoadError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadTickets();
  }, [loadTickets]);

  const submitTicket = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setMessage('');
    setError('');

    try {
      await createDashboardSupportTicket(form);
      setForm({ subject: '', category: 'Вопрос по бонусам', message: '' });
      setMessage('Обращение отправлено.');
      await loadTickets();
    } catch (caughtError) {
      if (caughtError instanceof ApiError) {
        setError(caughtError.message);
      } else {
        setError('Не удалось отправить обращение.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <span className="safi-kicker">Support</span>
        <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Поддержка</h1>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
          Обращения, контакты менеджера и история ответов.
        </p>
        {(message || error) && (
          <div className={`mt-6 rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
            {error || message}
          </div>
        )}
      </section>

      <section className="grid gap-8 lg:grid-cols-[0.36fr_0.64fr]">
        <aside className="space-y-8">
          <article className="rounded-[32px] border border-safi-green bg-safi-green p-7 text-white shadow-[0_18px_48px_rgba(11,23,18,0.10)]">
            <h2 className="font-serif text-3xl font-semibold text-white">Контакты менеджера</h2>
            <div className="mt-7 space-y-6">
              <ContactRow icon={<Phone className="h-5 w-5" />} label="Телефон / WhatsApp" value="+7 (701) 000-00-00" />
              <ContactRow icon={<MessageSquare className="h-5 w-5" />} label="Telegram" value="@safilife_support" />
              <ContactRow icon={<Mail className="h-5 w-5" />} label="Email" value="support@safilife.kz" />
            </div>
          </article>

          <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Частые вопросы</h2>
            <div className="mt-6 space-y-3">
              {['Как сделать апгрейд пакета?', 'Когда начисляются бонусы?', 'Как оформить вывод?', 'Где мой заказ?'].map((question) => (
                <div key={question} className="rounded-2xl border border-safi-border bg-safi-cream p-4 text-sm font-bold text-safi-green">
                  {question}
                </div>
              ))}
            </div>
          </article>
        </aside>

        <div className="space-y-8">
          <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Написать обращение</h2>
            <form className="mt-7 space-y-6" onSubmit={submitTicket}>
              <div className="grid gap-5 md:grid-cols-2">
                <label className="block">
                  <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Тема</span>
                  <input type="text" value={form.subject} onChange={(event) => setForm((current) => ({ ...current, subject: event.target.value }))} placeholder="Кратко суть вопроса" className={inputClass} required />
                </label>
                <label className="block">
                  <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Категория</span>
                  <select value={form.category} onChange={(event) => setForm((current) => ({ ...current, category: event.target.value }))} className={inputClass}>
                    <option>Вопрос по бонусам</option>
                    <option>Вопрос по выводу</option>
                    <option>Вопрос по структуре</option>
                    <option>Вопрос по пакету</option>
                    <option>Техническая проблема</option>
                    <option>Другое</option>
                  </select>
                </label>
              </div>
              <label className="block">
                <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Сообщение</span>
                <textarea rows={5} value={form.message} onChange={(event) => setForm((current) => ({ ...current, message: event.target.value }))} placeholder="Опишите вопрос подробно" className={`${inputClass} resize-none`} required />
              </label>
              <div className="flex flex-col gap-3 border-t border-safi-border pt-6 md:flex-row md:items-center md:justify-between">
                <button type="button" className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green">
                  <FileUp className="h-4 w-4" />
                  Прикрепить файл
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="inline-flex items-center justify-center rounded-full border border-safi-green bg-safi-green px-7 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)] disabled:opacity-60"
                >
                  {isSubmitting ? 'Отправляем...' : 'Отправить обращение'}
                </button>
              </div>
            </form>
          </article>

          <article className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="flex items-center justify-between border-b border-safi-border bg-safi-cream p-6 md:p-7">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">История обращений</h2>
              <button type="button" className="flex h-10 w-10 items-center justify-center rounded-full border border-safi-border bg-white text-safi-green">
                <Filter className="h-4 w-4" />
              </button>
            </div>
            {isLoading && (
              <div className="p-6 md:p-7">
                <LoadingState title="Загружаем обращения" description="Получаем историю обращений из API." />
              </div>
            )}

            {!isLoading && loadError && (
              <div className="p-6 md:p-7">
                <ErrorState description={loadError} onRetry={loadTickets} />
              </div>
            )}

            {!isLoading && !loadError && supportTickets.length === 0 && (
              <div className="p-6 md:p-7">
                <EmptyState title="Обращений пока нет" description="История появится после первого обращения." />
              </div>
            )}

            {!isLoading && !loadError && supportTickets.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[760px] text-left">
                <thead className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                  <tr>
                    <th className="px-7 py-4">ID / дата</th>
                    <th className="px-7 py-4">Тема</th>
                    <th className="px-7 py-4">Статус</th>
                    <th className="px-7 py-4 text-right">Ответ</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-safi-border text-sm">
                  {supportTickets.map((ticket) => (
                    <tr key={ticket.id} className="transition-colors hover:bg-safi-cream/70">
                      <td className="px-7 py-5">
                        <div className="font-extrabold text-safi-green">#{ticket.id}</div>
                        <div className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{ticket.date}</div>
                      </td>
                      <td className="px-7 py-5">
                        <div className="font-extrabold text-safi-green">{ticket.subject}</div>
                        <div className="mt-1 text-xs text-safi-muted">{ticket.category}</div>
                      </td>
                      <td className="px-7 py-5">
                        <Badge variant={ticket.status === 'Закрыто' ? 'default' : ticket.status === 'В работе' ? 'warning' : 'success'}>
                          {ticket.status}
                        </Badge>
                      </td>
                      <td className="px-7 py-5 text-right font-bold text-safi-green">{ticket.lastReply}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            )}
          </article>
        </div>
      </section>
    </div>
  );
}

function ContactRow({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="flex items-center gap-4">
      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-safi-gold">{icon}</div>
      <div>
        <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-white/60">{label}</div>
        <div className="mt-1 font-extrabold text-white">{value}</div>
      </div>
    </div>
  );
}

function normalizeStatus(status: string) {
  if (status === 'closed') {
    return 'Закрыто';
  }

  if (status === 'answered') {
    return 'Отвечено';
  }

  if (status === 'open') {
    return 'В работе';
  }

  return status;
}
