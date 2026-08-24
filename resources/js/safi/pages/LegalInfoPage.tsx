import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, CreditCard, FileText, Globe2, Landmark, Mail, MapPin, Phone, RotateCcw, ShieldCheck, Truck } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { fallbackLegalSettings, getPublicLegalSettings, LegalSettings } from '../lib/api';
import { NoTranslate } from '../components/ui/NoTranslate';
import { useUiText } from '../i18n/useUiText';

export type LegalInfoPageType = 'payment' | 'offer' | 'privacy' | 'delivery' | 'refund' | 'requisites';

interface LegalInfoPageProps {
  type: LegalInfoPageType;
}

interface LegalSection {
  title: string;
  paragraphs?: string[];
  bullets?: string[];
}

const legalNav: Array<{ type: LegalInfoPageType; to: string; key: string; fallback: string }> = [
  { type: 'payment', to: '/payment', key: 'legal.payment', fallback: 'Онлайн-оплата' },
  { type: 'offer', to: '/legal/offer', key: 'legal.offer', fallback: 'Договор оферты' },
  { type: 'privacy', to: '/legal/privacy', key: 'legal.privacy', fallback: 'Политика конфиденциальности' },
  { type: 'delivery', to: '/legal/delivery', key: 'legal.delivery', fallback: 'Доставка' },
  { type: 'refund', to: '/legal/refund', key: 'legal.refund', fallback: 'Возврат' },
  { type: 'requisites', to: '/legal/requisites', key: 'legal.requisites', fallback: 'Реквизиты' },
];

