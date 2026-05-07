<?php
/**
 * Single article page
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seo-enhancer.php';

$slug = $_GET['slug'] ?? '';
$article = getArticleBySlug($slug);

if (!$article || $article['status'] !== 'published') {
    http_response_code(404);
    die('Статья не найдена');
}

$config = getSiteConfig();

// Increment views
$article['views'] = ($article['views'] ?? 0) + 1;

// Хлебные крошки с микроразметкой
$breadcrumbsData = SEOEnhancer::getBreadcrumbs([
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Блог', 'url' => '/blog/'],
    ['name' => $article['title'], 'url' => '']
]);

// Авто-генерация Alt для изображения
$imageAlt = !empty($article['featured_image']) 
    ? SEOEnhancer::generateAlt($article['title'], 'к статье') 
    : '';

// Дата обновления для E-E-A-T
$updatedDate = SEOEnhancer::getUpdatedDate($article['created_at']);

ob_start();
?>

<div class="article-page">
    <div class="container">
        <?= $breadcrumbsData['html'] ?>
        
        <article class="article-full" itemscope itemtype="https://schema.org/Article">
            <?php if (!empty($article['featured_image'])): ?>
            <img src="<?= e($article['featured_image']) ?>" alt="<?= $imageAlt ?>" class="article-featured-image" itemprop="image" onerror="this.src='/img/placeholder.jpg'">
            <?php endif; ?>
            
            <header class="article-header">
                <h1 itemprop="headline"><?= e($article['title']) ?></h1>
                <div class="article-meta">
                    <span class="author" itemprop="author" itemscope itemtype="https://schema.org/Person">
                        👤 <span itemprop="name"><?= e($article['author']) ?></span>
                    </span>
                    <span class="date" itemprop="datePublished">
                        📅 <?= date('d.m.Y', strtotime($article['publish_date'])) ?>
                    </span>
                    <span class="date-updated" itemprop="dateModified">
                        ✏️ Обновлено: <?= date('d.m.Y', strtotime($updatedDate)) ?>
                    </span>
                    <span class="views">👁️ <?= number_format($article['views'] ?? 0) ?> просмотров</span>
                </div>
                <?php if (!empty($article['tags'])): ?>
                <div class="tags" itemprop="keywords">
                    <?php foreach ($article['tags'] as $tag): ?>
                    <span class="tag">#<?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </header>
            
            <div class="article-content" itemprop="articleBody">
                <?= $article['content'] ?>
            </div>
            
            <!-- Микроразметка Article -->
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "Article",
                "headline": "<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>",
                "image": "<?= htmlspecialchars($article['featured_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>",
                "author": {
                    "@type": "Person",
                    "name": "<?= htmlspecialchars($article['author'], ENT_QUOTES, 'UTF-8') ?>"
                },
                "datePublished": "<?= $article['publish_date'] ?>",
                "dateModified": "<?= $updatedDate ?>",
                "description": "<?= htmlspecialchars($article['excerpt'], ENT_QUOTES, 'UTF-8') ?>",
                "publisher": {
                    "@type": "Organization",
                    "name": "<?= htmlspecialchars($config['site_name'], ENT_QUOTES, 'UTF-8') ?>",
                    "logo": {
                        "@type": "ImageObject",
                        "url": "<?= htmlspecialchars($config['logo'] ?? '/img/logo.png', ENT_QUOTES, 'UTF-8') ?>"
                    }
                }
            }
            </script>
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
