import { useCallback, useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { FileUp, Filter, Mail, MessageSquare, Phone } from 'lucide-react';
import { Badge } from '../../components/dashboard/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import {
  ApiError,
  closeDashboardSupportTicket,
  createDashboardSupportTicket,
  getApiErrorState,
  getArray,
  getDashboardSupportTicket,
  getDashboardSupportTickets,
  getString,
  updateDashboardSupportTicket,
} from '../../lib/api';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

interface SupportTicketRow {
  id: string;
  date: string;
  subject: string;
  category: string;
  status: string;
  statusCode: string;
  message: string;
  adminReply: string;
  lastReply: string;
}

const emptyForm = { subject: '', category: 'Вопрос по бонусам', message: '' };

export default function Support() {
  const [supportTickets, setSupportTickets] = useState<SupportTicketRow[]>([]);
  const [selectedTicketId, setSelectedTicketId] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [editForm, setEditForm] = useState(emptyForm);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const selectedTicket = supportTickets.find((ticket) => ticket.id === selectedTicketId);
  const canEditSelectedTicket = Boolean(selectedTicket && selectedTicket.statusCode !== 'closed');

  const selectTicket = (ticket: SupportTicketRow) => {
    setSelectedTicketId(ticket.id);
    setEditForm({
      subject: ticket.subject,
      category: ticket.category,
      message: ticket.message,
    });
    setMessage('');
    setError('');
  };

  const loadTicketDetail = useCallback(async (ticketId: string) => {
    setIsLoadingDetail(true);

    try {
      const response = await getDashboardSupportTicket(ticketId);
      const ticket = normalizeTicket(unwrapTicket(response));

      setSupportTickets((current) => {
        const exists = current.some((item) => item.id === ticket.id);

        if (!exists) {
          return [ticket, ...current];
        }

        return current.map((item) => item.id === ticket.id ? ticket : item);
      });
      setEditForm({
        subject: ticket.subject,
        category: ticket.category,
        message: ticket.message,
      });
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось открыть обращение.');
    } finally {
      setIsLoadingDetail(false);
    }
  }, []);

  const loadTickets = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);

    try {
      const response = await getDashboardSupportTickets();
      const tickets = getArray(response, ['support_tickets']).map((item, index) => normalizeTicket(item, index));

      setSupportTickets(tickets);
      setSelectedTicketId((current) => current && tickets.some((ticket) => ticket.id === current) ? current : tickets[0]?.id || '');
    } catch (caughtError) {
      setSupportTickets([]);
      setSelectedTicketId('');
      setLoadError(getApiErrorState(caughtError).error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadTickets();
  }, [loadTickets]);

  useEffect(() => {
    if (selectedTicketId) {
      void loadTicketDetail(selectedTicketId);
    }
  }, [selectedTicketId, loadTicketDetail]);

  useEffect(() => {
    if (selectedTicket) {
      setEditForm({
        subject: selectedTicket.subject,
        category: selectedTicket.category,
        message: selectedTicket.message,
      });
    }
  }, [selectedTicket?.id]);

  const submitTicket = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setMessage('');
    setError('');

    try {
      await createDashboardSupportTicket(form);
      setForm(emptyForm);
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

  const updateTicket = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!selectedTicket || !canEditSelectedTicket) {
      return;
    }

    setIsUpdating(true);
    setMessage('');
    setError('');

    try {
      await updateDashboardSupportTicket(selectedTicket.id, editForm);
      setMessage('Обращение обновлено.');
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось обновить обращение.');
    } finally {
      setIsUpdating(false);
    }
  };

  const closeTicket = async () => {
    if (!selectedTicket || selectedTicket.statusCode === 'closed') {
      return;
    }

    setIsUpdating(true);
    setMessage('');
    setError('');

    try {
      await closeDashboardSupportTicket(selectedTicket.id);
      setMessage('Обращение закрыто.');
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось закрыть обращение.');
    } finally {
      setIsUpdating(false);
    }
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <span className="safi-kicker">Support</span>
        <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Поддержка</h1>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
          Создавайте обращения, отслеживайте ответы и закрывайте решённые вопросы.
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
            <h2 className="font-serif text-3xl font-semibold text-safi-green">Написать обращение</h2>
            <TicketForm
              form={form}
              submitLabel={isSubmitting ? 'Отправляем...' : 'Отправить обращение'}
              disabled={isSubmitting}
              onSubmit={submitTicket}
              onChange={setForm}
            />
          </article>
        </aside>

        <div className="space-y-8">
          <article className="overflow-hidden rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="flex items-center justify-between border-b border-safi-border bg-safi-cream p-6 md:p-7">
              <h2 className="font-serif text-3xl font-semibold text-safi-green">Мои обращения</h2>
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
                      <tr
                        key={ticket.id}
                        onClick={() => selectTicket(ticket)}
                        className={`cursor-pointer transition-colors hover:bg-safi-cream/70 ${ticket.id === selectedTicketId ? 'bg-safi-cream' : ''}`}
                      >
                        <td className="px-7 py-5">
                          <div className="font-extrabold text-safi-green">#{ticket.id}</div>
                          <div className="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{ticket.date}</div>
                        </td>
                        <td className="px-7 py-5">
                          <div className="font-extrabold text-safi-green">{ticket.subject}</div>
                          <div className="mt-1 text-xs text-safi-muted">{ticket.category}</div>
                        </td>
                        <td className="px-7 py-5">
                          <Badge variant={ticket.statusCode === 'closed' ? 'default' : ticket.statusCode === 'open' ? 'warning' : 'success'}>
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

          {selectedTicket && (
            <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.05)] md:p-8">
              <div className="flex flex-col gap-3 border-b border-safi-border pb-5 md:flex-row md:items-start md:justify-between">
                <div>
                  <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Обращение #{selectedTicket.id}</div>
                  <h2 className="mt-2 font-serif text-3xl font-semibold text-safi-green">{selectedTicket.subject}</h2>
                </div>
                <Badge variant={selectedTicket.statusCode === 'closed' ? 'default' : selectedTicket.statusCode === 'open' ? 'warning' : 'success'}>
                  {selectedTicket.status}
                </Badge>
              </div>

              {isLoadingDetail && (
                <div className="mt-5 rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                  Загружаем детали обращения...
                </div>
              )}

              <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="rounded-3xl border border-safi-border bg-safi-cream p-5">
                  <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Ваше сообщение</div>
                  <p className="mt-3 whitespace-pre-line text-sm font-medium leading-7 text-safi-green">{selectedTicket.message}</p>
                </div>
                <div className="rounded-3xl border border-safi-border bg-white p-5">
                  <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Ответ поддержки</div>
                  <p className="mt-3 whitespace-pre-line text-sm font-medium leading-7 text-safi-green">
                    {selectedTicket.adminReply || 'Ответ ещё не отправлен.'}
                  </p>
                </div>
              </div>

              {canEditSelectedTicket && (
                <div className="mt-8 border-t border-safi-border pt-7">
                  <h3 className="font-serif text-2xl font-semibold text-safi-green">Редактировать обращение</h3>
                  <TicketForm
                    form={editForm}
                    submitLabel={isUpdating ? 'Сохраняем...' : 'Сохранить изменения'}
                    disabled={isUpdating}
                    onSubmit={updateTicket}
                    onChange={setEditForm}
                    compact
                  />
                  <div className="mt-5 flex justify-end">
                    <button
                      type="button"
                      onClick={closeTicket}
                      disabled={isUpdating}
                      className="rounded-full border border-safi-border bg-safi-cream px-6 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green disabled:opacity-60"
                    >
                      Закрыть обращение
                    </button>
                  </div>
                </div>
              )}
            </article>
          )}
        </div>
      </section>
    </div>
  );
}

