export const user = {
  name: "Айдар Алиханов",
  partnerId: "SAFI-10234",
  referralCode: "SAFI10234",
  package: "VIP",
  status: "Лидер",
  sponsor: "Иван Иванов",
  registrationDate: "15.04.2026"
};

export const balance = {
  totalEarned: 850000,
  available: 320000,
  pending: 75000,
  withdrawn: 455000
};

export const structure = {
  totalPartners: 128,
  leftPartners: 65,
  rightPartners: 63,
  leftPV: 14500,
  rightPV: 12800,
  weakLeg: "Правая"
};

export const bonuses = {
  referral: 240000,
  binary: 380000,
  status: 250000,
  cashback: 60000,
  deposit: 38000,
  bonusX2: 0
};

export const transactions = [
  { id: "TRX-101", date: "25.04.2026 14:30", type: "Реферальный бонус", amount: "+18 000 тг", status: "Начислено", source: "Партнёр: Айдар", comment: "10% от пакета VIP" },
  { id: "TRX-102", date: "24.04.2026 10:15", type: "Бинарный бонус", amount: "+45 000 тг", status: "Начислено", source: "Левая ветка", comment: "8% от 562.5 PV" },
  { id: "TRX-103", date: "22.04.2026 09:00", type: "Кэшбэк 20%", amount: "+4 200 тг", status: "Начислено", source: "Личная покупка", comment: "Safi Face Serum" },
  { id: "TRX-104", date: "20.04.2026 16:45", type: "Вывод средств", amount: "-150 000 тг", status: "Выплачено", source: "Вывод на карту", comment: "Заявка #W-55" },
  { id: "TRX-105", date: "18.04.2026 11:20", type: "Реферальный бонус", amount: "+6 000 тг", status: "Начислено", source: "Партнёр: Елена", comment: "10% от пакета BUSINESS" },
  { id: "TRX-106", date: "15.04.2026 14:00", type: "Статусный бонус", amount: "+250 000 тг", status: "Начислено", source: "Достижение статуса Лидер", comment: "Единоразовая премия" },
  { id: "TRX-107", date: "14.04.2026 09:30", type: "Вывод средств", amount: "-200 000 тг", status: "Выплачено", source: "Счёт ИП", comment: "Заявка #W-42" },
  { id: "TRX-108", date: "12.04.2026 18:15", type: "Бинарный бонус", amount: "+32 000 тг", status: "Начислено", source: "Правая ветка", comment: "8% от 400 PV" },
  { id: "TRX-109", date: "10.04.2026 12:00", type: "Депозит 10%", amount: "+3 200 тг", status: "Начислено", source: "Бинарный бонус", comment: "Накопительный счет" },
  { id: "TRX-110", date: "05.04.2026 15:45", type: "Реферальный бонус", amount: "+3 000 тг", status: "Начислено", source: "Партнёр: Серик", comment: "5% от пакета BUSINESS" },
];

export const partners = [
  { name: "Ерлан Сыздыков", id: "SAFI-10555", line: 1, branch: "Левая", package: "VIP", status: "Менеджер", personalPV: 200, teamPV: 4500, registrationDate: "18.04.2026", activity: "Активен" },
  { name: "Алиса Петрова", id: "SAFI-10556", line: 1, branch: "Правая", package: "BUSINESS", status: "-", personalPV: 50, teamPV: 1200, registrationDate: "17.04.2026", activity: "Активен" },
  { name: "Марат Оспанов", id: "SAFI-10612", line: 2, branch: "Левая", package: "BUSINESS", status: "-", personalPV: 25, teamPV: 300, registrationDate: "20.04.2026", activity: "Неактивен" },
  { name: "Гульнара Касенова", id: "SAFI-10650", line: 1, branch: "Правая", package: "ELITE", status: "Лидер", personalPV: 500, teamPV: 18000, registrationDate: "10.03.2026", activity: "Активен" },
  { name: "Алексей Смирнов", id: "SAFI-10688", line: 3, branch: "Левая", package: "BUSINESS", status: "Менеджер", personalPV: 100, teamPV: 2500, registrationDate: "05.04.2026", activity: "Активен" },
  { name: "Динара Туленова", id: "SAFI-10700", line: 2, branch: "Правая", package: "VIP", status: "-", personalPV: 150, teamPV: 500, registrationDate: "14.04.2026", activity: "Активен" },
  { name: "Нурлан Саматов", id: "SAFI-10705", line: 1, branch: "Левая", package: "BUSINESS", status: "-", personalPV: 0, teamPV: 0, registrationDate: "22.04.2026", activity: "Неактивен" },
  { name: "Елена Волкова", id: "SAFI-10740", line: 2, branch: "Правая", package: "BUSINESS", status: "Менеджер", personalPV: 100, teamPV: 1500, registrationDate: "02.03.2026", activity: "Активен" },
  { name: "Кайрат Исаев", id: "SAFI-10811", line: 3, branch: "Левая", package: "VIP", status: "Лидер", personalPV: 300, teamPV: 11000, registrationDate: "15.02.2026", activity: "Активен" },
  { name: "Асель Муканова", id: "SAFI-10833", line: 1, branch: "Правая", package: "ELITE", status: "Директор", personalPV: 1000, teamPV: 55000, registrationDate: "10.01.2026", activity: "Активен" },
  { name: "Игорь Новиков", id: "SAFI-10890", line: 4, branch: "Левая", package: "BUSINESS", status: "-", personalPV: 20, teamPV: 100, registrationDate: "24.04.2026", activity: "Активен" },
  { name: "Санжар Болатов", id: "SAFI-10901", line: 2, branch: "Правая", package: "BUSINESS", status: "-", personalPV: 50, teamPV: 450, registrationDate: "20.03.2026", activity: "Неактивен" }
];

export const withdrawals = [
  { id: "W-55", date: "20.04.2026", amount: "150 000 тг", method: "Карта партнёра", status: "Выплачено", paymentDate: "22.04.2026", comment: "Успешный перевод" },
  { id: "W-42", date: "14.04.2026", amount: "200 000 тг", method: "Счёт ИП", status: "Выплачено", paymentDate: "16.04.2026", comment: "Успешный перевод" },
  { id: "W-30", date: "30.03.2026", amount: "105 000 тг", method: "Карта партнёра", status: "Выплачено", paymentDate: "02.04.2026", comment: "Успешный перевод" },
  { id: "W-15", date: "15.03.2026", amount: "55 000 тг", method: "Карта партнёра", status: "Выплачено", paymentDate: "17.03.2026", comment: "Успешный перевод" },
  { id: "W-05", date: "05.03.2026", amount: "20 000 тг", method: "Карта партнёра", status: "Отклонено", paymentDate: "-", comment: "Ошибка реквизитов" }
];

export const supportTickets = [
  { id: "T-1005", date: "26.04.2026", subject: "Где моя посылка?", category: "Другое", status: "В работе", lastReply: "-" },
  { id: "T-0988", date: "15.04.2026", subject: "Вопрос по статусному бонусу", category: "Вопрос по бонусам", status: "Закрыто", lastReply: "16.04.2026" },
  { id: "T-0840", date: "02.04.2026", subject: "Не могу добавить карту", category: "Вопрос по выводу", status: "Закрыто", lastReply: "03.04.2026" },
  { id: "T-0711", date: "15.03.2026", subject: "Ошибка при регистрации партнера", category: "Техническая проблема", status: "Закрыто", lastReply: "15.03.2026" },
  { id: "T-0520", date: "28.02.2026", subject: "Апгрейд пакета", category: "Вопрос по пакету", status: "Закрыто", lastReply: "01.03.2026" }
];
