<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name'                  => 'required|string|max:255',
                'email'                 => 'required|email|unique:users,email',
                'password'              => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required',
                'role'                  => 'required|in:super_admin,merchant,customer'
            ]);

            $user = User::create([
                'name'     => $validatedData['name'],
                'email'    => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role'     => $validatedData['role'],
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Registrasi berhasil',
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ],
                'token' => [
                    'access_token' => $token,
                    'token_type'   => 'bearer',
                    'expires_in'   => JWTAuth::factory()->getTTL() * 60
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat registrasi.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid Credentials'], 401);
        }

        $user = JWTAuth::user();

        return response()->json([
            'message' => 'Login berhasil',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'token' => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => JWTAuth::factory()->getTTL() * 60
            ]
        ]);
    }

    public function getMe()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $user = User::with('profile')->find($user->id);

            return response()->json([
                'message' => 'Data user berhasil diambil',
                'user'    => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => 'Logout berhasil!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal logout'], 500);
        }
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'message' => 'Token berhasil diperbarui',
                'token' => [
                    'access_token' => $newToken,
                    'token_type'   => 'bearer',
                    'expires_in'   => JWTAuth::factory()->getTTL() * 60
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token tidak dapat diperbarui'], 401);
        }
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'token' => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => JWTAuth::factory()->getTTL() * 60
            ]
        ], 200);
    }
}
