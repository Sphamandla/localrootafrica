<?php

namespace modules\localroots\services;

use Craft;
use craft\helpers\FileHelper;
use yii\base\Component;

class TemplateExtractorService extends Component
{
    public string $templateBase = '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
    public string $outputBase = '';

    /** @var array<string, string> */
    private array $pageMap = [
        'home' => 'default-shop/index.html',
        'shop' => 'shop/index.html',
        'cart' => 'cart/index.html',
        'checkout' => 'checkout/index.html',
        'account' => 'my-account/index.html',
        'product' => 'product/sunday-best/index.html',
    ];

    public function init(): void
    {
        parent::init();
        if ($this->outputBase === '') {
            $this->outputBase = Craft::getAlias('@root/templates/_static');
        }
    }

    public function extractAll(): array
    {
        $written = [];
        FileHelper::createDirectory($this->outputBase);

        $homeFile = $this->templateBase . '/' . $this->pageMap['home'];
        if (file_exists($homeFile)) {
            $html = file_get_contents($homeFile);
            $written = array_merge($written, $this->extractSharedFromHtml($html, 'home'));
        }

        foreach ($this->pageMap as $handle => $relativePath) {
            $file = $this->templateBase . '/' . $relativePath;
            if (!file_exists($file)) {
                continue;
            }
            $html = file_get_contents($file);
            $written = array_merge($written, $this->extractPageContent($html, $handle));
        }

        return $written;
    }

    /**
     * @return list<string>
     */
    private function extractSharedFromHtml(string $html, string $source): array
    {
        $written = [];

        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $m)) {
            $head = $this->rewritePaths($m[1]);
            $head = $this->stripWordPressMeta($head);
            $written[] = $this->writeFragment('head.twig', "{# Extracted from {$source} #}\n" . $head);
        }

        if (preg_match('/<header[^>]*elementor-location-header[^>]*>(.*)<\/header>/is', $html, $m)) {
            $header = '<header data-elementor-type="header" data-elementor-id="12447" class="elementor elementor-12447 elementor-location-header" data-elementor-post-type="elementor_library">' . $m[1] . '</header>';
            $header = $this->rewritePaths($header);
            $header = $this->injectCraftNav($header);
            $written[] = $this->writeFragment('header.twig', "{# Extracted from {$source} #}\n" . $header);
        }

        if (preg_match('/(<div[^>]*class="footer-wrapper"[^>]*>[\s\S]*?<\/div>\s*<\/div><!--\s*\/?\s*#page\s*-->[\s\S]*?<div[^>]*id="scroll-to-top"[\s\S]*?<\/div>\s*<\/div>)/i', $html, $m)) {
            $footer = $this->rewritePaths($m[1]);
            $written[] = $this->writeFragment('footer.twig', "{# Extracted from {$source} #}\n" . $footer);
        }

        if (preg_match('/(<script[\s\S]*?<\/body>)/i', $html, $m)) {
            $scripts = $this->rewritePaths($m[1]);
            $scripts = preg_replace('/<\/body>\s*$/i', '', $scripts);
            $written[] = $this->writeFragment('scripts.twig', "{# Extracted from {$source} #}\n" . $scripts);
        }

        if (preg_match('/<body[^>]*class="([^"]*)"/i', $html, $m)) {
            $written[] = $this->writeFragment('body-class-home.twig', trim($m[1]));
        }

        return $written;
    }

    /**
     * @return list<string>
     */
    private function extractPageContent(string $html, string $handle): array
    {
        $written = [];

        if (preg_match('/<body[^>]*class="([^"]*)"/i', $html, $m)) {
            $written[] = $this->writeFragment("body-class-{$handle}.twig", trim($m[1]));
        }

        if (preg_match('/<div[^>]*id="page"[^>]*class="main-container"[^>]*>\s*<div[^>]*id="main-content"[^>]*>(.*?)<\/div><!--\s*#main-content\s*-->/is', $html, $m)) {
            $content = '<div id="page" class="main-container">' . "\n"
                . '<div id="main-content">' . "\n"
                . $this->rewritePaths($m[1]) . "\n"
                . '</div><!-- #main-content -->';
            $written[] = $this->writeFragment("content-{$handle}.twig", "{# Extracted from {$handle} #}\n" . $content);
            return $written;
        }

        if (preg_match('/<div[^>]*id="page"[^>]*>(.*?)<div[^>]*class="footer-wrapper"/is', $html, $m)) {
            $content = '<div id="page" class="main-container">' . "\n"
                . $this->rewritePaths($m[1]);
            $written[] = $this->writeFragment("content-{$handle}.twig", "{# Extracted from {$handle} #}\n" . $content);
        }

        return $written;
    }

    private function writeFragment(string $filename, string $content): string
    {
        $path = $this->outputBase . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    public function rewritePaths(string $html): string
    {
        $replacements = [
            'https://innovecouture.vamtam.com/wp-content/' => '/wp-content/',
            'https://innovecouture.vamtam.com/default-shop/' => '/',
            'https://innovecouture.vamtam.com/' => '/',
            '../wp-content/' => '/wp-content/',
            '../wp-includes/' => '/wp-includes/',
            '../default-shop/index.html' => '/',
            '../default-shop/' => '/',
            '../index.html' => '/',
            '../shop/index.html' => '/shop',
            '../cart/index.html' => '/cart',
            '../checkout/index.html' => '/checkout',
            '../my-account/index.html' => '/account',
            '../my-account/lost-password/index.html' => '/account/lost-password',
            '../shop/' => '/shop/',
            '../cart/' => '/cart/',
            '../checkout/' => '/checkout/',
            '../my-account/' => '/account/',
            '../product/' => '/products/',
            '../wishlist/' => '/wishlist/',
            '../about/' => '/about/',
            '../contact/' => '/contact/',
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);
        // Keep %3F in asset URLs — exported WP Rocket files use literal ? in filenames.
        return $html;
    }

    private function stripWordPressMeta(string $head): string
    {
        $head = preg_replace('/<link[^>]+wp-json[^>]*>/i', '', $head);
        $head = preg_replace('/<link[^>]+oembed[^>]*>/i', '', $head);
        $head = preg_replace('/<link[^>]+feed[^>]*>/i', '', $head);
        $head = preg_replace('/<meta name=[\'"]generator[\'"][^>]*>/i', '', $head);
        return $head;
    }

    private function injectCraftNav(string $header): string
    {
        $header = preg_replace(
            '/(<a href="\/">[\s\S]*?<img[^>]+alt=")([^"]*)(")/i',
            '$1{{ siteSettings.siteName ?? "Innove Couture" }}$3',
            $header,
            1
        );

        return $header;
    }
}
