<?php
/**
 * API для получения похожих товаров
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';

$category = $_GET['category'] ?? '';
$exclude = $_GET['exclude'] ?? '';
$limit = (int)($_GET['limit'] ?? 4);

if (!$category) {
    echo json_encode([]);
    exit;
}

// Загружаем товары категории
$productsFile = __DIR__ . "/../data/products/{$category}.json";
if (!file_exists($productsFile)) {
    echo json_encode([]);
    exit;
}

$products = json_decode(file_get_contents($productsFile), true);
if (!is_array($products)) {
    echo json_encode([]);
    exit;
}

// Фильтруем: только активные, не текущий товар, в наличии
$filtered = array_filter($products, function($p) use ($exclude) {
    return $p['status'] === 'active' 
        && $p['id'] !== $exclude 
        && $p['stock'] > 0;
});

// Берем случайные товары
$keys = array_keys($filtered);
shuffle($keys);
$selectedKeys = array_slice($keys, 0, $limit);

$result = [];
foreach ($selectedKeys as $key) {
    $p = $filtered[$key];
    $result[] = [
        'id' => $p['id'],
        'slug' => $p['slug'],
        'title' => $p['title'],
        'price' => (float)$p['price'],
        'currency' => $p['currency'],
        'min_order' => (int)$p['min_order'],
        'image_url' => $p['image_url'],
        'stock' => (int)$p['stock']
    ];
}

echo json_encode($result);
