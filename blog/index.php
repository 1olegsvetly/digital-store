<?php
/**
 * Blog listing page
 */

require_once __DIR__ . '/../includes/functions.php';

$config = getSiteConfig();
$articles = getArticles(['status' => 'published']);

ob_start();
?>

<div class="blog-page">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="/">Главная</a>
            <span>/</span>
            <span>Блог</span>
        </nav>
        
        <h1>Блог</h1>
        <p class="page-description">Статьи, руководства и новости о цифровых товарах</p>
        
        <div class="articles-list">
            <?php if (empty($articles)): ?>
            <p class="no-articles">Статей пока нет</p>
            <?php else: ?>
            <?php foreach ($articles as $article): ?>
            <article class="article-card-large">
                <a href="/blog/<?= e($article['slug']) ?>">
                    <?php if (!empty($article['featured_image'])): ?>
                    <img src="<?= e($article['featured_image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy" onerror="this.src='/img/placeholder.jpg'">
                    <?php endif; ?>
                    <div class="article-content">
                        <h2><?= e($article['title']) ?></h2>
                        <p class="excerpt"><?= e($article['excerpt']) ?></p>
                        <div class="article-meta">
                            <span class="author">👤 <?= e($article['author']) ?></span>
                            <span class="date">📅 <?= date('d.m.Y', strtotime($article['publish_date'])) ?></span>
                            <span class="views">👁️ <?= number_format($article['views'] ?? 0) ?></span>
                        </div>
                        <?php if (!empty($article['tags'])): ?>
                        <div class="tags">
                            <?php foreach ($article['tags'] as $tag): ?>
                            <span class="tag">#<?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$pageTitle = 'Блог — ' . $config['site_name'];
$pageDescription = 'Статьи, руководства и новости о цифровых товарах';

include __DIR__ . '/../templates/layout.php';
