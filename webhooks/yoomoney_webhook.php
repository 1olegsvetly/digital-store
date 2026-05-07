<?php
/**
 * yoomoney_webhook.php — Готовый endpoint для HTTP-уведомлений YooMoney
 * ShillCMS Payment Module v2.7.0
 *
 * Разместите этот файл на вашем сервере и укажите его URL в настройках
 * YooMoney → «HTTP-уведомления».
 *
 * URL: https://yoursite.ru/webhooks/yoomoney_webhook.php
 */

// ─── Конфигурация ────────────────────────────────────────────────────────────
// Укажите пути к вашим файлам данных и конфигу
define('PM_ROOT',       __DIR__ . '/../');
define('PM_ORDERS',     PM_ROOT . 'data/orders.json');
define('PM_PAYMENTS',   PM_ROOT . 'data/payments.json');
define('PM_CONFIG',     PM_ROOT . 'config/payment.php');

// ─── Подключение модуля ──────────────────────────────────────────────────────
require_once PM_ROOT . 'core/PaymentHelper.php';
require_once PM_ROOT . 'core/YooMoneyPayment.php';
require_once PM_ROOT . 'core/OrderManager.php';

// ─── Загрузка конфига ────────────────────────────────────────────────────────
$config = file_exists(PM_CONFIG) ? require PM_CONFIG : [];
$secret = trim((string)($config['yoomoney']['notification_secret'] ?? ''));

// ─── Получение данных webhook ────────────────────────────────────────────────
$payload = $_POST;
if (empty($payload)) {
    parse_str(file_get_contents('php://input'), $payload);
}

pmAppendPaymentLog(PM_PAYMENTS, 'yoomoney_webhook_received', $payload);
http_response_code(200);

// ─── Обработка ───────────────────────────────────────────────────────────────
if (!$secret) {
    pmAppendPaymentLog(PM_PAYMENTS, 'yoomoney_webhook_error', ['error' => 'notification_secret не настроен']);
    echo 'OK';
    exit;
}

$orders    = pmLoadOrders(PM_ORDERS);
$paidIndex = -1;
$result    = processYooMoneyWebhook($payload, $secret, $orders, $paidIndex);

if (!$result['verified']) {
    pmAppendPaymentLog(PM_PAYMENTS, 'yoomoney_webhook_invalid', [
        'label'        => $payload['label'] ?? '',
        'operation_id' => $payload['operation_id'] ?? '',
        'error'        => $result['error'],
    ]);
    echo 'OK';
    exit;
}

if (!$result['paid']) {
    pmAppendPaymentLog(PM_PAYMENTS, 'yoomoney_webhook_skipped', [
        'label' => $payload['label'] ?? '',
        'error' => $result['error'],
    ]);
    echo 'OK';
    exit;
}

// Сохраняем обновлённые заказы
pmSaveOrders(PM_ORDERS, $orders);
pmAppendPaymentLog(PM_PAYMENTS, 'yoomoney_webhook_paid', [
    'order_number' => $orders[$paidIndex]['order_number'] ?? '',
    'operation_id' => $payload['operation_id'] ?? '',
]);

// ─── Здесь можно добавить выдачу товара ─────────────────────────────────────
// Например:
// require_once PM_ROOT . 'core/FulfillmentManager.php';
// fulfillOrder($orders[$paidIndex]);
// pmSaveOrders(PM_ORDERS, $orders);

echo 'OK';
exit;
