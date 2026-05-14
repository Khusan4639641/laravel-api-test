export interface Status {
  id: string;
  name: string;
  pv: number;
  incomePotential: number;
  reward: string;
  isCashBonus: boolean;
}

export const statuses: Status[] = [
  { id: 'manager', name: 'Менеджер', pv: 1000, incomePotential: 500000, reward: '2 продукта в подарок', isCashBonus: false },
  { id: 'leader', name: 'Лидер', pv: 2500, incomePotential: 1250000, reward: 'Набор косметики', isCashBonus: false },
  { id: 'director', name: 'Директор', pv: 5000, incomePotential: 2500000, reward: '250 000 тг', isCashBonus: true },
  { id: 'bronze', name: 'Бронзовый директор', pv: 10000, incomePotential: 5000000, reward: 'Путевка в санаторий или 400 000 тг', isCashBonus: true },
  { id: 'silver', name: 'Серебряный директор', pv: 25000, incomePotential: 12500000, reward: 'Путевка в теплые страны или 750 000 тг', isCashBonus: true },
  { id: 'gold', name: 'Золотой директор', pv: 50000, incomePotential: 25000000, reward: '5 000 000 тг', isCashBonus: true },
  { id: 'platinum', name: 'Платиновый директор', pv: 100000, incomePotential: 50000000, reward: '6 000 000 тг', isCashBonus: true },
  { id: 'emerald', name: 'Изумрудный директор', pv: 250000, incomePotential: 125000000, reward: '10 000 000 тг (автобонус)', isCashBonus: true },
  { id: 'diamond', name: 'Бриллиантовый директор', pv: 500000, incomePotential: 250000000, reward: '20 000 000 тг (жилищный бонус)', isCashBonus: true },
];
