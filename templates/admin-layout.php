<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Админ-панель') ?></title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin/" class="sidebar-logo">Admin Panel</a>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">📊 Dashboard</a>
                <a href="/admin/products.php" class="<?= strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : '' ?>">📦 Товары</a>
                <a href="/admin/articles.php" class="<?= strpos($_SERVER['PHP_SELF'], 'articles') !== false ? 'active' : '' ?>">✍️ Статьи</a>
                <a href="/admin/orders.php" class="<?= strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'active' : '' ?>">🛒 Заказы</a>
                <a href="/admin/import-products.php" class="<?= strpos($_SERVER['PHP_SELF'], 'import-products') !== false ? 'active' : '' ?>">📥 Импорт CSV</a>
                <a href="/admin/sitemap.php" class="<?= strpos($_SERVER['PHP_SELF'], 'sitemap') !== false ? 'active' : '' ?>">🗺️ Sitemap</a>
                <a href="/admin/settings.php" class="<?= strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : '' ?>">⚙️ Настройки</a>
                <hr>
                <a href="/" target="_blank">🌐 Открыть сайт</a>
                <a href="/admin/logout.php" class="logout">🚪 Выйти</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <?= $content ?>
        </main>
    </div>
    
    <script src="/js/admin.js"></script>
</body>
</html>
