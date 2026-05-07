<?php
/**
 * Core functions for flat-file CMS
 */

// Configuration
define('DATA_DIR', __DIR__ . '/../data');
define('CACHE_DIR', __DIR__ . '/../cache');
define('SESSIONS_DIR', __DIR__ . '/../sessions');

/**
 * Load JSON file with caching
 */
function loadJson($filepath) {
    if (!file_exists($filepath)) {
        return [];
    }
    
    $content = file_get_contents($filepath);
    return json_decode($content, true) ?: [];
}

/**
 * Save JSON file with atomic write and file locking
 */
function saveJson($filepath, $data) {
    $tempFile = $filepath . '.tmp';
    $lockFile = $filepath . '.lock';
    
    $fp = fopen($lockFile, 'c+');
    if (flock($fp, LOCK_EX)) {
        file_put_contents($tempFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        rename($tempFile, $filepath);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    @unlink($lockFile);
}

/**
 * Get cached content or generate new
 */
function getCached($key, $generator, $ttl = 300) {
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.html';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        return file_get_contents($cacheFile);
    }
    
    $content = $generator();
    file_put_contents($cacheFile, $content);
    return $content;
}

/**
 * Clear cache by pattern
 */
function clearCache($pattern = '*') {
    $files = glob(CACHE_DIR . '/' . $pattern);
    foreach ($files as $file) {
        @unlink($file);
    }
}

/**
 * Generate slug from text
 */
function generateSlug($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-') . '-' . substr(uniqid(), -5);
}

/**
 * Get site configuration
 */
function getSiteConfig() {
    return loadJson(DATA_DIR . '/config/site.json');
}

/**
 * Get SEO templates
 */
function getSeoTemplates() {
    return loadJson(DATA_DIR . '/config/seo.json');
}

/**
 * Start secure session
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    startSession();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Get all products from category
 */
function getProducts($category, $filters = []) {
    $filepath = DATA_DIR . "/products/{$category}.json";
    $products = loadJson($filepath);
    
    if (!empty($filters)) {
        $products = array_filter($products, function($p) use ($filters) {
            if (isset($filters['in_stock']) && $filters['in_stock'] && $p['stock'] <= 0) {
                return false;
            }
            if (isset($filters['price_max']) && $p['price'] > $filters['price_max']) {
                return false;
            }
            if (isset($filters['price_min']) && $p['price'] < $filters['price_min']) {
                return false;
            }
            if (isset($filters['status']) && $p['status'] !== $filters['status']) {
                return false;
            }
            return true;
        });
    }
    
    return $products;
}

/**
 * Get product by slug
 */
function getProductBySlug($slug) {
    $index = loadJson(DATA_DIR . '/products/index.json');
    
    if (isset($index[$slug])) {
        $category = $index[$slug]['category'];
        $products = getProducts($category);
        
        foreach ($products as $product) {
            if ($product['slug'] === $slug) {
                return $product;
            }
        }
    }
    
    return null;
}

/**
 * Get all articles
 */
function getArticles($filters = []) {
    $articles = [];
    $articleFiles = glob(DATA_DIR . '/articles/*.json');
    
    foreach ($articleFiles as $file) {
        if (basename($file) === 'index.json') continue;
        $fileArticles = loadJson($file);
        $articles = array_merge($articles, $fileArticles);
    }
    
    if (!empty($filters)) {
        $articles = array_filter($articles, function($a) use ($filters) {
            if (isset($filters['status']) && $a['status'] !== $filters['status']) {
                return false;
            }
            return true;
        });
    }
    
    // Sort by publish date descending
    usort($articles, function($a, $b) {
        return strtotime($b['publish_date']) - strtotime($a['publish_date']);
    });
    
    return $articles;
}

/**
 * Get article by slug
 */
function getArticleBySlug($slug) {
    $articles = getArticles();
    
    foreach ($articles as $article) {
        if ($article['slug'] === $slug) {
            return $article;
        }
    }
    
    return null;
}

/**
 * Update product index
 */
function updateProductIndex() {
    $index = [];
    $productFiles = glob(DATA_DIR . '/products/*.json');
    
    foreach ($productFiles as $file) {
        if (basename($file) === 'index.json') continue;
        $products = loadJson($file);
        
        foreach ($products as $product) {
            $index[$product['slug']] = [
                'category' => $product['category'],
                'file' => basename($file)
            ];
        }
    }
    
    saveJson(DATA_DIR . '/products/index.json', $index);
}

/**
 * Generate sitemap.xml
 */
function generateSitemap() {
    $config = getSiteConfig();
    $baseUrl = $config['site_url'] ?? 'https://yoursite.com';
    
    $urls = [];
    
    // Homepage
    $urls[] = [
        'loc' => $baseUrl,
        'lastmod' => date('Y-m-d'),
        'changefreq' => 'daily',
        'priority' => '1.0'
    ];
    
    // Products
    $productFiles = glob(DATA_DIR . '/products/*.json');
    foreach ($productFiles as $file) {
        if (basename($file) === 'index.json') continue;
        $products = loadJson($file);
        
        foreach ($products as $product) {
            if ($product['status'] === 'active') {
                $urls[] = [
                    'loc' => $baseUrl . '/catalog/' . $product['category'] . '/' . $product['slug'],
                    'lastmod' => date('Y-m-d', strtotime($product['updated_at'])),
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
            }
        }
    }
    
    // Articles
    $articles = getArticles(['status' => 'published']);
    foreach ($articles as $article) {
        $urls[] = [
            'loc' => $baseUrl . '/blog/' . $article['slug'],
            'lastmod' => date('Y-m-d', strtotime($article['publish_date'])),
            'changefreq' => 'monthly',
            'priority' => '0.6'
        ];
    }
    
    // Generate XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    foreach ($urls as $url) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . e($url['loc']) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
        $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    
    $xml .= '</urlset>';
    
    file_put_contents(__DIR__ . '/../sitemap.xml', $xml);
    
    return count($urls);
}

/**
 * Get product by ID from any category
 */
function getProductById($id) {
    $categories = ['facebook', 'instagram', 'google', 'tiktok', 'twitter', 'telegram'];
    
    foreach ($categories as $cat) {
        $file = DATA_DIR . "/products/{$cat}.json";
        if (file_exists($file)) {
            $products = loadJson($file);
            foreach ($products as $p) {
                if ($p['id'] === $id) {
                    return $p;
                }
            }
        }
    }
    
    return null;
}

/**
 * Generate webhook secret key
 */
function generateWebhookSecret() {
    return 'whsec_' . bin2hex(random_bytes(32));
}

/**
 * Get payment configuration
 */
function getPaymentConfig() {
    $configFile = DATA_DIR . '/config/payments.json';
    if (file_exists($configFile)) {
        return loadJson($configFile);
    }
    
    // Default config
    return [
        'enabled_methods' => ['yoomoney', 'crypto'],
        'demo_mode' => false,
        'yoomoney' => ['wallet' => '', 'secret_key' => '', 'enabled' => true, 'commission_percent' => 0],
        'crypto' => ['enabled' => true, 'networks' => ['BTC' => '', 'ETH' => '', 'USDT_TRC20' => '', 'USDT_ERC20' => '', 'TON' => '', 'SOL' => '']],
        'cards' => ['enabled' => false, 'number' => '', 'holder' => ''],
        'limits' => ['min_amount' => 10, 'max_amount' => 50000],
        'webhook_secret' => generateWebhookSecret()
    ];
}

/**
 * Check if payment method is enabled
 */
function isPaymentMethodEnabled($method) {
    $config = getPaymentConfig();
    return in_array($method, $config['enabled_methods']);
}
