<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Tampilkan semua cart user
     */
    public function index()
    {
        $user = auth('api')->user();

        $carts = Cart::with(['store', 'items.product'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($cart) {
                $total = $cart->items->sum(function ($item) {
                    return $item->product->price * $item->quantity;
                });

                return [
                    'id' => $cart->id,
                    'store' => [
                        'id' => $cart->store->id,
                        'name' => $cart->store->name,
                        'photo_url' => $cart->store->photo_url,
                    ],
                    'items' => $cart->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product' => [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'price' => $item->product->price,
                                'photo_url' => $item->product->photo_url,
                                'stock' => $item->product->stock,
                            ],
                            'quantity' => $item->quantity,
                            'subtotal' => $item->product->price * $item->quantity,
                        ];
                    }),
                    'total' => $total,
                    'created_at' => $cart->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $carts
        ]);
    }

    /**
     * Tambah produk ke cart
     */
    public function addItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();
            $product = Product::findOrFail($request->product_id);

            // Cek stok
            if ($product->stock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tidak mencukupi'
                ], 400);
            }

            // Cek apakah produk aktif
            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak tersedia'
                ], 400);
            }

            DB::beginTransaction();

            // Cari atau buat cart untuk store ini
            $cart = Cart::firstOrCreate([
                'user_id' => $user->id,
                'store_id' => $product->store_id,
            ]);

            // Cek apakah item sudah ada di cart
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                // Update quantity
                $newQuantity = $cartItem->quantity + $request->quantity;

                if ($product->stock < $newQuantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Stok tidak mencukupi'
                    ], 400);
                }

                $cartItem->update(['quantity' => $newQuantity]);
            } else {
                // Buat cart item baru
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan ke keranjang',
                'data' => [
                    'cart_item_id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan produk ke keranjang',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update quantity item di cart
     */
    public function updateItem(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth('api')->user();

            $cartItem = CartItem::with(['cart', 'product'])
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->findOrFail($itemId);

            // Cek stok
            if ($cartItem->product->stock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tidak mencukupi'
                ], 400);
            }

            $cartItem->update(['quantity' => $request->quantity]);

            return response()->json([
                'success' => true,
                'message' => 'Quantity berhasil diupdate',
                'data' => [
                    'cart_item_id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate quantity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus item dari cart
     */
    public function removeItem($itemId)
    {
        try {
            $user = auth('api')->user();

            $cartItem = CartItem::with('cart')
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->findOrFail($itemId);

            $cartId = $cartItem->cart_id;
            $cartItem->delete();

            // Cek apakah cart masih punya item
            $remainingItems = CartItem::where('cart_id', $cartId)->count();

            if ($remainingItems == 0) {
                Cart::find($cartId)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus dari keranjang'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kosongkan cart untuk store tertentu
     */
    public function clearCart($cartId)
    {
        try {
            $user = auth('api')->user();

            $cart = Cart::where('user_id', $user->id)
                ->findOrFail($cartId);

            CartItem::where('cart_id', $cart->id)->delete();
            $cart->delete();

            return response()->json([
                'success' => true,
                'message' => 'Keranjang berhasil dikosongkan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengosongkan keranjang',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
