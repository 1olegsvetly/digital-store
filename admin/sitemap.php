<?php
/**
 * Regenerate Sitemap
 */

require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    redirect('/admin/login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Неверный CSRF токен';
    } else {
        try {
            $count = generateSitemap();
            $message = "Sitemap успешно перегенерирован! Добавлено URL: {$count}";
            
            // Log action
            $log = loadJson(DATA_DIR . '/config/import_log.json');
            $log[] = [
                'action' => 'sitemap_regenerate',
                'timestamp' => date('c'),
                'user' => $_SESSION['admin_username'],
                'urls_count' => $count
            ];
            saveJson(DATA_DIR . '/config/import_log.json', $log);
        } catch (Exception $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    }
}

ob_start();
?>

<div class="admin-page">
    <div class="admin-header">
        <h1>🗺️ Перегенерировать Sitemap</h1>
        <a href="/admin/" class="btn btn-outline">← Назад</a>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    
    <div class="admin-section">
        <p>Sitemap.xml содержит ссылки на все активные товары и опубликованные статьи. Файл используется поисковыми системами для индексации сайта.</p>
        
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Перегенерировать sitemap.xml?')">
                    🔄 Перегенерировать sitemap.xml
                </button>
            </div>
        </form>
        
        <?php if (file_exists(__DIR__ . '/../sitemap.xml')): ?>
        <div class="sitemap-info">
            <h3>Текущий sitemap.xml</h3>
            <p>Размер: <?= number_format(filesize(__DIR__ . '/../sitemap.xml')) ?> байт</p>
            <p>Последнее изменение: <?= date('d.m.Y H:i', filemtime(__DIR__ . '/../sitemap.xml')) ?></p>
            <a href="/sitemap.xml" target="_blank" class="btn btn-outline">Открыть sitemap.xml</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Админ-панель — Sitemap';
include __DIR__ . '/../templates/admin-layout.php';
