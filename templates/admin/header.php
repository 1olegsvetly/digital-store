<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Админ панель' ?> - Digital Store</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f6fa; }
        
        .admin-layout { display: flex; min-height: 100vh; }
        
        .admin-sidebar {
            width: 260px;
            background: #2c3e50;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .admin-sidebar h2 {
            font-size: 1.3rem;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-nav a {
            display: block;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .admin-nav a:hover,
        .admin-nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .admin-nav a.danger {
            color: #e74c3c;
        }
        
        .admin-content {
            flex: 1;
            margin-left: 260px;
            padding: 40px;
        }
        
        .admin-content h1 {
            font-size: 2rem;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h2>🛠️ Админ панель</h2>
            <nav class="admin-nav">
                <a href="/admin/">📊 Dashboard</a>
                <a href="/admin/products.php">📦 Товары</a>
                <a href="/admin/articles.php">✍️ Статьи</a>
                <a href="/admin/orders.php" class="active">📬 Заказы</a>
                <a href="/admin/import.php">📥 Импорт CSV</a>
                <a href="/admin/settings.php">⚙️ Настройки</a>
                <a href="/admin/seo.php">🔍 SEO</a>
                <a href="/" target="_blank">🌐 На сайт</a>
                <a href="/admin/logout.php" class="danger">🚪 Выйти</a>
            </nav>
        </aside>
        
        <main class="admin-content">
