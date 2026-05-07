<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Digital Store — Магазин цифровых товаров') ?></title>
    <meta name="description" content="<?= e($pageDescription ?? 'Магазин цифровых товаров — аккаунты, ключи, доступы') ?>">
    <?php if (!empty($canonical)): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= e($pageTitle ?? 'Digital Store') ?>">
    <meta property="og:description" content="<?= e($pageDescription ?? '') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($config['site_url'] ?? '') ?>">
    <meta property="og:image" content="<?= e($config['site_url'] ?? '') ?>/img/og.jpg">
    
    <!-- CSS -->
    <link rel="stylesheet" href="/css/style.css">
    
    <?php if (!empty($schemaJson)): ?>
    <script type="application/ld+json"><?= $schemaJson ?></script>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="container header-inner">
            <a href="/" class="logo">
                <span class="logo-text">Digital Store</span>
            </a>
            <nav class="nav">
                <a href="/catalog">Каталог</a>
                <a href="/blog">Блог</a>
                <a href="/about">О нас</a>
                <a href="/contacts">Контакты</a>
            </nav>
            <div class="header-actions">
                <a href="/admin" class="btn btn-outline">Войти</a>
            </div>
        </div>
    </header>

    <main class="main">
        <?= $content ?>
    </main>

    <footer class="footer">
        <div class="container footer-inner">
            <div class="footer-section">
                <h4>Digital Store</h4>
                <p><?= e($config['footer_text'] ?? '© 2026 Digital Store') ?></p>
            </div>
            <div class="footer-section">
                <h4>Контакты</h4>
                <p>Email: <?= e($config['email'] ?? '') ?></p>
                <p>Tel: <?= e($config['phone'] ?? '') ?></p>
            </div>
            <div class="footer-section">
                <h4>Меню</h4>
                <ul>
                    <li><a href="/catalog">Каталог</a></li>
                    <li><a href="/blog">Блог</a></li>
                    <li><a href="/sitemap.xml">Sitemap</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <script src="/js/main.js"></script>
</body>
</html>
