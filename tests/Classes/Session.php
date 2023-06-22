<?php

namespace Plasticode\DI\Tests\Classes;

class Session implements SessionInterface
{
    public function __construct(
        SettingsProviderInterface $settingsProvider
    )
    {
    }
}
