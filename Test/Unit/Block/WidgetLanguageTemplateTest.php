<?php

namespace SmartCustomer\Reviews\Test\Unit\Block;

use PHPUnit\Framework\TestCase;

class WidgetLanguageTemplateTest extends TestCase
{
    public function testEveryWidgetTemplateForwardsTheSelectedLanguage(): void
    {
        $escaper = new class {
            public function __call($method, $arguments) { return htmlspecialchars((string)$arguments[0], ENT_QUOTES); }
        };
        foreach (['en', 'ar', 'nl', 'fr', 'de', 'it', 'pl', 'es', 'uk', 'jp', 'cn', 'hk'] as $language) {
            $block = new class($language) {
                private $language;
                public function __construct($language) { $this->language = $language; }
                public function getLanguage() { return $this->language; }
                public function getProducts() { return []; }
                public function printJson() { return '{}'; }
                public function __call($method, $arguments) { return strpos($method, 'is') === 0 ? true : ''; }
            };
            foreach (['tracking.phtml', 'widget/product_rating.phtml', 'widget/product_reviews.phtml'] as $template) {
                ob_start();
                include dirname(__DIR__, 3) . '/view/frontend/templates/' . $template;
                $html = ob_get_clean();
                $expected = $template === 'tracking.phtml' ? "language:'" . $language . "'" : 'data-language="' . $language . '"';
                $this->assertStringContainsString($expected, $html, $template . ': ' . $language);
            }
        }
    }
}
