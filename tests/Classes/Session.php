<?php

namespace Plasticode\DI\Tests\Classes;

use Plasticode\DI\Tests\Interfaces\SessionInterface;
use Plasticode\DI\Tests\Interfaces\SettingsProviderInterface;

class Session implements SessionInterface
{
    private SettingsProviderInterface $settingsProvider;

    public function __construct(
        SettingsProviderInterface $settingsProvider
    )
    {
        $this->settingsProvider = $settingsProvider;
    }

    public function settingsProvider(): SettingsProviderInterface
    {
        return $this->settingsProvider;
    }
}
