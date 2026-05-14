import { useEffect, useState } from 'react';
import { AdminTable, AdminBadge } from '../../components/admin/ui';
import { Filter } from 'lucide-react';
import { EmptyState, ErrorState, LoadingState } from '../../components/ui/AsyncState';
import {
  closeAdminSupportTicket,
  getAdminSupportTickets,
  getApiErrorState,
  getArray,
  getString,
  replyAdminSupportTicket,
} from '../../lib/api';

interface SupportTicketRow {
  id: string;
  date: string;
  partner: string;
  subject: string;
  category: string;
  status: string;
  lastReply: string;
}

export default function AdminSupport() {
  const [supportTickets, setSupportTickets] = useState<SupportTicketRow[]>([]);
  const [selectedTicketId, setSelectedTicketId] = useState('');
  const [replyText, setReplyText] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [message, setMessage] = useState('');

  const selectedTicket = supportTickets.find((ticket) => ticket.id === selectedTicketId);

  const loadTickets = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await getAdminSupportTickets();
      setSupportTickets(getArray(response, ['support_tickets']).map((item, index) => {
        const ticket = item && typeof item === 'object' ? item as Record<string, unknown> : {};
        const user = ticket.user && typeof ticket.user === 'object' ? ticket.user as Record<string, unknown> : {};
        return {
          id: getString(ticket, ['id']) || String(index + 1),
          date: getString(ticket, ['created_at']) || '-',
          partner: getString(user, ['name']) || '-',
          subject: getString(ticket, ['subject']) || '-',
          category: getString(ticket, ['category']) || '-',
          status: normalizeStatus(getString(ticket, ['status']) || 'open'),
          lastReply: getString(ticket, ['last_reply_at', 'replied_at']) || '-',
        };
      }));
    } catch (caughtError) {
      setSupportTickets([]);
      setError(getApiErrorState(caughtError).error || 'Не удалось загрузить обращения.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadTickets();
  }, []);

  const handleReply = async () => {
    if (!selectedTicketId || !replyText.trim()) {
      return;
    }

    setActionError(null);
    setMessage('');

    try {
      await replyAdminSupportTicket(selectedTicketId, replyText);
      setMessage('Ответ отправлен.');
      setReplyText('');
      setSelectedTicketId('');
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось отправить ответ.');
    }
  };

  const handleClose = async (ticketId: string) => {
    setActionError(null);
    setMessage('');

    try {
      await closeAdminSupportTicket(ticketId);
      setMessage('Обращение закрыто.');
      setSelectedTicketId('');
      await loadTickets();
    } catch (caughtError) {
      setActionError(getApiErrorState(caughtError).error || 'Не удалось закрыть обращение.');
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-serif font-bold text-safi-green mb-1">Обращения</h1>
          <p className="text-sm text-safi-text/70">Техподдержка и вопросы от партнёров</p>
        </div>
        <button className="flex items-center justify-center gap-2 px-6 py-3 bg-[#F5F5F0] hover:bg-safi-green/10 text-safi-green rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors md:w-auto w-full shrink-0">
          <Filter className="w-4 h-4" /> Фильтры
        </button>
      </div>

      {(message || actionError) && (
        <div className={`rounded-2xl border px-4 py-3 text-sm font-bold ${actionError ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'}`}>
          {actionError || message}
        </div>
      )}

      {selectedTicket && (
        <div className="bg-white p-6 rounded-[28px] border border-safi-green/5 shadow-sm">
          <div className="mb-4">
            <div className="text-[10px] uppercase font-bold tracking-widest text-safi-text/50">Ответ на обращение</div>
            <h3 className="mt-1 text-lg font-serif font-bold text-safi-green">{selectedTicket.subject}</h3>
          </div>
          <textarea
            rows={4}
            value={replyText}
            onChange={(event) => setReplyText(event.target.value)}
            className="w-full px-5 py-3.5 bg-[#F5F5F0] rounded-xl border-none focus:ring-2 focus:ring-safi-green/20 outline-none text-sm font-medium text-safi-green resize-none"
          />
          <div className="mt-4 flex flex-wrap gap-3">
            <button onClick={handleReply} className="px-5 py-3 bg-safi-green text-safi-gold hover:text-white rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
              Отправить ответ
            </button>
            <button onClick={() => handleClose(selectedTicket.id)} className="px-5 py-3 bg-[#F5F5F0] hover:bg-safi-green/10 text-safi-green rounded-xl font-bold uppercase tracking-widest text-[10px] transition-colors">
              Закрыть тикет
            </button>
          </div>
        </div>
      )}

      {isLoading && <LoadingState />}
      {!isLoading && error && <ErrorState description={error} onRetry={loadTickets} />}
      {!isLoading && !error && supportTickets.length === 0 && <EmptyState title="Обращений пока нет" description="Тикеты появятся после сообщений от партнеров." />}

      {!isLoading && !error && supportTickets.length > 0 && (
        <AdminTable headers={['Тикет / Даты', 'Отправитель', 'Тематика', 'Статус', 'Действие']}>
          {supportTickets.map((ticket) => (
            <tr key={ticket.id} className="hover:bg-safi-green/5 transition-colors group cursor-pointer">
              <td className="px-6 py-4">
                <div className="font-bold font-mono text-safi-green">{ticket.id}</div>
                <div className="text-[10px] text-safi-text/50 mt-1">Создан: {ticket.date}</div>
                {ticket.lastReply !== '-' && <div className="text-[10px] text-safi-gold font-bold mt-1">Ждёт ответа: {ticket.lastReply}</div>}
              </td>
              <td className="px-6 py-4">
                <div className="text-sm font-bold text-safi-green">{ticket.partner}</div>
              </td>
              <td className="px-6 py-4">
                <div className="font-bold text-safi-text mb-1">{ticket.subject}</div>
                <div className="text-xs text-safi-text/60">{ticket.category}</div>
              </td>
              <td className="px-6 py-4">
                <AdminBadge variant={ticket.status === 'Закрыто' ? 'default' : ticket.status === 'В работе' ? 'warning' : ticket.status === 'Новое' ? 'danger' : 'success'}>
                  {ticket.status}
                </AdminBadge>
              </td>
              <td className="px-6 py-4 text-right">
                <button
                  onClick={() => {
                    setSelectedTicketId(ticket.id);
                    setReplyText('');
                    setActionError(null);
                    setMessage('');
                  }}
                  className="px-4 py-2 bg-[#F5F5F0] hover:bg-safi-green hover:text-white text-safi-green text-[10px] uppercase tracking-widest font-bold rounded-lg transition-colors"
                >
                  Ответить
                </button>
              </td>
            </tr>
          ))}
        </AdminTable>
      )}
      
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
    return 'Новое';
  }

  return status;
}
