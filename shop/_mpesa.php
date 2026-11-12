<?php
declare(strict_types=1);

/**
 * Mephys - Safaricom Daraja M-Pesa integration
 */

function mephysMpesaConfig(): array
{
    // Values are supplied by the server environment, never embedded in source code.
    $callback = trim((string) (getenv('MEPHYS_MPESA_CALLBACK_URL') ?: 'https://3dfb-105-164-128-66.ngrok-free.app/shop/mpesa-callback.php'));
    $publicBase = trim((string) (getenv('MEPHYS_PUBLIC_BASE_URL') ?: ''));
    if ($callback === '' && $publicBase !== '') {
        $callback = rtrim($publicBase, '/') . '/shop/mpesa-callback.php';
    }

    return [
        'environment' => strtolower(trim((string) (getenv('MEPHYS_MPESA_ENV') ?: 'sandbox'))),
        'consumer_key' => trim((string) (getenv('MEPHYS_MPESA_CONSUMER_KEY') ?: 'nORoGgdu3FJUZDAPBWBlsID5DV6DcQaMz9KMBJA0DfAy5yDB')),
        'consumer_secret' => trim((string) (getenv('MEPHYS_MPESA_CONSUMER_SECRET') ?: 'd5063219wRReWBc7Ty3UwAYzBKmeaUC5De0ZSYRRV0t7yzHkdEKxIpPYdcOlcPsb')),
        'shortcode' => trim((string) (getenv('MEPHYS_MPESA_SHORTCODE') ?: '174379')),
        'passkey' => trim((string) (getenv('MEPHYS_MPESA_PASSKEY') ?: 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919')),
        'callback_url' => $callback,
        'account_reference' => trim((string) (getenv('MEPHYS_MPESA_ACCOUNT_REFERENCE') ?: 'MEPHYS')),
        'transaction_type' => trim((string) (getenv('MEPHYS_MPESA_TRANSACTION_TYPE') ?: 'CustomerPayBillOnline')),
    ];
}

/**
 * Fail before recording a new order if a payment request cannot be made.
 * Connectivity and actual Safaricom credentials still require live sandbox testing.
 */
function mephysMpesaAssertConfigured(array $cfg): void
{
    if (!in_array($cfg['environment'] ?? '', ['sandbox', 'production'], true)) {
        throw new RuntimeException('M-Pesa environment must be sandbox or production.');
    }
    foreach (['consumer_key', 'consumer_secret', 'shortcode', 'passkey', 'callback_url'] as $key) {
        if (trim((string) ($cfg[$key] ?? '')) === '') {
            throw new RuntimeException('M-Pesa configuration is incomplete: ' . $key . ' is missing.');
        }
    }
    if (!preg_match('/^\d{5,9}$/', (string) $cfg['shortcode'])) {
        throw new RuntimeException('M-Pesa shortcode must contain 5–9 digits.');
    }
    $url = (string) $cfg['callback_url'];
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (
        filter_var($url, FILTER_VALIDATE_URL) === false ||
        strtolower((string) ($parts['scheme'] ?? '')) !== 'https' ||
        $host === '' || $host === 'localhost' ||
        str_ends_with($host, '.local') || str_ends_with($host, '.test') ||
        filter_var($host, FILTER_VALIDATE_IP) !== false
    ) {
        throw new RuntimeException('M-Pesa callback URL must be a public HTTPS address, not localhost or a private IP.');
    }
    if (!in_array((string) ($cfg['transaction_type'] ?? ''), ['CustomerPayBillOnline', 'CustomerBuyGoodsOnline'], true)) {
        throw new RuntimeException('M-Pesa transaction type is invalid.');
    }
}

/**
 * Select the correct Safaricom API environment.
 */
function mephysMpesaBaseUrl(array $cfg): string
{
    if (($cfg['environment'] ?? '') === 'sandbox') {
        return 'https://sandbox.safaricom.co.ke';
    }

    if (($cfg['environment'] ?? '') === 'production') {
        return 'https://api.safaricom.co.ke';
    }

    throw new RuntimeException('Invalid M-Pesa environment. Use sandbox or production.');
}

/**
 * Send an HTTP request to Safaricom.
 */
function mephysMpesaRequest(
    string $url,
    array $headers = [],
    ?string $body = null
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for M-Pesa.');
    }

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Could not initialize M-Pesa connection.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($body !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('M-Pesa connection failed: ' . $error);
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            'M-Pesa returned an invalid response. HTTP ' .
            $status . '. Response: ' . substr((string)$raw, 0, 1500)
        );
    }

    if ($status < 200 || $status >= 300) {
        $message = $data['errorMessage']
            ?? $data['error_description']
            ?? $data['message']
            ?? $raw;

        throw new RuntimeException(
            'Safaricom API error (HTTP ' . $status . '): ' .
            substr((string)$message, 0, 1500)
        );
    }

    return $data;
}

