<?php

declare(strict_types=1);

namespace Simialbi\View\Smarty;

use Smarty\Filter\Output\TrimWhitespace;
use Smarty\Smarty;
use Throwable;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\View\TemplateRendererInterface;
use Yiisoft\View\ViewInterface;

use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_implicit_flush;
use function ob_start;

final class SmartyTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(public readonly Smarty $smarty)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function render(ViewInterface $view, string $template, array $parameters): string
    {
        $obInitialLevel = ob_get_level();
        ob_start();
        ob_implicit_flush(false);

        try {
            $cacheId = ArrayHelper::remove($parameters, 'cache_id');
            $compileId = ArrayHelper::remove($parameters, 'compile_id');

            $this->smarty->assign('this', $view);
            $this->smarty->assign($parameters);

//            if (!defined('APP_DEBUG') || !APP_DEBUG) {
//                $this->smarty->registerFilter(Smarty::FILTER_OUTPUT, [TrimWhitespace::class, 'filter'], 'trimwhitespace');
//            }

            $this->smarty->display($template, $cacheId, $compileId);

            /**
             * @var string We assume that in this case active output buffer is always existed, so `ob_get_clean()`
             * returns a string.
             */
            return ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $obInitialLevel) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
