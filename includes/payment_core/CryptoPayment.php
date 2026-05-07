<?php
/**
 * CryptoPayment.php — Модуль криптовалютной оплаты
 * ShillCMS Payment Module v2.7.0
 *
 * Поддерживаемые токены: BTC, ETH, USDT_TRC20, USDT_ERC20, USDT_BSC, USDT_POL,
 *                         SOL, TON, TRX, BNB, XMR, BCH, LTC, DOGE, DASH, POL
 *
 * Зависимости: PaymentHelper.php
 */

require_once __DIR__ . '/PaymentHelper.php';

// ─────────────────────────────────────────────────────────────────────────────
// Загрузка каталога токенов
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Загружает каталог токенов из JSON-файла
 * @param string $catalogPath Путь к файлу crypto_tokens.json
 * @return array
 */
function pmLoadCryptoTokens($catalogPath) {
    if (!file_exists($catalogPath)) return [];
    $catalog = json_decode(file_get_contents($catalogPath), true);
    return is_array($catalog['tokens'] ?? null) ? $catalog['tokens'] : [];
}

/**
 * Ищет токен по коду (например, 'USDT_TRC20')
 */
function pmFindTokenByCode($tokens, $code) {
    $code = strtoupper(trim((string)$code));
    foreach ($tokens as $token) {
        if (strtoupper((string)($token['code'] ?? '')) === $code) {
            return $token;
        }
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Курсы валют
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Получает актуальные курсы криптовалют (CoinGecko + ЦБ РФ)
 * Кэширует результат в файл на 12 часов.
 *
 * @param string $cachePath  Путь к файлу кэша (например, data/crypto_rates.json)
 * @param array  $tokens     Массив токенов из каталога
 * @param bool   $force      Принудительное обновление
 * @return array ['rates' => [...], 'usd_per_rub' => float, 'rub_per_usd' => float]
 */
function pmGetCryptoRates($cachePath, $tokens, $force = false) {
    $cache = file_exists($cachePath) ? (json_decode(file_get_contents($cachePath), true) ?? []) : [];

    // Проверка свежести кэша (12 часов)
    if (!$force && !empty($cache['updated_at_unix']) && (time() - (int)$cache['updated_at_unix']) < 43200) {
        return $cache;
    }

    $coinIds = [];
    foreach ($tokens as $token) {
        $coinId = trim((string)($token['coingecko_id'] ?? ''));
        if ($coinId !== '') $coinIds[$coinId] = true;
    }
    if (empty($coinIds)) return is_array($cache) ? $cache : [];

    $cgUrl    = 'https://api.coingecko.com/api/v3/simple/price?ids=' . rawurlencode(implode(',', array_keys($coinIds))) . '&vs_currencies=usd&include_last_updated_at=true';
    $cgData   = pmHttpGet($cgUrl);
    $fxData   = pmHttpGet('https://www.cbr-xml-daily.ru/daily_json.js');

    if (!is_array($cgData) || !is_array($fxData)) return is_array($cache) ? $cache : [];

    $usdValute = $fxData['Valute']['USD'] ?? null;
    $rubPerUsd = 0;
    if (is_array($usdValute) && !empty($usdValute['Value'])) {
        $rubPerUsd = ((float)$usdValute['Value']) / max(1, (float)($usdValute['Nominal'] ?? 1));
    }
    if ($rubPerUsd <= 0) return is_array($cache) ? $cache : [];

    $usdPerRub = 1 / $rubPerUsd;
    $rates     = [];
    foreach ($tokens as $token) {
        $coinId = trim((string)($token['coingecko_id'] ?? ''));
        $code   = strtoupper((string)($token['code'] ?? ''));
        if ($coinId === '' || $code === '' || empty($cgData[$coinId]['usd'])) continue;
        $usdRate = (float)$cgData[$coinId]['usd'];
        $rates[$code] = [
            'code'   => $code,
            'usd'    => $usdRate,
            'rub'    => $usdRate * $rubPerUsd,
        ];
    }

    $payload = [
        'updated_at'      => date('Y-m-d H:i:s'),
        'updated_at_unix' => time(),
        'usd_per_rub'     => $usdPerRub,
        'rub_per_usd'     => $rubPerUsd,
        'rates'           => $rates,
    ];
    file_put_contents($cachePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $payload;
}

// ─────────────────────────────────────────────────────────────────────────────
// Построение URI для оплаты
// ─────────────────────────────────────────────────────────────────────────────

function pmBuildCryptoPaymentUri($token, $wallet, $amount, $orderNumber = '') {
    $wallet = trim((string)$wallet);
    if ($wallet === '') return '';
    $code            = strtoupper((string)($token['code'] ?? ''));
    $scheme          = trim((string)($token['uri_scheme'] ?? ''));
    $formattedAmount = pmFormatCryptoAmount($amount, (int)($token['decimals'] ?? 8));

    switch ($code) {
        case 'BTC': case 'BCH': case 'LTC': case 'DOGE': case 'DASH':
            $base = ($scheme !== '' ? $scheme . ':' : '') . $wallet;
            return $base . '?amount=' . rawurlencode($formattedAmount);
        case 'XMR':
            $q = 'tx_amount=' . rawurlencode($formattedAmount);
            if ($orderNumber !== '') $q .= '&tx_description=' . rawurlencode('Order ' . $orderNumber);
            return 'monero:' . $wallet . '?' . $q;
        case 'SOL':
            return 'solana:' . $wallet . '?amount=' . rawurlencode($formattedAmount);
        case 'TRX': case 'USDT_TRC20':
            return 'tron:' . $wallet . '?amount=' . rawurlencode($formattedAmount);
        case 'TON':
            $nano = (string)max(0, (int)round(((float)$amount) * 1e9));
            $uri  = 'ton://transfer/' . $wallet . '?amount=' . rawurlencode($nano);
            if ($orderNumber !== '') $uri .= '&text=' . rawurlencode('Order ' . $orderNumber);
            return $uri;
        default:
            return $wallet;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Снятие снимка баланса (для верификации)
// ─────────────────────────────────────────────────────────────────────────────

function pmEvmRpc($url, $method, $params = []) {
    return pmHttpPost($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

function pmDetectEvmBalance($rpcUrl, $wallet, $decimals = 18) {
    $resp = pmEvmRpc($rpcUrl, 'eth_getBalance', [$wallet, 'latest']);
    $hex  = $resp['result'] ?? '';
    if (!is_string($hex) || $hex === '') return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => pmDecimalStringToFloat(pmHexToDecString($hex), $decimals)];
}

function pmDetectEvmTokenBalance($rpcUrl, $wallet, $contract, $decimals = 6) {
    $wallet   = strtolower(trim((string)$wallet));
    $contract = trim((string)$contract);
    if ($wallet === '' || $contract === '') return ['status' => 'unavailable', 'balance' => 0.0];
    $data = '0x70a08231' . str_pad(substr($wallet, 2), 64, '0', STR_PAD_LEFT);
    $resp = pmEvmRpc($rpcUrl, 'eth_call', [['to' => $contract, 'data' => $data], 'latest']);
    $hex  = $resp['result'] ?? '';
    if (!is_string($hex) || $hex === '') return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => pmDecimalStringToFloat(pmHexToDecString($hex), $decimals)];
}

function pmDetectSolanaBalance($wallet) {
    $resp  = pmHttpPost('https://api.mainnet-beta.solana.com', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'getBalance', 'params' => [$wallet, ['commitment' => 'finalized']]]);
    $value = $resp['result']['value'] ?? null;
    if (!is_numeric($value)) return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => ((float)$value / 1e9)];
}

function pmDetectTonBalance($wallet) {
    $resp    = pmHttpGet('https://toncenter.com/api/v2/getAddressInformation?address=' . rawurlencode($wallet));
    $balance = $resp['result']['balance'] ?? null;
    if (!is_numeric($balance)) return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => ((float)$balance / 1e9)];
}

function pmDetectTronBalance($wallet) {
    $resp    = pmHttpGet('https://apilist.tronscanapi.com/api/account?address=' . rawurlencode($wallet));
    $balance = $resp['balance'] ?? null;
    if (!is_numeric($balance)) return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => ((float)$balance / 1e6)];
}

function pmDetectTrc20Balance($wallet, $contract, $decimals = 6) {
    $resp   = pmHttpGet('https://apilist.tronscanapi.com/api/account/tokens?address=' . rawurlencode($wallet));
    $tokens = $resp['data'] ?? [];
    if (!is_array($tokens)) return ['status' => 'unavailable', 'balance' => 0.0];
    $contract = strtoupper(trim((string)$contract));
    foreach ($tokens as $token) {
        $addr = strtoupper((string)($token['tokenId'] ?? $token['tokenAddress'] ?? ''));
        if ($addr !== $contract) continue;
        $raw  = $token['balance'] ?? $token['amount'] ?? '0';
        $dec  = (int)($token['tokenDecimal'] ?? $token['tokenDecimalCount'] ?? $decimals);
        return ['status' => 'ok', 'balance' => pmDecimalStringToFloat((string)$raw, $dec)];
    }
    return ['status' => 'ok', 'balance' => 0.0];
}

function pmDetectBlockchainInfoBalance($wallet) {
    $resp    = pmHttpGet('https://blockchain.info/rawaddr/' . rawurlencode($wallet) . '?limit=0');
    $balance = $resp['final_balance'] ?? null;
    if (!is_numeric($balance)) return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => ((float)$balance / 1e8)];
}

