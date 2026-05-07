<?php
/**
 * Single article page
 */

require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? '';
$article = getArticleBySlug($slug);

if (!$article || $article['status'] !== 'published') {
    http_response_code(404);
    die('Статья не найдена');
}

$config = getSiteConfig();

// Increment views
$article['views'] = ($article['views'] ?? 0) + 1;
// Note: In production, you'd save this back to the file

ob_start();
?>

<div class="article-page">
    <div class="container">
        <nav class="breadcrumbs">
            <a href="/">Главная</a>
            <span>/</span>
            <a href="/blog">Блог</a>
            <span>/</span>
            <span><?= e($article['title']) ?></span>
        </nav>
        
        <article class="article-full">
            <?php if (!empty($article['featured_image'])): ?>
            <img src="<?= e($article['featured_image']) ?>" alt="<?= e($article['title']) ?>" class="article-featured-image" onerror="this.src='/img/placeholder.jpg'">
            <?php endif; ?>
            
            <header class="article-header">
                <h1><?= e($article['title']) ?></h1>
                <div class="article-meta">
                    <span class="author">👤 <?= e($article['author']) ?></span>
                    <span class="date">📅 <?= date('d.m.Y', strtotime($article['publish_date'])) ?></span>
                    <span class="views">👁️ <?= number_format($article['views'] ?? 0) ?> просмотров</span>
                </div>
                <?php if (!empty($article['tags'])): ?>
                <div class="tags">
                    <?php foreach ($article['tags'] as $tag): ?>
                    <span class="tag">#<?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </header>
            
            <div class="article-content">
                <?= $article['content'] ?>
            </div>
        </article>
        
        <div class="related-articles">
            <h2>Читайте также</h2>
            <div class="articles-grid">
                <?php
                $allArticles = getArticles(['status' => 'published']);
                $related = array_filter($allArticles, function($a) use ($article) {
                    return $a['slug'] !== $article['slug'];
                });
                foreach (array_slice($related, 0, 3) as $relatedArticle):
                ?>
                <article class="article-card">
                    <a href="/blog/<?= e($relatedArticle['slug']) ?>">
                        <h3><?= e($relatedArticle['title']) ?></h3>
                        <p class="excerpt"><?= e($relatedArticle['excerpt']) ?></p>
                        <span class="date"><?= date('d.m.Y', strtotime($relatedArticle['publish_date'])) ?></span>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$pageTitle = $article['seo']['meta_title'] ?? $article['title'] . ' — Блог ' . $config['site_name'];
$pageDescription = $article['seo']['meta_description'] ?? $article['excerpt'];

include __DIR__ . '/../templates/layout.php';
