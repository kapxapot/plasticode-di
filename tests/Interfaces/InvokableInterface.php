<?php

namespace Plasticode\DI\Tests\Interfaces;

interface InvokableInterface
{
    public function __invoke(TerminusInterface $terminus): TerminusInterface;
}
