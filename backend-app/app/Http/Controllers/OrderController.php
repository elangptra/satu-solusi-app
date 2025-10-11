<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Checkout seluruh cart
     */
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|exists:carts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();

            $cart = Cart::with(['items.product'])
                ->where('user_id', $user->id)
                ->findOrFail($request->cart_id);

            if ($cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keranjang kosong'
                ], 400);
            }

            DB::beginTransaction();

            // Validasi stok dan hitung total
            $totalPrice = 0;
            foreach ($cart->items as $item) {
                if ($item->product->stock < $item->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stok produk {$item->product->name} tidak mencukupi"
                    ], 400);
                }

                if (!$item->product->is_active) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Produk {$item->product->name} tidak tersedia"
                    ], 400);
                }

                $totalPrice += $item->product->price * $item->quantity;
            }

            // Buat order
            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => $cart->store_id,
                'status' => 'pending',
                'total_price' => $totalPrice,
            ]);

            // Pindahkan cart items ke order items & kurangi stok
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product->id,
                    'product_name' => $item->product->name,
                    'price_at_purchase' => $item->product->price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->product->price * $item->quantity,
                ]);

                // Kurangi stok
                $item->product->decrement('stock', $item->quantity);
            }

            // Hapus cart items dan cart
            CartItem::where('cart_id', $cart->id)->delete();
            $cart->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil',
                'data' => [
                    'order_id' => $order->id,
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Checkout gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Partial checkout (checkout beberapa item saja)
     */
    public function partialCheckout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_item_ids' => 'required|array|min:1',
            'cart_item_ids.*' => 'required|exists:cart_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();

            // Ambil cart items yang dipilih
            $cartItems = CartItem::with(['cart', 'product'])
                ->whereIn('id', $request->cart_item_ids)
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item tidak ditemukan'
                ], 404);
            }

            // Validasi semua item dari store yang sama
            $storeIds = $cartItems->pluck('cart.store_id')->unique();
            if ($storeIds->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item harus dari store yang sama'
                ], 400);
            }

            DB::beginTransaction();

            // Validasi stok dan hitung total
            $totalPrice = 0;
            foreach ($cartItems as $item) {
                if ($item->product->stock < $item->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stok produk {$item->product->name} tidak mencukupi"
                    ], 400);
                }

                if (!$item->product->is_active) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Produk {$item->product->name} tidak tersedia"
                    ], 400);
                }

                $totalPrice += $item->product->price * $item->quantity;
            }

            // Buat order
            $storeId = $cartItems->first()->cart->store_id;
            $order = Order::create([
                'user_id' => $user->id,
                'store_id' => $storeId,
                'status' => 'pending',
                'total_price' => $totalPrice,
            ]);

            // Pindahkan cart items ke order items & kurangi stok
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product->id,
                    'product_name' => $item->product->name,
                    'price_at_purchase' => $item->product->price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->product->price * $item->quantity,
                ]);

                // Kurangi stok
                $item->product->decrement('stock', $item->quantity);

                // Hapus cart item
                $cartId = $item->cart_id;
                $item->delete();

                // Cek apakah cart masih punya item
                $remainingItems = CartItem::where('cart_id', $cartId)->count();
                if ($remainingItems == 0) {
                    Cart::find($cartId)->delete();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil',
                'data' => [
                    'order_id' => $order->id,
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Checkout gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lihat riwayat order user
     */
    public function myOrders()
    {
        $user = auth('api')->user();

        $orders = Order::with(['store', 'items.product'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'store' => [
                        'id' => $order->store->id,
                        'name' => $order->store->name,
                        'photo_url' => $order->store->photo_url,
                    ],
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'price' => $item->price_at_purchase,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Detail order
     */
    public function show($orderId)
    {
        try {
            $user = auth('api')->user();

            $order = Order::with(['store', 'items.product'])
                ->where('user_id', $user->id)
                ->findOrFail($orderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'store' => [
                        'id' => $order->store->id,
                        'name' => $order->store->name,
                        'photo_url' => $order->store->photo_url,
                        'address' => $order->store->address,
                    ],
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'price' => $item->price_at_purchase,
                            'quantity' => $item->quantity,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cancel order (hanya jika status masih pending)
     */
    public function cancel($orderId)
    {
        try {
            $user = auth('api')->user();

            $order = Order::with('items.product')
                ->where('user_id', $user->id)
                ->findOrFail($orderId);

            if ($order->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order tidak dapat dibatalkan'
                ], 400);
            }

            DB::beginTransaction();

            // Kembalikan stok
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            // Update status order
            $order->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil dibatalkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lihat order untuk penjual (per store)
     */
    public function storeOrders()
    {
        try {
            $user = auth('api')->user();

            if (!$user->store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki toko'
                ], 403);
            }

            $orders = Order::with(['user.profile', 'items'])
                ->where('store_id', $user->store->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'user' => [
                            'id' => $order->user->id,
                            'name' => $order->user->name,
                            'phone' => $order->user->profile->phone ?? null,
                        ],
                        'items_count' => $order->items->count(),
                        'total_price' => $order->total_price,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status order (untuk penjual)
     */
    public function updateStatus(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:paid,processing,shipped,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();

            if (!$user->store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki toko'
                ], 403);
            }

            $order = Order::where('store_id', $user->store->id)
                ->findOrFail($orderId);

            $order->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Status order berhasil diupdate',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate status order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
