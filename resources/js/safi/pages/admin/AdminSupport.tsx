import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FileUp, Paperclip, RotateCcw, Search, Send, X } from 'lucide-react';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { AdminPagination } from '../../components/admin/AdminPagination';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { adminText } from '../../i18n/adminText';
import {
  closeAdminSupportTicket,
  downloadAdminSupportAttachment,
  getAdminSupportTicket,
  getAdminSupportTickets,
  getApiErrorState,
  getArray,
  getNumber,
  getString,
  reopenAdminSupportTicket,
  replyAdminSupportTicket,
  unwrapRecord,
} from '../../lib/api';

const maxFileSize = 5 * 1024 * 1024;
const pageSizeOptions = [10, 20, 50, 100];

interface SupportAttachment {
  id: string;
  name: string;
  size: number;
}

interface SupportTicketMessage {
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
  partnerId: string;
  partner: string;
  email: string;
  subject: string;
  status: string;
  statusCode: string;
  lastMessageAt: string;
  messages: SupportTicketMessage[];
}

interface SupportPagination {
  total: number;
  perPage: number;
  currentPage: number;
  lastPage: number;
  from: number;
  to: number;
}

const statuses = [
  { value: '', label: adminText('a_0JLRgdC1INGB') },
  { value: 'waiting_admin', label: 'Ждёт администратора' },
  { value: 'waiting_user', label: 'Ждёт пользователя' },
  { value: 'open', label: adminText('a_0J3QvtCy0L7Q') },
  { value: 'closed', label: adminText('a_0JfQsNC60YDR_2') },
];

