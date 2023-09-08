<?php

namespace Plasticode\DI\Tests\Interfaces;

interface DependantInterface
{
    public function dependency(): TerminusInterface;
}
