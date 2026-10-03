<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the /v1 API to callers without a private key from the API settings page.
 *
 * The alias is api.key, registered in bootstrap/app.php and put on the whole v1 group.
 * The key comes in the X-Api-Key header. No key, a wrong key, or a switched-off key gets 401.
 * A browser request whose Origin is not the key's origin gets 403. A request without an Origin
 * header is a server call and passes on the key alone. CORS preflight (OPTIONS) is answered by
 * Laravel's HandleCors before routing, so it never reaches this middleware.
 *
 * Extending:
 * - A new rule on a key belongs in ApiKey::allows, not here.
 * - A route that must stay open has to be registered outside the v1 group.
 */
class RequireApiKey
{
    /** The request header that carries the key. */
    public const HEADER = 'X-Api-Key';

    /**
     * Lets the request through only with an active key used from its own origin.
     *
     * The Laravel middleware contract owns this method name.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = ApiKey::match($request->header(self::HEADER));

        if ($key === null) {
            return response()->json([
                'message' => 'کلید API فرستاده نشده یا معتبر نیست.',
            ], 401);
        }

        if (! $key->allows($request->header('Origin'))) {
            return response()->json([
                'message' => 'این کلید API برای این دامنه تعریف نشده است.',
            ], 403);
        }

        $key->stamp();

        return $next($request);
    }
}
