<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Get all users with profile
     */
    public function index()
    {
        try {
            $users = User::with('profile')->get();

            return response()->json([
                'message' => 'Data seluruh user berhasil diambil',
                'users'   => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal mengambil data user',
                'message' => $e->getMessage()
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
     * Update user by ID (with profile photo upload)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::with('profile')->find($id);

            if (!$user) {
                return response()->json(['error' => 'User tidak ditemukan'], 404);
            }

            $validatedData = $request->validate([
                'name'      => 'sometimes|string|max:255',
                'email'     => 'sometimes|email|unique:users,email,' . $id,
                'password'  => 'nullable|string|min:8|confirmed',
                'role'      => 'sometimes|in:super_admin,merchant,customer',
                'photo'     => 'sometimes|file|image|mimes:jpeg,png,jpg|max:2048',
                'address'   => 'sometimes|string|nullable',
                'phone'     => 'sometimes|string|nullable'
            ]);

            // 🖼️ Upload photo jika ada
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

            // 🔄 Update data user
            $user->update([
                'name'     => $validatedData['name'] ?? $user->name,
                'email'    => $validatedData['email'] ?? $user->email,
                'role'     => $validatedData['role'] ?? $user->role,
                'password' => isset($validatedData['password'])
                    ? Hash::make($validatedData['password'])
                    : $user->password,
            ]);

            // 🔗 Update atau buat profile user
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'photo_url' => $photoUrl,
                    'address'   => $validatedData['address'] ?? $user->profile->address ?? null,
                    'phone'     => $validatedData['phone'] ?? $user->profile->phone ?? null,
                ]
            );

            return response()->json([
                'message' => 'Data user berhasil diperbarui',
                'user'    => $user->load('profile')
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

            // 🧹 Hapus foto jika ada
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
