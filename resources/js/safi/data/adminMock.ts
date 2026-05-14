export const adminStats = {
  totalPartners: 2450,
  newPartners14Days: 186,
  activePartners: 1870,
  inactivePartners: 580,
  totalRevenue: 128500000,
  totalBonusesPaid: 34800000,
  pendingWithdrawals: 7250000,
  withdrawalRequests: 124,
  packagesSold: 1320,
  totalPV: 257000
};

export const partners = Array.from({ length: 20 }).map((_, i) => ({
  id: `SAFI-${10000 + i}`,
  fullName: ['Айдар Алиханов', 'Елена Волкова', 'Кайрат Исаев', 'Асель Муканова', 'Тимур Бекмансуров'][i % 5] + ` ${i + 1}`,
  phone: `+7 (701) 000-00-${i.toString().padStart(2, '0')}`,
  email: `partner${i}@example.com`,
  city: ['Алматы', 'Астана', 'Шымкент', 'Караганда'][i % 4],
  sponsor: `SAFI-${10000 + (i > 0 ? i - 1 : 0)}`,
  invitedCount: Math.floor(Math.random() * 20),
  package: ['BUSINESS', 'VIP', 'ELITE', 'BUSINESS'][i % 4],
  status: ['Участник', 'Менеджер', 'Лидер', 'Директор'][i % 4],
  personalPV: Math.floor(Math.random() * 1000),
  teamPV: Math.floor(Math.random() * 50000),
  leftPV: Math.floor(Math.random() * 25000),
  rightPV: Math.floor(Math.random() * 25000),
  totalIncome: Math.floor(Math.random() * 5000000),
  availableBalance: Math.floor(Math.random() * 500000),
  registrationDate: `1${i%9 + 1}.04.2026`,
  activity: i % 5 === 0 ? 'Неактивен' : 'Активен',
  accountStatus: i % 15 === 0 ? 'Заблокирован' : 'Активен'
}));

export const transactions = Array.from({ length: 20 }).map((_, i) => ({
  id: `TRX-900${i}`,
  date: `25.04.2026 14:${i.toString().padStart(2, '0')}`,
  partnerId: `SAFI-${10000 + i}`,
  partnerName: partners[i].fullName,
  type: ['Реферальный бонус', 'Бинарный бонус', 'Кэшбэк 20%', 'Вывод средств'][i % 4],
  amount: (i % 2 === 0 ? '+' : '-') + `${Math.floor(Math.random() * 50000)} тг`,
  status: ['Начислено', 'Выплачено', 'В обработке', 'Отклонено'][i % 4],
  source: 'Система',
  comment: 'Автоматическое начисление',
  processedBy: 'Система'
}));

export const withdrawals = Array.from({ length: 15 }).map((_, i) => ({
  id: `W-10${i}`,
  date: `24.04.2026`,
  partnerId: `SAFI-${10001 + i}`,
  partnerName: partners[i].fullName,
  amount: `${Math.floor(Math.random() * 200000) + 10000} тг`,
  method: i % 2 === 0 ? 'Карта партнёра' : 'Счёт ИП',
  reqs: i % 2 === 0 ? '4400 **** **** 1234' : 'KZ1234567890',
  bank: 'Kaspi Bank',
  iin: '900101400123',
  status: ['Новая заявка', 'В обработке', 'Выплачено', 'Отклонено'][i % 4],
  comment: i % 4 === 3 ? 'Ошибка реквизитов' : '',
  processedDate: i % 4 === 2 ? '25.04.2026' : '-'
}));

export const bonuses = Array.from({ length: 20 }).map((_, i) => ({
  date: `23.04.2026`,
  partnerId: `SAFI-${10000 + i}`,
  partnerName: partners[i].fullName,
  type: ['Реферальный бонус', 'Бинарный бонус', 'Статусный бонус', 'Bonus X2'][i % 4],
  basis: 'Активация VIP',
  percentage: '10%',
  amount: `${Math.floor(Math.random() * 100000)} тг`,
  status: 'Начислено',
  comment: ''
}));

export const products = [
  { id: 1, name: 'Safi Face Serum', category: 'Красота', price: 21000, pv: 35, stock: 150, status: 'Активен', added: '01.01.2026' },
  { id: 2, name: 'Safi Collagen', category: 'Здоровье', price: 15000, pv: 25, stock: 80, status: 'Активен', added: '15.01.2026' },
  { id: 3, name: 'Safi Omega 3', category: 'Здоровье', price: 12000, pv: 20, stock: 200, status: 'Активен', added: '20.01.2026' },
  { id: 4, name: 'Safi Detox Tea', category: 'Ежедневное использование', price: 8000, pv: 10, stock: 50, status: 'Нет в наличии', added: '05.02.2026' },
];

export const supportTickets = Array.from({ length: 10 }).map((_, i) => ({
  id: `T-20${i}`,
  date: `26.04.2026`,
  partner: partners[i].fullName,
  subject: ['Где посылка?', 'Вопрос по бонусу', 'Изменить спонсора', 'Ошибка вывода'][i % 4],
  category: ['Другое', 'Вопрос по бонусам', 'Вопрос по структуре', 'Вопрос по выводу'][i % 4],
  status: ['Новое', 'В работе', 'Отвечено', 'Закрыто'][i % 4],
  lastReply: i % 4 !== 0 ? '1 час назад' : '-',
  admin: i % 4 !== 0 ? 'Admin1' : '-'
}));

export const adminLogs = Array.from({ length: 15 }).map((_, i) => ({
  date: `26.04.2026 15:${i.toString().padStart(2, '0')}`,
  admin: 'Super Admin',
  action: ['Изменил статус', 'Одобрил вывод', 'Сменил пароль', 'Добавил продукт'][i % 4],
  section: ['Партнеры', 'Выводы', 'Партнеры', 'Продукты'][i % 4],
  objectId: `ID-${100 + i}`,
  comment: 'По запросу пользователя'
}));
