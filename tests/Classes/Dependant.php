<?php

namespace Plasticode\DI\Tests\Classes;

use Plasticode\DI\Tests\Interfaces\DependantInterface;
use Plasticode\DI\Tests\Interfaces\TerminusInterface;

/**
 * This class depends on another class.
 */
class Dependant implements DependantInterface
{
    private TerminusInterface $terminus;

    public function __construct(TerminusInterface $terminus)
    {
        $this->terminus = $terminus;
    }

    public function dependency(): TerminusInterface
    {
        return $this->terminus;
    }
}
