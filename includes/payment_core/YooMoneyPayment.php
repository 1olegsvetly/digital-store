<?php
/**
 * YooMoneyPayment.php — Модуль оплаты через YooMoney
 * ShillCMS Payment Module v2.7.0
 *
 * Зависимости: PaymentHelper.php
 */

require_once __DIR__ . '/PaymentHelper.php';

/**
 * Строит данные для формы YooMoney (POST-редирект на yoomoney.ru)
 *
 * @param array $order   Заказ: ['order_number', 'label', 'totals' => ['amount']]
 * @param array $config  Конфиг: ['yoomoney' => ['wallet', 'payment_type', 'success_url', 'fail_url']]
 * @return array ['success' => bool, 'type' => 'redirect_form', 'action', 'method', 'fields', ...]
 */
function buildYooMoneyPaymentData($order, $config) {
    $wallet = trim((string)($config['yoomoney']['wallet'] ?? ''));
    if ($wallet === '') {
        return ['success' => false, 'error' => 'Не указан кошелёк YooMoney в конфиге'];
    }

    $paymentType = strtoupper((string)($config['yoomoney']['payment_type'] ?? 'AC'));
    if (!in_array($paymentType, ['AC', 'PC'], true)) {
        $paymentType = 'AC';
    }

    $successUrl = trim((string)($config['yoomoney']['success_url'] ?? ''));
    if ($successUrl === '') {
        $successUrl = pmBuildAbsoluteUrl('oplata/?status=success&order=' . rawurlencode($order['order_number'] ?? ''));
    } elseif (strpos($successUrl, 'order=') === false) {
        $sep        = (strpos($successUrl, '?') === false) ? '?' : '&';
        $successUrl .= $sep . 'order=' . rawurlencode($order['order_number'] ?? '');
    }

    $failUrl = trim((string)($config['yoomoney']['fail_url'] ?? ''));
    if ($failUrl === '') {
        $failUrl = pmBuildAbsoluteUrl('oplata/?status=fail&order=' . rawurlencode($order['order_number'] ?? ''));
    }

    return [
        'success' => true,
        'type'    => 'redirect_form',
        'gateway' => 'yoomoney',
        'action'  => 'https://yoomoney.ru/quickpay/confirm',
        'method'  => 'POST',
        'fields'  => [
            'receiver'      => $wallet,
            'quickpay-form' => 'button',
            'paymentType'   => $paymentType,
            'targets'       => 'Оплата заказа ' . ($order['order_number'] ?? ''),
            'sum'           => number_format((float)($order['totals']['amount'] ?? 0), 2, '.', ''),
            'label'         => $order['label'] ?? $order['order_number'] ?? '',
            'successURL'    => $successUrl,
        ],
        'display' => [
            'title'       => 'Оплата через YooMoney',
            'description' => $paymentType === 'AC'
                ? 'Переход на оплату банковской картой через YooMoney'
                : 'Переход на оплату через кошелёк YooMoney',
        ],
    ];
}

/**
 * Верификация SHA1-подписи уведомления от YooMoney
 *
 * @param array  $payload Данные из $_POST
 * @param string $secret  notification_secret из настроек YooMoney
 * @return bool
 */
function verifyYooMoneyNotification($payload, $secret) {
    if (!$secret) return false;
    $parts = [
        $payload['notification_type'] ?? '',
        $payload['operation_id']      ?? '',
        $payload['amount']            ?? '',
        $payload['currency']          ?? '',
        $payload['datetime']          ?? '',
        $payload['sender']            ?? '',
        $payload['codepro']           ?? '',
        $secret,
        $payload['label']             ?? '',
    ];
    $hash = sha1(implode('&', $parts));
    return hash_equals($hash, (string)($payload['sha1_hash'] ?? ''));
}

/**
 * Обработка входящего YooMoney webhook
 * Возвращает массив с результатом обработки.
 *
 * @param array  $payload Данные из $_POST / php://input
 * @param string $secret  notification_secret
 * @param array  $orders  Массив заказов (передаётся по ссылке)
 * @param int    &$paidIndex  Индекс оплаченного заказа (out)
 * @return array ['verified' => bool, 'paid' => bool, 'error' => string|null]
 */
function processYooMoneyWebhook($payload, $secret, &$orders, &$paidIndex) {
    $paidIndex = -1;

    if (!verifyYooMoneyNotification($payload, $secret)) {
        return ['verified' => false, 'paid' => false, 'error' => 'Неверная подпись уведомления'];
    }

    $identifier = trim((string)($payload['label'] ?? ''));
    foreach ($orders as $index => $order) {
        if (($order['order_number'] ?? '') === $identifier || ($order['label'] ?? '') === $identifier) {
            $paidIndex = $index;
            break;
        }
    }

    if ($paidIndex < 0) {
        return ['verified' => true, 'paid' => false, 'error' => 'Заказ не найден: ' . $identifier];
    }

    if (($orders[$paidIndex]['payment_status'] ?? '') === 'paid') {
        return ['verified' => true, 'paid' => false, 'error' => 'Заказ уже оплачен'];
    }

    $withdrawAmount  = (float)($payload['withdraw_amount'] ?? 0);
    $receivedAmount  = (float)($payload['amount'] ?? 0);
    $incomingAmount  = round(max($withdrawAmount, $receivedAmount), 2);
    $expectedAmount  = round((float)($orders[$paidIndex]['totals']['amount'] ?? 0), 2);
    $tolerance       = max(0.01 * $expectedAmount, 1.0);

    if ($incomingAmount < $expectedAmount - $tolerance) {
        return [
            'verified' => true,
            'paid'     => false,
            'error'    => sprintf('Сумма не совпадает: ожидалось %.2f, получено %.2f', $expectedAmount, $incomingAmount),
        ];
    }

    $orders[$paidIndex]['status']         = 'paid';
    $orders[$paidIndex]['payment_status'] = 'paid';
    $orders[$paidIndex]['payment_method'] = 'yoomoney';
    $orders[$paidIndex]['paid_at']        = date('Y-m-d H:i:s');
    $orders[$paidIndex]['updated_at']     = date('Y-m-d H:i:s');
    $orders[$paidIndex]['transaction']    = [
        'gateway'           => 'yoomoney',
        'operation_id'      => $payload['operation_id'] ?? '',
        'notification_type' => $payload['notification_type'] ?? '',
        'amount'            => $incomingAmount,
        'sender'            => $payload['sender'] ?? '',
        'datetime'          => $payload['datetime'] ?? '',
    ];
    $orders[$paidIndex]['history'][] = [
        'time'    => date('Y-m-d H:i:s'),
        'status'  => 'paid',
        'message' => 'Платёж подтверждён уведомлением YooMoney',
    ];

    return ['verified' => true, 'paid' => true, 'error' => null];
}
