<?php
session_start();
require_once '../includes/functions.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$configFile = '../data/config/payments.json';
$message = '';
$messageType = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $message = 'Ошибка CSRF-токена';
        $messageType = 'error';
    } else {
        $config = [
            'enabled_methods' => $_POST['enabled_methods'] ?? [],
            'demo_mode' => isset($_POST['demo_mode']),
            'yoomoney' => [
                'wallet' => trim($_POST['yoomoney_wallet'] ?? ''),
                'secret_key' => trim($_POST['yoomoney_secret'] ?? ''),
                'enabled' => isset($_POST['yoomoney_enabled']),
                'commission_percent' => floatval($_POST['yoomoney_commission'] ?? 0)
            ],
            'crypto' => [
                'enabled' => isset($_POST['crypto_enabled']),
                'networks' => [
                    'BTC' => trim($_POST['crypto_btc'] ?? ''),
                    'ETH' => trim($_POST['crypto_eth'] ?? ''),
                    'USDT_TRC20' => trim($_POST['crypto_usdt_trc20'] ?? ''),
                    'USDT_ERC20' => trim($_POST['crypto_usdt_erc20'] ?? ''),
                    'USDT_BEP20' => trim($_POST['crypto_usdt_bep20'] ?? ''),
                    'USDT_ARBITRUM' => trim($_POST['crypto_usdt_arbitrum'] ?? ''),
                    'USDT_POLYGON' => trim($_POST['crypto_usdt_polygon'] ?? ''),
                    'USDT_SOLANA' => trim($_POST['crypto_usdt_solana'] ?? ''),
                    'BCH' => trim($_POST['crypto_bch'] ?? ''),
                    'BCH_BEP20' => trim($_POST['crypto_bch_bep20'] ?? ''),
                    'BNB' => trim($_POST['crypto_bnb'] ?? ''),
                    'LTC' => trim($_POST['crypto_ltc'] ?? ''),
                    'DOGE' => trim($_POST['crypto_doge'] ?? ''),
                    'TON' => trim($_POST['crypto_ton'] ?? ''),
                    'TRX' => trim($_POST['crypto_trx'] ?? ''),
                    'DASH' => trim($_POST['crypto_dash'] ?? ''),
                    'SOL' => trim($_POST['crypto_sol'] ?? ''),
                    'NOTCOIN' => trim($_POST['crypto_notcoin'] ?? '')
                ]
            ],
            'cards' => [
                'enabled' => isset($_POST['cards_enabled']),
                'number' => trim($_POST['cards_number'] ?? ''),
                'holder' => trim($_POST['cards_holder'] ?? '')
            ],
            'limits' => [
                'min_amount' => floatval($_POST['min_amount'] ?? 10),
                'max_amount' => floatval($_POST['max_amount'] ?? 50000)
            ],
            'webhook_secret' => $_POST['webhook_secret'] ?? generateWebhookSecret()
        ];
        
        // Генерация секрета если пустой
        if (empty($config['webhook_secret']) || strpos($config['webhook_secret'], 'auto_generated') !== false) {
            $config['webhook_secret'] = generateWebhookSecret();
        }
        
        saveJson($configFile, $config);
        $message = 'Настройки оплаты успешно сохранены!';
        $messageType = 'success';
        
        // Перезагрузка конфигурации
        $currentConfig = $config;
    }
}

