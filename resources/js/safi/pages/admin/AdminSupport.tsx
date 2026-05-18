import { useCallback, useEffect, useMemo, useState } from 'react';
import { AdminBadge, AdminTable } from '../../components/admin/ui';
import { Search, UserCheck } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import { useAdminContext } from '../../components/admin/AdminLayout';
import {
  assignAdminSupportTicket,
  getAdminSupportTicket,
  getAdminSupportTickets,
  getApiErrorState,
  getArray,
  getString,
  replyAdminSupportTicket,
  updateAdminSupportTicketStatus,
} from '../../lib/api';

interface SupportTicketMessage {
  id: string;
  author: string;
  message: string;
  isStaff: boolean;
  date: string;
}

interface SupportTicketRow {
  id: string;
  date: string;
  partner: string;
  email: string;
  subject: string;
  category: string;
  message: string;
  adminReply: string;
  status: string;
  statusCode: string;
  priority: string;
  lastReply: string;
  assignedTo: string;
  assignedToId: string;
  messages: SupportTicketMessage[];
}

const statuses = [
  { value: 'open', label: 'Новое' },
  { value: 'in_progress', label: 'В работе' },
  { value: 'answered', label: 'Отвечено' },
  { value: 'closed', label: 'Закрыто' },
];

const statusFilters = [
  { value: 'all', label: 'Все статусы' },
  ...statuses,
];

