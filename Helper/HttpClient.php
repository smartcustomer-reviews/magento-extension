<?php

namespace SmartCustomer\Reviews\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class HttpClient extends AbstractHelper
{
    const HTTP_REQUEST_TIMEOUT = 3;

    public function __construct()
    {
    }

    public function request($url, $httpRequest, $origin = null, $data = null, $params = [], $timeout = self::HTTP_REQUEST_TIMEOUT)
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            // Cap the entire request, not just connection establishment. This
            // keeps order saves fail-open when the remote service accepts a
            // connection but does not send a response.
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($httpRequest == 'POST') {
                $encoded_data = json_encode($data);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'content-type: application/json',
                    'Content-Length: ' . strlen($encoded_data),
                    'Origin: ' . $origin
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded_data);
            } elseif ($httpRequest == 'GET') {
                curl_setopt($ch, CURLOPT_POST, false);
            }
                
            if (!empty($params) && is_array($params)) {
                $url .= '?' . http_build_query($params);
            }
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $content = curl_exec($ch);
            $responseData = json_decode($content);
            $responseInfo = curl_getinfo($ch);
            $responseCode = $responseInfo['http_code'];
            
            $response = [
                'code' => $responseCode
            ];
            if (is_object($responseData) || is_array($responseData)) {
                $response['data'] = $responseData;
            }
            
            return $response;
        } catch (\Throwable $e) {

        }
    }
}
