<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../includes/functions.php';

startSession();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Rate limiting check
    $lockFile = DATA_DIR . '/users/login_attempts.lock';
    $attempts = 0;
    $lastAttempt = 0;
    
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        if ($lockData && isset($lockData[$username])) {
            $attempts = $lockData[$username]['attempts'] ?? 0;
            $lastAttempt = $lockData[$username]['last_attempt'] ?? 0;
            
            // Lockout for 15 minutes after 5 failed attempts
            if ($attempts >= 5 && (time() - $lastAttempt) < 900) {
                $error = 'Слишком много попыток входа. Попробуйте через ' . ceil((900 - (time() - $lastAttempt)) / 60) . ' мин.';
            }
        }
    }
    
    if (empty($error)) {
        // Load admins
        $admins = loadJson(DATA_DIR . '/users/admins.json');
        $adminFound = null;
        
        foreach ($admins as $admin) {
            if ($admin['username'] === $username && $admin['status'] === 'active') {
                $adminFound = $admin;
                break;
            }
        }
        
        if ($adminFound && password_verify($password, $adminFound['password_hash'])) {
            // Success - reset attempts and login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $adminFound['id'];
            $_SESSION['admin_username'] = $adminFound['username'];
            
            // Update last login
            foreach ($admins as &$admin) {
                if ($admin['id'] === $adminFound['id']) {
                    $admin['last_login'] = date('c');
                }
            }
            saveJson(DATA_DIR . '/users/admins.json', $admins);
            
            // Clear lock file
            @unlink($lockFile);
            
            redirect('/admin/');
        } else {
            // Failed attempt
            $attempts++;
            $lockData[$username] = [
                'attempts' => $attempts,
                'last_attempt' => time()
            ];
            file_put_contents($lockFile, json_encode($lockData));
            
            $error = 'Неверный логин или пароль';
        }
    }
}

ob_start();
?>

<div class="login-page">
    <div class="login-container">
        <h1>Вход в админ-панель</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            
            <div class="form-group">
                <label for="username">Логин</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Войти</button>
        </form>
        
        <p class="login-note"><a href="/">← Вернуться на сайт</a></p>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Вход в админ-панель';
include __DIR__ . '/../templates/admin-layout.php';