export default function AdminSupport() {
  const [supportTickets, setSupportTickets] = useState<SupportTicketRow[]>([]);
  const [selectedTicketId, setSelectedTicketId] = useState('');
  const [selectedTicket, setSelectedTicket] = useState<SupportTicketRow | null>(null);
  const [replyText, setReplyText] = useState('');
  const [replyFile, setReplyFile] = useState<File | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [pagination, setPagination] = useState<SupportPagination>({
    total: 0,
    perPage: 20,
    currentPage: 1,
    lastPage: 1,
    from: 0,
    to: 0,
  });
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const bottomRef = useRef<HTMLDivElement | null>(null);

  const isClosed = selectedTicket?.statusCode === 'closed';
  const paginationMeta = useMemo(() => ({
    current_page: pagination.currentPage,
    last_page: pagination.lastPage,
    per_page: pagination.perPage,
    total: pagination.total,
    from: pagination.from,
    to: pagination.to,
  }), [pagination]);

  const loadTicketDetail = useCallback(async (ticketId: string, silent = false) => {
    if (!silent) {
      setIsLoadingDetail(true);
    }

    try {
      const response = await getAdminSupportTicket(ticketId);
      const ticket = normalizeTicket(unwrapTicket(response));

      setSelectedTicket(ticket);
      setSupportTickets((current) => {
        const exists = current.some((item) => item.id === ticket.id);

        return exists
          ? current.map((item) => item.id === ticket.id ? ticket : item)
          : [ticket, ...current];
      });
    } catch (caughtError) {
      if (!silent) {
        setActionError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_27'));
      }
    } finally {
      if (!silent) {
        setIsLoadingDetail(false);
      }
    }
  }, []);

  const loadTickets = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminSupportTickets({
        ...(statusFilter ? { status: statusFilter } : {}),
        ...(searchTerm ? { q: searchTerm } : {}),
        page,
        per_page: perPage,
      });
      const tickets = getArray(response, ['support_tickets']).map((item, index) => normalizeTicket(item, index));

      setSupportTickets(tickets);
      setPagination(normalizePagination(response, page, perPage, tickets.length));
      setSelectedTicketId((current) => current && tickets.some((ticket) => ticket.id === current) ? current : tickets[0]?.id || '');
      setSelectedTicket((current) => current && tickets.some((ticket) => ticket.id === current.id) ? current : tickets[0] || null);
    } catch (caughtError) {
      setSupportTickets([]);
      setSelectedTicket(null);
      setSelectedTicketId('');
      setError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_28'));
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter, searchTerm, page, perPage]);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setPage(1);
      setSearchTerm(searchQuery.trim());
    }, searchQuery.trim() ? 350 : 0);

    return () => window.clearTimeout(timeout);
  }, [searchQuery]);

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

  const selectTicket = (ticket: SupportTicketRow) => {
    setSelectedTicketId(ticket.id);
    setSelectedTicket(ticket);
    setActionError(null);
    setMessage('');
  };

  const handleReply = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!selectedTicket || isClosed) {
      return;
    }

    setActionError(null);
    setMessage('');

    if (!replyText.trim() && !replyFile) {
      setActionError('Введите сообщение или прикрепите файл.');
      return;
    }

    if (!validateFile(replyFile, setActionError)) {
      return;
    }

    setIsSaving(true);

    try {
      const payload = new FormData();
      payload.append('message', replyText);

      if (replyFile) {
        payload.append('file', replyFile);
      }

      await replyAdminSupportTicket(selectedTicket.id, payload);
      setReplyText('');
      setReplyFile(null);
      setMessage(adminText('a_0J7RgtCy0LXR_2'));
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || adminText('a_0J3QtSDRg9C0_29'));
    } finally {
      setIsSaving(false);
    }
  };

  const handleClose = async () => {
    if (!selectedTicket || isClosed) {
      return;
    }

    setIsSaving(true);
    setActionError(null);
    setMessage('');

    try {
      await closeAdminSupportTicket(selectedTicket.id);
      setMessage('Обращение закрыто.');
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось закрыть обращение.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleReopen = async () => {
    if (!selectedTicket || !isClosed) {
      return;
    }

    setIsSaving(true);
    setActionError(null);
    setMessage('');

    try {
      await reopenAdminSupportTicket(selectedTicket.id);
      setMessage('Обращение переоткрыто.');
      await loadTicketDetail(selectedTicket.id);
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось переоткрыть обращение.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 className="mb-1 font-serif text-3xl font-bold text-safi-green">{adminText('a_0J7QsdGA0LDR_2')}</h1>
          <p className="text-sm text-safi-text/70">Чаты поддержки между партнёрами и администраторами.</p>
        </div>
      </div>

      <section className="grid gap-3 rounded-[28px] border border-safi-border bg-white p-4 shadow-sm md:grid-cols-[1fr_220px]">
        <label className="relative block">
          <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
          <input
            value={searchQuery}
            onChange={(event) => setSearchQuery(event.target.value)}
            className="w-full rounded-2xl border border-safi-border bg-safi-cream py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
            placeholder="Поиск по имени, email, ID или теме"
          />
        </label>
        <select
          value={statusFilter}
          onChange={(event) => {
            setStatusFilter(event.target.value);
            setPage(1);
          }}
          className="rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green outline-none focus:border-safi-green"
        >
          {statuses.map((status) => (
            <option key={status.value || 'all'} value={status.value}>{status.label}</option>
          ))}
        </select>
      </section>

      {(message || actionError) && (
        <div className={`rounded-2xl border px-4 py-3 text-sm font-bold ${actionError ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
          {actionError || message}
        </div>
      )}

      <section className="grid gap-6 xl:grid-cols-[minmax(440px,0.48fr)_minmax(0,0.52fr)]">
        <div className="space-y-4">
          {isLoading && <LoadingState />}
          {!isLoading && error && <ErrorState description={error} onRetry={loadTickets} />}
          {!isLoading && !error && supportTickets.length === 0 && <EmptyState title={adminText('a_0J7QsdGA0LDR_4')} description={adminText('a_0KLQuNC60LXR')} />}

          {!isLoading && !error && supportTickets.length > 0 && (
            <>
              <AdminTable headers={['ID', 'Партнёр', 'Тема', 'Статус', 'Последнее', '']}>
                {supportTickets.map((ticket) => (
                  <tr key={ticket.id} className={`group cursor-pointer transition-colors hover:bg-safi-green/5 ${ticket.id === selectedTicketId ? 'bg-safi-green/5' : ''}`}>
                    <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                      <div className="font-mono font-bold text-safi-green">#{ticket.id}</div>
                      <div className="mt-1 text-[10px] text-safi-text/50">{ticket.date}</div>
                    </td>
                    <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                      <div className="text-sm font-bold text-safi-green">{ticket.partner}</div>
                      <div className="mt-1 text-xs text-safi-text/60">ID {ticket.partnerId} · {ticket.email}</div>
                    </td>
                    <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                      <div className="max-w-[220px] truncate font-bold text-safi-text">{ticket.subject}</div>
                    </td>
                    <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                      <AdminBadge variant={badgeVariant(ticket.statusCode)}>{ticket.status}</AdminBadge>
                    </td>
                    <td className="px-6 py-4 text-xs font-bold text-safi-muted" onClick={() => selectTicket(ticket)}>{ticket.lastMessageAt || '-'}</td>
                    <td className="px-6 py-4 text-right">
                      <button
                        onClick={() => selectTicket(ticket)}
                        className="rounded-lg bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                      >Открыть</button>
                    </td>
                  </tr>
                ))}
              </AdminTable>

              <AdminPagination
                meta={paginationMeta}
                perPageOptions={pageSizeOptions}
                onPageChange={setPage}
                onPerPageChange={(nextPerPage) => {
                  setPerPage(nextPerPage);
                  setPage(1);
                }}
              />
            </>
          )}
        </div>

        <article className="flex min-h-[680px] flex-col rounded-[28px] border border-safi-green/5 bg-white shadow-sm">
          {!selectedTicket && (
            <div className="flex flex-1 items-center justify-center p-8">
              <EmptyState title="Выберите обращение" description="Чат и действия появятся здесь." />
            </div>
          )}

          {selectedTicket && (
            <>
              <div className="border-b border-safi-border bg-safi-cream px-6 py-5">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">Обращение #{selectedTicket.id}</div>
                    <h2 className="mt-2 font-serif text-2xl font-bold text-safi-green">{selectedTicket.subject}</h2>
                    <div className="mt-2 text-sm font-bold text-safi-text/70">{selectedTicket.partner} · ID {selectedTicket.partnerId}</div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <AdminBadge variant={badgeVariant(selectedTicket.statusCode)}>{selectedTicket.status}</AdminBadge>
                    {isClosed ? (
                      <button
                        type="button"
                        onClick={handleReopen}
                        disabled={isSaving}
                        className="inline-flex items-center gap-2 rounded-full border border-safi-border bg-white px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:opacity-60"
                      >
                        <RotateCcw className="h-4 w-4" />Переоткрыть
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={handleClose}
                        disabled={isSaving}
                        className="rounded-full border border-safi-border bg-white px-4 py-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-green transition-colors hover:border-safi-green disabled:opacity-60"
                      >
                        Закрыть обращение
                      </button>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex-1 space-y-4 overflow-y-auto bg-[#F5F5F0]/55 p-5">
                {isLoadingDetail && (
                  <div className="rounded-2xl border border-safi-border bg-white px-4 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-safi-muted">{adminText('a_0JfQsNCz0YDR')}</div>
                )}
                {selectedTicket.messages.map((ticketMessage) => (
                  <ChatBubble key={ticketMessage.id} message={ticketMessage} onDownload={downloadAdminSupportAttachment} />
                ))}
                <div ref={bottomRef} />
              </div>

              <div className="border-t border-safi-border bg-white p-5">
                {isClosed ? (
                  <div className="rounded-2xl border border-safi-border bg-safi-cream px-5 py-4 text-sm font-bold text-safi-muted">
                    Обращение закрыто. Чат доступен только для чтения.
                  </div>
                ) : (
                  <form className="space-y-4" onSubmit={handleReply}>
                    <textarea
                      rows={4}
                      value={replyText}
                      onChange={(event) => setReplyText(event.target.value)}
                      className="w-full resize-none rounded-xl bg-[#F5F5F0] px-5 py-3.5 text-sm font-medium text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
                      placeholder={adminText('a_0J3QsNC_0LjR')}
                    />
                    <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                      <FilePicker file={replyFile} disabled={isSaving} onChange={setReplyFile} />
                      <button
                        type="submit"
                        disabled={isSaving || (!replyText.trim() && !replyFile)}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-safi-green px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:opacity-60"
                      >
                        <Send className="h-4 w-4" />Ответить
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
    <div className="flex min-h-[48px] items-center gap-3 rounded-xl bg-[#F5F5F0] px-4 py-3">
      <FileUp className="h-4 w-4 shrink-0 text-safi-gold" />
      <label className="min-w-0 flex-1 cursor-pointer text-xs font-bold text-safi-green">
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
          className="flex h-7 w-7 items-center justify-center rounded-full bg-white text-safi-green"
          aria-label="Убрать файл"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}

function ChatBubble({ message, onDownload }: { message: SupportTicketMessage; onDownload: (id: string, filename: string) => Promise<void> }) {
  return (
    <div className={`flex ${message.isStaff ? 'justify-end' : 'justify-start'}`}>
      <div className={`max-w-[min(680px,92%)] rounded-3xl border px-5 py-4 shadow-sm ${message.isStaff ? 'border-safi-green bg-safi-green text-white' : 'border-safi-border bg-white'}`}>
        <div className={`flex flex-wrap items-center gap-2 text-[10px] font-extrabold uppercase tracking-[0.14em] ${message.isStaff ? 'text-white/70' : 'text-safi-muted'}`}>
          <span>{message.isStaff ? 'Админ' : message.author}</span>
          <span>{message.date}</span>
        </div>
        {message.message && <p className={`mt-2 whitespace-pre-line text-sm leading-7 ${message.isStaff ? 'text-white' : 'text-safi-green'}`}>{message.message}</p>}
        {message.attachments.length > 0 && (
          <div className="mt-3 space-y-2">
            {message.attachments.map((attachment) => (
              <button
                key={attachment.id}
                type="button"
                onClick={() => void onDownload(attachment.id, attachment.name)}
                className={`flex w-full items-center gap-2 rounded-2xl px-3 py-2 text-left text-xs font-bold ${message.isStaff ? 'bg-white/10 text-white' : 'bg-safi-cream text-safi-green'}`}
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
  const user = unwrapRecord(ticket, ['user']);
  const statusCode = getString(ticket, ['status']) || 'open';

  return {
    id: getString(ticket, ['id']) || String(index + 1),
    date: getString(ticket, ['created_at']) || '-',
    partnerId: getString(user, ['id']) || getString(ticket, ['user_id']) || '-',
    partner: getString(user, ['name', 'login']) || '-',
    email: getString(user, ['email']) || '-',
    subject: getString(ticket, ['subject']) || '-',
    status: normalizeStatus(statusCode),
    statusCode,
    lastMessageAt: getString(ticket, ['last_message_at', 'last_reply_at', 'updated_at', 'created_at']) || '-',
    messages: normalizeMessages(ticket),
  };
}

function normalizeMessages(ticket: Record<string, unknown>): SupportTicketMessage[] {
  return getArray(ticket.messages, []).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const user = unwrapRecord(record, ['user']);
    const isStaff = record.is_staff === true || record.is_staff === 1 || getString(record, ['sender_role']) === 'admin';

    return {
      id: getString(record, ['id']) || String(index + 1),
      author: getString(user, ['name']) || (isStaff ? 'Админ' : 'Партнёр'),
      message: getString(record, ['message']) || '',
      isStaff,
      date: getString(record, ['created_at']) || '-',
      attachments: getArray(record, ['attachments']).map(normalizeAttachment),
    };
  });
}

function normalizeAttachment(item: unknown): SupportAttachment {
  const record = isRecord(item) ? item : {};

  return {
    id: getString(record, ['id']) || '',
    name: getString(record, ['original_name', 'originalName', 'name']) || 'attachment',
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

function normalizePagination(response: unknown, fallbackPage: number, fallbackPerPage: number, rowCount: number): SupportPagination {
  const meta = unwrapRecord(response, ['meta']);
  const total = getNumber(meta, ['total']) ?? rowCount;
  const perPage = getNumber(meta, ['per_page', 'perPage']) ?? fallbackPerPage;
  const currentPage = getNumber(meta, ['current_page', 'currentPage']) ?? fallbackPage;

  return {
    total,
    perPage,
    currentPage,
    lastPage: getNumber(meta, ['last_page', 'lastPage']) ?? Math.max(1, Math.ceil(total / perPage)),
    from: getNumber(meta, ['from']) ?? (total === 0 ? 0 : ((currentPage - 1) * perPage) + 1),
    to: getNumber(meta, ['to']) ?? (total === 0 ? 0 : Math.min(currentPage * perPage, total)),
  };
}

function validateFile(file: File | null, setError: (message: string | null) => void) {
  if (file && file.size > maxFileSize) {
    setError('Файл не должен превышать 5 MB');
    return false;
  }

  return true;
}

function badgeVariant(status: string): 'default' | 'success' | 'warning' | 'danger' | 'gold' {
  if (status === 'closed') {
    return 'default';
  }

  if (status === 'waiting_admin' || status === 'open') {
    return 'danger';
  }

  if (status === 'waiting_user') {
    return 'success';
  }

  return 'warning';
}

function normalizeStatus(status: string) {
  if (status === 'closed') {
    return adminText('a_0JfQsNC60YDR_2');
  }

  if (status === 'waiting_admin') {
    return 'Ждёт администратора';
  }

  if (status === 'waiting_user') {
    return 'Ждёт пользователя';
  }

  if (status === 'answered') {
    return adminText('a_0J7RgtCy0LXR');
  }

  return adminText('a_0J3QvtCy0L7Q');
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
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
