<?php
/**
 * Homepage template
 */
$products = getProducts('facebook');
$articles = getArticles(['status' => 'published']);
?>

<section class="hero">
    <div class="container">
        <h1>Digital Store — Магазин цифровых товаров</h1>
        <p class="hero-subtitle">Аккаунты, ключи, доступы по лучшим ценам</p>
        <a href="/catalog" class="btn btn-primary">Перейти в каталог</a>
    </div>
</section>

<section class="featured-products">
    <div class="container">
        <h2>Популярные товары</h2>
        <div class="products-grid">
            <?php foreach (array_slice($products, 0, 4) as $product): ?>
            <div class="product-card">
                <a href="/catalog/<?= e($product['category']) ?>/<?= e($product['slug']) ?>">
                    <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['title']) ?>" loading="lazy">
                    <h3><?= e($product['title']) ?></h3>
                    <p class="price"><?= number_format($product['price'], 3) ?> <?= e($product['currency']) ?></p>
                    <p class="stock">В наличии: <?= number_format($product['stock']) ?> шт.</p>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="blog-preview">
    <div class="container">
        <h2>Последние статьи</h2>
        <div class="articles-grid">
            <?php foreach (array_slice($articles, 0, 3) as $article): ?>
            <article class="article-card">
                <a href="/blog/<?= e($article['slug']) ?>">
                    <h3><?= e($article['title']) ?></h3>
                    <p class="excerpt"><?= e($article['excerpt']) ?></p>
                    <span class="date"><?= date('d.m.Y', strtotime($article['publish_date'])) ?></span>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="features">
    <div class="container">
        <div class="features-grid">
            <div class="feature">
                <h3>⚡ Мгновенная выдача</h3>
                <p>Товары доставляются автоматически после оплаты</p>
            </div>
            <div class="feature">
                <h3>🛡️ Гарантия качества</h3>
                <p>Все товары проходят проверку перед продажей</p>
            </div>
            <div class="feature">
                <h3>💳 Удобная оплата</h3>
                <p>Принимаем криптовалюты и электронные платежи</p>
            </div>
            <div class="feature">
                <h3>📞 Поддержка 24/7</h3>
                <p>Отвечаем на вопросы в любое время суток</p>
            </div>
        </div>
    </div>
</section>
