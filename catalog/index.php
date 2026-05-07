<?php
/**
 * Catalog page - list products by category
 */

require_once __DIR__ . '/../includes/functions.php';

$config = getSiteConfig();
$category = $_GET['cat'] ?? '';
$subcategory = $_GET['sub'] ?? '';

// Filters
$filters = [
    'in_stock' => isset($_GET['in_stock']) && $_GET['in_stock'] === '1',
    'price_min' => isset($_GET['price_min']) ? floatval($_GET['price_min']) : null,
    'price_max' => isset($_GET['price_max']) ? floatval($_GET['price_max']) : null,
];

$products = [];
if ($category) {
    $products = getProducts($category, array_filter($filters));
} else {
    // Show all categories
    $productFiles = glob(DATA_DIR . '/products/*.json');
    foreach ($productFiles as $file) {
        if (basename($file) === 'index.json') continue;
        $catProducts = loadJson($file);
        $products = array_merge($products, $catProducts);
    }
}

ob_start();
?>

<div class="catalog-page">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="/">Главная</a>
            <?php if ($category): ?>
            <span>/</span>
            <a href="/catalog/<?= e($category) ?>"><?= e(ucfirst($category)) ?></a>
            <?php if ($subcategory): ?>
            <span>/</span>
            <span><?= e(ucfirst($subcategory)) ?></span>
            <?php endif; ?>
            <?php else: ?>
            <span>/</span>
            <span>Каталог</span>
            <?php endif; ?>
        </nav>
        
        <h1><?= $category ? e(ucfirst($category)) : 'Каталог товаров' ?></h1>
        
        <div class="catalog-layout">
            <aside class="filters">
                <form method="GET" class="filter-form">
                    <?php if ($category): ?>
                    <input type="hidden" name="cat" value="<?= e($category) ?>">
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Цена от</label>
                        <input type="number" step="0.01" name="price_min" value="<?= e($_GET['price_min'] ?? '') ?>" placeholder="Min">
                    </div>
                    
                    <div class="filter-group">
                        <label>Цена до</label>
                        <input type="number" step="0.01" name="price_max" value="<?= e($_GET['price_max'] ?? '') ?>" placeholder="Max">
                    </div>
                    
                    <div class="filter-group">
                        <label>
                            <input type="checkbox" name="in_stock" value="1" <?= !empty($_GET['in_stock']) ? 'checked' : '' ?>>
                            Только в наличии
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Применить</button>
                </form>
            </aside>
            
            <div class="products-section">
                <?php if (empty($products)): ?>
                <p class="no-products">Товары не найдены</p>
                <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                    <?php if ($product['status'] !== 'active') continue; ?>
                    <div class="product-card">
                        <a href="/catalog/<?= e($product['category']) ?>/<?= e($product['slug']) ?>">
                            <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['title']) ?>" loading="lazy" onerror="this.src='/img/placeholder.jpg'">
                            <h3><?= e($product['title']) ?></h3>
                            <p class="description-short"><?= e($product['description_short']) ?></p>
                            <div class="product-meta">
                                <span class="price"><?= number_format($product['price'], 3) ?> <?= e($product['currency']) ?></span>
                                <span class="min-order">от <?= $product['min_order'] ?> шт.</span>
                            </div>
                            <p class="stock">В наличии: <?= number_format($product['stock']) ?> шт.</p>
                            <?php if ($product['guarantee_hours']): ?>
                            <p class="guarantee">🛡️ Гарантия <?= $product['guarantee_hours'] ?>ч</p>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$pageTitle = ($category ? ucfirst($category) : 'Каталог') . ' — ' . $config['site_name'];
$pageDescription = 'Большой выбор цифровых товаров категории ' . ($category ? $category : 'всех категорий');

include __DIR__ . '/../templates/layout.php';
