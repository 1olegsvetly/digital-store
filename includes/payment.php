<?php
/**
 * Payment Module v1.0.2
 * Интеграция с системой оплаты ShillCMS v2.7.0
 * Обработка платежей, создание заказов и выдача товаров
 * Поддержка: YooMoney, Crypto (BTC, ETH, USDT, SOL, TON, TRX, BNB, XMR)
 * Без базы данных, хранение в JSON
 */

require_once __DIR__ . '/payment_core/PaymentHelper.php';
require_once __DIR__ . '/payment_core/YooMoneyPayment.php';
require_once __DIR__ . '/payment_core/CryptoPayment.php';
require_once __DIR__ . '/payment_core/OrderManager.php';

class PaymentSystem {
    private $ordersFile = '/data/orders/pending.json';
    private $completedFile = '/data/orders/completed.json';
    private $productsDir = '/data/products/';
    private $config;
    private $tokens;
    private $ratesCache;

    public function __construct() {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $this->ordersFile)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . $this->ordersFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $this->completedFile)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . $this->completedFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        $configFile = $_SERVER['DOCUMENT_ROOT'] . '/config/payment.php';
        $this->config = file_exists($configFile) ? require $configFile : [];
        
        $tokensFile = $_SERVER['DOCUMENT_ROOT'] . ($this->config['crypto']['tokens_file'] ?? '/data/crypto/tokens.json');
        $this->tokens = pmLoadCryptoTokens($tokensFile);
        
        $ratesFile = $_SERVER['DOCUMENT_ROOT'] . ($this->config['crypto']['rates_cache'] ?? '/data/crypto/rates.json');
        $this->ratesCache = pmGetCryptoRates($ratesFile, $this->tokens);
    }

    public function createOrder($items, $user_data, $paymentMethod = 'yoomoney') {
        $orderId = pmCreateOrderNumber('ORD');
        $total = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $product = $this->findProduct($item['id']);
            if (!$product || $product['stock'] < $item['qty']) {
                throw new Exception("Товар {$item['id']} недоступен");
            }

            $itemTotal = $product['price'] * $item['qty'];
            $total += $itemTotal;

            $orderItems[] = [
                'product_id' => $product['id'],
                'title' => $product['title'],
                'slug' => $product['slug'] ?? '',
                'icon' => $product['image_url'] ?? '',
                'is_demo' => false,
                'price' => $product['price'],
                'qty' => $item['qty']
            ];
        }

        $order = pmCreateOrder(
            $user_data['email'] ?? 'guest@example.com',
            $orderItems,
            $paymentMethod,
            'ORD'
        );
        
        $order['totals']['amount'] = $total;
        $order['payment_token'] = bin2hex(random_bytes(16));
        $order['delivery_data'] = null;

        $this->saveOrder($order, 'pending');
        $paymentData = $this->getPaymentData($order, $paymentMethod);

        return [
            'order_id' => $order['order_number'],
            'payment_url' => '/checkout/pay.php?token=' . $order['payment_token'],
            'payment_data' => $paymentData,
            'total' => $total
        ];
    }
    
    public function getPaymentData($order, $paymentMethod) {
        if ($paymentMethod === 'demo') {
            return [
                'success' => true,
                'type' => 'demo',
                'gateway' => 'demo',
                'display' => [
                    'title' => 'Демо-оплата',
                    'description' => 'Тестовый режим без реального списания средств',
                ],
            ];
        }
        
        if ($paymentMethod === 'yoomoney' && ($this->config['methods']['yoomoney']['enabled'] ?? false)) {
            return buildYooMoneyPaymentData($order, $this->config);
        }
        
        if ($paymentMethod === 'crypto' && ($this->config['methods']['crypto']['enabled'] ?? false)) {
            $result = buildCryptoPaymentData($order, $this->tokens, $this->ratesCache);
            if ($result['success']) {
                $this->saveOrder($order, 'pending');
            }
            return $result;
        }
        
        return ['success' => false, 'error' => 'Метод оплаты недоступен'];
    }

    public function confirmPayment($token) {
        $orders = $this->getOrders('pending');

        foreach ($orders as $key => $order) {
            if (($order['payment_token'] ?? '') === $token) {
                if (($order['payment_method'] ?? '') === 'crypto') {
                    if (!pmTryAutoVerifyCryptoOrder($orders[$key], $this->tokens)) {
                        return false;
                    }
                } else {
                    $orders[$key]['status'] = 'paid';
                    $orders[$key]['paid_at'] = date('Y-m-d H:i:s');
                    $orders[$key]['history'][] = [
                        'time' => date('Y-m-d H:i:s'),
                        'status' => 'paid',
                        'message' => 'Платёж подтверждён',
                    ];
                }

                $deliveryData = $this->generateDeliveryData($orders[$key]['items']);
                $orders[$key]['delivery_data'] = $deliveryData;
                $orders[$key]['fulfilled_at'] = date('Y-m-d H:i:s');

                $this->saveOrder($orders[$key], 'completed');
                unset($orders[$key]);
                $this->updateOrdersFile($orders, 'pending');
                $this->updateStock($orders[$key]['items']);

                return $deliveryData;
            }
        }
        return false;
    }
    
    public function manualConfirmPayment($orderNumber) {
        $orders = $this->getOrders('pending');
        
        foreach ($orders as $key => $order) {
            if (($order['order_number'] ?? '') === $orderNumber || ($order['label'] ?? '') === $orderNumber) {
                $orders[$key]['status'] = 'paid';
                $orders[$key]['payment_status'] = 'paid';
                $orders[$key]['paid_at'] = date('Y-m-d H:i:s');
                $orders[$key]['history'][] = [
                    'time' => date('Y-m-d H:i:s'),
                    'status' => 'paid',
                    'message' => 'Подтверждено вручную',
                ];

                $deliveryData = $this->generateDeliveryData($orders[$key]['items']);
                $orders[$key]['delivery_data'] = $deliveryData;
                $orders[$key]['fulfilled_at'] = date('Y-m-d H:i:s');

                $this->saveOrder($orders[$key], 'completed');
                unset($orders[$key]);
                $this->updateOrdersFile($orders, 'pending');
                $this->updateStock($orders[$key]['items']);

                return $deliveryData;
            }
        }
        return false;
    }

    public function getOrderByToken($token) {
        $completed = $this->getOrders('completed');
        foreach ($completed as $order) {
            if (($order['payment_token'] ?? '') === $token) return pmBuildPublicOrderData($order);
        }
        $pending = $this->getOrders('pending');
        foreach ($pending as $order) {
            if (($order['payment_token'] ?? '') === $token) return pmBuildPublicOrderData($order);
        }
        return null;
    }
    
    public function getOrderByNumber($orderNumber) {
        $completed = $this->getOrders('completed');
        foreach ($completed as $order) {
            if (($order['order_number'] ?? '') === $orderNumber) return pmBuildPublicOrderData($order);
        }
        $pending = $this->getOrders('pending');
        foreach ($pending as $order) {
            if (($order['order_number'] ?? '') === $orderNumber) return pmBuildPublicOrderData($order);
        }
        return null;
    }
    
    public function getAllOrders($status = null) {
        $all = [];
        if ($status === null || $status === 'pending') {
            $pending = $this->getOrders('pending');
            foreach ($pending as $order) {
                $all[] = pmBuildPublicOrderData($order);
            }
        }
        if ($status === null || $status === 'completed') {
            $completed = $this->getOrders('completed');
            foreach ($completed as $order) {
                $all[] = pmBuildPublicOrderData($order);
            }
        }
        usort($all, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        return $all;
    }

    private function findProduct($id) {
        $categories = ['facebook', 'instagram', 'google', 'tiktok', 'telegram', 'twitter', 'other'];
        foreach ($categories as $cat) {
            $file = $_SERVER['DOCUMENT_ROOT'] . $this->productsDir . "{$cat}.json";
            if (file_exists($file)) {
                $products = json_decode(file_get_contents($file), true);
                if (is_array($products)) {
                    foreach ($products as $p) {
                        if (($p['id'] ?? '') === $id) return $p;
                    }
                }
            }
        }
        return null;
    }

    private function generateDeliveryData($items) {
        $data = [];
        foreach ($items as $item) {
            $generatedAccounts = [];
            for ($i = 0; $i < $item['qty']; $i++) {
                $login = 'user_' . bin2hex(random_bytes(4));
                $pass = bin2hex(random_bytes(8));
                $generatedAccounts[] = [
                    'login' => $login,
                    'password' => $pass,
                    'email' => "{$login}@tempmail.com",
                    'note' => "Куплено в заказе " . date('Y-m-d'),
                    'product' => $item['title'],
                ];
            }
            $data[$item['product_id']] = $generatedAccounts;
        }
        return $data;
    }

    private function updateStock($items) {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            $qty = $item['qty'] ?? 1;
            
            $categories = ['facebook', 'instagram', 'google', 'tiktok', 'telegram', 'twitter', 'other'];
            foreach ($categories as $cat) {
                $file = $_SERVER['DOCUMENT_ROOT'] . $this->productsDir . "{$cat}.json";
                if (file_exists($file)) {
                    $products = json_decode(file_get_contents($file), true);
                    if (is_array($products)) {
                        foreach ($products as $key => $p) {
                            if (($p['id'] ?? '') === $productId) {
                                $products[$key]['stock'] = max(0, ($p['stock'] ?? 0) - $qty);
                                $products[$key]['updated_at'] = date('Y-m-d\TH:i:s\Z');
                                $tmpFile = $file . '.tmp';
                                file_put_contents($tmpFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                rename($tmpFile, $file);
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }

    private function getOrders($type) {
        $file = $_SERVER['DOCUMENT_ROOT'] . ($type === 'pending' ? $this->ordersFile : $this->completedFile);
        if (!file_exists($file)) return [];
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function saveOrder($order, $type) {
        $file = $_SERVER['DOCUMENT_ROOT'] . ($type === 'pending' ? $this->ordersFile : $this->completedFile);
        $orders = $this->getOrders($type);
        
        $found = false;
        foreach ($orders as $key => $o) {
            if (($o['order_number'] ?? '') === ($order['order_number'] ?? '')) {
                $orders[$key] = $order;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $orders[] = $order;
        }
        
        $this->updateOrdersFile($orders, $type);
    }

    private function updateOrdersFile($orders, $type) {
        $file = $_SERVER['DOCUMENT_ROOT'] . ($type === 'pending' ? $this->ordersFile : $this->completedFile);
        $tmp = $file . '.tmp';
        file_put_contents($tmp, json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tmp, $file);
    }
}
