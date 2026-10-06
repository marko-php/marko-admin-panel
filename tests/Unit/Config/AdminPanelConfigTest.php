<?php

declare(strict_types=1);

use Marko\AdminPanel\Config\AdminPanelConfig;
use Marko\AdminPanel\Config\AdminPanelConfigInterface;
use Marko\Testing\Fake\FakeConfigRepository;

it('reads the page title from admin-panel.page_title', function (): void {
    $config = new AdminPanelConfig(new FakeConfigRepository([
        'admin-panel.page_title' => 'My Custom Admin',
    ]));

    expect($config)->toBeInstanceOf(AdminPanelConfigInterface::class)
        ->and($config->getPageTitle())->toBe('My Custom Admin');
});

it('ships config/admin-panel.php with only the page title default', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin-panel.php';
    $configData = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($configData)->toBeArray()
        ->and($configData)->toBe(['page_title' => 'Marko Admin']);
});

it('binds AdminPanelConfigInterface to AdminPanelConfig in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';
    $module = require $modulePath;

    expect(file_exists($modulePath))->toBeTrue()
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminPanelConfigInterface::class)
        ->and($module['bindings'][AdminPanelConfigInterface::class])
            ->toBe(AdminPanelConfig::class);
});