function pmDetectBlockchairBalance($chain, $wallet, $decimals = 8) {
    $resp = pmHttpGet('https://api.blockchair.com/' . rawurlencode($chain) . '/dashboards/address/' . rawurlencode($wallet));
    $data = $resp['data'][$wallet]['address'] ?? null;
    if (!is_array($data)) return ['status' => 'unavailable', 'balance' => 0.0];
    $raw  = $data['balance'] ?? $data['received'] ?? null;
    if (!is_numeric($raw)) return ['status' => 'unavailable', 'balance' => 0.0];
    return ['status' => 'ok', 'balance' => ((float)$raw / pow(10, $decimals))];
}

/**
 * Снимает снимок баланса кошелька для заданного токена
 */
function pmTakeBalanceSnapshot($token, $wallet) {
    $code     = strtoupper((string)($token['code'] ?? ''));
    $wallet   = trim((string)$wallet);
    $contract = trim((string)($token['token_contract'] ?? ''));
    switch ($code) {
        case 'BTC':        return pmDetectBlockchainInfoBalance($wallet);
        case 'BCH':        return pmDetectBlockchairBalance('bitcoin-cash', $wallet, 8);
        case 'LTC':        return pmDetectBlockchairBalance('litecoin', $wallet, 8);
        case 'DOGE':       return pmDetectBlockchairBalance('dogecoin', $wallet, 8);
        case 'DASH':       return pmDetectBlockchairBalance('dash', $wallet, 8);
        case 'ETH':        return pmDetectEvmBalance('https://cloudflare-eth.com', $wallet, 18);
        case 'POL':        return pmDetectEvmBalance('https://polygon-rpc.com', $wallet, 18);
        case 'USDT_ERC20': return pmDetectEvmTokenBalance('https://cloudflare-eth.com', $wallet, $contract, (int)($token['decimals'] ?? 6));
        case 'USDT_POL':   return pmDetectEvmTokenBalance('https://polygon-rpc.com', $wallet, $contract, (int)($token['decimals'] ?? 6));
        case 'TRX':        return pmDetectTronBalance($wallet);
        case 'USDT_TRC20': return pmDetectTrc20Balance($wallet, $contract, (int)($token['decimals'] ?? 6));
        case 'SOL':        return pmDetectSolanaBalance($wallet);
        case 'TON':        return pmDetectTonBalance($wallet);
        case 'BNB': case 'USDT_BSC': case 'XMR':
            return ['status' => 'manual_review', 'balance' => 0.0];
        default:
            return ['status' => 'unavailable', 'balance' => 0.0];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Создание крипто-инвойса
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Строит данные крипто-инвойса для заказа
 *
 * @param array  $order       Заказ (должен содержать $order['crypto']['token_code'])
 * @param array  $tokens      Массив токенов из каталога
 * @param array  $ratesCache  Кэш курсов из pmGetCryptoRates()
 * @return array ['success' => bool, 'invoice' => array, ...]
 */
function buildCryptoPaymentData(&$order, $tokens, $ratesCache) {
    $selectedCode = strtoupper(trim((string)($order['crypto']['token_code'] ?? '')));
    if ($selectedCode === '') {
        return ['success' => false, 'error' => 'Не выбран токен для криптооплаты'];
    }

    $token = pmFindTokenByCode($tokens, $selectedCode);
    if (!$token) {
        return ['success' => false, 'error' => 'Выбранный токен недоступен'];
    }

    $wallet = trim((string)($token['wallet'] ?? ''));
    if ($wallet === '') {
        return ['success' => false, 'error' => 'Для выбранного токена не настроен кошелёк'];
    }

    $rates     = $ratesCache['rates'] ?? [];
    $tokenRate = $rates[$selectedCode] ?? null;
    if (!is_array($tokenRate) || empty($tokenRate['usd'])) {
        return ['success' => false, 'error' => 'Курс для выбранного токена недоступен'];
    }

    $rubAmount = round((float)($order['totals']['amount'] ?? 0), 2);
    $usdPerRub = (float)($ratesCache['usd_per_rub'] ?? 0);
    $rubPerUsd = (float)($ratesCache['rub_per_usd'] ?? 0);
    $usdAmount = $usdPerRub > 0 ? ($rubAmount * $usdPerRub) : ($rubPerUsd > 0 ? ($rubAmount / $rubPerUsd) : 0);
    if ($usdAmount <= 0) {
        return ['success' => false, 'error' => 'Не удалось пересчитать стоимость в USD'];
    }

    $decimals       = (int)($token['decimals'] ?? 8);
    $expectedAmount = round($usdAmount / (float)$tokenRate['usd'], min(10, max(4, $decimals)));
    $salt           = pmBuildAmountSalt($order['order_number'] ?? '', $decimals);
    $expectedAmount = round($expectedAmount + $salt, min(10, max(4, $decimals)));
    $paymentUri     = pmBuildCryptoPaymentUri($token, $wallet, $expectedAmount, $order['order_number'] ?? '');
    $qrValue        = $paymentUri !== '' ? $paymentUri : $wallet;
    $balanceSnap    = pmTakeBalanceSnapshot($token, $wallet);

    $invoice = [
        'token_code'           => $selectedCode,
        'token_name'           => (string)($token['name'] ?? $selectedCode),
        'token_symbol'         => (string)($token['usd_symbol'] ?? $selectedCode),
        'network'              => (string)($token['network'] ?? ''),
        'wallet'               => $wallet,
        'wallet_mask'          => pmBuildWalletMask($wallet),
        'expected_amount'      => $expectedAmount,
        'expected_amount_text' => pmFormatCryptoAmount($expectedAmount, $decimals),
        'amount_rub'           => $rubAmount,
        'amount_usd'           => round($usdAmount, 2),
        'rate_usd'             => (float)($tokenRate['usd'] ?? 0),
        'rate_rub'             => (float)($tokenRate['rub'] ?? 0),
        'confirmations_required' => (int)($token['confirmations_required'] ?? 1),
        'amount_salt'          => $salt,
        'snapshot_balance'     => (float)($balanceSnap['balance'] ?? 0),
        'snapshot_status'      => (string)($balanceSnap['status'] ?? 'unavailable'),
        'snapshot_taken_at'    => date('Y-m-d H:i:s'),
        'payment_uri'          => $paymentUri,
        'qr_value'             => $qrValue,
        'qr_image_url'         => 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode($qrValue),
        'invoice_status'       => 'awaiting_payment',
        'created_at'           => date('Y-m-d H:i:s'),
        'expires_at'           => date('Y-m-d H:i:s', time() + (20 * 60)),
    ];

    $order['crypto'] = $invoice;

    return [
        'success' => true,
        'type'    => 'crypto_invoice',
        'gateway' => 'crypto',
        'invoice' => $invoice,
        'display' => [
            'title'       => 'Оплата криптовалютой',
            'description' => 'Переведите точную сумму на указанный адрес и дождитесь подтверждения сети.',
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Автоверификация крипто-платежа
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Проверяет, не истёк ли инвойс
 */
function pmIsCryptoInvoiceExpired($order) {
    $expiresAt = trim((string)($order['crypto']['expires_at'] ?? ''));
    if ($expiresAt === '') return false;
    $ts = strtotime($expiresAt);
    return $ts !== false && time() >= $ts;
}

/**
 * Пытается автоматически верифицировать крипто-платёж по балансу кошелька.
 * Изменяет $order по ссылке при успехе.
 *
 * @param array  $order   Заказ (по ссылке)
 * @param array  $tokens  Каталог токенов
 * @return bool  true если платёж подтверждён
 */
function pmTryAutoVerifyCryptoOrder(&$order, $tokens) {
    if (($order['payment_method'] ?? '') !== 'crypto' || ($order['payment_status'] ?? '') === 'paid') {
        return false;
    }
    if (empty($order['crypto']) || !is_array($order['crypto'])) {
        return false;
    }

    if (pmIsCryptoInvoiceExpired($order)) {
        $order['crypto']['invoice_status'] = 'expired';
        $order['payment_status']           = 'failed';
        $order['status']                   = 'failed';
        $order['history'][]                = [
            'time'    => date('Y-m-d H:i:s'),
            'status'  => 'failed',
            'message' => 'Инвойс истёк без подтверждения оплаты',
        ];
        return false;
    }

    $tokenCode = strtoupper((string)($order['crypto']['token_code'] ?? ''));
    $token     = pmFindTokenByCode($tokens, $tokenCode);
    if (!$token) return false;

    $wallet          = trim((string)($order['crypto']['wallet'] ?? ''));
    $contract        = trim((string)($token['token_contract'] ?? ''));
    $decimals        = (int)($token['decimals'] ?? 8);
    $expectedAmount  = (float)($order['crypto']['expected_amount'] ?? 0);
    $snapshotBalance = (float)($order['crypto']['snapshot_balance'] ?? 0);

    $currentSnap = pmTakeBalanceSnapshot($token, $wallet);
    $currentBal  = (float)($currentSnap['balance'] ?? 0);

    $order['crypto']['last_checked_at']     = date('Y-m-d H:i:s');
    $order['crypto']['verification_source'] = (string)($currentSnap['status'] ?? '');

    if (($currentSnap['status'] ?? '') === 'unavailable') {
        return false;
    }
    if (($currentSnap['status'] ?? '') === 'manual_review') {
        return false;
    }

    $delta = round($currentBal - $snapshotBalance, $decimals);
    $order['crypto']['detected_delta']      = $delta;
    $order['crypto']['detected_delta_text'] = pmFormatCryptoAmount($delta, $decimals);

    $tolerance = $expectedAmount * 0.001; // 0.1% допуск
    if ($delta >= ($expectedAmount - $tolerance)) {
        $order['status']                        = 'paid';
        $order['payment_status']                = 'paid';
        $order['paid_at']                       = date('Y-m-d H:i:s');
        $order['updated_at']                    = date('Y-m-d H:i:s');
        $order['crypto']['invoice_status']      = 'paid';
        $order['crypto']['verification_status'] = 'confirmed';
        $order['transaction'] = [
            'gateway'    => 'crypto',
            'token_code' => $tokenCode,
            'amount'     => $delta,
            'source'     => $order['crypto']['verification_source'],
        ];
        $order['history'][] = [
            'time'    => date('Y-m-d H:i:s'),
            'status'  => 'paid',
            'message' => sprintf('Крипто-платёж подтверждён: +%s %s', pmFormatCryptoAmount($delta, $decimals), $tokenCode),
        ];
        return true;
    }

    return false;
}
