<?php
/**
 * OrderManager.php — Управление заказами
 * ShillCMS Payment Module v2.7.0
 *
 * Зависимости: PaymentHelper.php
 */

require_once __DIR__ . '/PaymentHelper.php';

/**
 * Создаёт новый заказ
 *
 * @param string $email          Email покупателя
 * @param array  $items          Массив позиций: [['product_id', 'name', 'price', 'qty', 'slug', 'icon', 'is_demo']]
 * @param string $paymentMethod  'yoomoney' | 'crypto' | 'demo'
 * @param string $prefix         Префикс номера заказа
 * @return array
 */
function pmCreateOrder($email, $items, $paymentMethod = 'yoomoney', $prefix = 'ORDER') {
    $totals = pmCalculateTotals($items);
    $orderNumber = pmCreateOrderNumber($prefix);
    return [
        'order_number'   => $orderNumber,
        'label'          => $orderNumber,
        'status'         => 'pending',
        'payment_status' => 'pending',
        'payment_method' => $paymentMethod,
        'email'          => $email,
        'items'          => $items,
        'totals'         => $totals,
        'created_at'     => date('Y-m-d H:i:s'),
        'updated_at'     => date('Y-m-d H:i:s'),
        'history'        => [[
            'time'    => date('Y-m-d H:i:s'),
            'status'  => 'created',
            'message' => 'Заказ создан',
        ]],
    ];
}

/**
 * Считает итоги заказа
 */
function pmCalculateTotals($items) {
    $amount   = 0;
    $quantity = 0;
    foreach ($items as $item) {
        $amount   += ((float)$item['price']) * ((int)$item['qty']);
        $quantity += (int)$item['qty'];
    }
    return ['amount' => round($amount, 2), 'quantity' => $quantity];
}

/**
 * Находит заказ в массиве по номеру или label
 * @return int Индекс или -1
 */
function pmFindOrderIndex($orders, $identifier) {
    foreach ($orders as $index => $order) {
        if (($order['order_number'] ?? '') === $identifier || ($order['label'] ?? '') === $identifier) {
            return $index;
        }
    }
    return -1;
}

/**
 * Загружает заказы из JSON-файла
 */
function pmLoadOrders($filePath) {
    if (!file_exists($filePath)) return [];
    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}

/**
 * Сохраняет заказы в JSON-файл
 */
function pmSaveOrders($filePath, $orders) {
    return file_put_contents($filePath, json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Добавляет запись в лог платежей
 */
function pmAppendPaymentLog($logFilePath, $event, $payload = []) {
    $logs   = file_exists($logFilePath) ? (json_decode(file_get_contents($logFilePath), true) ?? []) : [];
    $logs[] = ['time' => date('Y-m-d H:i:s'), 'event' => $event, 'payload' => $payload];
    file_put_contents($logFilePath, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Возвращает публичные данные заказа (без чувствительных полей)
 */
function pmBuildPublicOrderData($order) {
    $public = [
        'order_number'    => $order['order_number'] ?? '',
        'label'           => $order['label'] ?? '',
        'status'          => $order['status'] ?? 'pending',
        'payment_status'  => $order['payment_status'] ?? 'pending',
        'payment_method'  => $order['payment_method'] ?? 'demo',
        'amount'          => (float)($order['totals']['amount'] ?? 0),
        'quantity'        => (int)($order['totals']['quantity'] ?? 0),
        'created_at'      => $order['created_at'] ?? '',
        'updated_at'      => $order['updated_at'] ?? '',
        'paid_at'         => $order['paid_at'] ?? null,
        'fulfilled_at'    => $order['fulfilled_at'] ?? null,
        'is_demo_payment' => !empty($order['is_demo_payment']),
        'items'           => array_map(function($item) {
            return [
                'product_id' => (int)($item['product_id'] ?? 0),
                'slug'       => $item['slug'] ?? '',
                'name'       => $item['name'] ?? '',
                'qty'        => (int)($item['qty'] ?? 0),
                'price'      => (float)($item['price'] ?? 0),
            ];
        }, $order['items'] ?? []),
    ];

    if (($order['payment_method'] ?? '') === 'crypto' && !empty($order['crypto'])) {
        $c = $order['crypto'];
        $public['crypto'] = [
            'token_code'             => $c['token_code'] ?? '',
            'token_name'             => $c['token_name'] ?? '',
            'token_symbol'           => $c['token_symbol'] ?? '',
            'network'                => $c['network'] ?? '',
            'wallet'                 => $c['wallet'] ?? '',
            'wallet_mask'            => $c['wallet_mask'] ?? '',
            'expected_amount'        => (float)($c['expected_amount'] ?? 0),
            'expected_amount_text'   => $c['expected_amount_text'] ?? '',
            'amount_rub'             => (float)($c['amount_rub'] ?? 0),
            'amount_usd'             => (float)($c['amount_usd'] ?? 0),
            'rate_usd'               => (float)($c['rate_usd'] ?? 0),
            'payment_uri'            => $c['payment_uri'] ?? '',
            'qr_image_url'           => $c['qr_image_url'] ?? '',
            'invoice_status'         => $c['invoice_status'] ?? 'awaiting_payment',
            'created_at'             => $c['created_at'] ?? '',
            'expires_at'             => $c['expires_at'] ?? '',
        ];
    }

    if (!empty($order['transaction'])) {
        $public['transaction'] = $order['transaction'];
    }
    if (!empty($order['delivered_items'])) {
        $public['delivered_items'] = $order['delivered_items'];
    }

    return $public;
}
