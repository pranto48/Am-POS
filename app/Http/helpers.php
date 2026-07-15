<?php

/**
 * Decrypts AES-256-CBC encrypted response from the licensing portal.
 */
function decryptPortalLicenseData(string $encrypted_data)
{
    $data = base64_decode($encrypted_data);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');

    if (strlen($data) < $iv_length) {
        return false;
    }

    $iv        = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', 'ITSupportBD_SecureKey_2024', 0, $iv);

    if ($decrypted === false) {
        return false;
    }

    return json_decode($decrypted, true);
}

/**
 * pos_boot — verifies the application license with the IT Support BD portal.
 * Called during installation and upgrade to validate the license key.
 *
 * @param string $ul    App URL
 * @param string $pt    Path
 * @param string $lc    License Key
 * @param string $em    Client Email
 * @param string $un    Client ID / Username
 * @param int    $type  Verification type (1 = install, default)
 * @param mixed  $pid   Product ID (optional)
 */
function pos_boot($ul, $pt, $lc, $em, $un, $type = 1, $pid = null)
{
    // Only skip license check if the app is not yet installed (no .env file)
    if (! file_exists(base_path('.env'))) {
        session([
            'license_status_code' => 'active',
            'license_message'     => 'License check skipped (pre-install).',
            'license_max_devices' => 1,
            'license_expires_at'  => date('Y-m-d', strtotime('+1 day')),
            'license_last_verified' => time(),
        ]);
        \Illuminate\Support\Facades\Cache::put('license_check_success', true, 24 * 60 * 60);
        return null;
    }

    // Generate or retrieve the installation ID for this server
    $installation_id = session('installation_id');
    if (empty($installation_id)) {
        $installation_id = env('INSTALLATION_ID') ?: (string) \Illuminate\Support\Str::uuid();
        session(['installation_id' => $installation_id]);
    }

    $post_data = [
        'app_license_key'      => $lc,
        'user_id'              => $un ?: 'anonymous',
        'current_device_count' => 0,
        'installation_id'      => $installation_id,
    ];

    $ch = curl_init();
    $curlConfig = [
        CURLOPT_URL            => config('author.licensing_portal_url'),
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($post_data),
        CURLOPT_TIMEOUT        => 15,
    ];
    curl_setopt_array($ch, $curlConfig);
    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = 'License portal connection error: ' . curl_error($ch);
        curl_close($ch);
        return redirect()->back()
            ->with('error', $error_msg)
            ->withInput();
    }
    curl_close($ch);

    if ($result) {
        $decrypted = decryptPortalLicenseData($result);
        if ($decrypted === false) {
            return redirect()->back()
                ->with('error', 'Failed to decrypt license response from portal. Please check your license key or contact support.')
                ->withInput();
        }

        if (isset($decrypted['success']) && $decrypted['success'] === true) {
            session([
                'license_status_code'  => $decrypted['actual_status'] ?? 'active',
                'license_message'      => $decrypted['message'] ?? 'License verified.',
                'license_max_devices'  => $decrypted['max_devices'] ?? 1,
                'license_expires_at'   => $decrypted['expires_at'] ?? '',
                'license_last_verified' => time(),
            ]);
            \Illuminate\Support\Facades\Cache::put('license_check_success', true, 24 * 60 * 60);
            return null;
        } else {
            $msg = $decrypted['message'] ?? 'Invalid or expired license key. Please contact support at portal.itsupport.com.bd.';
            return redirect()->back()
                ->with('error', $msg)
                ->withInput();
        }
    }

    // Could not reach portal — allow install to proceed but log the issue
    return null;
}

if (! function_exists('humanFilesize')) {
    function humanFilesize($size, $precision = 2)
    {
        $units = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
        $step = 1024;
        $i = 0;

        while (($size / $step) > 0.9) {
            $size = $size / $step;
            $i++;
        }
        
        return round($size, $precision).$units[$i];
    }
}

/**
 * Checks if the uploaded document is an image
 */
if (! function_exists('isFileImage')) {
    function isFileImage($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $array = ['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG', 'gif', 'GIF'];
        $output = in_array($ext, $array) ? true : false;

        return $output;
    }
}

if (! function_exists('isAppInstalled')) {
    function isAppInstalled()
    {
        $envPath = base_path('.env');
        return file_exists($envPath);
    }
}

/**
 * Checks if pusher has credential or not
 *
 * and return boolean
 */
if (! function_exists('isPusherEnabled')) {
    function isPusherEnabled()
    {
        if (empty(config('broadcasting.connections.pusher.key')) ||
            empty(config('broadcasting.connections.pusher.secret')) ||
            empty(config('broadcasting.connections.pusher.app_id'))) {
            return false;
        }
        return true;
    }
}

/**
 * Detects if the current device is a mobile device based on User Agent.
 */
if (! function_exists('isMobile')) {
    function isMobile()
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) || (bool) preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac(\-|\/|s)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt(\-|\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(15|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
    }
}

/**
 * Convert number to its ordinal representation (e.g. 1 to 1st, 2 to 2nd).
 */
if (! function_exists('str_ordinal')) {
    function str_ordinal($number)
    {
        $number = (int) $number;
        if (in_array(($number % 100), array(11, 12, 13))) {
            return $number . 'th';
        }
        switch ($number % 10) {
            case 1:  return $number . 'st';
            case 2:  return $number . 'nd';
            case 3:  return $number . 'rd';
            default: return $number . 'th';
        }
    }
}
