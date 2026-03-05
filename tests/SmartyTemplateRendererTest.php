<?php

namespace Simialbi\View\Smarty\Tests;

use PHPUnit\Framework\TestCase;
use Simialbi\View\Smarty\SmartyExtensions;
use Simialbi\View\Smarty\SmartyTemplateRenderer;
use Smarty\Smarty;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Files\FileHelper;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Test\Support\Container\SimpleContainer;
use Yiisoft\View\WebView;

class SmartyTemplateRendererTest extends TestCase
{
    private string $layoutPath;
    private string $tempDirectory;

    public function testLayout(): void
    {
        $content = $this
            ->getView()
            ->render('index.tpl', ['name' => 'Jon Doe']);

        $result = $this
            ->getView()
            ->setTitle('Yii Demo (Smarty)')
            ->render($this->layoutPath, ['content' => $content]);

        $this->assertStringContainsString('Yii Demo (Smarty)', $result);
        $this->assertStringContainsString('Jon Doe', $result);
        $this->assertStringNotContainsString('{$name}', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDirectory = __DIR__ . '/public/tmp/View';
        FileHelper::ensureDirectory($this->tempDirectory);
        $this->layoutPath = dirname(__DIR__) . '/tests/public/views/layout.tpl';

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        FileHelper::removeDirectory($this->tempDirectory);
    }

    private function getContainer(): SimpleContainer
    {
        $aliases = new Aliases([
            '@root' => dirname(__DIR__),
            '@public' => '@root/tests/public',
            '@basePath' => '@public/assets',
            '@views' => '@public/views',
            '@baseUrl' => '/base-url',
        ]);

        $smarty = new Smarty();
        $smarty->setCompileDir($this->tempDirectory);
        $smarty->setCacheDir($this->tempDirectory);
        $smarty->setTemplateDir(dirname($this->layoutPath));

        $renderer = new SmartyTemplateRenderer($smarty);
        $urlGenerator = new UrlGenerator(new RouteCollection(new RouteCollector()));
        $ext = new SmartyExtensions($renderer, $urlGenerator);
//        $smarty->setTemplateDir($this->layoutPath);

        return new SimpleContainer([
            Aliases::class => $aliases,
            Smarty::class => $smarty,
            SmartyTemplateRenderer::class => $renderer,
            SmartyExtensions::class => $ext,
        ]);
    }

    private function getView(?SimpleContainer $container = null): WebView
    {
        $container ??= $this->getContainer();

        return (new WebView($container->get(Aliases::class)->get('@views')))
            ->withRenderers(['tpl' => new SmartyTemplateRenderer($container->get(Smarty::class))])
            ->withFallbackExtension('tpl');
    }
}
