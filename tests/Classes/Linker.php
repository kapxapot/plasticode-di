<?php

namespace Plasticode\DI\Tests\Classes;

use Plasticode\DI\Tests\Interfaces\LinkerInterface;
use Plasticode\DI\Tests\Interfaces\SettingsProviderInterface;

class Linker implements LinkerInterface
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
