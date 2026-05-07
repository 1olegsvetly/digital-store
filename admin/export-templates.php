<?php
/**
 * Admin Panel - Import/Export CSV Templates
 */

require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!isAdmin()) {
    redirect('/admin/login.php');
}

$action = $_GET['action'] ?? '';

// Handle CSV export actions
if ($action === 'export_products_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products_template.csv"');
    
    // Add BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'id',
        'slug',
        'category',
        'subcategory',
        'title',
        'description_short',
        'description_full',
        'price',
        'currency',
        'stock',
        'min_order',
        'guarantee_hours',
        'image_url',
        'images',
        'seo_meta_title',
        'seo_meta_description',
        'seo_keywords',
        'status'
    ]);
    
    // Example row
    fputcsv($output, [
        'fb_auto_001',
        'facebook-avtoregi-podtverzhdeny',
        'facebook',
        'avtoregi',
        'Аккаунты FB | Подтверждены по почте',
        'Краткое описание для карточки товара',
        '<p>Полное описание с HTML тегами. Преимущества, характеристики.</p>',
        '0.148',
        'USD',
        '1000',
        '100',
        '48',
        '/img/fb-auto.jpg',
        '["/img/fb-1.jpg", "/img/fb-2.jpg"]',
        'Купить аккаунты Facebook — от $0.148 | Магазин',
        'Автореги Facebook с подтверждением. Гарантия 48ч. Мгновенная выдача.',
        'facebook,аккаунты,автореги,купить',
        'active'
    ]);
    
    fclose($output);
    exit;
}

if ($action === 'export_articles_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="articles_template.csv"');
    
    // Add BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'id',
        'slug',
        'title',
        'excerpt',
        'content',
        'author',
        'publish_date',
        'category',
        'tags',
        'featured_image',
        'seo_meta_title',
        'seo_meta_description',
        'seo_keywords',
        'status'
    ]);
    
    // Example row
    fputcsv($output, [
        'art_001',
        'kak-vybrat-akkaunt',
        'Как выбрать аккаунт: полное руководство',
        'Краткое описание для превью статьи',
        '<article><h2>Введение</h2><p>Текст статьи с HTML тегами...</p></article>',
        'Admin',
        '2026-05-07',
        'guides',
        '["аккаунты", "гайд", "новичкам"]',
        '/img/blog/guide.jpg',
        'Как выбрать аккаунт — советы эксперта | Блог',
        'Пошаговое руководство по выбору аккаунтов для рекламы и продвижения.',
        'выбор аккаунта,гайд,советы',
        'published'
    ]);
    
    fclose($output);
    exit;
}

ob_start();
?>

