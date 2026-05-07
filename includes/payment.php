<?php
/**
 * Payment Module v1.0.1
 * Обработка платежей, создание заказов и выдача товаров
 * Без базы данных, хранение в JSON
 */

class PaymentSystem {
    private $ordersFile = '/data/orders/pending.json';
    private $completedFile = '/data/orders/completed.json';
    private $productsDir = '/data/products/';
    
    // Конструктор: инициализация файлов если нет
    public function __construct() {
        if (!file_exists($this->ordersFile)) {
            file_put_contents($this->ordersFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        if (!file_exists($this->completedFile)) {
            file_put_contents($this->completedFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Создание нового заказа
     * @param array $items Массив товаров [['id' => '...', 'qty' => 1], ...]
     * @param array $user_data Данные покупателя ['email' => '...', 'name' => '...']
     * @return array ['order_id' => '...', 'payment_url' => '...']
     */
    public function createOrder($items, $user_data) {
        $orderId = 'ORD_' . time() . '_' . bin2hex(random_bytes(4));
        $total = 0;
        $orderItems = [];

        // Сбор данных о товарах и подсчет суммы
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
                'price' => $product['price'],
                'qty' => $item['qty'],
                'subtotal' => $itemTotal
            ];
        }

        $orderData = [
            'id' => $orderId,
            'status' => 'pending', // pending, paid, completed, cancelled
            'created_at' => date('Y-m-d H:i:s'),
            'user' => $user_data,
            'items' => $orderItems,
            'total' => $total,
            'currency' => 'USD',
            'payment_token' => bin2hex(random_bytes(16)), // Уникальный токен для ссылки оплаты
            'delivery_data' => null // Сюда запишутся данные товара после оплаты
        ];

        // Сохранение заказа
        $this->saveOrder($orderData, 'pending');

        // Генерация ссылки на оплату (в реальном проекте здесь ссылка на шлюз)
        $paymentUrl = '/checkout/pay.php?token=' . $orderData['payment_token'];

        return [
            'order_id' => $orderId,
            'payment_url' => $paymentUrl,
            'total' => $total
        ];
    }

    /**
     * Проверка и подтверждение оплаты (вызывается после успеха от шлюза)
     * @param string $token Токен оплаты
     * @return bool|string Возвращает данные товара при успехе, false при ошибке
     */
    public function confirmPayment($token) {
        $orders = $this->getOrders('pending');
        
        foreach ($orders as $key => $order) {
            if ($order['payment_token'] === $token) {
                // Обновляем статус
                $orders[$key]['status'] = 'paid';
                $orders[$key]['paid_at'] = date('Y-m-d H:i:s');
                
                // Генерируем данные для выдачи (цифровой товар)
                $deliveryData = $this->generateDeliveryData($order['items']);
                $orders[$key]['delivery_data'] = $deliveryData;
                
                // Переносим в выполненные
                $this->saveOrder($orders[$key], 'completed');
                
                // Удаляем из ожидающих
                unset($orders[$key]);
                $this->updateOrdersFile($orders, 'pending');
                
                // Уменьшаем сток товаров
                $this->updateStock($order['items']);
                
                return $deliveryData;
            }
        }
        return false;
    }

    /**
     * Получение данных заказа по токену (для страницы проверки статуса)
     */
    public function getOrderByToken($token) {
        // Проверяем завершенные
        $completed = $this->getOrders('completed');
        foreach ($completed as $order) {
            if ($order['payment_token'] === $token) return $order;
        }
        // Проверяем ожидающие
        $pending = $this->getOrders('pending');
        foreach ($pending as $order) {
            if ($order['payment_token'] === $token) return $order;
        }
        return null;
    }

    // --- Внутренние методы ---

    private function findProduct($id) {
        // Поиск товара по всем категориям (упрощенно)
        $categories = ['facebook', 'instagram', 'google', 'tiktok']; // Расширить при необходимости
        foreach ($categories as $cat) {
            $file = $_SERVER['DOCUMENT_ROOT'] . $this->productsDir . "{$cat}.json";
            if (file_exists($file)) {
                $products = json_decode(file_get_contents($file), true);
                foreach ($products as $p) {
                    if ($p['id'] === $id) return $p;
                }
            }
        }
        return null;
    }

    private function generateDeliveryData($items) {
        $data = [];
        foreach ($items as $item) {
            // Здесь логика генерации цифрового товара
            // Например: список логинов:паролей
            $generatedAccounts = [];
            for ($i = 0; $i < $item['qty']; $i++) {
                $login = 'user_' . bin2hex(random_bytes(4));
                $pass = bin2hex(random_bytes(8));
                $generatedAccounts[] = [
                    'login' => $login,
                    'password' => $pass,
                    'email' => "{$login}@tempmail.com",
                    'note' => "Куплено в заказе " . date('Y-m-d')
                ];
            }
            $data[$item['product_id']] = $generatedAccounts;
        }
        return $data;
    }

    private function updateStock($items) {
        // Упрощенная логика обновления стока в JSON
        // В продакшене нужна блокировка файла (flock)
        foreach ($items as $item) {
            // Найти файл категории и обновить количество
            // Реализация аналогична поиску в findProduct
        }
    }

    private function getOrders($type) {
        $file = $type === 'pending' ? $this->ordersFile : $this->completedFile;
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true);
    }

    private function saveOrder($order, $type) {
        $file = $type === 'pending' ? $this->ordersFile : $this->completedFile;
        $orders = $this->getOrders($type);
        $orders[] = $order;
        $this->updateOrdersFile($orders, $type);
    }

    private function updateOrdersFile($orders, $type) {
        $file = $type === 'pending' ? $this->ordersFile : $this->completedFile;
        // Атомарная запись
        $tmp = $file . '.tmp';
        file_put_contents($tmp, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tmp, $file);
    }
}
