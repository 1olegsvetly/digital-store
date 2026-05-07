<?php
/**
 * Single product page
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seo-enhancer.php';

$slug = $_GET['slug'] ?? '';
$product = getProductBySlug($slug);

if (!$product || $product['status'] !== 'active') {
    http_response_code(404);
    die('Товар не найден');
}

$config = getSiteConfig();

// Хлебные крошки с микроразметкой
$breadcrumbsData = SEOEnhancer::getBreadcrumbs([
    ['name' => 'Главная', 'url' => '/'],
    ['name' => ucfirst($product['category']), 'url' => '/catalog/index.php?cat=' . $product['category']],
    ['name' => $product['title'], 'url' => '']
]);

// Статус наличия
$stockStatus = SEOEnhancer::getStockStatus($product['stock'], $product['min_order']);

// Авто-генерация Alt для изображений
$mainImageAlt = SEOEnhancer::generateAlt($product['title'], 'фото');

// Build schema.org JSON-LD with FAQ
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

$faqSchema = SEOEnhancer::getFAQSchema($product);

ob_start();
?>

<div class="product-page">
    <div class="container">
        <?= $breadcrumbsData['html'] ?>
        
        <div class="product-detail">
            <div class="product-images">
                <img src="<?= e($product['image_url']) ?>" alt="<?= $mainImageAlt ?>" id="main-image" onerror="this.src='/img/placeholder.jpg'">
                <?php if (!empty($product['images'])): ?>
                <div class="thumbnail-list">
                    <?php foreach ($product['images'] as $img): ?>
                    <img src="<?= e($img) ?>" alt="<?= SEOEnhancer::generateAlt($product['title'], 'дополнительное фото') ?>" class="thumbnail" onclick="document.getElementById('main-image').src='<?= e($img) ?>'">
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
                
                <!-- Индикатор наличия -->
                <div class="stock-indicator <?= $stockStatus['class'] ?>">
                    <span class="stock-dot"></span>
                    <span class="stock-text"><?= $stockStatus['text'] ?> (<?= number_format($product['stock']) ?> шт.)</span>
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
                
                <!-- Блок FAQ -->
                <div class="faq-section mt-5">
                    <h2>Частые вопросы</h2>
                    <div class="accordion" id="product-faq">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Как я получу товар после оплаты?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#product-faq">
                                <div class="accordion-body">
                                    Товар выдается автоматически сразу после подтверждения оплаты. Вы получите данные на экране и на email.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Есть ли гарантия на товар?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#product-faq">
                                <div class="accordion-body">
                                    Да, гарантия составляет <?= $product['guarantee_hours'] ?> часов. Если товар перестанет работать по нашей вине, мы заменим его.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Какие способы оплаты доступны?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#product-faq">
                                <div class="accordion-body">
                                    Мы принимаем YooMoney, банковские карты и криптовалюты (BTC, ETH, USDT, TON).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Похожие товары -->
                <div class="related-products mt-5">
                    <h2>Похожие товары</h2>
                    <div class="row" id="related-products-container">
                        <div class="col-12 text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                        </div>
                    </div>
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
<script type="application/ld+json">
<?= $breadcrumbsData['json_ld'] ?>
</script>
<script type="application/ld+json">
<?= $faqSchema ?>
</script>
<script>
function addToCartAndCheckout(productId) {
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

// Загрузка похожих товаров
fetch('/api/related-products.php?category=<?= urlencode($product['category']) ?>&exclude=<?= urlencode($product['id']) ?>&limit=4')
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('related-products-container');
        if (!data || data.length === 0) {
            container.innerHTML = '<p class="text-muted">Нет похожих товаров</p>';
            return;
        }
        container.innerHTML = data.map(p => `
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card h-100 product-card">
                    <a href="/catalog/product.php?slug=${p.slug}">
                        <img src="${p.image_url}" alt="${p.title}" class="card-img-top" loading="lazy" onerror="this.src='/img/placeholder.jpg'">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h6 class="card-title">
                            <a href="/catalog/product.php?slug=${p.slug}" class="text-decoration-none">${p.title}</a>
                        </h6>
                        <div class="mt-auto">
                            <div class="price">${p.price.toFixed(3)} ${p.currency}</div>
                            <small class="text-muted">от ${p.min_order} шт.</small>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    })
    .catch(e => {
        document.getElementById('related-products-container').innerHTML = '';
    });
</script>
<?php
$scripts = ob_get_clean();

include __DIR__ . '/../templates/layout.php';
