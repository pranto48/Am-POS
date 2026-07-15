<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class IsInstalled
{
    /**
     * Handle an incoming request.
     * Redirects to /install if .env is missing.
     * Verifies license on every request (cached for 24h on success).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $envPath = base_path('.env');

        // If not installed yet, redirect to installer
        if (! file_exists($envPath)) {
            return redirect(url('/') . '/install');
        }

        // Skip license check for artisan/console, install, login, logout routes
        if (
            app()->runningInConsole() ||
            $request->is('install*') ||
            $request->is('login*') ||
            $request->is('logout*')
        ) {
            return $next($request);
        }

        // Verify license (cached for 24 hours after success)
        if (! Cache::has('license_check_success')) {
            $license_key    = env('LICENSE_KEY');
            $user_id        = env('CLIENT_ID') ?: 'anonymous';
            $installation_id = env('INSTALLATION_ID');

            // If license key or installation ID is missing, block access
            if (empty($license_key) || empty($installation_id)) {
                abort(403, 'Application license is not configured. Please complete installation.');
            }

            $post_data = [
                'app_license_key'      => $license_key,
                'user_id'              => $user_id,
                'current_device_count' => 0,
                'installation_id'      => $installation_id,
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => config('author.licensing_portal_url'),
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($post_data),
                CURLOPT_TIMEOUT        => 10,
            ]);
            $result   = curl_exec($ch);
            $curl_err = curl_errno($ch);
            curl_close($ch);

            if (! $curl_err && $result) {
                $decrypted = decryptPortalLicenseData($result);
                if ($decrypted !== false && isset($decrypted['success']) && $decrypted['success'] === true) {
                    // Valid license — cache for 24 hours
                    Cache::put('license_check_success', true, 24 * 60 * 60);
                } else {
                    $msg = ($decrypted && isset($decrypted['message']))
                        ? $decrypted['message']
                        : 'Application license verification failed. Please contact support.';
                    abort(403, $msg);
                }
            } else {
                // Portal unreachable — allow for 2 hours to handle network issues gracefully
                Cache::put('license_check_success', true, 2 * 60 * 60);
            }
        }

        return $next($request);
    }
}
