import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FileUp, Paperclip, Plus, Send, X } from 'lucide-react';
import { Badge } from '../../components/dashboard/ui';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import {
  ApiError,
  closeDashboardSupportTicket,
  createDashboardSupportTicket,
  downloadDashboardSupportAttachment,
  getApiErrorState,
  getArray,
  getDashboardSupportTicket,
  getDashboardSupportTickets,
  getNumber,
  getString,
  sendDashboardSupportMessage,
  unwrapRecord,
} from '../../lib/api';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25 disabled:cursor-not-allowed disabled:opacity-60';
const maxFileSize = 5 * 1024 * 1024;

interface SupportAttachment {
  id: string;
  name: string;
  mimeType: string;
  size: number;
}

interface SupportMessage {
  id: string;
  author: string;
  message: string;
  isStaff: boolean;
  date: string;
  attachments: SupportAttachment[];
}

interface SupportTicketRow {
  id: string;
  date: string;
  subject: string;
  status: string;
  statusCode: string;
  lastMessageAt: string;
  messages: SupportMessage[];
}

const emptyForm = { subject: '', message: '' };

export default function Support() {
  const [supportTickets, setSupportTickets] = useState<SupportTicketRow[]>([]);
  const [selectedTicketId, setSelectedTicketId] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [formFile, setFormFile] = useState<File | null>(null);
  const [replyText, setReplyText] = useState('');
  const [replyFile, setReplyFile] = useState<File | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const bottomRef = useRef<HTMLDivElement | null>(null);

  const selectedTicket = supportTickets.find((ticket) => ticket.id === selectedTicketId) || null;
  const isClosed = selectedTicket?.statusCode === 'closed';

  const sortedTickets = useMemo(
    () => [...supportTickets].sort((left, right) => right.lastMessageAt.localeCompare(left.lastMessageAt)),
    [supportTickets],
  );

  const loadTicketDetail = useCallback(async (ticketId: string, silent = false) => {
    if (!silent) {
      setIsLoadingDetail(true);
    }

    try {
      const response = await getDashboardSupportTicket(ticketId);
      const ticket = normalizeTicket(unwrapTicket(response));

      setSupportTickets((current) => {
        const exists = current.some((item) => item.id === ticket.id);

        return exists
          ? current.map((item) => item.id === ticket.id ? ticket : item)
          : [ticket, ...current];
      });
    } catch (caughtError) {
      if (!silent) {
        setError(getApiErrorState(caughtError).error || 'Не удалось открыть обращение.');
      }
    } finally {
      if (!silent) {
        setIsLoadingDetail(false);
      }
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
    if (!selectedTicketId || isClosed) {
      return undefined;
    }

    const timer = window.setInterval(() => {
      void loadTicketDetail(selectedTicketId, true);
    }, 12000);

    return () => window.clearInterval(timer);
  }, [selectedTicketId, isClosed, loadTicketDetail]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: 'end' });
  }, [selectedTicket?.messages.length, selectedTicketId]);

  const submitTicket = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setNotice('');
    setError('');

    if (!form.message.trim() && !formFile) {
      setError('Введите сообщение или прикрепите файл.');
      return;
    }

    if (!validateFile(formFile, setError)) {
      return;
    }

    setIsSubmitting(true);

    try {
      const payload = new FormData();
      payload.append('subject', form.subject);
      payload.append('message', form.message);

      if (formFile) {
        payload.append('file', formFile);
      }

      const response = await createDashboardSupportTicket(payload);
      const ticket = normalizeTicket(unwrapTicket(response));

      setForm(emptyForm);
      setFormFile(null);
      setNotice('Обращение создано.');
      await loadTickets();
      setSelectedTicketId(ticket.id);
    } catch (caughtError) {
      setError(caughtError instanceof ApiError ? caughtError.message : 'Не удалось создать обращение.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const sendMessage = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!selectedTicket || isClosed) {
      return;
    }

    setNotice('');
    setError('');

    if (!replyText.trim() && !replyFile) {
      setError('Введите сообщение или прикрепите файл.');
      return;
    }

    if (!validateFile(replyFile, setError)) {
      return;
    }

    setIsSending(true);

    try {
      const payload = new FormData();
      payload.append('message', replyText);

      if (replyFile) {
        payload.append('file', replyFile);
      }

      await sendDashboardSupportMessage(selectedTicket.id, payload);
      setReplyText('');
      setReplyFile(null);
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось отправить сообщение.');
    } finally {
      setIsSending(false);
    }
  };

  const closeTicket = async () => {
    if (!selectedTicket || isClosed) {
      return;
    }

    setIsSending(true);
    setNotice('');
    setError('');

    try {
      await closeDashboardSupportTicket(selectedTicket.id);
      setNotice('Обращение закрыто.');
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setError(getApiErrorState(caughtError).error || 'Не удалось закрыть обращение.');
    } finally {
      setIsSending(false);
    }
  };

  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <span className="safi-kicker">Support</span>
        <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Поддержка</h1>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
          Создайте обращение и продолжайте переписку в одном чате, пока вопрос не закрыт.
        </p>
        {(notice || error) && (
          <div className={`mt-6 rounded-2xl border px-4 py-3 text-sm font-bold ${error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
            {error || notice}
          </div>
        )}
      </section>

      <section className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <aside className="space-y-6">
          <article className="rounded-[28px] border border-safi-border bg-white p-5 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-serif text-2xl font-semibold text-safi-green">Мои обращения</h2>
              <Plus className="h-5 w-5 text-safi-gold" />
            </div>

            {isLoading && <LoadingState title="Загружаем обращения" />}
            {!isLoading && loadError && <ErrorState description={loadError} onRetry={loadTickets} />}
            {!isLoading && !loadError && sortedTickets.length === 0 && (
              <EmptyState title="Обращений пока нет" description="Создайте первое обращение ниже." />
            )}

            {!isLoading && !loadError && sortedTickets.length > 0 && (
              <div className="max-h-[520px] space-y-3 overflow-y-auto pr-1">
                {sortedTickets.map((ticket) => (
                  <button
                    key={ticket.id}
                    type="button"
                    onClick={() => setSelectedTicketId(ticket.id)}
                    className={`w-full rounded-2xl border p-4 text-left transition-colors ${ticket.id === selectedTicketId ? 'border-safi-green bg-safi-cream' : 'border-safi-border bg-white hover:bg-safi-cream/70'}`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="truncate text-sm font-extrabold text-safi-green">{ticket.subject}</div>
                        <div className="mt-1 font-mono text-[10px] text-safi-muted">#{ticket.id}</div>
                      </div>
                      <Badge variant={ticket.statusCode === 'closed' ? 'default' : ticket.statusCode === 'waiting_user' ? 'success' : 'warning'}>
                        {ticket.status}
                      </Badge>
                    </div>
                    <div className="mt-3 text-[10px] font-bold uppercase tracking-[0.12em] text-safi-muted">{ticket.lastMessageAt || ticket.date}</div>
                  </button>
                ))}
              </div>
            )}
          </article>

          <article className="rounded-[28px] border border-safi-border bg-white p-5 shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
            <h2 className="font-serif text-2xl font-semibold text-safi-green">Новое обращение</h2>
            <form className="mt-5 space-y-4" onSubmit={submitTicket}>
              <input
                value={form.subject}
                onChange={(event) => setForm((current) => ({ ...current, subject: event.target.value }))}
                className={inputClass}
                placeholder="Тема обращения"
                disabled={isSubmitting}
              />
              <textarea
                rows={4}
                value={form.message}
                onChange={(event) => setForm((current) => ({ ...current, message: event.target.value }))}
                className={inputClass}
                placeholder="Опишите вопрос"
                disabled={isSubmitting}
              />
              <FilePicker file={formFile} disabled={isSubmitting} onChange={setFormFile} />
              <button
                type="submit"
                disabled={isSubmitting || (!form.message.trim() && !formFile)}
                className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-safi-green px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green/90 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Send className="h-4 w-4 text-safi-gold" />{isSubmitting ? 'Отправляем...' : 'Создать обращение'}
              </button>
            </form>
          </article>
        </aside>

        <article className="flex min-h-[680px] flex-col rounded-[32px] border border-safi-border bg-white shadow-[0_18px_48px_rgba(11,23,18,0.05)]">
          {!selectedTicket && (
            <div className="flex flex-1 items-center justify-center p-8">
              <EmptyState title="Выберите обращение" description="История переписки появится здесь." />
            </div>
          )}

          {selectedTicket && (
            <>
              <div className="border-b border-safi-border bg-safi-cream px-6 py-5">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-safi-muted">#{selectedTicket.id}</div>
                    <h2 className="mt-1 font-serif text-2xl font-semibold text-safi-green">{selectedTicket.subject}</h2>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={selectedTicket.statusCode === 'closed' ? 'default' : selectedTicket.statusCode === 'waiting_user' ? 'success' : 'warning'}>
                      {selectedTicket.status}
                    </Badge>
                    {!isClosed && (
                      <button
                        type="button"
                        onClick={closeTicket}
                        disabled={isSending}
                        className="rounded-full border border-safi-border bg-white px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:opacity-60"
                      >
                        Закрыть
                      </button>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex-1 space-y-4 overflow-y-auto bg-[#F5F5F0]/55 p-5 md:p-6">
                {isLoadingDetail && (
                  <div className="rounded-2xl border border-safi-border bg-white px-4 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                    Обновляем чат...
                  </div>
                )}
                {selectedTicket.messages.map((item) => (
                  <ChatBubble key={item.id} message={item} onDownload={downloadDashboardSupportAttachment} />
                ))}
                <div ref={bottomRef} />
              </div>

              <div className="border-t border-safi-border bg-white p-5">
                {isClosed ? (
                  <div className="rounded-2xl border border-safi-border bg-safi-cream px-5 py-4 text-sm font-bold text-safi-muted">
                    Обращение закрыто. Создайте новое обращение, если нужна помощь.
                  </div>
                ) : (
                  <form className="space-y-4" onSubmit={sendMessage}>
                    <textarea
                      rows={3}
                      value={replyText}
                      onChange={(event) => setReplyText(event.target.value)}
                      className={inputClass}
                      placeholder="Напишите сообщение"
                      disabled={isSending}
                    />
                    <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                      <FilePicker file={replyFile} disabled={isSending} onChange={setReplyFile} />
                      <button
                        type="submit"
                        disabled={isSending || (!replyText.trim() && !replyFile)}
                        className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-2xl bg-safi-green px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green/90 disabled:cursor-not-allowed disabled:opacity-60"
                      >
                        <Send className="h-4 w-4 text-safi-gold" />Отправить
                      </button>
                    </div>
                  </form>
                )}
              </div>
            </>
          )}
        </article>
      </section>
    </div>
  );
}

function FilePicker({ file, disabled, onChange }: { file: File | null; disabled?: boolean; onChange: (file: File | null) => void }) {
  return (
    <div className="flex min-h-[56px] items-center gap-3 rounded-2xl border border-safi-border bg-white px-4 py-3">
      <FileUp className="h-5 w-5 shrink-0 text-safi-gold" />
      <label className="min-w-0 flex-1 cursor-pointer text-sm font-bold text-safi-green">
        <input
          type="file"
          className="hidden"
          disabled={disabled}
          accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt"
          onChange={(event) => onChange(event.target.files?.[0] || null)}
        />
        <span className="block truncate">{file ? file.name : 'Прикрепить файл до 5 MB'}</span>
      </label>
      {file && (
        <button
          type="button"
          onClick={() => onChange(null)}
          className="flex h-8 w-8 items-center justify-center rounded-full bg-safi-cream text-safi-green"
          aria-label="Убрать файл"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}

function ChatBubble({ message, onDownload }: { message: SupportMessage; onDownload: (id: string, filename: string) => Promise<void> }) {
  return (
    <div className={`flex ${message.isStaff ? 'justify-start' : 'justify-end'}`}>
      <div className={`max-w-[min(680px,92%)] rounded-3xl border px-5 py-4 shadow-sm ${message.isStaff ? 'border-safi-border bg-white' : 'border-safi-green bg-safi-green text-white'}`}>
        <div className={`flex flex-wrap items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.14em] ${message.isStaff ? 'text-safi-muted' : 'text-white/70'}`}>
          <span>{message.isStaff ? 'Поддержка' : 'Вы'}</span>
          <span>{message.date}</span>
        </div>
        {message.message && <p className={`mt-2 whitespace-pre-line text-sm leading-7 ${message.isStaff ? 'text-safi-green' : 'text-white'}`}>{message.message}</p>}
        {message.attachments.length > 0 && (
          <div className="mt-3 space-y-2">
            {message.attachments.map((attachment) => (
              <button
                key={attachment.id}
                type="button"
                onClick={() => void onDownload(attachment.id, attachment.name)}
                className={`flex w-full items-center gap-2 rounded-2xl px-3 py-2 text-left text-xs font-bold ${message.isStaff ? 'bg-safi-cream text-safi-green' : 'bg-white/10 text-white'}`}
              >
                <Paperclip className="h-4 w-4 shrink-0" />
                <span className="min-w-0 flex-1 truncate">{attachment.name}</span>
                <span>{formatFileSize(attachment.size)}</span>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function normalizeTicket(item: unknown, index = 0): SupportTicketRow {
  const ticket = isRecord(item) ? item : {};
  const statusCode = getString(ticket, ['status']) || 'open';
  const subject = getString(ticket, ['subject']) || `Обращение ${index + 1}`;

  return {
    id: getString(ticket, ['id']) || String(index + 1),
    date: getString(ticket, ['created_at']) || '',
    subject,
    status: normalizeStatus(statusCode),
    statusCode,
    lastMessageAt: getString(ticket, ['last_message_at', 'last_reply_at', 'updated_at', 'created_at']) || '',
    messages: normalizeMessages(ticket),
  };
}

function normalizeMessages(ticket: Record<string, unknown>): SupportMessage[] {
  return getArray(ticket.messages, []).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const user = unwrapRecord(record, ['user']);
    const isStaff = record.is_staff === true || record.is_staff === 1 || getString(record, ['sender_role']) === 'admin';

    return {
      id: getString(record, ['id']) || String(index + 1),
      author: getString(user, ['name']) || (isStaff ? 'Поддержка' : 'Вы'),
      message: getString(record, ['message']) || '',
      isStaff,
      date: getString(record, ['created_at']) || '',
      attachments: getArray(record, ['attachments']).map(normalizeAttachment),
    };
  });
}

function normalizeAttachment(item: unknown): SupportAttachment {
  const record = isRecord(item) ? item : {};

  return {
    id: getString(record, ['id']) || '',
    name: getString(record, ['original_name', 'originalName', 'name']) || 'attachment',
    mimeType: getString(record, ['mime_type', 'mimeType']) || '',
    size: getNumber(record, ['size']) ?? 0,
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

function validateFile(file: File | null, setError: (message: string) => void) {
  if (file && file.size > maxFileSize) {
    setError('Файл не должен превышать 5 MB');
    return false;
  }

  return true;
}

function normalizeStatus(status: string) {
  if (status === 'closed') {
    return 'Закрыто';
  }

  if (status === 'waiting_user') {
    return 'Ждёт пользователя';
  }

  if (status === 'waiting_admin') {
    return 'Ждёт администратора';
  }

  if (status === 'answered') {
    return 'Получен ответ';
  }

  return 'Открыто';
}

function formatFileSize(size: number) {
  if (!size) {
    return '';
  }

  if (size < 1024 * 1024) {
    return `${Math.ceil(size / 1024)} KB`;
  }

  return `${(size / 1024 / 1024).toFixed(1)} MB`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}
