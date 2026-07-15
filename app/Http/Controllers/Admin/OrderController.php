<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%' . $search . '%')
                  ->orWhere('recipient_name', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', '%' . $search . '%')
                         ->orWhere('email', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->paginate(25);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load([
            'user',
            'items.product.images',
            'items.productVariant',
            'statusHistories.user',
            'voucher',
            'review',
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,processing,shipped,delivered,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
            'shipping_awb' => ['nullable', 'string', 'max:50', 'required_if:status,shipped'],
        ]);

        $oldStatus = $order->status;
        $order->update([
            'status' => $validated['status'],
            'internal_notes' => $validated['notes'] ?? $order->internal_notes,
        ]);

        // Update timestamps
        $timestampFields = [
            'shipped' => 'shipped_at',
            'delivered' => 'delivered_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
        ];

        if (isset($timestampFields[$validated['status']])) {
            $order->update([$timestampFields[$validated['status']] => now()]);
        }

        // Update AWB if shipped
        if ($validated['status'] === 'shipped' && !empty($validated['shipping_awb'])) {
            $order->update(['shipping_awb' => $validated['shipping_awb']]);
        }

        // Handle cancellation - restore stock
        if ($validated['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
            foreach ($order->items as $item) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }
        }

        // Add status history
        $order->addStatusHistory(
            $validated['status'],
            $validated['notes'] ?? 'Status updated by admin',
            auth()->id()
        );

        return back()->with('success', 'Order status updated successfully.');
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'in:paid,unpaid,failed,refunded'],
        ]);

        $order->update([
            'payment_status' => $validated['payment_status'],
            'paid_at' => $validated['payment_status'] === 'paid' ? now() : $order->paid_at,
        ]);

        if ($validated['payment_status'] === 'paid' && $order->status === 'pending') {
            $order->update(['status' => 'confirmed']);
            $order->addStatusHistory('confirmed', 'Payment marked as paid by admin', auth()->id());
        }

        return back()->with('success', 'Payment status updated successfully.');
    }
}
