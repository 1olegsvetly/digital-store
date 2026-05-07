<?php
/**
 * PaymentHelper.php — Вспомогательные функции для системы оплаты
 * ShillCMS Payment Module v2.7.0
 *
 * Зависимости: нет (standalone)
 */

/**
 * Универсальный HTTP-запрос с поддержкой cURL и file_get_contents
 */
function pmHttpRequest($url, $method = 'GET', $headers = [], $body = null, $timeout = 20) {
    $method = strtoupper(trim((string)$method)) ?: 'GET';
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_USERAGENT      => 'ShillCMS-PaymentModule/2.7.0',
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);
        $response   = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || $statusCode >= 400) return null;
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    $contextOptions = [
        'http' => [
            'method'     => $method,
            'timeout'    => $timeout,
            'header'     => implode("\r\n", $headerLines),
            'user_agent' => 'ShillCMS-PaymentModule/2.7.0',
        ]
    ];
    if ($body !== null) {
        $contextOptions['http']['content'] = $body;
    }
    $context  = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function pmHttpGet($url, $headers = [], $timeout = 20) {
    return pmHttpRequest($url, 'GET', $headers, null, $timeout);
}

function pmHttpPost($url, $payload = [], $headers = [], $timeout = 20) {
    $headers = array_merge(['Content-Type' => 'application/json'], $headers);
    $body    = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
    return pmHttpRequest($url, 'POST', $headers, $body, $timeout);
}

/**
 * Форматирование суммы криптовалюты (убирает лишние нули)
 */
function pmFormatCryptoAmount($amount, $decimals = 8) {
    $precision = max(0, min(12, (int)$decimals));
    $formatted = number_format((float)$amount, $precision, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted !== '' ? $formatted : '0';
}

/**
 * Маска кошелька: первые 7 + ... + последние 6 символов
 */
function pmBuildWalletMask($wallet) {
    $wallet = trim((string)$wallet);
    if ($wallet === '') return '';
    return mb_strlen($wallet) > 14
        ? mb_substr($wallet, 0, 7) . '...' . mb_substr($wallet, -6)
        : $wallet;
}

/**
 * Генерация уникального номера заказа
 */
function pmCreateOrderNumber($prefix = 'ORDER') {
    return $prefix . '-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * Построение абсолютного URL по текущему хосту
 */
function pmBuildAbsoluteUrl($path) {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    } else {
        $baseUrl = 'http://localhost';
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Безопасная очистка строки
 */
function pmSanitize($input) {
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Перевод hex в десятичную строку (для EVM-балансов)
 */
function pmHexToDecString($hex) {
    $hex = strtolower(preg_replace('/^0x/', '', trim((string)$hex)));
    if ($hex === '' || $hex === '0') return '0';
    $dec = '0';
    for ($i = 0; $i < strlen($hex); $i++) {
        $carry  = hexdec($hex[$i]);
        $result = '';
        for ($j = strlen($dec) - 1; $j >= 0; $j--) {
            $num    = ((int)$dec[$j]) * 16 + $carry;
            $result = ($num % 10) . $result;
            $carry  = intdiv($num, 10);
        }
        while ($carry > 0) {
            $result = ($carry % 10) . $result;
            $carry  = intdiv($carry, 10);
        }
        $dec = ltrim($result, '0') ?: '0';
    }
    return $dec;
}

/**
 * Перевод целочисленной строки в float с учётом decimals
 */
function pmDecimalStringToFloat($value, $decimals) {
    $value    = trim((string)$value);
    $decimals = max(0, min(18, (int)$decimals));
    if ($value === '' || $decimals === 0) return (float)$value;
    $negative = ($value[0] === '-');
    if ($negative) $value = substr($value, 1);
    $value = ltrim($value, '0') ?: '0';
    if (strlen($value) <= $decimals) {
        $value = str_pad($value, $decimals + 1, '0', STR_PAD_LEFT);
    }
    $intPart  = substr($value, 0, -$decimals) ?: '0';
    $fracPart = substr($value, -$decimals);
    $float    = (float)($intPart . '.' . $fracPart);
    return $negative ? -$float : $float;
}

/**
 * Уникальная соль суммы для различения платежей одного токена
 */
function pmBuildAmountSalt($orderNumber, $decimals) {
    $decimals = max(4, min(12, (int)$decimals));
    $baseStep = pow(10, -min($decimals, 6));
    $hash     = abs(crc32((string)$orderNumber));
    $steps    = ($hash % 899) + 101;
    return round($baseStep * $steps, $decimals);
}