export default function LegalInfoPage({ type }: LegalInfoPageProps) {
  const { t } = useTranslation();
  const ui = useUiText();
  const [settings, setSettings] = useState<LegalSettings>(fallbackLegalSettings);

  useEffect(() => {
    let isMounted = true;

    getPublicLegalSettings()
      .then((legalSettings) => {
        if (isMounted) {
          setSettings(legalSettings);
        }
      })
      .catch(() => {
        // Public legal pages remain available with safe defaults if settings API is temporarily unavailable.
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const page = useMemo(() => buildPage(type, settings, t), [settings, t, type]);
  const Icon = page.icon;

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="relative overflow-hidden border-b border-safi-border bg-safi-green py-16 text-white md:py-24">
        <div className="absolute left-[-10%] top-[-30%] h-96 w-96 rounded-full bg-safi-gold/20 blur-3xl" />
        <div className="absolute bottom-[-40%] right-[-8%] h-[420px] w-[420px] rounded-full bg-white/10 blur-3xl" />
        <Container className="relative z-10">
          <div className="mx-auto max-w-5xl">
            <div className="mb-7 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-safi-gold ring-1 ring-white/10">
              <Icon className="h-6 w-6" />
            </div>
            <p className="safi-kicker text-safi-gold">Safi Life Legal</p>
            <h1 className="mt-4 max-w-4xl font-serif text-4xl font-semibold leading-[1.05] md:text-7xl">{ui(page.title)}</h1>
            <p className="mt-7 max-w-3xl text-base leading-8 text-white/72 md:text-xl">{ui(page.subtitle)}</p>
            <p className="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-white/45">{ui('Редакция от 9 июня 2026 г.')}</p>
          </div>
        </Container>
      </section>

      <section className="bg-white py-8 md:py-10">
        <Container>
          <div className="flex gap-3 overflow-x-auto pb-2 hide-scrollbar">
            {legalNav.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                className={`shrink-0 rounded-full border px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] transition-colors ${
                  item.type === type
                    ? 'border-safi-green bg-safi-green text-safi-gold'
                    : 'border-safi-border bg-safi-bg text-safi-green/70 hover:border-safi-green/40 hover:text-safi-green'
                }`}
              >
                {t(item.key, ui(item.fallback))}
              </Link>
            ))}
            <Link to="/contacts" className="shrink-0 rounded-full border border-safi-border bg-safi-bg px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-green/70 transition-colors hover:border-safi-green/40 hover:text-safi-green">
              {t('legal.contacts', 'Контакты')}
            </Link>
          </div>
        </Container>
      </section>

      <section className="py-14 md:py-20">
        <Container>
          <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
            <main className="space-y-6">
              {page.sections.map((section) => (
                <LegalArticle key={section.title} section={section} />
              ))}

              {type === 'privacy' && (
                <div className="rounded-[28px] border border-safi-green/10 bg-white p-7 shadow-sm">
                  <h2 className="font-serif text-2xl font-semibold text-safi-green">{ui('Дополнительный документ TipTop Pay')}</h2>
                  <p className="mt-4 text-sm leading-7 text-safi-muted">
                    Для информации о подходе платежного провайдера к обработке данных можно также открыть документ TipTop Pay:{' '}
                    <a href="https://static.tiptoppay.kz/docs/privacy_policy.pdf" target="_blank" rel="noreferrer" className="font-bold text-safi-green underline decoration-safi-gold decoration-2 underline-offset-4">
                      privacy_policy.pdf
                    </a>
                  </p>
                </div>
              )}

              {type === 'requisites' && <RequisitesTable settings={settings} />}
            </main>

            <aside className="space-y-5 lg:sticky lg:top-28 lg:h-fit">
              <ContactCard settings={settings} type={type} />
              <SellerCard settings={settings} />
            </aside>
          </div>
        </Container>
      </section>
    </div>
  );
}

function LegalArticle({ section }: { section: LegalSection }) {
  const ui = useUiText();
  return (
    <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-sm md:p-9">
      <h2 className="font-serif text-2xl font-semibold leading-tight text-safi-green md:text-3xl">{ui(section.title)}</h2>
      {section.paragraphs && (
        <div className="mt-5 space-y-4 text-sm leading-7 text-safi-muted md:text-base">
          {section.paragraphs.map((paragraph) => (
            <p key={paragraph}>{paragraph}</p>
          ))}
        </div>
      )}
      {section.bullets && (
        <ul className="mt-5 grid gap-3 text-sm leading-7 text-safi-muted md:text-base">
          {section.bullets.map((bullet) => (
            <li key={bullet} className="flex gap-3">
              <span className="mt-3 h-2 w-2 shrink-0 rounded-full bg-safi-gold" />
              <span>{bullet}</span>
            </li>
          ))}
        </ul>
      )}
    </article>
  );
}

function ContactCard({ settings, type }: { settings: LegalSettings; type: LegalInfoPageType }) {
  const ui = useUiText();
  const title = type === 'refund'
    ? 'Контакты по вопросам возврата'
    : type === 'payment'
      ? 'Контакты по вопросам оплаты'
      : 'Контакты для обращений';

  return (
    <div className="rounded-[32px] bg-safi-green p-7 text-white shadow-xl">
      <h3 className="font-serif text-2xl font-semibold">{ui(title)}</h3>
      <div className="mt-6 space-y-5 text-sm leading-6 text-white/75">
        <ContactLine icon={Mail} label={ui('Поддержка')} value={settings.support_email} href={`mailto:${settings.support_email}`} />
        <ContactLine icon={Mail} label={ui('Споры и возвраты')} value={settings.dispute_email} href={`mailto:${settings.dispute_email}`} />
        <ContactLine icon={Phone} label={ui('Телефон')} value={settings.support_phone} />
        <ContactLine icon={MapPin} label={ui('Адрес')} value={settings.actual_address} />
      </div>
      <Link to="/contacts" className="mt-7 inline-flex rounded-full border border-safi-gold px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-gold transition-colors hover:bg-safi-gold hover:text-safi-green">
        {ui('Открыть контакты')}
      </Link>
    </div>
  );
}

function SellerCard({ settings }: { settings: LegalSettings }) {
  const ui = useUiText();
  return (
    <div className="rounded-[32px] border border-safi-border bg-white p-7 shadow-sm">
      <h3 className="font-serif text-2xl font-semibold text-safi-green">{ui('Юридическое лицо')}</h3>
      <div className="mt-5 space-y-4 text-sm text-safi-muted">
        <ContactLine icon={Building2} label={ui('Наименование')} value={settings.company_legal_name} dark />
        <ContactLine icon={FileText} label="БИН" value={settings.company_bin} dark />
        <ContactLine icon={Globe2} label={ui('Сайт')} value={settings.website_url} href={settings.website_url} dark />
      </div>
    </div>
  );
}

function ContactLine({ icon: Icon, label, value, href, dark = false }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string; href?: string; dark?: boolean }) {
  const content = <NoTranslate as="span" className={dark ? 'font-bold text-safi-green' : 'font-bold text-white'}>{value}</NoTranslate>;

  return (
    <div className="flex gap-3">
      <Icon className={`mt-0.5 h-4 w-4 shrink-0 ${dark ? 'text-safi-gold' : 'text-safi-gold'}`} />
      <div>
        <div className={`text-[10px] font-extrabold uppercase tracking-[0.16em] ${dark ? 'text-safi-text/45' : 'text-white/45'}`}>{label}</div>
        {href ? (
          <a href={href} target={href.startsWith('http') ? '_blank' : undefined} rel={href.startsWith('http') ? 'noreferrer' : undefined} className="transition-colors hover:text-safi-gold">
            {content}
          </a>
        ) : content}
      </div>
    </div>
  );
}

