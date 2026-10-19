<?php


/*
|--------------------------------------------------------------------------
| PROVIDER API HELPER
|--------------------------------------------------------------------------
*/

function provider_settings()
{
    global $db;

    $result = $db->query("
        SELECT *
        FROM api_settings
        WHERE status = 1
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$result || !$result->num_rows) {
        throw new Exception("Provider API is not configured.");
    }

    return $result->fetch_assoc();
}


/*
|--------------------------------------------------------------------------
| PROVIDER REQUEST
|--------------------------------------------------------------------------
*/

function provider_request($params)
{
    $settings = provider_settings();

    $params['key'] = $settings['api_key'];

    $ch = curl_init($settings['api_url']);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {

        $error = curl_error($ch);

        curl_close($ch);

        throw new Exception(
            "Provider connection failed: " . $error
        );
    }

    $http_code = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($http_code < 200 || $http_code >= 300) {

        throw new Exception(
            "Provider returned HTTP " . $http_code
        );
    }

    $data = json_decode(
        $response,
        true
    );

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {

        throw new Exception(
            "Invalid provider response."
        );
    }

    return $data;
}


/*
|--------------------------------------------------------------------------
| GET PROVIDER SERVICES
|--------------------------------------------------------------------------
*/

function provider_services()
{
    return provider_request([
        'action' => 'services'
    ]);
}


/*
|--------------------------------------------------------------------------
| CREATE PROVIDER ORDER
|--------------------------------------------------------------------------
*/

function provider_add_order(
    $service_id,
    $link,
    $quantity
) {

    return provider_request([
        'action'   => 'add',
        'service'  => $service_id,
        'link'     => $link,
        'quantity' => $quantity
    ]);
}


/*
|--------------------------------------------------------------------------
| CHECK PROVIDER ORDER STATUS
|--------------------------------------------------------------------------
*/

function provider_order_status(
    $provider_order_id
) {

    return provider_request([
        'action' => 'status',
        'order'  => $provider_order_id
    ]);
}


/*
|--------------------------------------------------------------------------
| PROVIDER BALANCE
|--------------------------------------------------------------------------
*/

function provider_balance()
{
    return provider_request([
        'action' => 'balance'
    ]);
}