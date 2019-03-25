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
            $client_uri = @end(explode('/',$uri));
            if($client_uri == 'clients') {
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
            }
            else {
                $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_NUMERIC_CHECK);
            }
            
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($chSign, $options);

        $res = curl_exec($chSign);
        $error = curl_error($chSign);
        $resStatus = curl_getinfo($chSign, CURLINFO_HTTP_CODE);

        if (($resStatus >= 400)
            && isset($res)
            && isset(json_decode($res)->code)) {
            if(json_decode($res)->code == 'AMOUNT_TOO_BIG') {
                throw new \Exception('The value of your cart exceeds the maximum amount. Please remove some of the items from the cart.');
            }
            if(json_decode($res)->code == 'AMOUNT_TOO_SMALL') {
                throw new \Exception('amount_fiat in body should be greater than or equal to 0.01');
            }
            if(json_decode($res)->code == 'INVALID_PHONE_NUMBER') {
                throw new \Exception('Invalid phone number');
            }
            if(json_decode($res)->code == 'AUTH_TOKEN_INVALID') {
                throw new \Exception('Monetha plugin setup is invalid, please contact merchant.');
            }
            if(json_decode($res)->code == 'INTERNAL_ERROR') {
                throw new \Exception('There\'s some internal server error, please contact merchant.');
            }
            if(json_decode($res)->code == 'UNSUPPORTED_CURRENCY') {
                throw new \Exception('Selected currency is not supported by monetha.');
            }
            if(json_decode($res)->code == 'PROCESSOR_MISSING') {
                throw new \Exception('Can\'t process order, please contact merchant.');
            }
            if(json_decode($res)->code == 'INVALID_PHONE_COUNTRY_CODE') {
                throw new \Exception('This country code is invalid, please input correct country code.');
            }
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
