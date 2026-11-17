<?php

require_once __DIR__ . '/config.php';

function callApi(
    string $path,
    string $method = 'GET',
    ?array $data = null,
    ?string $token = null
): array {

    $url = API_BASE . ltrim($path, '/');

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => VERIFY_SSL,
        CURLOPT_SSL_VERIFYHOST => VERIFY_SSL ? 2 : 0,
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);

    curl_close($ch);

    if ($body === false) {
        return [
            'status' => 0,
            'data'   => null,
            'error'  => $error,
            'raw'    => null
        ];
    }

    return [
        'status' => $status,
        'data'   => json_decode($body, true),
        'error'  => null,
        'raw'    => $body
    ];
}