function TicketForm({
  form,
  submitLabel,
  disabled,
  compact = false,
  onSubmit,
  onChange,
}: {
  form: typeof emptyForm;
  submitLabel: string;
  disabled: boolean;
  compact?: boolean;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onChange: (form: typeof emptyForm) => void;
}) {
  return (
    <form className={compact ? 'mt-5 space-y-5' : 'mt-7 space-y-6'} onSubmit={onSubmit}>
      <div className="grid gap-5 md:grid-cols-2">
        <label className="block">
          <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Тема</span>
          <input type="text" value={form.subject} onChange={(event) => onChange({ ...form, subject: event.target.value })} placeholder="Кратко суть вопроса" className={inputClass} required />
        </label>
        <label className="block">
          <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Категория</span>
          <select value={form.category} onChange={(event) => onChange({ ...form, category: event.target.value })} className={inputClass}>
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
        <textarea rows={compact ? 4 : 5} value={form.message} onChange={(event) => onChange({ ...form, message: event.target.value })} placeholder="Опишите вопрос подробно" className={`${inputClass} resize-none`} required />
      </label>
      <div className="flex flex-col gap-3 border-t border-safi-border pt-6 md:flex-row md:items-center md:justify-between">
        {!compact && (
          <button type="button" className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green">
            <FileUp className="h-4 w-4" />
            Прикрепить файл
          </button>
        )}
        <button
          type="submit"
          disabled={disabled}
          className="inline-flex items-center justify-center rounded-full border border-safi-green bg-safi-green px-7 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)] disabled:opacity-60"
        >
          {submitLabel}
        </button>
      </div>
    </form>
  );
}

function ContactRow({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
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

function normalizeTicket(item: unknown, index = 0): SupportTicketRow {
  const ticket = isRecord(item) ? item : {};
  const statusCode = getString(ticket, ['status']) || 'open';
  const messages = Array.isArray(ticket.messages) ? ticket.messages : [];
  const staffMessage = [...messages].reverse().find((message) => {
    const record = isRecord(message) ? message : {};

    return record.is_staff === true || record.is_staff === 1;
  });
  const staffRecord = isRecord(staffMessage) ? staffMessage : undefined;

  return {
    id: getString(ticket, ['id']) || String(index + 1),
    date: getString(ticket, ['created_at']) || '',
    subject: getString(ticket, ['subject']) || '-',
    category: getString(ticket, ['category']) || '-',
    status: normalizeStatus(statusCode),
    statusCode,
    message: getString(ticket, ['message']) || '',
    adminReply: getString(ticket, ['admin_reply']) || getString(staffRecord, ['message']) || '',
    lastReply: getString(ticket, ['last_reply_at', 'replied_at']) || '-',
  };
}

function unwrapTicket(response: unknown) {
  const record = isRecord(response) ? response : {};

  if (isRecord(record.support_ticket)) {
    return record.support_ticket;
  }

  if (isRecord(record.data)) {
    if (isRecord(record.data.support_ticket)) {
      return record.data.support_ticket;
    }

    return record.data;
  }

  return record;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function normalizeStatus(status: string) {
  if (status === 'closed') {
    return 'Закрыто';
  }

  if (status === 'answered') {
    return 'Отвечено';
  }

  if (status === 'in_progress') {
    return 'В работе';
  }

  if (status === 'open') {
    return 'Новое';
  }

  return status;
}
