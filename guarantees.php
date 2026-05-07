<?php
/**
 * Страница "Гарантии и Возврат"
 * Важный фактор доверия E-E-A-T для Google
 */
require_once __DIR__ . '/../includes/functions.php';

$trustData = json_decode(file_get_contents(__DIR__ . '/../data/config/trust.json'), true);
$pageTitle = "Гарантии и возврат средств | " . getSiteConfig('name');
$metaDescription = "Полная информация о гарантиях, возврате средств и замене товаров. Честные условия работы магазина цифровых товаров.";

include __DIR__ . '/../templates/header.php';
?>

<main class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Хлебные крошки -->
            <?php
            $breadcrumbs = SEOEnhancer::getBreadcrumbs([
                ['name' => 'Главная', 'url' => '/'],
                ['name' => 'Гарантии и возврат', 'url' => '/guarantees.php']
            ]);
            echo $breadcrumbs['html'];
            ?>

            <h1 class="mb-4">Гарантии и возврат средств</h1>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h4 mb-3">🛡️ Наша гарантия</h2>
                    <p class="lead">Мы уверены в качестве наших товаров и предоставляем честную гарантию.</p>
                    
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <strong>⏱ Срок гарантии:</strong> 
                            <span class="badge bg-success"><?= $trustData['guarantees']['refund_period_hours'] ?> часов</span>
                            с момента покупки
                        </li>
                        <li class="mb-3">
                            <strong>🔄 Политика замены:</strong> 
                            <?= htmlspecialchars($trustData['guarantees']['replacement_policy']) ?>
                        </li>
                        <li class="mb-3">
                            <strong>💬 Поддержка:</strong> 
                            <?= htmlspecialchars($trustData['guarantees']['support_response_time']) ?>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h4 mb-3">📋 Условия возврата</h2>
                    
                    <div class="accordion" id="refundAccordion">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                    Когда можно вернуть товар?
                                </button>
                            </h3>
                            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#refundAccordion">
                                <div class="accordion-body">
                                    Возврат средств возможен в следующих случаях:
                                    <ul>
                                        <li>Товар не работает по нашей вине (брак)</li>
                                        <li>Товар не соответствует описанию на сайте</li>
                                        <li>Техническая ошибка при выдаче данных</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                    Когда возврат невозможен?
                                </button>
                            </h3>
                            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#refundAccordion">
                                <div class="accordion-body">
                                    Мы не возвращаем средства если:
                                    <ul>
                                        <li>Прошло более <?= $trustData['guarantees']['refund_period_hours'] ?> часов с момента покупки</li>
                                        <li>Товар перестал работать по вашей вине (нарушение правил использования)</li>
                                        <li>Вы передумали после получения рабочих данных (цифровой товар)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                    Как оформить возврат?
                                </button>
                            </h3>
                            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#refundAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Напишите в поддержку на email: <a href="mailto:<?= htmlspecialchars($trustData['company']['email']) ?>"><?= htmlspecialchars($trustData['company']['email']) ?></a></li>
                                        <li>Укажите номер заказа и описание проблемы</li>
                                        <li>Приложите скриншоты ошибки (если есть)</li>
                                        <li>Наша команда проверит заявку в течение 15 минут</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4 bg-light">
                <div class="card-body text-center">
                    <h2 class="h4 mb-3">📞 Контакты поддержки</h2>
                    <p class="mb-2"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($trustData['company']['email']) ?>"><?= htmlspecialchars($trustData['company']['email']) ?></a></p>
                    <p class="mb-2"><strong>Телефон:</strong> <?= htmlspecialchars($trustData['company']['phone']) ?></p>
                    <p class="mb-0"><strong>Режим работы:</strong> <?= htmlspecialchars($trustData['company']['work_hours']) ?></p>
                </div>
            </div>

            <!-- Микроразметка Organization -->
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "Organization",
                "name": "<?= htmlspecialchars($trustData['company']['name']) ?>",
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": "<?= htmlspecialchars($trustData['company']['address']) ?>"
                },
                "email": "<?= htmlspecialchars($trustData['company']['email']) ?>",
                "telephone": "<?= htmlspecialchars($trustData['company']['phone']) ?>",
                "openingHours": "Mo-Su 09:00-21:00"
            }
            </script>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../templates/footer.php'; ?>
