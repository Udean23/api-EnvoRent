<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Exception;

class SocialAuthController extends Controller
{
    public function handleGoogleCallback(Request $request)
    {
        try {
            $token = $request->input('token');
            if ($token) {
                $googleUser = Socialite::driver('google')
                    ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                    ->stateless()
                    ->userFromToken($token);
            } else {
                $googleUser = Socialite::driver('google')
                    ->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))
                    ->stateless()
                    ->user();
            }
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'role' => 'customer',
                    'password' => null,
                ]);
            }

            $sanctumToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $sanctumToken,
                'token_type' => 'Bearer',
                'user' => $user
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal login dengan Google',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
