<?php

namespace App\Http\Controllers\V1;

use App\Auth\AccessTokens;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, AccessTokens $tokens): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'required' => 'فیلد :attribute الزامی است.',
        ], [
            'username' => 'کاربری',
            'password' => 'رمز',
        ]);

        $customer = Customer::query()->where('username', $data['username'])->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            return response()->json([
                'message' => 'کاربری یا رمز نادرست است.',
            ], 401);
        }

        $customer->forceFill([
            'last_login' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $minutes = (int) config('sanctum.api_expiration');

        return response()->json([
            'token_type' => 'Bearer',
            'expires_in' => $minutes * 60,
            'access_token' => $tokens->issue($customer, AccessTokens::ABILITY_API, AccessTokens::ABILITY_API, $minutes),
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return response()->json([
            'id' => $customer->id,
            'username' => $customer->username,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
        ]);
    }

    public function register(): Response
    {
        return response()->noContent();
    }

    public function verify(): Response
    {
        return response()->noContent();
    }

    public function forgot(): Response
    {
        return response()->noContent();
    }
}