<div class="admin-page">
    <div class="admin-header">
        <h1>📥 Импорт / Экспорт шаблонов</h1>
        <a href="/admin/index.php" class="btn btn-outline">← Назад в панель</a>
    </div>
    
    <div class="admin-content">
        <div class="card">
            <h2>📦 Шаблоны для импорта товаров</h2>
            <p>Скачайте CSV-шаблон для массового добавления товаров. Заполните файл и загрузите через страницу импорта.</p>
            
            <div class="import-actions">
                <a href="?action=export_products_template" class="btn btn-primary">
                    📥 Скачать шаблон товаров (CSV)
                </a>
            </div>
            
            <div class="template-info">
                <h3>Структура файла товаров:</h3>
                <ul>
                    <li><strong>id</strong> — Уникальный идентификатор (например: fb_auto_001)</li>
                    <li><strong>slug</strong> — ЧПУ ссылка (например: facebook-avtoregi)</li>
                    <li><strong>category</strong> — Категория (facebook, instagram, telegram...)</li>
                    <li><strong>subcategory</strong> — Подкатегория (avtoregi, premium...)</li>
                    <li><strong>title</strong> — Название товара</li>
                    <li><strong>description_short</strong> — Краткое описание для карточки</li>
                    <li><strong>description_full</strong> — Полное описание (HTML разрешён)</li>
                    <li><strong>price</strong> — Цена (число)</li>
                    <li><strong>currency</strong> — Валюта (USD, EUR, RUB)</li>
                    <li><strong>stock</strong> — Количество на складе</li>
                    <li><strong>min_order</strong> — Минимальный заказ</li>
                    <li><strong>guarantee_hours</strong> — Гарантия в часах</li>
                    <li><strong>image_url</strong> — Главное изображение (путь)</li>
                    <li><strong>images</strong> — Дополнительные фото (JSON массив)</li>
                    <li><strong>seo_meta_title</strong> — Meta title для SEO</li>
                    <li><strong>seo_meta_description</strong> — Meta description для SEO</li>
                    <li><strong>seo_keywords</strong> — Ключевые слова (через запятую)</li>
                    <li><strong>status</strong> — Статус (active, inactive)</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <h2>✍️ Шаблоны для импорта статей</h2>
            <p>Скачайте CSV-шаблон для массового добавления статей в блог.</p>
            
            <div class="import-actions">
                <a href="?action=export_articles_template" class="btn btn-primary">
                    📥 Скачать шаблон статей (CSV)
                </a>
            </div>
            
            <div class="template-info">
                <h3>Структура файла статей:</h3>
                <ul>
                    <li><strong>id</strong> — Уникальный идентификатор (например: art_001)</li>
                    <li><strong>slug</strong> — ЧПУ ссылка (например: kak-vybrat-akkaunt)</li>
                    <li><strong>title</strong> — Заголовок статьи</li>
                    <li><strong>excerpt</strong> — Краткое описание для превью</li>
                    <li><strong>content</strong> — Полный текст статьи (HTML разрешён)</li>
                    <li><strong>author</strong> — Автор статьи</li>
                    <li><strong>publish_date</strong> — Дата публикации (YYYY-MM-DD)</li>
                    <li><strong>category</strong> — Категория статьи (guides, news...)</li>
                    <li><strong>tags</strong> — Теги (JSON массив)</li>
                    <li><strong>featured_image</strong> — Изображение для превью</li>
                    <li><strong>seo_meta_title</strong> — Meta title для SEO</li>
                    <li><strong>seo_meta_description</strong> — Meta description для SEO</li>
                    <li><strong>seo_keywords</strong> — Ключевые слова (через запятую)</li>
                    <li><strong>status</strong> — Статус (published, draft)</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <h2>ℹ️ Как использовать шаблоны</h2>
            <ol>
                <li>Скачайте нужный шаблон (товары или статьи)</li>
                <li>Откройте файл в Excel, Google Sheets или другом редакторе</li>
                <li>Заполните данные, следуя структуре (первая строка — пример)</li>
                <li>Сохраните файл в формате CSV (UTF-8)</li>
                <li>Перейдите на страницу импорта и загрузите файл</li>
                <li>Система автоматически создаст или обновит записи</li>
            </ol>
            
            <div class="quick-links">
                <a href="/admin/import-products.php" class="btn btn-outline">Импорт товаров →</a>
                <a href="/admin/import-articles.php" class="btn btn-outline">Импорт статей →</a>
            </div>
        </div>
    </div>
</div>

<style>
.admin-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.admin-header h1 {
    margin: 0;
    color: #333;
}

.card {
    background: #fff;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.card h2 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 22px;
}

.card h3 {
    color: #34495e;
    font-size: 18px;
    margin-top: 20px;
}

.import-actions {
    margin: 20px 0;
}

.template-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    margin-top: 20px;
}

.template-info ul {
    margin: 10px 0;
    padding-left: 20px;
}

.template-info li {
    margin-bottom: 8px;
    line-height: 1.5;
}

.template-info strong {
    color: #2c3e50;
}

.quick-links {
    margin-top: 20px;
    display: flex;
    gap: 15px;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-outline {
    background: transparent;
    border: 2px solid #3498db;
    color: #3498db;
}

.btn-outline:hover {
    background: #3498db;
    color: white;
}

@media (max-width: 768px) {
    .admin-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .quick-links {
        flex-direction: column;
    }
}
</style>

<?php
$content = ob_get_clean();
$pageTitle = 'Импорт / Экспорт шаблонов — Админ-панель';
include __DIR__ . '/../templates/admin-layout.php';
