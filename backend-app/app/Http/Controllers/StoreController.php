<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    /**
     * Get all stores (with search, sort, and pagination)
     */
    public function index(Request $request)
    {
        try {
            $search     = $request->query('search');
            $sortBy     = $request->query('sort_by', 'id');
            $sortOrder  = $request->query('sort_order', 'asc');
            $perPage    = (int) $request->query('per_page', 10);

            $allowedSorts = ['id', 'name', 'address', 'created_at'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'id';
            }

            $query = Store::with('owner:id,name,email,role')
                ->select('id', 'user_id', 'name', 'photo_url', 'address', 'description', 'created_at', 'updated_at');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('address', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $sortOrder);

            $stores = $query->paginate($perPage);
            $formatted = $stores->getCollection()->map(function ($store) {
                return [
                    'id'          => $store->id,
                    'name'        => $store->name,
                    'photo_url'   => $store->photo_url,
                    'address'     => $store->address,
                    'description' => $store->description,
                    'owner'       => $store->owner,
                    'created_at'  => $store->created_at,
                    'updated_at'  => $store->updated_at,
                ];
            });

            $stores->setCollection($formatted);

            return response()->json([
                'message' => 'Data store berhasil diambil',
                'data'    => $stores->items(),
                'meta'    => [
                    'current_page' => $stores->currentPage(),
                    'per_page'     => $stores->perPage(),
                    'total'        => $stores->total(),
                    'total_page'   => $stores->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data store',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get store by ID
     */
    public function show($id)
    {
        try {
            $store = Store::with('owner:id,name,email,role')->find($id);

            if (!$store) {
                return response()->json(['error' => 'Store tidak ditemukan'], 404);
            }

            return response()->json([
                'message' => 'Data store berhasil diambil',
                'store'   => $store,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data store',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new store (1 user = 1 store)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id'     => 'required|exists:users,id|unique:stores,user_id',
                'name'        => 'required|string|max:100',
                'photo'       => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'address'     => 'sometimes|string|nullable',
                'description' => 'sometimes|string|nullable',
            ]);

            $photoUrl = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads/store_photos', $filename, 'public');
                $photoUrl = '/storage/' . $path;
            }

            $store = Store::create([
                'user_id'     => $validated['user_id'],
                'name'        => $validated['name'],
                'photo_url'   => $photoUrl,
                'address'     => $validated['address'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            return response()->json([
                'message' => 'Store baru berhasil dibuat',
                'store'   => $store->load('owner'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal membuat store',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update store
     */
    public function update(Request $request, $id)
    {
        try {
            $store = Store::find($id);

            if (!$store) {
                return response()->json(['error' => 'Store tidak ditemukan'], 404);
            }

            $validated = $request->validate([
                'name'        => 'sometimes|string|max:100',
                'photo'       => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'address'     => 'sometimes|string|nullable',
                'description' => 'sometimes|string|nullable',
            ]);

            $photoUrl = $store->photo_url;
            if ($request->hasFile('photo')) {
                if ($photoUrl && Storage::disk('public')->exists(str_replace('/storage/', '', $photoUrl))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $photoUrl));
                }

                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads/store_photos', $filename, 'public');
                $photoUrl = '/storage/' . $path;
            }

            $store->update([
                'name'        => $validated['name'] ?? $store->name,
                'photo_url'   => $photoUrl,
                'address'     => $validated['address'] ?? $store->address,
                'description' => $validated['description'] ?? $store->description,
            ]);

            return response()->json([
                'message' => 'Data store berhasil diperbarui',
                'store'   => $store->refresh()->load('owner'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal memperbarui store',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete store
     */
    public function destroy($id)
    {
        try {
            $store = Store::find($id);

            if (!$store) {
                return response()->json(['error' => 'Store tidak ditemukan'], 404);
            }

            if ($store->photo_url) {
                $photoPath = str_replace('/storage/', '', $store->photo_url);
                if (Storage::disk('public')->exists($photoPath)) {
                    Storage::disk('public')->delete($photoPath);
                }
            }

            $store->delete();

            return response()->json(['message' => 'Store berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal menghapus store',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