function RequisitesTable({ settings }: { settings: LegalSettings }) {
  const ui = useUiText();
  const rows = [
    ['Наименование юридического лица', settings.company_legal_name],
    ['БИН', settings.company_bin],
    ['Юридический адрес', settings.legal_address],
    ['Фактический адрес', settings.actual_address],
    ['Банк', settings.bank_name],
    ['ИИК', settings.iban],
    ['БИК', settings.bik],
    ['КБе', settings.kbe],
    ['Телефон', settings.support_phone],
    ['Email', settings.support_email],
    ['Руководитель', settings.director_name],
    ['Сайт', settings.website_url],
  ];

  return (
    <article className="rounded-[32px] border border-safi-border bg-white p-7 shadow-sm md:p-9">
      <h2 className="font-serif text-2xl font-semibold leading-tight text-safi-green md:text-3xl">{ui('Реквизиты продавца')}</h2>
      <div className="mt-6 overflow-hidden rounded-3xl border border-safi-border">
        {rows.map(([label, value]) => (
          <div key={label} className="grid gap-2 border-b border-safi-border p-5 last:border-b-0 md:grid-cols-[260px_minmax(0,1fr)]">
            <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-text/45">{ui(label)}</div>
            <NoTranslate as="div" className="break-words text-sm font-bold leading-7 text-safi-green">{value}</NoTranslate>
          </div>
        ))}
      </div>
    </article>
  );
}

