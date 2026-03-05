<?php

namespace Simialbi\View\Smarty;

use Psr\Container\ContainerExceptionInterface;
use Smarty\Exception;
use Smarty\Smarty;
use Smarty\Template;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Definitions\ArrayDefinition;
use Yiisoft\Html\Tag\Meta;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Strings\StringHelper;
use Yiisoft\View\WebView;
use Yiisoft\Widget\WidgetFactory;

use function array_pop;
use function constant;
use function ob_start;
use function ob_get_clean;
use function ob_implicit_flush;
use function trigger_error;

final class SmartyExtensions
{
    private static array $stack = [];
    private readonly Smarty $smarty;

    public function __construct(
        private readonly SmartyTemplateRenderer $renderer,
        private readonly UrlGeneratorInterface  $urlGenerator
    )
    {
        $smarty = $this->renderer->smarty;
        $this->smarty = $smarty;

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'path', [$this, 'functionPath']);
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'url', [$this, 'functionUrl']);
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'registerJsFile', [$this, 'functionRegisterJsFile']);
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'registerCssFile', [$this, 'functionRegisterCssFile']);
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'set', [$this, 'functionSet']);
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'meta', [$this, 'functionMeta']);
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'void', [$this, 'modifierVoid']);
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'registerJs', [$this, 'blockJavaScript']);
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'registerCss', [$this, 'blockCss']);
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'title', function (array $params, ?string $content, Template $template, bool &$repeat): void {
            if (!$repeat) {
                $this->functionSet(['title' => $content], $template);
            }
        });
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'description', function (array $params, ?string $content, Template $template, bool &$repeat): void {
            if (!$repeat) {
                $this->functionMeta(['name' => 'description', 'content' => $content], $template);
            }
        });
        $smarty->registerPlugin(Smarty::PLUGIN_COMPILER, 'use', [$this, 'compilerUse']);
    }

    /**
     * Smarty template function to get relative URL for using in links
     *
     * Usage is the following:
     *
     * {path route='blog/view' alias=$post.alias user=$user.id}
     *
     * where route is Yii route and the rest of parameters are passed as is.
     *
     * @param array $params
     * @param Template $template
     *
     * @return string
     */
    public function functionPath(array $params, Template $template): string
    {
        if (!isset($params['route'])) {
            trigger_error('Missing route parameter', E_USER_WARNING);
        }

        $route = ArrayHelper::remove($params, 'route');

        return $this->urlGenerator->generate($route, $params);
    }

    /**
     * Smarty template function to get absolute URL for using in links
     *
     * Usage is the following:
     *
     * {url route='blog/view' alias=$post.alias user=$user.id}
     *
     * where route is Yii route and the rest of parameters are passed as is.
     *
     * @param array $params
     * @param Template $template
     *
     * @return string
     */
    public function functionUrl(array $params, Template $template): string
    {
        if (!isset($params['route'])) {
            trigger_error('Missing route parameter', E_USER_WARNING);
        }

        $route = ArrayHelper::remove($params, 'route');

        return $this->urlGenerator->generateAbsolute($route, $params);
    }

    /**
     * Smarty compiler function plugin
     * Usage is the following:
     *
     * {use class="app\assets\AppAsset"}
     * {use class="yii\helpers\Html"}
     * {use class='yii\widgets\ActiveForm' type='block'}
     * {use class='@app\widgets\MyWidget' as='my_widget' type='function'}
     *
     * Supported attributes: class, as, type. Type defaults to 'static'.
     *
     * @param array $params
     * @param Template $template
     *
     * @return string
     * @note Even though this method is public it should not be called directly.
     * @throws Exception
     */
    public function compilerUse(array $params, Template $template): string
    {
        if (!isset($params[ArrayDefinition::CLASS_NAME])) {
            trigger_error("use: missing '" . ArrayDefinition::CLASS_NAME . "' parameter");
        }
        // Compiler plugin parameters may include quotes, so remove them
        foreach ($params as $key => $value) {
            $params[$key] = trim($value, '\'"');
        }

        $class = $params[ArrayDefinition::CLASS_NAME];
        $alias = ArrayHelper::getValue($params, 'as', StringHelper::baseName($params[ArrayDefinition::CLASS_NAME]));
        $type = ArrayHelper::getValue($params, 'type', 'static');

        if (!isset($this->smarty->registered_plugins[$type][$alias])) {
            // Register the class during compile time
            $this->smarty->registerClass($alias, $class);

            // Skip already registered block and function
            if ($type === Smarty::PLUGIN_BLOCK) {
                // Register widget tag during compile time
                $this->smarty->registerPlugin(Smarty::PLUGIN_BLOCK, $alias, function (array $params, string $content, Template $template, bool &$repeat) use ($class) {
                    $this->widgetBlock($class, $params, $content, $template, $repeat);
                });
            } elseif ($type === Smarty::PLUGIN_FUNCTION) {
                // Register widget tag during compile time
                $this->smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, $alias, function (array $params, Template $template) use ($class) {
                    $this->widgetFunction($class, $params, $template);
                });
            }
        }

        return '';
    }

    /**
     * Smarty modifier plugin
     * Converts any output to void
     *
     * @return void
     * @note Even though this method is public it should not be called directly.
     */
    public function modifierVoid(): void
    {
        return;
    }

    /**
     * Smarty function plugin
     * Usage is the following:
     *
     * {set title="My Page"}
     * {set theme="frontend"}
     *
     * Supported attributes: title, theme
     *
     * @param array $params
     * @param Template $template
     *
     * @return void
     * @note Even though this method is public it should not be called directly.
     */
    public function functionSet(array $params, Template $template): void
    {
        if (isset($params['title'])) {
            $template->tpl_vars['this']->value->setTitle(ArrayHelper::remove($params, 'title'));
        }
        if (isset($params['theme'])) {
            $template->tpl_vars['this']->value->setTheme(ArrayHelper::remove($params, 'theme'));
        }
//        if (isset($params['layout'])) { TODO
//            Yii::$app->controller->layout = ArrayHelper::remove($params, 'layout');
//        }

        if (!empty($params)) {
            $template->assign('params', $params);
        }
    }

    /**
     * Smarty function plugin
     * Usage is the following:
     *
     * {meta keywords="Yii,PHP,Smarty,framework"}
     *
     * Supported attributes: any; all attributes are passed as
     * parameter array to Yii's registerMetaTag function.
     *
     * @param array $params
     * @param Template $template
     *
     * @note Even though this method is public it should not be called directly.
     */
    public function functionMeta(array $params, Template $template): void
    {
        $key = $params['name'] ?? null;

        $template->tpl_vars['this']->value->registerMetaTag(Meta::tag()->attributes($params), $key);
    }

    /**
     * Smarty function plugin
     * Usage is the following:
     *
     * {registerJsFile url='https://maps.google.com/maps/api/js?sensor=false' position='POS_END'}
     *
     * Supported attributes: url, key, depends, position and valid HTML attributes for the script tag.
     * Refer to Yii documentation for details.
     * The position attribute is passed as text without the class prefix.
     * Default is 'POS_END'.
     *
     * @param array $params
     * @param Template $template
     *
     * @return void
     * @note Even though this method is public it should not be called directly.
     */
    public function functionRegisterJsFile(array $params, Template $template): void
    {
        if (!isset($params['url'])) {
            trigger_error("registerJsFile: missing 'url' parameter");
        }

        $url = ArrayHelper::remove($params, 'url');
        $key = ArrayHelper::remove($params, 'key');
        $position = isset($params['position'])
            ? $this->getViewConstVal($params['position'], WebView::POSITION_READY)
            : WebView::POSITION_READY;
        ArrayHelper::remove($params, 'position');

        $template->tpl_vars['this']->value->registerJsFile($url, $position, $params, $key);
    }

    /**
     * Smarty block function plugin
     * Usage is the following:
     *
     * {registerJs key='show' position='POS_LOAD'}
     *     $("span.show").replaceWith('<div class="show">');
     * {/registerJs}
     *
     * Supported attributes: key, position. Refer to Yii documentation for details.
     * The position attribute is passed as text without the class prefix.
     * Default is 'POS_READY'.
     *
     * @param array $params
     * @param ?string $content
     * @param Template $template
     * @param bool $repeat
     *
     * @return void
     * @note Even though this method is public it should not be called directly.
     */
    public function blockJavaScript(array $params, ?string $content, Template $template, bool &$repeat): void
    {
        if (!$repeat) {
            $key = $params['key'] ?? null;
            $position = isset($params['position'])
                ? $this->getViewConstVal($params['position'], WebView::POSITION_READY)
                : WebView::POSITION_READY;

            $template->tpl_vars['this']->value->registerJs($content, $position, $key);
        }
    }

    /**
     * Smarty function plugin
     * Usage is the following:
     *
     * {registerCssFile url='@assets/css/normalizer.css'}
     *
     * Supported attributes: url, key, depends and valid HTML attributes for the link tag.
     * Refer to Yii documentation for details.
     *
     * @param array $params
     * @param Template $template
     *
     * @return void
     * @note Even though this method is public it should not be called directly.
     */
    public function functionRegisterCssFile(array $params, Template $template): void
    {
        if (!isset($params['url'])) {
            trigger_error("registerCssFile: missing 'url' parameter");
        }

        $url = ArrayHelper::remove($params, 'url');
        $key = ArrayHelper::remove($params, 'key');
        $position = isset($params['position'])
            ? $this->getViewConstVal($params['position'], WebView::POSITION_HEAD)
            : WebView::POSITION_HEAD;
        ArrayHelper::remove($params, 'position');

        $template->tpl_vars['this']->value->registerCssFile($url, $position, $params, $key);
    }

    /**
     * Smarty block function plugin
     * Usage is the following:
     *
     * {registerCss}
     * div.header {
     *     background-color: #3366bd;
     *     color: white;
     * }
     * {/registerCss}
     *
     * Supported attributes: key and valid HTML attributes for the style tag.
     * Refer to Yii documentation for details.
     *
     * @param array $params
     * @param ?string $content
     * @param Template $template
     * @param bool $repeat
     *
     * @return void
     * @note Even though this method is public it should not be called directly.
     */
    public function blockCss(array $params, ?string $content, Template $template, bool &$repeat): void
    {
        if (!$repeat) {
            $key = $params['key'] ?? null;
            $position = isset($params['position'])
                ? $this->getViewConstVal($params['position'], WebView::POSITION_HEAD)
                : WebView::POSITION_HEAD;

            $template->tpl_vars['this']->value->registerCss($content, $position, $params, $key);
        }
    }

    /**
     * Helper function to convert a textual constant identifier to a View class
     * integer constant value.
     *
     * @param string $string Constant identifier name
     * @param int $default Default value
     *
     * @return int
     */
    private function getViewConstVal(string $string, int $default): int
    {
        try {
            $val = @constant('\Yiisoft\View\WebView::' . $string);
        } catch (\Throwable) {
        }

        return $val ?? $default;
    }

    /**
     * Smarty plugin callback function to support widget as Smarty blocks.
     * This function is not called directly by Smarty but through a
     * magic __call wrapper.
     *
     * Example usage is the following:
     *
     *    {ActiveForm assign='form' id='login-form'}
     *        {$form->field($model, 'username')}
     *        {$form->field($model, 'password')->passwordInput()}
     *        <div class="form-group">
     *            <input type="submit" value="Login" class="btn btn-primary" />
     *        </div>
     *    {/ActiveForm}
     *
     * @param string $class
     * @param array $params
     * @param ?string $content
     * @param Template $template
     * @param bool $repeat
     *
     * @return string
     * @throws ContainerExceptionInterface|\ReflectionException
     */
    private function widgetBlock(string $class, array $params, ?string $content, Template $template, bool &$repeat): string
    {
        // Check if this is the opening ($content is null) or closing tag.
        if ($repeat) {
            $params[ArrayDefinition::CLASS_NAME] = $class;
            // Figure out where to put the result of the widget call, if any
            $assign = ArrayHelper::remove($params, 'assign', false);
            ob_start();
            ob_implicit_flush(false);
            $widget = WidgetFactory::createWidget($params);
            self::$stack[] = $widget;
            if ($assign) {
                $template->assign($assign, $widget);
            }
        } else {
            /** @var \Yiisoft\Widget\Widget $widget */
            $widget = array_pop(self::$stack);
            echo $content;
            $out = $widget->render();

            return ob_get_clean() . $out;
        }

        return '';
    }

    /**
     * Smarty plugin callback function to support widgets as Smarty functions.
     * This function is not called directly by Smarty but through a
     * magic __call wrapper.
     *
     * Example usage is the following:
     *
     * {GridView dataProvider=$provider}
     *
     * @param string $class
     * @param array $params
     * @param Template $template
     *
     * @return string
     * @throws ContainerExceptionInterface
     * @throws \ReflectionException
     */
    private function widgetFunction(string $class, array $params, Template $template): string
    {
        $repeat = false;
        $this->widgetBlock($class, $params, null, $template, $repeat); // WidgetFactory::createWidget($params)
        return $this->widgetBlock($class, $params, '', $template, $repeat); // $widget->render()
    }
}
