<?php

declare(strict_types=1);

use Smarty\Smarty;
use Yiisoft\Definitions\Reference;

return [
    'smarty/smarty' => [
        'template_dir' => '',
        'compile_dir' => '@runtime/Smarty/compile',
    ],
    'yiisoft/view' => [
        'renderers' => [
            'tpl' => Reference::to(Smarty::class),
        ],
        'defaultExtension' => 'tpl'
    ]
];
