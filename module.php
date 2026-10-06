<?php

declare(strict_types=1);

use Marko\AdminPanel\Config\AdminPanelConfig;
use Marko\AdminPanel\Config\AdminPanelConfigInterface;
use Marko\AdminPanel\Menu\AdminMenuBuilder;
use Marko\AdminPanel\Menu\AdminMenuBuilderInterface;

return [
    'bindings' => [
        AdminPanelConfigInterface::class => AdminPanelConfig::class,
        AdminMenuBuilderInterface::class => AdminMenuBuilder::class,
    ],
];
