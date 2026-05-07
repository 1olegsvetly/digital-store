<?php
/**
 * Страница оформления заказа (Checkout)
 * Форма ввода данных покупателя и выбора способа оплаты
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment.php';

session_start();

// Обработка добавления в корзину (прямой переход к оформлению)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = $_POST['add_to_cart'];
    $qty = max(1, (int)($_POST['qty'] ?? 1)); // По умолчанию 1 шт
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Добавляем товар в корзину
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $qty;
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
}

// Получаем корзину из сессии
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: /catalog/');
    exit;
}

// Подсчет суммы
$total = 0;
$cartItems = [];
foreach ($cart as $itemId => $qty) {
    $product = getProductById($itemId);
    if ($product) {
        $subtotal = $product['price'] * $qty;
        $total += $subtotal;
        $cartItems[] = [
            'product' => $product,
            'qty' => $qty,
            'subtotal' => $subtotal
        ];
    }
}

// Обработка формы заказа
$error = '';
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $name = htmlspecialchars($_POST['name'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'card';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email';
    } elseif (empty($name)) {
        $error = 'Введите имя';
    } else {
        try {
            $items = array_map(fn($i) => ['id' => $i['product']['id'], 'qty' => $i['qty']], $cartItems);
            
            $payment = new PaymentSystem();
            $result = $payment->createOrder($items, [
                'email' => $email,
                'name' => $name
            ]);

            // Сохраняем токен заказа в сессии для последующей проверки
            $_SESSION['current_order_token'] = $result['order_id'];
            
            // Редирект на страницу оплаты
            header('Location: /checkout/pay.php?token=' . urlencode($result['payment_url']));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Оформление заказа';
include __DIR__ . '/../templates/header.php';
?>

<div class="checkout-container">
    <h1>Оформление заказа</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <div class="checkout-grid">
        <!-- Форма данных покупателя -->
        <div class="checkout-form">
            <h2>Данные покупателя</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Имя *</label>
                    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <small>На этот email придет доступ к товару</small>
                </div>

                <div class="form-group">
                    <label>Способ оплаты</label>
                    <div class="payment-methods">
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="card" checked>
                            <span>💳 Банковская карта</span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="crypto">
                            <span>₿ Cryptocurrency</span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="qiwi">
                            <span>QIWI Кошелек</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">
                    Перейти к оплате $<?= number_format($total, 2) ?>
                </button>
            </form>
        </div>

        <!-- Список товаров -->
        <div class="order-summary">
            <h2>Ваш заказ</h2>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Кол-во</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['product']['title']) ?></strong>
                            <br><small>$<?= number_format($item['product']['price'], 3) ?> / шт.</small>
                        </td>
                        <td><?= $item['qty'] ?></td>
                        <td>$<?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2"><strong>Итого:</strong></td>
                        <td><strong>$<?= number_format($total, 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <div class="security-note">
                🔒 Безопасная оплата через защищенное соединение SSL
            </div>
        </div>
    </div>
</div>

<style>
.checkout-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-top: 30px;
}

@media (max-width: 768px) {
    .checkout-grid {
        grid-template-columns: 1fr;
    }
}

.checkout-form h2, .order-summary h2 {
    margin-bottom: 20px;
    font-size: 1.5rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-group input[type="text"],
.form-group input[type="email"] {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
}

.form-group small {
    color: #666;
    font-size: 0.85rem;
}

.payment-methods {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.payment-option {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.payment-option:hover {
    border-color: #3498db;
    background: #f0f9ff;
}

.payment-option input[type="radio"] {
    margin-right: 12px;
    width: 20px;
    height: 20px;
}

.btn-lg {
    width: 100%;
    padding: 15px;
    font-size: 1.1rem;
    margin-top: 20px;
}

.order-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-table th,
.order-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.order-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.order-table tfoot td {
    font-size: 1.2rem;
    background: #f8f9fa;
}

.security-note {
    margin-top: 20px;
    padding: 15px;
    background: #e8f5e9;
    border-radius: 8px;
    color: #2e7d32;
    text-align: center;
}

.alert-error {
    background: #ffebee;
    color: #c62828;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
</style>

<?php include __DIR__ . '/../templates/footer.php'; ?>
