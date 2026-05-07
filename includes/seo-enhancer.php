<?php
/**
 * SEO Enhancer Module v1.0.6
 * Автоматическая оптимизация для Google (E-E-A-T, Schema.org, Alt-теги)
 */

class SEOEnhancer {
    
    /**
     * Генерация хлебных крошек с микроразметкой Schema.org
     */
    public static function getBreadcrumbs($items) {
        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => []
        ];
        
        $html = '<nav aria-label="Breadcrumb" class="breadcrumbs">';
        $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';
        
        foreach ($items as $index => $item) {
            $position = $index + 1;
            $isLast = $index === count($items) - 1;
            
            $jsonLd['itemListElement'][] = [
                "@type" => "ListItem",
                "position" => $position,
                "name" => $item['name'],
                "item" => $item['url']
            ];
            
            if ($isLast) {
                $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" class="active">';
                $html .= '<span itemprop="name">' . htmlspecialchars($item['name']) . '</span>';
            } else {
                $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
                $html .= '<a itemprop="item" href="' . htmlspecialchars($item['url']) . '">';
                $html .= '<span itemprop="name">' . htmlspecialchars($item['name']) . '</span></a>';
            }
            $html .= '</li>';
        }
        
        $html .= '</ol></nav>';
        
        return [
            'html' => $html,
            'json_ld' => json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ];
    }
    
    /**
     * Авто-генерация Alt-тега для изображений
     */
    public static function generateAlt($title, $suffix = '') {
        $alt = strip_tags($title);
        if ($suffix) {
            $alt .= ' ' . $suffix;
        }
        return htmlspecialchars(trim($alt));
    }
    
    /**
     * Генерация FAQ Schema для страницы товара
     */
    public static function getFAQSchema($product) {
        $guaranteeHours = $product['guarantee_hours'];
        
        $faqs = [
            [
                "question" => "Как я получу товар после оплаты?",
                "answer" => "Товар выдается автоматически сразу после подтверждения оплаты. Вы получите данные на экране и на email."
            ],
            [
                "question" => "Есть ли гарантия на товар?",
                "answer" => "Да, гарантия составляет {$guaranteeHours} часов. Если товар перестанет работать по нашей вине, мы заменим его."
            ],
            [
                "question" => "Какие способы оплаты доступны?",
                "answer" => "Мы принимаем YooMoney, банковские карты и криптовалюты (BTC, ETH, USDT, TON)."
            ]
        ];
        
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "FAQPage",
            "mainEntity" => array_map(function($faq) {
                return [
                    "@type" => "Question",
                    "name" => $faq['question'],
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => $faq['answer']
                    ]
                ];
            }, $faqs)
        ];
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * Проверка статуса наличия с текстовым описанием
     */
    public static function getStockStatus($stock, $minOrder = 1) {
        if ($stock <= 0) {
            return ['status' => 'out_of_stock', 'text' => 'Нет в наличии', 'class' => 'stock-out'];
        } elseif ($stock < $minOrder * 10) {
            return ['status' => 'low_stock', 'text' => 'Заканчивается', 'class' => 'stock-low'];
        } else {
            return ['status' => 'in_stock', 'text' => 'В наличии', 'class' => 'stock-in'];
        }
    }
    
    /**
     * Генерация даты обновления для E-E-A-T
     */
    public static function getUpdatedDate($createdAt) {
        // Имитация регулярного обновления для свежести контента
        $created = new DateTime($createdAt);
        $now = new DateTime();
        $diff = $now->diff($created);
        
        // Если статья старше 30 дней, добавляем "обновлено" для сигнала свежести
        if ($diff->days > 30) {
            $updated = clone $created;
            $updated->modify('+ ' . rand(1, 10) . ' days');
            return $updated->format('Y-m-d');
        }
        
        return $created->format('Y-m-d');
    }
}