function buildPage(type: LegalInfoPageType, settings: LegalSettings, t: (key: string, fallback: string) => string) {
  const seller = settings.company_legal_name;

  if (type === 'payment') {
    return {
      icon: CreditCard,
      title: t('legal.cardPayment', 'Оплата банковской картой онлайн'),
      subtitle: 'Информация о безопасной оплате заказов Safi Life через платежную страницу TipTop Pay.',
      sections: [
        {
          title: 'Как проходит онлайн-оплата',
          bullets: [
            'Сайт Safi Life подключается к интернет-эквайрингу TipTop Pay.',
            'Оплата возможна банковскими картами Visa и Mastercard, выпущенными банками, поддерживающими интернет-платежи.',
            'После подтверждения заказа откроется защищенное окно платежной страницы TipTop Pay.',
            'Для дополнительной аутентификации держателя карты может использоваться 3-D Secure.',
            'Данные банковской карты вводятся в защищенном окне TipTop Pay.',
            t('legal.cardDataNotStored', 'Данные карты не сохраняются на сайте Safi Life.'),
            'Передача платежных данных происходит по защищенным каналам с использованием современных протоколов безопасности.',
          ],
        },
        {
          title: 'Подтверждение и поддержка по платежу',
          paragraphs: [
            'После успешной оплаты пользователь получает подтверждение заказа в интерфейсе сайта или личного кабинета. Если банк отклоняет платеж, заказ не считается оплаченным до успешного проведения операции.',
            `По вопросам оплаты, спорных ситуаций и возвратов можно обратиться в поддержку ${seller} по контактам, указанным на этой странице.`,
          ],
        },
      ],
    };
  }

  if (type === 'offer') {
    return {
      icon: FileText,
      title: t('legal.offerAgreement', 'Договор публичной оферты'),
      subtitle: 'Публичная оферта о продаже товаров Safi Life и использовании сайта на территории Республики Казахстан.',
      sections: [
        {
          title: '1. Общие положения',
          paragraphs: [
            `Настоящий договор публичной оферты определяет условия продажи товаров и оказания сопутствующих сервисов ${seller} через сайт Safi Life.`,
            'Оформляя заказ, регистрируясь на сайте или оплачивая товары, покупатель подтверждает согласие с условиями настоящей оферты. Договор регулируется законодательством Республики Казахстан.',
          ],
        },
        {
          title: '2. Предмет договора',
          paragraphs: [
            'Продавец обязуется передать покупателю товары Safi Life, а покупатель обязуется принять товары и оплатить их в порядке, установленном на сайте.',
            'Наименование, цена, количество, характеристики и доступность товаров указываются в каталоге и подтверждаются при оформлении заказа.',
          ],
        },
        {
          title: '3. Регистрация и учетная запись',
          paragraphs: [
            'Покупатель или партнер обязан указывать достоверные регистрационные данные, актуальный номер телефона, email и адрес доставки.',
            'Пользователь несет ответственность за сохранность доступа к личному кабинету и своевременное обновление контактной информации.',
          ],
        },
        {
          title: '4. Товары, цены и оплата',
          paragraphs: [
            'Цены на сайте указываются в тенге Республики Казахстан. Оплата производится доступными на сайте способами, включая онлайн-оплату банковской картой через TipTop Pay.',
            'Заказ считается принятым к обработке после подтверждения заявки и успешного поступления оплаты, если для выбранного способа оплаты требуется предварительная оплата.',
          ],
        },
        {
          title: '5. Доставка',
          paragraphs: [
            'Доставка осуществляется по территории Республики Казахстан. Условия, сроки и доступность доставки подтверждаются менеджером после оформления заказа.',
            'Покупатель обязан обеспечить корректность адреса доставки и доступность по указанному телефону.',
          ],
        },
        {
          title: '6. Возвраты',
          paragraphs: [
            'Возврат товара и денежных средств осуществляется в соответствии с правилами возврата, опубликованными на сайте, и применимым законодательством Республики Казахстан.',
            'При онлайн-оплате возврат денежных средств производится на банковскую карту, с которой была выполнена оплата.',
          ],
        },
        {
          title: '7. Права и обязанности сторон',
          bullets: [
            'Продавец обязан предоставить актуальную информацию о товарах, оплате, доставке и возврате.',
            'Покупатель обязан своевременно оплатить заказ и предоставить достоверные данные для доставки и связи.',
            'Продавец вправе отказать в обработке заказа при отсутствии товара, некорректных данных или невозможности связаться с покупателем.',
          ],
        },
        {
          title: '8. Персональные данные и ответственность',
          paragraphs: [
            'Обработка персональных данных осуществляется согласно политике конфиденциальности Safi Life.',
            'Стороны несут ответственность за нарушение условий оферты в соответствии с законодательством Республики Казахстан. Продавец не отвечает за сбои банков, операторов связи, платежных систем и иных третьих лиц, если такие сбои находятся вне контроля продавца.',
          ],
        },
        {
          title: '9. Реквизиты продавца',
          paragraphs: [
            `${settings.company_legal_name}, БИН ${settings.company_bin}, юридический адрес: ${settings.legal_address}. Банк: ${settings.bank_name}, ИИК: ${settings.iban}, БИК: ${settings.bik}.`,
          ],
        },
      ],
    };
  }

  if (type === 'privacy') {
    return {
      icon: ShieldCheck,
      title: t('legal.privacyPolicy', 'Политика конфиденциальности'),
      subtitle: 'Правила обработки и защиты персональных данных пользователей Safi Life.',
      sections: [
        {
          title: '1. Термины и область применения',
          paragraphs: [
            'Настоящая политика описывает, какие персональные данные собирает Safi Life, для каких целей они используются и как защищаются.',
            `Оператором данных для целей настоящей политики является ${seller}.`,
          ],
        },
        {
          title: '2. Какие данные собираются',
          bullets: [
            'ФИО, логин, email, номер телефона и данные учетной записи.',
            'Адрес доставки, город, комментарии к заказу и история заказов.',
            'Платежный статус заказа и технические идентификаторы платежа. Полные данные банковской карты на сайте Safi Life не хранятся.',
            'Технические данные: IP-адрес, тип устройства, cookies, события безопасности и аналитики.',
          ],
        },
        {
          title: '3. Цели обработки',
          bullets: [
            'Регистрация пользователя и предоставление доступа к личному кабинету.',
            'Оформление, оплата, доставка и сопровождение заказов.',
            'Поддержка пользователей, рассмотрение обращений, споров и возвратов.',
            'Выполнение требований законодательства Республики Казахстан, бухгалтерского учета и аудита.',
            'Улучшение работы сайта, безопасности и пользовательского опыта.',
          ],
        },
        {
          title: '4. Передача третьим лицам',
          paragraphs: [
            'Данные могут передаваться платежным провайдерам, банкам, службам доставки, поставщикам хостинга, технической поддержки и государственным органам в случаях, предусмотренных законодательством.',
            'Передача данных ограничивается объемом, необходимым для исполнения заказа, проведения платежа, доставки, поддержки или законного требования.',
          ],
        },
        {
          title: '5. Хранение, безопасность, cookies',
          paragraphs: [
            'Safi Life принимает организационные и технические меры для защиты персональных данных от неправомерного доступа, изменения, раскрытия или уничтожения.',
            'Cookies и аналитические события могут использоваться для авторизации, безопасности, сохранения настроек языка, анализа посещаемости и улучшения сайта.',
          ],
        },
        {
          title: '6. Контакты по конфиденциальности',
          paragraphs: [
            `По вопросам обработки персональных данных можно обратиться на ${settings.privacy_email}. Реквизиты оператора: ${settings.company_legal_name}, БИН ${settings.company_bin}, адрес: ${settings.legal_address}.`,
          ],
        },
      ],
    };
  }

  if (type === 'delivery') {
    return {
      icon: Truck,
      title: t('legal.deliveryRules', 'Правила доставки'),
      subtitle: 'Порядок доставки заказов Safi Life по территории Республики Казахстан.',
      sections: [
        {
          title: 'Доставка по Казахстану',
          paragraphs: [
            'Доставка товаров Safi Life осуществляется по территории Республики Казахстан доступными службами доставки или иным способом, согласованным с покупателем.',
            'Город, адрес доставки, телефон получателя и комментарии указываются покупателем при оформлении заказа.',
          ],
        },
        {
          title: 'Сроки и подтверждение заказа',
          paragraphs: [
            'После оформления заказа менеджер подтверждает наличие товара, корректность контактных данных и условия доставки.',
            'Срок доставки зависит от города, доступности товара, графика службы доставки и корректности данных, указанных покупателем.',
          ],
        },
        {
          title: 'Обязанности покупателя',
          bullets: [
            'Указать корректный телефон, город и адрес доставки.',
            'Быть доступным для подтверждения заказа и получения товара.',
            'Проверить комплектность заказа при получении и своевременно сообщить о замечаниях.',
            'Отслеживать статус заказа в личном кабинете Safi Life.',
          ],
        },
      ],
    };
  }

  if (type === 'refund') {
    return {
      icon: RotateCcw,
      title: t('legal.refundMoney', 'Возврат денежных средств'),
      subtitle: 'Правила возврата денежных средств и обработки отказов платежей Safi Life.',
      sections: [
        {
          title: 'Порядок возврата',
          paragraphs: [
            'При онлайн-оплате возврат наличными денежными средствами не допускается.',
            'Возврат осуществляется на банковскую карту, с которой была произведена оплата, если иное не предусмотрено законодательством Республики Казахстан и правилами платежной системы.',
            `Заявление на возврат направляется на email ${settings.dispute_email}. В заявлении необходимо указать номер заказа, ФИО, контактный телефон, дату оплаты и причину возврата.`,
            'Срок рассмотрения заявления составляет до 10 рабочих дней. Срок зачисления денежных средств зависит от банка-эмитента карты и обычно составляет до 15 рабочих дней после подтверждения возврата.',
          ],
        },
        {
          title: 'Возможные причины отказа платежа',
          bullets: [
            'Банковская карта не предназначена для интернет-платежей или операция запрещена банком-эмитентом.',
            'Недостаточно денежных средств на банковской карте.',
            'Введены неверные данные карты, код подтверждения или 3-D Secure пароль.',
            'Истек срок действия банковской карты.',
            'Банк или платежная система временно ограничили проведение операции.',
          ],
        },
        {
          title: 'Спорные ситуации',
          paragraphs: [
            `По вопросам возврата и спорных операций пользователь может обратиться в поддержку по телефону ${settings.support_phone} или на email ${settings.dispute_email}.`,
          ],
        },
      ],
    };
  }

  return {
    icon: Landmark,
    title: t('legal.legalEntity', 'Реквизиты и информация о юридическом лице'),
    subtitle: 'Официальные данные продавца Safi Life для оплаты, договоров, обращений и спорных ситуаций.',
    sections: [
      {
        title: 'Информация о компании',
        paragraphs: [
          'Реквизиты используются для договоров, бухгалтерских документов, обработки обращений, возвратов и взаимодействия с платежными провайдерами.',
          'Если данные компании были изменены, актуальная информация публикуется на этой странице и в настройках Safi Life.',
        ],
      },
    ],
  };
}
