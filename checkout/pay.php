<?php
/**
 * Страница оплаты (Payment Page)
 * Симуляция платежного шлюза и подтверждение оплаты
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment.php';

session_start();

// Получаем токен из URL
$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: /');
    exit;
}

// Извлекаем payment_token из полного URL (если передан полный путь)
if (strpos($token, '?token=') !== false) {
    parse_str(parse_url($token, PHP_URL_QUERY), $params);
    $paymentToken = $params['token'] ?? '';
} else {
    $paymentToken = $token;
}

$payment = new PaymentSystem();
$order = $payment->getOrderByToken($paymentToken);

if (!$order) {
    // Заказ не найден
    $pageTitle = 'Заказ не найден';
    include __DIR__ . '/../templates/header.php';
    ?>
    <div class="payment-container">
        <div class="payment-status error">
            <h1>❌ Заказ не найден</h1>
            <p>Ссылка недействительна или срок её действия истек.</p>
            <a href="/" class="btn btn-primary">На главную</a>
        </div>
    </div>
    <?php
    include __DIR__ . '/../templates/footer.php';
    exit;
}

// Обработка подтверждения оплаты (симуляция)
$deliveryData = null;
$isPaid = $order['status'] === 'paid' || $order['status'] === 'completed';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // В реальном проекте здесь был бы callback от платежной системы
    $deliveryData = $payment->confirmPayment($paymentToken);
    if ($deliveryData) {
        $isPaid = true;
        $order = $payment->getOrderByToken($paymentToken); // Обновляем данные
    }
}

$pageTitle = 'Оплата заказа #' . $order['id'];
include __DIR__ . '/../templates/header.php';
?>

<div class="payment-container">
    <?php if ($isPaid && $order['delivery_data']): ?>
        <!-- Успешная оплата - показываем товар -->
        <div class="payment-status success">
            <div class="success-icon">✅</div>
            <h1>Оплата успешна!</h1>
            <p class="order-id">Заказ #<?= htmlspecialchars($order['id']) ?></p>
            
            <div class="delivery-data">
                <h2>Ваши товары</h2>
                <?php foreach ($order['delivery_data'] as $productId => $accounts): ?>
                    <div class="product-delivery">
                        <h3>Товар #<?= htmlspecialchars($productId) ?></h3>
                        <div class="accounts-list">
                            <?php foreach ($accounts as $acc): ?>
                                <div class="account-item">
                                    <div class="account-row">
                                        <span class="label">Login:</span>
                                        <code><?= htmlspecialchars($acc['login']) ?></code>
                                    </div>
                                    <div class="account-row">
                                        <span class="label">Password:</span>
                                        <code><?= htmlspecialchars($acc['password']) ?></code>
                                    </div>
                                    <div class="account-row">
                                        <span class="label">Email:</span>
                                        <code><?= htmlspecialchars($acc['email']) ?></code>
                                    </div>
                                    <button class="btn-copy" onclick="copyToClipboard('<?= htmlspecialchars($acc['login'] . ':' . $acc['password']) ?>')">
                                        📋 Копировать
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="download-section">
                <a href="?download=<?= urlencode($paymentToken) ?>" class="btn btn-primary">
                    ⬇️ Скачать все данные (TXT)
                </a>
                <p class="note">
                    💡 Сохраните эти данные в надежном месте. Доступ будет доступен только 24 часа.
                </p>
            </div>

            <?php
            // Обработка скачивания файла
            if (isset($_GET['download'])) {
                $data = "";
                foreach ($order['delivery_data'] as $productId => $accounts) {
                    $data .= "=== Товар: $productId ===\n\n";
                    foreach ($accounts as $acc) {
                        $data .= "Login: {$acc['login']}\n";
                        $data .= "Password: {$acc['password']}\n";
                        $data .= "Email: {$acc['email']}\n";
                        $data .= "------------------------\n";
                    }
                    $data .= "\n";
                }
                
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="order_' . $order['id'] . '.txt"');
                echo $data;
                exit;
            }
            ?>
        </div>

    <?php elseif ($order['status'] === 'pending'): ?>
        <!-- Ожидает оплаты -->
        <div class="payment-form">
            <h1>Оплата заказа</h1>
            <div class="order-info">
                <p><strong>Заказ #:</strong> <?= htmlspecialchars($order['id']) ?></p>
                <p><strong>Сумма:</strong> $<?= number_format($order['total'], 2) ?> <?= $order['currency'] ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($order['user']['email']) ?></p>
            </div>

            <div class="payment-simulation">
                <h2>💳 Платежная форма</h2>
                <form method="POST">
                    <div class="fake-card-form">
                        <div class="form-group">
                            <label>Номер карты</label>
                            <input type="text" placeholder="0000 0000 0000 0000" maxlength="19" disabled>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Срок действия</label>
                                <input type="text" placeholder="MM/YY" maxlength="5" disabled>
                            </div>
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="password" placeholder="123" maxlength="3" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="simulation-notice">
                        🔒 <strong>Демо режим:</strong> Это тестовая среда. Реальные платежи не обрабатываются.
                    </div>

                    <button type="submit" name="confirm_payment" class="btn btn-primary btn-lg">
                        Оплатить $<?= number_format($order['total'], 2) ?>
                    </button>
                </form>
            </div>

            <div class="security-badges">
                <span>🔒 SSL Secure</span>
                <span>✓ Verified Merchant</span>
                <span>⚡ Instant Delivery</span>
            </div>
        </div>

    <?php else: ?>
        <!-- Другой статус -->
        <div class="payment-status info">
            <h1>ℹ️ Статус заказа: <?= htmlspecialchars($order['status']) ?></h1>
            <p>Пожалуйста, свяжитесь с поддержкой для уточнения деталей.</p>
            <a href="/" class="btn btn-primary">На главную</a>
        </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Скопировано в буфер обмена!');
    }).catch(err => {
        console.error('Ошибка копирования:', err);
    });
}
</script>

<style>
.payment-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.payment-status {
    text-align: center;
    padding: 40px 20px;
    border-radius: 12px;
    margin-top: 20px;
}

.payment-status.success {
    background: #e8f5e9;
    border: 2px solid #4caf50;
}

.payment-status.error {
    background: #ffebee;
    border: 2px solid #f44336;
}

.payment-status.info {
    background: #e3f2fd;
    border: 2px solid #2196f3;
}

.success-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.order-id {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 30px;
}

.delivery-data {
    background: white;
    padding: 30px;
    border-radius: 12px;
    margin: 30px 0;
    text-align: left;
}

.product-delivery {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.product-delivery:last-child {
    border-bottom: none;
}

.accounts-list {
    margin-top: 15px;
}

.account-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    position: relative;
}

.account-row {
    display: flex;
    margin-bottom: 8px;
}

.account-row .label {
    font-weight: 600;
    min-width: 100px;
    color: #555;
}

.account-row code {
    background: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    flex: 1;
}

.btn-copy {
    background: #3498db;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    margin-top: 10px;
}

.btn-copy:hover {
    background: #2980b9;
}

.download-section {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px dashed #ddd;
}

.download-section .note {
    margin-top: 15px;
    color: #666;
    font-size: 0.9rem;
}

.payment-form {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.order-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.order-info p {
    margin: 10px 0;
}

.payment-simulation {
    margin-top: 30px;
}

.fake-card-form {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px;
    border-radius: 12px;
    color: white;
    margin-bottom: 20px;
}

.fake-card-form .form-group label {
    color: rgba(255,255,255,0.9);
}

.fake-card-form input {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
}

.fake-card-form input::placeholder {
    color: rgba(255,255,255,0.7);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.simulation-notice {
    background: #fff3cd;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.security-badges {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    color: #666;
    font-size: 0.9rem;
}

.btn-lg {
    width: 100%;
    padding: 18px;
    font-size: 1.2rem;
    font-weight: 600;
}
</style>

<?php include __DIR__ . '/../templates/footer.php'; ?>
