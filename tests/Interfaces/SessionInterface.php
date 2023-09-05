<?php

namespace Plasticode\DI\Tests\Interfaces;

interface SessionInterface
{
    public function settingsProvider(): SettingsProviderInterface;
}
