<?php
/**
 * Admin Panel - Dashboard
 */

require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!isAdmin()) {
    redirect('/admin/login.php');
}

$config = getSiteConfig();

// Count statistics
$productCount = 0;
$productFiles = glob(DATA_DIR . '/products/*.json');
foreach ($productFiles as $file) {
    if (basename($file) === 'index.json') continue;
    $products = loadJson($file);
    $productCount += count($products);
}

$articleCount = count(getArticles());
$pendingOrders = count(loadJson(DATA_DIR . '/orders/pending.json'));

ob_start();
?>

<div class="admin-dashboard">
    <div class="admin-header">
        <h1>Панель администратора</h1>
        <div class="admin-user">
            <span>👤 <?= e($_SESSION['admin_username'] ?? 'Admin') ?></span>
            <a href="/admin/logout.php" class="btn btn-outline">Выйти</a>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>📦 Товары</h3>
            <p class="stat-value"><?= $productCount ?></p>
            <a href="/admin/products.php" class="stat-link">Управлять →</a>
        </div>
        <div class="stat-card">
            <h3>✍️ Статьи</h3>
            <p class="stat-value"><?= $articleCount ?></p>
            <a href="/admin/articles.php" class="stat-link">Управлять →</a>
        </div>
        <div class="stat-card">
            <h3>🛒 Заказы</h3>
            <p class="stat-value"><?= $pendingOrders ?></p>
            <a href="/admin/orders.php" class="stat-link">Просмотреть →</a>
        </div>
        <div class="stat-card">
            <h3>⚙️ Настройки</h3>
            <p class="stat-value">—</p>
            <a href="/admin/settings.php" class="stat-link">Открыть →</a>
        </div>
    </div>
    
    <div class="quick-actions">
        <h2>Быстрые действия</h2>
        <div class="actions-grid">
            <a href="/admin/import-products.php" class="action-card">
                <span class="action-icon">📥</span>
                <span class="action-title">Импорт товаров CSV</span>
            </a>
            <a href="/admin/import-articles.php" class="action-card">
                <span class="action-icon">📝</span>
                <span class="action-title">Импорт статей CSV</span>
            </a>
            <a href="/admin/sitemap.php" class="action-card">
                <span class="action-icon">🗺️</span>
                <span class="action-title">Перегенерировать Sitemap</span>
            </a>
            <a href="/admin/payments.php" class="action-card">
                <span class="action-icon">💳</span>
                <span class="action-title">Настройки оплаты</span>
            </a>
            <a href="/" class="action-card" target="_blank">
                <span class="action-icon">🌐</span>
                <span class="action-title">Открыть сайт</span>
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Админ-панель — Dashboard';
include __DIR__ . '/../templates/admin-layout.php';
