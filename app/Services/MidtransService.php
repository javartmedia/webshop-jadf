<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

class MidtransService
{
    protected bool $isProduction;
    protected string $serverKey;
    protected string $clientKey;

    public function __construct()
    {
        $this->serverKey = Setting::getValue('midtrans_server_key', '');
        $this->clientKey = Setting::getValue('midtrans_client_key', '');
        $this->isProduction = app()->environment('production');

        Config::$serverKey = $this->serverKey;
        Config::$isProduction = $this->isProduction;
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createTransaction(Order $order): string
    {
        $user = $order->user;
        $items = [];

        foreach ($order->items as $item) {
            $items[] = [
                'id' => $item->product_sku,
                'price' => (int) $item->unit_price,
                'quantity' => $item->quantity,
                'name' => substr($item->product_name, 0, 50),
            ];
        }

        // Add shipping as an item
        if ($order->shipping_cost > 0) {
            $items[] = [
                'id' => 'SHIPPING',
                'price' => (int) $order->shipping_cost,
                'quantity' => 1,
                'name' => 'Shipping Cost (' . $order->shipping_courier . ')',
            ];
        }

        // Add voucher discount as negative item
        if ($order->voucher_discount > 0) {
            $items[] = [
                'id' => 'VOUCHER',
                'price' => -(int) $order->voucher_discount,
                'quantity' => 1,
                'name' => 'Voucher Discount',
            ];
        }

        // Add loyalty discount
        if ($order->loyalty_discount > 0) {
            $items[] = [
                'id' => 'LOYALTY',
                'price' => -(int) $order->loyalty_discount,
                'quantity' => 1,
                'name' => 'Loyalty Points Discount',
            ];
        }

        $transactionDetails = [
            'order_id' => $order->order_number,
            'gross_amount' => (int) max($order->grand_total, 1), // Minimum 1 IDR for Midtrans
        ];

        $customerDetails = [
            'first_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? $order->shipping_phone,
            'shipping_address' => [
                'first_name' => $order->recipient_name,
                'address' => $order->shipping_address,
                'city' => $order->shipping_city,
                'postal_code' => $order->shipping_postal_code,
                'phone' => $order->shipping_phone,
                'country_code' => 'IDN',
            ],
        ];

        $params = [
            'transaction_details' => $transactionDetails,
            'customer_details' => $customerDetails,
            'item_details' => $items,
            'callbacks' => [
                'finish' => config('app.url') . '/orders/' . $order->order_number,
                'error' => config('app.url') . '/checkout/error',
                'pending' => config('app.url') . '/checkout/pending',
            ],
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s T'),
                'unit' => 'hours',
                'duration' => 24,
            ],
            'enabled_payments' => [
                'credit_card',
                'gopay',
                'shopeepay',
                'bank_transfer',
                'bca_va',
                'bni_va',
                'bri_va',
                'permata_va',
                'indomaret',
                'alfamart',
                'akulaku',
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            return $snapToken;
        } catch (\Exception $e) {
            \Log::error('Midtrans Snap Error: ' . $e->getMessage(), $params);
            throw $e;
        }
    }

    public function handleCallback(array $payload): ?array
    {
        try {
            $notification = new Notification();
            return [
                'order_id' => $notification->order_id,
                'transaction_id' => $notification->transaction_id,
                'transaction_status' => $notification->transaction_status,
                'payment_type' => $notification->payment_type,
                'fraud_status' => $notification->fraud_status ?? null,
                'gross_amount' => $notification->gross_amount,
                'status_code' => $notification->status_code,
                'status_message' => $notification->status_message,
                'signature_key' => $notification->signature_key ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('Midtrans Notification Error: ' . $e->getMessage());
            return null;
        }
    }

    public function verifySignature(array $payload): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $serverKey = $this->serverKey;

        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return $signature === ($payload['signature_key'] ?? '');
    }
}