export default function AdminSupport() {
  const { currentUser } = useAdminContext();
  const [supportTickets, setSupportTickets] = useState<SupportTicketRow[]>([]);
  const [selectedTicketId, setSelectedTicketId] = useState('');
  const [selectedTicket, setSelectedTicket] = useState<SupportTicketRow | null>(null);
  const [replyText, setReplyText] = useState('');
  const [statusValue, setStatusValue] = useState('answered');
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [message, setMessage] = useState('');

  const filteredTickets = useMemo(() => {
    const query = searchQuery.trim().toLowerCase();

    return supportTickets.filter((ticket) => {
      const matchesStatus = statusFilter === 'all' || ticket.statusCode === statusFilter;
      const matchesSearch = !query || [
        ticket.id,
        ticket.partner,
        ticket.email,
        ticket.subject,
        ticket.category,
        ticket.message,
        ticket.assignedTo,
      ].some((value) => value.toLowerCase().includes(query));

      return matchesStatus && matchesSearch;
    });
  }, [searchQuery, statusFilter, supportTickets]);

  const loadTicketDetail = useCallback(async (ticketId: string, fallback?: SupportTicketRow) => {
    if (fallback) {
      setSelectedTicket(fallback);
      setReplyText(fallback.adminReply);
      setStatusValue(fallback.statusCode === 'closed' ? 'closed' : fallback.statusCode === 'open' ? 'answered' : fallback.statusCode);
    }

    setIsLoadingDetail(true);

    try {
      const response = await getAdminSupportTicket(ticketId);
      const ticket = normalizeTicket(unwrapTicket(response));

      setSelectedTicket(ticket);
      setReplyText(ticket.adminReply);
      setStatusValue(ticket.statusCode === 'closed' ? 'closed' : ticket.statusCode === 'open' ? 'answered' : ticket.statusCode);
      setSupportTickets((current) => {
        const exists = current.some((item) => item.id === ticket.id);

        if (!exists) {
          return [ticket, ...current];
        }

        return current.map((item) => item.id === ticket.id ? ticket : item);
      });
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось открыть обращение.');
    } finally {
      setIsLoadingDetail(false);
    }
  }, []);

  const selectTicket = (ticket: SupportTicketRow) => {
    setSelectedTicketId(ticket.id);
    setActionError(null);
    setMessage('');
    void loadTicketDetail(ticket.id, ticket);
  };

  const loadTickets = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminSupportTickets();
      const tickets = getArray(response, ['support_tickets']).map((item, index) => normalizeTicket(item, index));

      setSupportTickets(tickets);
      setSelectedTicket((current) => current && tickets.some((ticket) => ticket.id === current.id) ? current : tickets[0] || null);
      setSelectedTicketId((current) => current && tickets.some((ticket) => ticket.id === current) ? current : tickets[0]?.id || '');
    } catch (caughtError) {
      setSupportTickets([]);
      setSelectedTicket(null);
      setSelectedTicketId('');
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить обращения.');
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

  const handleReply = async () => {
    if (!selectedTicketId || !replyText.trim()) {
      return;
    }

    setIsSaving(true);
    setActionError(null);
    setMessage('');

    try {
      await replyAdminSupportTicket(selectedTicketId, replyText, statusValue);
      setReplyText('');
      setMessage('Ответ отправлен.');
      await loadTicketDetail(selectedTicketId);
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось отправить ответ.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleStatusChange = async () => {
    if (!selectedTicketId) {
      return;
    }

    setIsSaving(true);
    setActionError(null);
    setMessage('');

    try {
      await updateAdminSupportTicketStatus(selectedTicketId, statusValue);
      setMessage('Статус обновлён.');
      await loadTicketDetail(selectedTicketId);
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось сменить статус.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleAssign = async () => {
    if (!selectedTicketId) {
      return;
    }

    setIsSaving(true);
    setActionError(null);
    setMessage('');

    try {
      await assignAdminSupportTicket(selectedTicketId, currentUser.id ?? undefined);
      setMessage('Обращение назначено.');
      await loadTicketDetail(selectedTicketId);
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось назначить обращение.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 className="mb-1 font-serif text-3xl font-bold text-safi-green">Обращения</h1>
          <p className="text-sm text-safi-text/70">Все обращения пользователей, ответы, статусы и ответственные сотрудники</p>
        </div>
      </div>

      <section className="grid gap-3 rounded-[28px] border border-safi-border bg-white p-4 shadow-sm md:grid-cols-[1fr_220px]">
        <label className="relative block">
          <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-safi-muted" />
          <input
            value={searchQuery}
            onChange={(event) => setSearchQuery(event.target.value)}
            className="w-full rounded-2xl border border-safi-border bg-safi-cream py-3 pl-11 pr-4 text-sm font-bold text-safi-green outline-none focus:border-safi-green"
            placeholder="Поиск по ID, партнёру, email, теме"
          />
        </label>
        <select
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value)}
          className="rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-green outline-none focus:border-safi-green"
        >
          {statusFilters.map((status) => (
            <option key={status.value} value={status.value}>{status.label}</option>
          ))}
        </select>
      </section>

      {(message || actionError) && (
        <div className={`rounded-2xl border px-4 py-3 text-sm font-bold ${actionError ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
          {actionError || message}
        </div>
      )}

      {selectedTicket && (
        <section className="grid gap-6 rounded-[28px] border border-safi-green/5 bg-white p-6 shadow-sm xl:grid-cols-[0.45fr_0.55fr]">
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">Обращение #{selectedTicket.id}</div>
              <AdminBadge variant={badgeVariant(selectedTicket.statusCode)}>{selectedTicket.status}</AdminBadge>
            </div>
            <h2 className="mt-2 font-serif text-2xl font-bold text-safi-green">{selectedTicket.subject}</h2>
            <div className="mt-2 text-sm font-bold text-safi-text/70">{selectedTicket.partner} · {selectedTicket.email}</div>
            <div className="mt-2 text-xs font-extrabold uppercase tracking-[0.14em] text-safi-muted">
              Ответственный: {selectedTicket.assignedTo}
            </div>

            {isLoadingDetail && (
              <div className="mt-5 rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 text-xs font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                Загружаем детали обращения...
              </div>
            )}

            <div className="mt-5 rounded-3xl border border-safi-green/5 bg-[#F5F5F0] p-5">
              <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">{selectedTicket.category}</div>
              <p className="mt-3 whitespace-pre-line text-sm leading-7 text-safi-green">{selectedTicket.message}</p>
            </div>

            <div className="mt-4 space-y-3">
              <div className="text-[10px] font-bold uppercase tracking-widest text-safi-text/50">История</div>
              {selectedTicket.messages.length === 0 && (
                <div className="rounded-2xl border border-safi-border bg-white p-4 text-sm text-safi-muted">Сообщений пока нет.</div>
              )}
              {selectedTicket.messages.map((ticketMessage) => (
                <div key={ticketMessage.id} className={`rounded-2xl border p-4 ${ticketMessage.isStaff ? 'border-safi-green/10 bg-white' : 'border-safi-border bg-safi-cream'}`}>
                  <div className="flex items-center justify-between gap-3 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted">
                    <span>{ticketMessage.author}</span>
                    <span>{ticketMessage.date}</span>
                  </div>
                  <p className="mt-2 whitespace-pre-line text-sm leading-7 text-safi-green">{ticketMessage.message}</p>
                </div>
              ))}
            </div>
          </div>

          <div>
            <label className="block">
              <span className="mb-2 block text-[10px] font-bold uppercase tracking-widest text-safi-text/50">Ответ поддержки</span>
              <textarea
                rows={7}
                value={replyText}
                onChange={(event) => setReplyText(event.target.value)}
                className="w-full resize-none rounded-xl bg-[#F5F5F0] px-5 py-3.5 text-sm font-medium text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
                placeholder="Напишите ответ пользователю"
              />
            </label>

            <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
              <select
                value={statusValue}
                onChange={(event) => setStatusValue(event.target.value)}
                className="rounded-xl bg-[#F5F5F0] px-4 py-3 text-xs font-bold uppercase tracking-widest text-safi-green outline-none focus:ring-2 focus:ring-safi-green/20"
              >
                {statuses.map((status) => (
                  <option key={status.value} value={status.value}>{status.label}</option>
                ))}
              </select>
              <button
                onClick={handleAssign}
                disabled={isSaving}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#F5F5F0] px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:opacity-60"
              >
                <UserCheck className="h-4 w-4" />
                Назначить на меня
              </button>
            </div>

            <div className="mt-3 grid gap-3 md:grid-cols-2">
              <button
                onClick={handleReply}
                disabled={isSaving || !replyText.trim()}
                className="rounded-xl bg-safi-green px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-gold transition-colors hover:text-white disabled:opacity-60"
              >
                Отправить ответ
              </button>
              <button
                onClick={handleStatusChange}
                disabled={isSaving}
                className="rounded-xl bg-[#F5F5F0] px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green/10 disabled:opacity-60"
              >
                Сменить статус
              </button>
            </div>
          </div>
        </section>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadTickets} />}
      {!isLoading && !error && supportTickets.length === 0 && <EmptyState title="Обращений пока нет" description="Тикеты появятся после сообщений от пользователей." />}
      {!isLoading && !error && supportTickets.length > 0 && filteredTickets.length === 0 && (
        <EmptyState title="Ничего не найдено" description="Измените поиск или фильтр статуса." />
      )}

      {!isLoading && !error && filteredTickets.length > 0 && (
        <AdminTable headers={['Тикет / даты', 'Отправитель', 'Тематика', 'Статус', 'Ответственный', 'Действие']}>
          {filteredTickets.map((ticket) => (
            <tr key={ticket.id} className={`group cursor-pointer transition-colors hover:bg-safi-green/5 ${ticket.id === selectedTicketId ? 'bg-safi-green/5' : ''}`}>
              <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                <div className="font-mono font-bold text-safi-green">#{ticket.id}</div>
                <div className="mt-1 text-[10px] text-safi-text/50">Создан: {ticket.date}</div>
                {ticket.lastReply !== '-' && <div className="mt-1 text-[10px] font-bold text-safi-gold">Активность: {ticket.lastReply}</div>}
              </td>
              <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                <div className="text-sm font-bold text-safi-green">{ticket.partner}</div>
                <div className="mt-1 text-xs text-safi-text/60">{ticket.email}</div>
              </td>
              <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                <div className="mb-1 font-bold text-safi-text">{ticket.subject}</div>
                <div className="text-xs text-safi-text/60">{ticket.category}</div>
              </td>
              <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                <AdminBadge variant={badgeVariant(ticket.statusCode)}>{ticket.status}</AdminBadge>
              </td>
              <td className="px-6 py-4" onClick={() => selectTicket(ticket)}>
                <div className="text-sm font-bold text-safi-green">{ticket.assignedTo}</div>
              </td>
              <td className="px-6 py-4 text-right">
                <button
                  onClick={() => selectTicket(ticket)}
                  className="rounded-lg bg-[#F5F5F0] px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-safi-green transition-colors hover:bg-safi-green hover:text-white"
                >
                  Открыть
                </button>
              </td>
            </tr>
          ))}
        </AdminTable>
      )}
    </div>
  );
}

function normalizeTicket(item: unknown, index = 0): SupportTicketRow {
  const ticket = isRecord(item) ? item : {};
  const user = getRecord(ticket.user);
  const assignedUser = getRecord(ticket.assigned_user) || getRecord(ticket.assignedTo);
  const statusCode = getString(ticket, ['status']) || 'open';
  const messages = getMessages(ticket);
  const staffMessage = [...messages].reverse().find((message) => message.isStaff);
  const assignedToId = getString(ticket, ['assigned_to', 'assignedTo']) || getString(assignedUser, ['id']) || '';

  return {
    id: getString(ticket, ['id']) || String(index + 1),
    date: getString(ticket, ['created_at']) || '-',
    partner: getString(user, ['name']) || '-',
    email: getString(user, ['email']) || '-',
    subject: getString(ticket, ['subject']) || '-',
    category: getString(ticket, ['category']) || '-',
    message: getString(ticket, ['message']) || '',
    adminReply: getString(ticket, ['admin_reply']) || staffMessage?.message || '',
    status: normalizeStatus(statusCode),
    statusCode,
    priority: getString(ticket, ['priority']) || '-',
    lastReply: getString(ticket, ['last_reply_at', 'replied_at']) || '-',
    assignedTo: getString(assignedUser, ['name']) || (assignedToId ? `ID ${assignedToId}` : 'Не назначено'),
    assignedToId,
    messages,
  };
}

function getMessages(ticket: Record<string, unknown>): SupportTicketMessage[] {
  const messages = Array.isArray(ticket.messages) ? ticket.messages : [];

  return messages.map((item, index) => {
    const record = isRecord(item) ? item : {};
    const user = getRecord(record.user);
    const isStaff = record.is_staff === true || record.is_staff === 1;

    return {
      id: getString(record, ['id']) || String(index + 1),
      author: getString(user, ['name']) || (isStaff ? 'Support' : 'Пользователь'),
      message: getString(record, ['message']) || '',
      isStaff,
      date: getString(record, ['created_at']) || '-',
    };
  });
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

function getRecord(value: unknown): Record<string, unknown> | undefined {
  return isRecord(value) ? value : undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function badgeVariant(status: string): 'default' | 'success' | 'warning' | 'danger' | 'gold' {
  if (status === 'closed') {
    return 'default';
  }

  if (status === 'open') {
    return 'danger';
  }

  if (status === 'in_progress') {
    return 'warning';
  }

  return 'success';
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
