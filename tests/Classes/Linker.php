<?php

namespace Plasticode\DI\Tests\Classes;

class Linker implements LinkerInterface
{
    private SettingsProviderInterface $settingsProvider;

    public function __construct(
        SettingsProviderInterface $settingsProvider
    )
    {
        $this->settingsProvider = $settingsProvider;
    }
}
