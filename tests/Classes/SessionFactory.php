<?php

namespace Plasticode\DI\Tests\Classes;

class SessionFactory
{
    public function __invoke(
        SettingsProviderInterface $settingsProvider
    ): SessionInterface
    {
        return new Session($settingsProvider);
    }
}
