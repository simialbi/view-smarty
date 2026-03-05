<?php

declare(strict_types=1);

use Smarty\Smarty;
use Yiisoft\Aliases\Aliases;

/** @var array $params */

return [
    Smarty::class => static fn (Aliases $aliases) => (new Smarty())
        ->setTemplateDir($aliases->get($params['smarty/smarty']['template_dir']))
        ->setCompileDir($aliases->get($params['smarty/smarty']['compile_dir'])),
];
