<?php
/**
 * Single product page
 */

require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? '';
$product = getProductBySlug($slug);

if (!$product || $product['status'] !== 'active') {
    http_response_code(404);
    die('Товар не найден');
}

$config = getSiteConfig();

// Build schema.org JSON-LD
$schemaJson = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['title'],
    'description' => $product['description_short'],
    'image' => $product['image_url'],
    'offers' => [
        '@type' => 'Offer',
        'price' => $product['price'],
        'priceCurrency' => $product['currency'],
        'availability' => $product['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'minOrderQuantity' => $product['min_order']
    ],
    'aggregateRating' => [
        '@type' => 'AggregateRating',
        'ratingValue' => $product['rating'],
        'reviewCount' => 100
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

ob_start();
?>

<div class="product-page">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="/">Главная</a>
            <span>/</span>
            <a href="/catalog/<?= e($product['category']) ?>"><?= e(ucfirst($product['category'])) ?></a>
            <span>/</span>
            <span><?= e($product['title']) ?></span>
        </nav>
        
        <div class="product-detail">
            <div class="product-images">
                <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['title']) ?>" id="main-image" onerror="this.src='/img/placeholder.jpg'">
                <?php if (!empty($product['images'])): ?>
                <div class="thumbnail-list">
                    <?php foreach ($product['images'] as $img): ?>
                    <img src="<?= e($img) ?>" alt="" class="thumbnail" onclick="document.getElementById('main-image').src='<?= e($img) ?>'">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="product-info">
                <h1><?= e($product['title']) ?></h1>
                
                <div class="product-meta-row">
                    <span class="price-large"><?= number_format($product['price'], 3) ?> <?= e($product['currency']) ?></span>
                    <span class="min-order">от <?= $product['min_order'] ?> шт.</span>
                </div>
                
                <div class="product-stats">
                    <div class="stat">
                        <span class="stat-value">📦 <?= number_format($product['stock']) ?></span>
                        <span class="stat-label">в наличии</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">⭐ <?= number_format($product['rating'], 1) ?></span>
                        <span class="stat-label">рейтинг</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">🛡️ <?= $product['guarantee_hours'] ?>ч</span>
                        <span class="stat-label">гарантия</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">⚠️ <?= $product['defect_percent'] ?>%</span>
                        <span class="stat-label">брак</span>
                    </div>
                </div>
                
                <div class="product-actions">
                    <button class="btn btn-primary btn-large" onclick="addToCartAndCheckout('<?= $product['id'] ?>')">
                        🛒 Купить сейчас
                    </button>
                </div>
                
                <div class="product-description">
                    <h2>Описание</h2>
                    <?= $product['description_full'] ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$pageTitle = $product['seo']['meta_title'] ?? $product['title'] . ' — ' . $config['site_name'];
$pageDescription = $product['seo']['meta_description'] ?? $product['description_short'];
$canonical = $product['seo']['canonical'] ?? '';

ob_start();
?>
<script>
function addToCartAndCheckout(productId) {
    // Создаем форму для добавления в корзину и редиректа на checkout
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/checkout/index.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'add_to_cart';
    input.value = productId;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php
$scripts = ob_get_clean();

include __DIR__ . '/../templates/layout.php';