/**
 * Generate Safaricom OAuth access token.
 */
function mephysMpesaAccessToken(array $cfg): string
{
    if (
        ($cfg['consumer_key'] ?? '') === '' ||
        ($cfg['consumer_secret'] ?? '') === ''
    ) {
        throw new RuntimeException(
            'M-Pesa Consumer Key or Consumer Secret is missing.'
        );
    }

    $base = mephysMpesaBaseUrl($cfg);

    $url = $base . '/oauth/v1/generate?grant_type=client_credentials';

    $auth = base64_encode(
        $cfg['consumer_key'] . ':' . $cfg['consumer_secret']
    );

    $data = mephysMpesaRequest(
        $url,
        [
            'Authorization: Basic ' . $auth,
            'Accept: application/json',
        ]
    );

    if (empty($data['access_token'])) {
        throw new RuntimeException(
            'Safaricom did not return an access token.'
        );
    }

    return (string)$data['access_token'];
}

/**
 * Convert Kenyan mobile numbers into 254XXXXXXXXX format.
 */
function mephysMpesaPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        $digits = '254' . substr($digits, 1);
    }

    if (
        (str_starts_with($digits, '7') ||
         str_starts_with($digits, '1')) &&
        strlen($digits) === 9
    ) {
        $digits = '254' . $digits;
    }

    if (!preg_match('/^254[71]\d{8}$/', $digits)) {
        throw new InvalidArgumentException(
            'Enter a valid Kenyan mobile number, for example 0712345678.'
        );
    }

    return $digits;
}

/**
 * Initiate an M-Pesa STK Push.
 */
function mephysMpesaStkPush(
    array $cfg,
    string $phone,
    int $amount,
    string $orderReference
): array {
    mephysMpesaAssertConfigured($cfg);

    if ($amount < 1) {
        throw new InvalidArgumentException(
            'M-Pesa payment amount must be at least KES 1.'
        );
    }

    $callback = filter_var($cfg['callback_url'], FILTER_VALIDATE_URL);
    $callbackScheme = strtolower((string)parse_url($cfg['callback_url'], PHP_URL_SCHEME));

    if ($callback === false || $callbackScheme !== 'https') {
        throw new RuntimeException(
            'The M-Pesa callback URL must be a valid public HTTPS URL.'
        );
    }

    $phone = mephysMpesaPhone($phone);

    $base = mephysMpesaBaseUrl($cfg);
    $token = mephysMpesaAccessToken($cfg);

    $timestamp = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Africa/Nairobi')
    ))->format('YmdHis');

    $password = base64_encode(
        $cfg['shortcode'] .
        $cfg['passkey'] .
        $timestamp
    );

    $payload = [
        'BusinessShortCode' => $cfg['shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => $cfg['transaction_type'],
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $cfg['shortcode'],
        'PhoneNumber' => $phone,
        'CallBackURL' => $cfg['callback_url'],
        'AccountReference' => substr($orderReference, 0, 12),
        'TransactionDesc' => 'Mephys order payment',
    ];

    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    return mephysMpesaRequest(
        $base . '/mpesa/stkpush/v1/processrequest',
        [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        $json
    );
}

/**
 * Query the status of an existing STK Push.
 */
function mephysMpesaStkQuery(
    array $cfg,
    string $checkoutRequestId
): array {
    foreach (
        ['consumer_key', 'consumer_secret', 'shortcode', 'passkey']
        as $key
    ) {
        if (empty($cfg[$key])) {
            throw new RuntimeException(
                'M-Pesa STK query configuration is incomplete: ' . $key . ' is missing.'
            );
        }
    }

    $base = mephysMpesaBaseUrl($cfg);
    $token = mephysMpesaAccessToken($cfg);

    $timestamp = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Africa/Nairobi')
    ))->format('YmdHis');

    $password = base64_encode(
        $cfg['shortcode'] .
        $cfg['passkey'] .
        $timestamp
    );

    $payload = [
        'BusinessShortCode' => $cfg['shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkoutRequestId,
    ];

    return mephysMpesaRequest(
        $base . '/mpesa/stkpushquery/v1/query',
        [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        json_encode($payload, JSON_THROW_ON_ERROR)
    );
}
