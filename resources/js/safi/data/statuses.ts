export interface Status {
  id: string;
  name: string;
  pv: number;
  incomePotential: number;
  reward: string;
  isCashBonus: boolean;
  rewardType?: string;
  cashAmount?: number;
  compensationAmount?: number;
  compensationAvailable?: boolean;
}

export const statuses: Status[] = [
  { id: 'manager', name: 'Менеджер', pv: 1000, incomePotential: 500000, reward: '2 продукта в подарок', isCashBonus: false },
  { id: 'leader', name: 'Лидер', pv: 2500, incomePotential: 1250000, reward: 'Набор косметики', isCashBonus: false },
  { id: 'director', name: 'Директор', pv: 5000, incomePotential: 2500000, reward: '250 000 ₸ cash bonus', isCashBonus: true },
  { id: 'bronze_director', name: 'Бронзовый директор', pv: 10000, incomePotential: 5000000, reward: 'Путевка в санаторий + 100 000 ₸, при отказе 400 000 ₸', isCashBonus: true, rewardType: 'trip', cashAmount: 100000, compensationAmount: 400000, compensationAvailable: true },
  { id: 'silver_director', name: 'Серебряный директор', pv: 25000, incomePotential: 12500000, reward: 'Зарубежная поездка + 250 000 ₸, при отказе 750 000 ₸', isCashBonus: true, rewardType: 'foreign_trip', cashAmount: 250000, compensationAmount: 750000, compensationAvailable: true },
  { id: 'gold_director', name: 'Золотой директор', pv: 50000, incomePotential: 25000000, reward: '5 000 000 ₸ cash bonus', isCashBonus: true },
  { id: 'platinum_director', name: 'Платиновый директор', pv: 100000, incomePotential: 50000000, reward: '6 000 000 ₸ cash bonus', isCashBonus: true },
  { id: 'emerald_director', name: 'Изумрудный директор', pv: 250000, incomePotential: 125000000, reward: '10 000 000 ₸ auto bonus', isCashBonus: true },
  { id: 'diamond_director', name: 'Бриллиантовый директор', pv: 500000, incomePotential: 250000000, reward: '20 000 000 ₸ apartment bonus', isCashBonus: true },
];
