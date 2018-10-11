<?php

class HttpService
{
    public static function callApi(string $uri, string $method = 'GET', array $body = null, array $headers)
    {
        $chSign = curl_init();

        $options = [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>  array_merge($headers, array(
                "Cache-Control: no-cache",
                "Content-Type: application/json"
            )),
        ];

        if ($method !== 'GET' && $body) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_NUMERIC_CHECK);
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($chSign, $options);

        $res = curl_exec($chSign);
        $error = curl_error($chSign);
        $resStatus = curl_getinfo($chSign, CURLINFO_HTTP_CODE);

        if ($resStatus == 400
            && isset($res)
            && isset(json_decode($res)->code)
            && json_decode($res)->code == 'AMOUNT_TOO_BIG') {
            throw new \Exception('The value of your cart exceeds the maximum amount. Please remove some of the items from the cart.');
        }

        if ($error) {
            throw new \Exception($error);
        }

        if ($resStatus < 200 || $resStatus >= 300) {
            throw new \Exception($res);
        }

        $resJson = json_decode($res);

        curl_close($chSign);

        return $resJson;
    }
}