// Загрузка текущих настроек
if (file_exists($configFile)) {
    $currentConfig = json_decode(file_get_contents($configFile), true);
} else {
    $currentConfig = [
        'enabled_methods' => ['yoomoney', 'crypto'],
        'demo_mode' => false,
        'yoomoney' => ['wallet' => '', 'secret_key' => '', 'enabled' => true, 'commission_percent' => 0],
        'crypto' => [
            'enabled' => true, 
            'networks' => [
                'BTC' => '', 'ETH' => '', 'USDT_TRC20' => '', 'USDT_ERC20' => '',
                'USDT_BEP20' => '', 'USDT_ARBITRUM' => '', 'USDT_POLYGON' => '', 'USDT_SOLANA' => '',
                'BCH' => '', 'BCH_BEP20' => '', 'BNB' => '', 'LTC' => '', 'DOGE' => '',
                'TON' => '', 'TRX' => '', 'DASH' => '', 'SOL' => '', 'NOTCOIN' => ''
            ]
        ],
        'cards' => ['enabled' => false, 'number' => '', 'holder' => ''],
        'limits' => ['min_amount' => 10, 'max_amount' => 50000],
        'webhook_secret' => generateWebhookSecret()
    ];
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки оплаты - Админ-панель</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .settings-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .settings-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .crypto-networks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .btn-save {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-save:hover {
            background: #218838;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .webhook-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            border-left: 4px solid #007bff;
        }
        .webhook-url {
            font-family: monospace;
            background: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-content">
            <h1>⚙️ Настройки платёжной системы</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Общие настройки -->
                <div class="settings-section">
                    <h3>📋 Общие настройки</h3>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="demo_mode" name="demo_mode" <?php echo !empty($currentConfig['demo_mode']) ? 'checked' : ''; ?>>
                        <label for="demo_mode">Демо-режим (тестовые платежи)</label>
                    </div>
                    
                    <div class="form-group">
                        <label>Минимальная сумма платежа (RUB):</label>
                        <input type="number" name="min_amount" value="<?php echo htmlspecialchars($currentConfig['limits']['min_amount']); ?>" step="1" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Максимальная сумма платежа (RUB):</label>
                        <input type="number" name="max_amount" value="<?php echo htmlspecialchars($currentConfig['limits']['max_amount']); ?>" step="1" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Активные методы оплаты:</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="yoomoney_method" name="enabled_methods[]" value="yoomoney" <?php echo in_array('yoomoney', $currentConfig['enabled_methods']) ? 'checked' : ''; ?>>
                            <label for="yoomoney_method">ЮMoney</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="crypto_method" name="enabled_methods[]" value="crypto" <?php echo in_array('crypto', $currentConfig['enabled_methods']) ? 'checked' : ''; ?>>
                            <label for="crypto_method">Криптовалюты</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="cards_method" name="enabled_methods[]" value="cards" <?php echo in_array('cards', $currentConfig['enabled_methods']) ? 'checked' : ''; ?>>
                            <label for="cards_method">Банковские карты (ручной перевод)</label>
                        </div>
                    </div>
                </div>
                
                <!-- ЮMoney -->
                <div class="settings-section">
                    <h3>💳 ЮMoney (Яндекс.Деньги)</h3>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="yoomoney_enabled" name="yoomoney_enabled" <?php echo !empty($currentConfig['yoomoney']['enabled']) ? 'checked' : ''; ?>>
                        <label for="yoomoney_enabled">Включить ЮMoney</label>
                    </div>
                    
                    <div class="form-group">
                        <label>Кошелёк ЮMoney:</label>
                        <input type="text" name="yoomoney_wallet" value="<?php echo htmlspecialchars($currentConfig['yoomoney']['wallet']); ?>" placeholder="41001XXXXXXXXXXXX">
                    </div>
                    
                    <div class="form-group">
                        <label>Секретный ключ (для уведомлений):</label>
                        <input type="password" name="yoomoney_secret" value="<?php echo htmlspecialchars($currentConfig['yoomoney']['secret_key']); ?>" placeholder="Укажите секрет из настроек ЮMoney">
                    </div>
                    
                    <div class="form-group">
                        <label>Комиссия (%):</label>
                        <input type="number" name="yoomoney_commission" value="<?php echo htmlspecialchars($currentConfig['yoomoney']['commission_percent']); ?>" step="0.1" min="0" max="100">
                    </div>
                </div>
                
                <!-- Криптовалюты -->
                <div class="settings-section">
                    <h3>₿ Криптовалюты</h3>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="crypto_enabled" name="crypto_enabled" <?php echo !empty($currentConfig['crypto']['enabled']) ? 'checked' : ''; ?>>
                        <label for="crypto_enabled">Включить криптовалюты</label>
                    </div>
                    
                    <div class="crypto-networks">
                        <div class="form-group">
                            <label>BTC (Bitcoin):</label>
                            <input type="text" name="crypto_btc" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['BTC']); ?>" placeholder="Ваш BTC кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>ETH (Ethereum):</label>
                            <input type="text" name="crypto_eth" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['ETH']); ?>" placeholder="Ваш ETH кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>USDT TRC20:</label>
                            <input type="text" name="crypto_usdt_trc20" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['USDT_TRC20']); ?>" placeholder="Ваш USDT TRC20 кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>USDT ERC20:</label>
                            <input type="text" name="crypto_usdt_erc20" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['USDT_ERC20']); ?>" placeholder="Ваш USDT ERC20 кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>USDT BEP20:</label>
                            <input type="text" name="crypto_usdt_bep20" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['USDT_BEP20']); ?>" placeholder="Ваш USDT BEP20 кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>USDT Arbitrum:</label>
                            <input type="text" name="crypto_usdt_arbitrum" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['USDT_ARBITRUM']); ?>" placeholder="Ваш USDT Arbitrum кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>USDT Polygon:</label>
                            <input type="text" name="crypto_usdt_polygon" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['USDT_POLYGON']); ?>" placeholder="Ваш USDT Polygon кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>USDT Solana:</label>
                            <input type="text" name="crypto_usdt_solana" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['USDT_SOLANA']); ?>" placeholder="Ваш USDT Solana кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>BCH (Bitcoin Cash):</label>
                            <input type="text" name="crypto_bch" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['BCH']); ?>" placeholder="Ваш BCH кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>BCH BEP20:</label>
                            <input type="text" name="crypto_bch_bep20" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['BCH_BEP20']); ?>" placeholder="Ваш BCH BEP20 кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>BNB:</label>
                            <input type="text" name="crypto_bnb" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['BNB']); ?>" placeholder="Ваш BNB кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>LTC (Litecoin):</label>
                            <input type="text" name="crypto_ltc" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['LTC']); ?>" placeholder="Ваш LTC кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>DOGE:</label>
                            <input type="text" name="crypto_doge" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['DOGE']); ?>" placeholder="Ваш DOGE кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>TON:</label>
                            <input type="text" name="crypto_ton" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['TON']); ?>" placeholder="Ваш TON кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>TRX (Tron):</label>
                            <input type="text" name="crypto_trx" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['TRX']); ?>" placeholder="Ваш TRX кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>DASH:</label>
                            <input type="text" name="crypto_dash" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['DASH']); ?>" placeholder="Ваш DASH кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>SOL (Solana):</label>
                            <input type="text" name="crypto_sol" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['SOL']); ?>" placeholder="Ваш SOL кошелёк">
                        </div>
                        
                        <div class="form-group">
                            <label>NOTCOIN:</label>
                            <input type="text" name="crypto_notcoin" value="<?php echo htmlspecialchars($currentConfig['crypto']['networks']['NOTCOIN']); ?>" placeholder="Ваш NOTCOIN кошелёк">
                        </div>
                    </div>
                </div>
                
                <!-- Банковские карты -->
                <div class="settings-section">
                    <h3>🏦 Банковские карты</h3>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="cards_enabled" name="cards_enabled" <?php echo !empty($currentConfig['cards']['enabled']) ? 'checked' : ''; ?>>
                        <label for="cards_enabled">Включить оплату картами</label>
                    </div>
                    
                    <div class="form-group">
                        <label>Номер карты:</label>
                        <input type="text" name="cards_number" value="<?php echo htmlspecialchars($currentConfig['cards']['number']); ?>" placeholder="0000 0000 0000 0000" maxlength="19">
                    </div>
                    
                    <div class="form-group">
                        <label>Владелец карты:</label>
                        <input type="text" name="cards_holder" value="<?php echo htmlspecialchars($currentConfig['cards']['holder']); ?>" placeholder="IVAN IVANOV">
                    </div>
                </div>
                
                <!-- Webhook информация -->
                <div class="settings-section">
                    <h3>🔗 Webhook для автоматических уведомлений</h3>
                    
                    <div class="form-group">
                        <label>Секрет webhook (автоматически генерируется):</label>
                        <input type="text" name="webhook_secret" value="<?php echo htmlspecialchars($currentConfig['webhook_secret']); ?>" readonly style="background: #f5f5f5;">
                    </div>
                    
                    <div class="webhook-info">
                        <strong>URL для настройки в платёжных системах:</strong><br>
                        <div class="webhook-url"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/webhooks/yoomoney_webhook.php</div>
                        <br>
                        <small>Используйте этот URL при настройке уведомлений в ЮMoney или других платёжных системах.</small>
                    </div>
                </div>
                
                <button type="submit" class="btn-save">💾 Сохранить настройки</button>
            </form>
        </main>
    </div>
</body>
</html>
