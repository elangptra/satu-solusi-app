<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Get all products (with search, sort, and pagination)
     */
    public function index(Request $request)
    {
        try {
            $search     = $request->query('search');
            $sortBy     = $request->query('sort_by', 'id');
            $sortOrder  = $request->query('sort_order', 'asc');
            $perPage    = (int) $request->query('per_page', 10);

            $allowedSorts = ['id', 'name', 'price', 'stock', 'category', 'created_at'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'id';
            }

            $query = Product::with('store:id,name')
                ->select('id', 'store_id', 'name', 'description', 'price', 'stock', 'category', 'photo_url', 'is_active', 'created_at', 'updated_at');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $sortOrder);
            $products = $query->paginate($perPage);

            $formatted = $products->getCollection()->map(function ($product) {
                return [
                    'id'          => $product->id,
                    'name'        => $product->name,
                    'description' => $product->description,
                    'price'       => $product->price,
                    'stock'       => $product->stock,
                    'category'    => $product->category,
                    'photo_url'   => $product->photo_url,
                    'is_active'   => $product->is_active,
                    'store'       => $product->store,
                    'created_at'  => $product->created_at,
                    'updated_at'  => $product->updated_at,
                ];
            });

            $products->setCollection($formatted);

            return response()->json([
                'message' => 'Data produk berhasil diambil',
                'data'    => $products->items(),
                'meta'    => [
                    'current_page' => $products->currentPage(),
                    'per_page'     => $products->perPage(),
                    'total'        => $products->total(),
                    'total_page'   => $products->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error get products: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Gagal mengambil data produk',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get product by ID
     */
    public function show($id)
    {
        try {
            $product = Product::with('store:id,name')->find($id);

            if (!$product) {
                return response()->json(['error' => 'Produk tidak ditemukan'], 404);
            }

            return response()->json([
                'message' => 'Data produk berhasil diambil',
                'product' => $product,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data produk',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get products by store ID
     */
    public function getByStoreId($storeId)
    {
        try {
            $store = Store::find($storeId);
            if (!$store) {
                return response()->json(['error' => 'Store tidak ditemukan'], 404);
            }

            $products = Product::where('store_id', $storeId)
                ->with('store:id,name')
                ->get();

            if ($products->isEmpty()) {
                return response()->json(['error' => 'Tidak ada produk untuk store ini'], 404);
            }

            return response()->json([
                'message'  => 'Data produk berdasarkan store_id berhasil diambil',
                'store'    => $store->only(['id', 'name']),
                'products' => $products,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data produk berdasarkan store_id',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new product
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'store_id'    => 'required|exists:stores,id',
                'name'        => 'required|string|max:100',
                'description' => 'sometimes|string|nullable',
                'price'       => 'required|numeric|min:0',
                'stock'       => 'required|integer|min:0',
                'category'    => 'sometimes|string|nullable',
                'photo'       => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'is_active'   => 'boolean',
            ]);

            $photoUrl = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads/product_photos', $filename, 'public');
                $photoUrl = '/storage/' . $path;
            }

            $product = Product::create([
                'store_id'    => $validated['store_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price'       => $validated['price'],
                'stock'       => $validated['stock'],
                'category'    => $validated['category'] ?? null,
                'photo_url'   => $photoUrl,
                'is_active'   => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'message' => 'Produk baru berhasil dibuat',
                'product' => $product->load('store'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error create product: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Gagal membuat produk',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return response()->json(['error' => 'Produk tidak ditemukan'], 404);
            }

            $validated = $request->validate([
                'name'        => 'sometimes|string|max:100',
                'description' => 'sometimes|string|nullable',
                'price'       => 'sometimes|numeric|min:0',
                'stock'       => 'sometimes|integer|min:0',
                'category'    => 'sometimes|string|nullable',
                'photo'       => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'is_active'   => 'sometimes|boolean',
            ]);

            $photoUrl = $product->photo_url;
            if ($request->hasFile('photo')) {
                if ($photoUrl && Storage::disk('public')->exists(str_replace('/storage/', '', $photoUrl))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $photoUrl));
                }

                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads/product_photos', $filename, 'public');
                $photoUrl = '/storage/' . $path;
            }

            $product->update([
                'name'        => $validated['name'] ?? $product->name,
                'description' => $validated['description'] ?? $product->description,
                'price'       => $validated['price'] ?? $product->price,
                'stock'       => $validated['stock'] ?? $product->stock,
                'category'    => $validated['category'] ?? $product->category,
                'photo_url'   => $photoUrl,
                'is_active'   => $validated['is_active'] ?? $product->is_active,
            ]);

            return response()->json([
                'message' => 'Produk berhasil diperbarui',
                'product' => $product->refresh()->load('store'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error update product: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Gagal memperbarui produk',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return response()->json(['error' => 'Produk tidak ditemukan'], 404);
            }

            if ($product->photo_url) {
                $photoPath = str_replace('/storage/', '', $product->photo_url);
                if (Storage::disk('public')->exists($photoPath)) {
                    Storage::disk('public')->delete($photoPath);
                }
            }

            $product->delete();

            return response()->json(['message' => 'Produk berhasil dihapus'], 200);
        } catch (\Exception $e) {
            Log::error('Error delete product: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Gagal menghapus produk',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
