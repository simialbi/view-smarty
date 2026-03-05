{**
 * @var Yiisoft\Aliases\Aliases $aliases
 * @var Yiisoft\Assets\AssetManager $assetManager
 * @var string $content
 * @var string|null $csrf
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 *}

{$this->beginPage()}
<!DOCTYPE html>
<html lang="{$this->getLocale()}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$this->getTitle()|escape}</title>
    {$this->head()}
</head>
<body>
{$this->beginBody()}

<div class="content">
    {$content}
</div>

{$this->endBody()}
</body>
{$this->endPage()}
</html>
