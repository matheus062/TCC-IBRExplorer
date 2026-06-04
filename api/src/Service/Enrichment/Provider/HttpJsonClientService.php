<?php

declare(strict_types=1);

namespace IBRExplorer\Service\Enrichment\Provider;

class HttpJsonClientService {

    public function get(
        string $url,
        array  $query = [],
        array  $headers = [],
        float  $timeoutSeconds = 8.0,
        ?array $basicAuth = null
    ): array {
        return $this->request('GET', $url, $query, $headers, $timeoutSeconds, $basicAuth);
    }

    private function request(
        string $method,
        string $url,
        array  $query = [],
        array  $headers = [],
        float  $timeoutSeconds = 8.0,
        ?array $basicAuth = null,
        ?array $jsonBody = null
    ): array {
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return [
                'executed' => false,
                'ok' => false,
                'statusCode' => 0,
                'headers' => [],
                'data' => null,
                'body' => null,
                'error' => 'Não foi possível inicializar a requisição HTTP.',
            ];
        }

        $responseHeaders = [];

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT_MS => (int)($timeoutSeconds * 1000),
            CURLOPT_TIMEOUT_MS => (int)($timeoutSeconds * 1000),
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($basicAuth !== null) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, ($basicAuth['username'] ?? '') . ':' . ($basicAuth['password'] ?? ''));
        }

        if ($jsonBody !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($jsonBody));
        }

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false) {
            return [
                'executed' => true,
                'ok' => false,
                'statusCode' => $statusCode,
                'headers' => $responseHeaders,
                'data' => null,
                'body' => null,
                'error' => $error ?: 'Falha HTTP desconhecida.',
            ];
        }

        $data = json_decode($body, true);

        return [
            'executed' => true,
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'statusCode' => $statusCode,
            'headers' => $responseHeaders,
            'data' => is_array($data) ? $data : null,
            'body' => $body,
            'error' => is_array($data) ? null : json_last_error_msg(),
        ];
    }

    private function formatHeaders(array $headers): array {
        $formatted = [];

        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }

        return $formatted;
    }

    public function postJson(
        string $url,
        array  $body = [],
        array  $query = [],
        array  $headers = [],
        float  $timeoutSeconds = 8.0,
        ?array $basicAuth = null
    ): array {
        $headers = [
            'Content-Type' => 'application/json',
            ...$headers,
        ];

        return $this->request('POST', $url, $query, $headers, $timeoutSeconds, $basicAuth, $body);
    }

}
