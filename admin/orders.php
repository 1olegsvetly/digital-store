<?php
/**
 * Админ-панель: Управление заказами
 * Просмотр всех заказов, подтверждение оплат вручную
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment.php';

session_start();
checkAdminAuth();

$payment = new PaymentSystem();

// Получаем все заказы
$pendingOrders = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/data/orders/pending.json'), true) ?? [];
$completedOrders = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/data/orders/completed.json'), true) ?? [];

// Ручное подтверждение оплаты (если админ нажал кнопку)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_manual'])) {
    $token = $_POST['payment_token'] ?? '';
    if ($token) {
        $result = $payment->confirmPayment($token);
        if ($result) {
            $successMessage = 'Оплата подтверждена, товар выдан!';
        } else {
            $errorMessage = 'Не удалось подтвердить оплату. Проверьте токен.';
        }
    }
}

$pageTitle = 'Заказы - Админ панель';
include __DIR__ . '/../templates/admin/header.php';
?>

<div class="admin-content">
    <h1>📦 Управление заказами</h1>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>
    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-error"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="orders-tabs">
        <button class="tab-btn active" onclick="showTab('pending')">Ожидающие (<span id="pending-count"><?= count($pendingOrders) ?></span>)</button>
        <button class="tab-btn" onclick="showTab('completed')">Выполненные (<span id="completed-count"><?= count($completedOrders) ?></span>)</button>
    </div>

    <!-- Ожидающие заказы -->
    <div id="pending-tab" class="tab-content active">
        <?php if (empty($pendingOrders)): ?>
            <p class="empty-state">Нет ожидающих заказов</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID заказа</th>
                        <th>Дата</th>
                        <th>Клиент</th>
                        <th>Товары</th>
                        <th>Сумма</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $order): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($order['id']) ?></code></td>
                        <td><?= htmlspecialchars($order['created_at']) ?></td>
                        <td>
                            <?= htmlspecialchars($order['user']['name']) ?><br>
                            <small><?= htmlspecialchars($order['user']['email']) ?></small>
                        </td>
                        <td>
                            <?php foreach ($order['items'] as $item): ?>
                                <div><?= $item['qty'] ?>x <?= htmlspecialchars($item['title']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td><strong>$<?= number_format($order['total'], 2) ?></strong></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="payment_token" value="<?= htmlspecialchars($order['payment_token']) ?>">
                                <button type="submit" name="confirm_manual" class="btn btn-sm btn-success">
                                    ✓ Подтвердить оплату
                                </button>
                            </form>
                            <a href="/checkout/pay.php?token=<?= urlencode($order['payment_token']) ?>" 
                               class="btn btn-sm btn-primary" target="_blank">
                                🔗 Ссылка на оплату
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Выполненные заказы -->
    <div id="completed-tab" class="tab-content">
        <?php if (empty($completedOrders)): ?>
            <p class="empty-state">Нет выполненных заказов</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID заказа</th>
                        <th>Дата оплаты</th>
                        <th>Клиент</th>
                        <th>Товары</th>
                        <th>Сумма</th>
                        <th>Просмотр</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedOrders as $order): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($order['id']) ?></code></td>
                        <td><?= htmlspecialchars($order['paid_at'] ?? '-') ?></td>
                        <td>
                            <?= htmlspecialchars($order['user']['name']) ?><br>
                            <small><?= htmlspecialchars($order['user']['email']) ?></small>
                        </td>
                        <td>
                            <?php foreach ($order['items'] as $item): ?>
                                <div><?= $item['qty'] ?>x <?= htmlspecialchars($item['title']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td><strong>$<?= number_format($order['total'], 2) ?></strong></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="showOrderDetails('<?= htmlspecialchars($order['id']) ?>')">
                                👁️ Детали
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно с деталями заказа -->
<div id="order-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Детали заказа</h2>
        <div id="order-details"></div>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function showOrderDetails(orderId) {
    // В реальном проекте здесь был бы AJAX запрос
    // Для демо покажем заглушку
    document.getElementById('order-details').innerHTML = '<p>Детали заказа: ' + orderId + '</p><p>Здесь будут данные выданных товаров.</p>';
    document.getElementById('order-modal').style.display = 'block';
}

function closeModal() {
    document.getElementById('order-modal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('order-modal')) {
        closeModal();
    }
}
</script>

<style>
.orders-tabs {
    margin-bottom: 20px;
}

.tab-btn {
    padding: 12px 24px;
    background: #f0f0f0;
    border: none;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    font-size: 1rem;
    margin-right: 5px;
}

.tab-btn.active {
    background: #3498db;
    color: white;
}

.tab-content {
    display: none;
    background: white;
    padding: 30px;
    border-radius: 0 8px 8px 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.tab-content.active {
    display: block;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.admin-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
    margin: 2px;
}

.btn-success {
    background: #4caf50;
    color: white;
}

.btn-success:hover {
    background: #45a049;
}

.btn-info {
    background: #2196f3;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    margin: 10% auto;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    position: relative;
}

.close {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 28px;
    cursor: pointer;
    color: #999;
}

.close:hover {
    color: #333;
}
</style>

<?php include __DIR__ . '/../templates/admin/footer.php'; ?>
