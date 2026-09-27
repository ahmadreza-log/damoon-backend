<?php

namespace App\Http\Controllers\V1;

use App\Auth\AccessTokens;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Customer authentication, version 1.
 *
 * Routes live under /v1/auth and have no /api prefix.
 * Scramble documents this controller at /docs/api.
 *
 * Customers are created by staff in the panel. There is no public register, verify, or forgot route.
 *
 * Extending:
 * - Add a new action as a single-word method here and register it in the v1 group in routes/api.php.
 * - Validate the body with validate so the OpenAPI schema is built from those rules.
 */
class AuthController extends Controller
{
    /**
     * Customer login.
     *
     * Body: username and password.
     * Success: token_type, expires_in in seconds, and access_token with the api ability.
     * Failure: 401. This token cannot open the staff panel.
     */
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

    /**
     * The signed-in customer's profile.
     *
     * Requires an Authorization: Bearer header. A panel token receives 401 here.
     */
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
}
