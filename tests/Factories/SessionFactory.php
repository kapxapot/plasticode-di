<?php

namespace Plasticode\DI\Tests\Factories;

use Plasticode\DI\Tests\Classes\Session;
use Plasticode\DI\Tests\Interfaces\SessionInterface;
use Plasticode\DI\Tests\Interfaces\SettingsProviderInterface;

class SessionFactory
{
    public function __invoke(
        SettingsProviderInterface $settingsProvider
    ): SessionInterface
    {
        return new Session($settingsProvider);
    }
}
