<?php

declare(strict_types=1);

namespace Moemzade\Media;

use RuntimeException;

final class R2Storage implements Storage
{
    /** @param array{account_id:string,access_key_id:string,secret_access_key:string,bucket:string,public_url:string} $config */
    public function __construct(private readonly array $config)
    {
        foreach (['account_id', 'access_key_id', 'secret_access_key', 'bucket', 'public_url'] as $key) {
            if (trim($this->config[$key] ?? '') === '') {
                throw new RuntimeException("Missing R2 setting: {$key}");
            }
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Cloudflare R2.');
        }
    }

    public function driver(): string
    {
        return 'r2';
    }

    public function put(string $key, string $localFile, string $mimeType): string
    {
        $payload = file_get_contents($localFile);
        if ($payload === false) {
            throw new RuntimeException('Cannot read optimized image.');
        }

        $this->request('PUT', $key, $payload, $mimeType);
        return rtrim($this->config['public_url'], '/') . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    }

    public function delete(string $key): void
    {
        $this->request('DELETE', $key, '', 'application/octet-stream');
    }

    private function request(string $method, string $key, string $payload, string $mimeType): void
    {

        $key = ltrim(str_replace('..', '', $key), '/');
        $host = $this->config['account_id'] . '.r2.cloudflarestorage.com';
        $canonicalUri = '/' . rawurlencode($this->config['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $endpoint = 'https://' . $host . $canonicalUri;
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);
        $canonicalHeaders = "content-type:{$mimeType}\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "{$method}\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $scope = "{$date}/auto/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $this->config['secret_access_key'], true);
        $regionKey = hash_hmac('sha256', 'auto', $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->config['access_key_id'] . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $curl = curl_init($endpoint);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authorization,
                'Content-Type: ' . $mimeType,
                'Host: ' . $host,
                'x-amz-content-sha256: ' . $payloadHash,
                'x-amz-date: ' . $amzDate,
                'Expect:',
            ],
        ];
        if ($method === 'PUT') {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('R2 request failed (' . $status . '): ' . ($error ?: 'request rejected'));
        }
    }
}
