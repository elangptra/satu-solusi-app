<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get all users with profile
     */
    public function index(Request $request)
    {
        try {
            // Ambil query parameter
            $search     = $request->query('search');
            $sortBy     = $request->query('sort_by', 'id');
            $sortOrder  = $request->query('sort_order', 'asc'); 
            $perPage    = (int) $request->query('per_page', 10);

            // Validasi kolom sorting
            $allowedSorts = ['id', 'name', 'email', 'role', 'address', 'phone'];
            if (!in_array($sortBy, $allowedSorts)) {
                $sortBy = 'id';
            }

            // join ke user_profiles
            $query = User::with('profile')
                ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.role',
                    'users.created_at',
                    'users.updated_at',
                    'user_profiles.address',
                    'user_profiles.phone',
                    'user_profiles.photo_url'
                );

            // Filter pencarian
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                        ->orWhere('users.email', 'LIKE', "%{$search}%")
                        ->orWhere('users.role', 'LIKE', "%{$search}%")
                        ->orWhere('user_profiles.address', 'LIKE', "%{$search}%")
                        ->orWhere('user_profiles.phone', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $sortOrder);
            $users = $query->paginate($perPage);
            $formatted = $users->getCollection()->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'role'       => $user->role,
                    'address'    => $user->address,
                    'phone'      => $user->phone,
                    'photo_url'  => $user->photo_url,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

            // Replace collection di pagination
            $users->setCollection($formatted);

            return response()->json([
                'message' => 'Data user berhasil diambil',
                'data'    => $users->items(),
                'meta'    => [
                    'current_page' => $users->currentPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                    'total_page'    => $users->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data user',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get user by ID
     */
    public function show($id)
    {
        try {
            $user = User::with('profile')->find($id);

            if (!$user) {
                return response()->json(['error' => 'User tidak ditemukan'], 404);
            }

            return response()->json([
                'message' => 'Data user berhasil diambil',
                'user'    => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new user with profile and optional photo
     */
    public function store(Request $request)
    {
        try {
            // Validasi input
            $rules = [
                'name'      => 'required|string|max:255',
                'email'     => 'required|email|unique:users,email',
                'role'      => 'required|in:super_admin,merchant,customer',
                'password'  => 'required|string|min:8|confirmed',
                'photo'     => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'address'   => 'sometimes|string|nullable',
                'phone'     => 'sometimes|string|nullable',
            ];

            $validatedData = $request->validate($rules);

            // Upload photo jika ada
            $photoUrl = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads/profile_photos', $filename, 'public');
                $photoUrl = '/storage/' . $path;
            }

            // Simpan data user
            $user = User::create([
                'name'     => $validatedData['name'],
                'email'    => $validatedData['email'],
                'role'     => $validatedData['role'],
                'password' => Hash::make($validatedData['password']),
            ]);

            // Simpan data profil
            $profileData = [];
            if ($photoUrl) {
                $profileData['photo_url'] = $photoUrl;
            }
            if ($request->has('address')) {
                $profileData['address'] = $validatedData['address'] ?? null;
            }
            if ($request->has('phone')) {
                $profileData['phone'] = $validatedData['phone'] ?? null;
            }

            if (!empty($profileData)) {
                $user->profile()->create($profileData);
            }

            return response()->json([
                'message' => 'User baru berhasil dibuat',
                'user'    => $user->load('profile'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal membuat user baru',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user by ID (with profile photo upload)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::with('profile')->find($id);

            if (!$user) {
                return response()->json(['error' => 'User tidak ditemukan'], 404);
            }

            $rules = [
                'name'      => 'sometimes|string|max:255',
                'email'     => 'sometimes|email|unique:users,email,' . $id,
                'role'      => 'sometimes|in:super_admin,merchant,customer',
                'photo'     => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'address'   => 'sometimes|string|nullable',
                'phone'     => 'sometimes|string|nullable'
            ];

            // Hanya validasi password jika diisi
            if ($request->filled('password')) {
                $rules['password'] = 'required|string|min:8';
                $rules['password_confirmation'] = 'required|same:password';
            }

            $validatedData = $request->validate($rules);

            // Upload photo jika ada
            $photoUrl = $user->profile->photo_url ?? null;
            if ($request->hasFile('photo')) {
                // Hapus foto lama jika ada
                if ($photoUrl && Storage::disk('public')->exists(str_replace('/storage/', '', $photoUrl))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $photoUrl));
                }

                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads/profile_photos', $filename, 'public');
                $photoUrl = '/storage/' . $path;
            }

            // Update data user (hanya field yang ada di request)
            $userData = [];
            if ($request->has('name')) {
                $userData['name'] = $validatedData['name'];
            }
            if ($request->has('email')) {
                $userData['email'] = $validatedData['email'];
            }
            if ($request->has('role')) {
                $userData['role'] = $validatedData['role'];
            }
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($validatedData['password']);
            }

            if (!empty($userData)) {
                $user->update($userData);
            }

            // Update atau buat profile user
            $profileData = [];
            if ($request->hasFile('photo')) {
                $profileData['photo_url'] = $photoUrl;
            }
            if ($request->has('address')) {
                $profileData['address'] = $validatedData['address'];
            }
            if ($request->has('phone')) {
                $profileData['phone'] = $validatedData['phone'];
            }

            if (!empty($profileData)) {
                if ($user->profile) {
                    $user->profile->update($profileData);
                } else {
                    $user->profile()->create(array_merge(
                        ['user_id' => $user->id],
                        $profileData
                    ));
                }
            }

            return response()->json([
                'message' => 'Data user berhasil diperbarui',
                'user'    => $user->refresh()->load('profile'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal memperbarui data user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user by ID
     */
    public function destroy($id)
    {
        try {
            $user = User::with('profile')->find($id);

            if (!$user) {
                return response()->json(['error' => 'User tidak ditemukan'], 404);
            }

            // Hapus foto jika ada
            if ($user->profile && $user->profile->photo_url) {
                $photoPath = str_replace('/storage/', '', $user->profile->photo_url);
                if (Storage::disk('public')->exists($photoPath)) {
                    Storage::disk('public')->delete($photoPath);
                }
            }

            // Hapus profile dan user
            $user->profile()?->delete();
            $user->delete();

            return response()->json([
                'message' => 'User berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal menghapus user',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
