import { FileUp, Filter, Mail, MessageSquare, Phone } from 'lucide-react';
import { supportTickets } from '../../data/dashboardMock';
import { Badge } from '../../components/dashboard/ui';

const inputClass = 'w-full rounded-2xl border border-safi-border bg-white px-5 py-4 text-sm font-bold text-safi-green outline-none transition-all placeholder:text-safi-muted/50 focus:border-safi-green focus:ring-2 focus:ring-safi-gold/25';

export default function Support() {
  return (
    <div className="space-y-8">
      <section className="rounded-[36px] border border-safi-border bg-white p-7 shadow-[0_18px_48px_rgba(11,23,18,0.06)] md:p-8">
        <span className="safi-kicker">Support</span>
        <h1 className="mt-3 font-serif text-4xl font-semibold text-safi-green md:text-5xl">Поддержка</h1>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-safi-muted">
          Обращения, контакты менеджера и история ответов.
        </p>
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
            <form className="mt-7 space-y-6" onSubmit={(event) => event.preventDefault()}>
              <div className="grid gap-5 md:grid-cols-2">
                <label className="block">
                  <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Тема</span>
                  <input type="text" placeholder="Кратко суть вопроса" className={inputClass} />
                </label>
                <label className="block">
                  <span className="mb-2 block text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">Категория</span>
                  <select className={inputClass}>
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
                <textarea rows={5} placeholder="Опишите вопрос подробно" className={`${inputClass} resize-none`} />
              </label>
              <div className="flex flex-col gap-3 border-t border-safi-border pt-6 md:flex-row md:items-center md:justify-between">
                <button type="button" className="inline-flex items-center justify-center gap-2 rounded-full border border-safi-border bg-safi-cream px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:border-safi-green">
                  <FileUp className="h-4 w-4" />
                  Прикрепить файл
                </button>
                <button type="submit" className="inline-flex items-center justify-center rounded-full border border-safi-green bg-safi-green px-7 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)]">
                  Отправить обращение
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
