<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('login', 'aidar')->first();
        $products = Product::query()->whereIn('sku', ['COS-HYDRA-001', 'SAFI-COLLAGEN-GLOW', 'BAD-OMEGA-001'])->get()->keyBy('sku');

        if (! $user || $products->isEmpty()) {
            return;
        }

        $orders = [
            ['number' => 'ORD-DEMO-0001', 'items' => [['sku' => 'COS-HYDRA-001', 'quantity' => 2], ['sku' => 'BAD-OMEGA-001', 'quantity' => 1]]],
            ['number' => 'ORD-DEMO-0002', 'items' => [['sku' => 'SAFI-COLLAGEN-GLOW', 'quantity' => 1]]],
        ];

        foreach ($orders as $orderData) {
            $subtotal = '0';
            $totalPv = '0';

            foreach ($orderData['items'] as $item) {
                $product = $products->get($item['sku']);

                if (! $product) {
                    continue;
                }

                $subtotal = bcadd($subtotal, bcmul((string) $product->price, (string) $item['quantity'], 2), 2);
                $totalPv = bcadd($totalPv, bcmul((string) $product->pv, (string) $item['quantity'], 2), 2);
            }

            $order = Order::query()->updateOrCreate(
                ['order_number' => $orderData['number']],
                [
                    'user_id' => $user->id,
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'subtotal_amount' => $subtotal,
                    'discount_amount' => 0,
                    'total_amount' => $subtotal,
                    'total_pv' => $totalPv,
                    'shipping_address' => ['city' => 'Алматы', 'address' => 'Demo address'],
                ]
            );

            foreach ($orderData['items'] as $item) {
                $product = $products->get($item['sku']);

                if (! $product) {
                    continue;
                }

                $quantity = (int) $item['quantity'];

                $order->items()->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'total_price' => bcmul((string) $product->price, (string) $quantity, 2),
                        'unit_pv' => $product->pv,
                        'total_pv' => bcmul((string) $product->pv, (string) $quantity, 2),
                        'item_snapshot' => [
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'price' => (string) $product->price,
                            'pv' => (string) $product->pv,
                        ],
                    ]
                );
            }
        }
    }
}
