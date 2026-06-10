import { Link, useSearchParams } from 'react-router-dom';
import { CheckCircle2, CreditCard, XCircle } from 'lucide-react';
import { Container } from '../components/ui/Container';

export default function PaymentResultPage({ result }: { result: 'success' | 'fail' }) {
  const [searchParams] = useSearchParams();
  const orderId = searchParams.get('order');
  const packageCode = searchParams.get('package');
  const isSuccess = result === 'success';
  const Icon = isSuccess ? CheckCircle2 : XCircle;

  return (
    <div className="min-h-[calc(100vh-80px)] bg-safi-bg py-20 text-safi-green">
      <Container>
        <section className="mx-auto max-w-3xl rounded-[40px] border border-safi-border bg-white p-8 text-center shadow-[0_24px_70px_rgba(11,23,18,0.08)] md:p-12">
          <div className={`mx-auto mb-7 flex h-20 w-20 items-center justify-center rounded-[28px] ${isSuccess ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
            <Icon className="h-10 w-10" />
          </div>
          <p className="safi-kicker">TipTop Pay</p>
          <h1 className="mt-4 font-serif text-4xl font-semibold md:text-6xl">
            {isSuccess ? 'Спасибо! Платеж принят в обработку.' : 'Платеж не выполнен.'}
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-sm leading-7 text-safi-muted md:text-base">
            {isSuccess
              ? 'Статус обновится после подтверждения платежной системой. Frontend callback не активирует заказ или пакет.'
              : 'Виджет TipTop Pay не подтвердил оплату. Заказ или пакет не будут активированы, пока платежная система не отправит успешное уведомление.'}
          </p>

          {!isSuccess && (
            <div className="mt-8 rounded-3xl border border-safi-border bg-safi-cream p-6 text-left">
              <div className="mb-4 flex items-center gap-3 font-serif text-2xl font-semibold text-safi-green">
                <CreditCard className="h-5 w-5 text-safi-gold" /> Что можно проверить
              </div>
              <ul className="grid gap-3 text-sm leading-7 text-safi-muted">
                <li>Проверьте корректность данных банковской карты.</li>
                <li>Убедитесь, что карта поддерживает интернет-платежи.</li>
                <li>Проверьте достаточность средств и лимиты по карте.</li>
                <li>Проверьте срок действия карты.</li>
                <li>Если банк отклоняет операцию, обратитесь в банк-эмитент.</li>
                <li>Повторите оплату из карточки заказа.</li>
              </ul>
            </div>
          )}

          <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
            <Link to={packageCode ? '/dashboard/package' : orderId ? `/dashboard/orders/${orderId}` : '/dashboard/orders'} className="inline-flex items-center justify-center rounded-full bg-safi-green px-7 py-4 text-[10px] font-extrabold uppercase tracking-[0.16em] text-white transition-colors hover:bg-safi-green-hover">
              {packageCode ? 'Пакет и статус' : orderId ? 'Открыть заказ' : 'Мои заказы'}
            </Link>
            <Link to="/payment" className="inline-flex items-center justify-center rounded-full border border-safi-green px-7 py-4 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green transition-colors hover:bg-safi-green hover:text-white">
              Информация об оплате
            </Link>
          </div>
        </section>
      </Container>
    </div>
  );
}
