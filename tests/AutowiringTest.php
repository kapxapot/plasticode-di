<?php

namespace Plasticode\DI\Tests;

use PHPUnit\Framework\TestCase;
use Plasticode\DI\Autowirer;
use Plasticode\DI\Containers\AutowiringContainer;
use Plasticode\DI\ParamResolvers\UntypedContainerParamResolver;
use Plasticode\DI\Tests\Classes\Dependant;
use Plasticode\DI\Tests\Classes\Invokable;
use Plasticode\DI\Tests\Classes\Terminus;
use Plasticode\DI\Tests\Factories\DependantFactory;
use Plasticode\DI\Tests\Factories\DependantFactoryFactory;
use Plasticode\DI\Tests\Factories\InvokableFactory;
use Plasticode\DI\Tests\Interfaces\DependantInterface;
use Plasticode\DI\Tests\Interfaces\InvokableInterface;
use Plasticode\DI\Tests\Interfaces\TerminusInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use stdClass;

final class AutowiringTest extends TestCase
{
    private Autowirer $autowirer;

    public function setUp(): void
    {
        parent::setUp();

        $this->autowirer = new Autowirer();
    }

    public function tearDown(): void
    {
        unset($this->autowirer);

        parent::tearDown();
    }

    /**
     * Creates a container with the provided mappings.
     */
    private function createContainer(?array $map = null): AutowiringContainer
    {
        return new AutowiringContainer($this->autowirer, $map);
    }

    /**
     * Tests that an "empty" container successfully resolves:
     * - ContainerInterface
     * - Autowirer
     */
    public function testDefaults(): void
    {
        $container = $this->createContainer();

        $this->assertSame($container, $container->get(ContainerInterface::class));
        $this->assertSame($this->autowirer, $container->get(Autowirer::class));
    }

    /**
     * Tests that if something cannot be autowired:
     * - `has()` returns false
     * - `get()` throws an exception
     */
    public function testUndefined(): void
    {
        $container = $this->createContainer();

        $this->assertFalse(
            $container->has(TerminusInterface::class)
        );

        $this->expectException(ContainerExceptionInterface::class);

        $container->get(TerminusInterface::class);
    }

    /**
     * Tests the simple mapping `interface` -> `class`.
     *
     * TerminusInterface -(maps to)-> Terminus
     */
    public function testSimple(): void
    {
        $container = $this->createContainer([
            TerminusInterface::class => Terminus::class,
        ]);

        $this->assertTrue(
            $container->has(TerminusInterface::class)
        );

        $this->assertInstanceOf(
            Terminus::class,
            $container->get(TerminusInterface::class)
        );
    }

    /**
     * Tests the dependency injection of a class into another class.
     *
     * DependantInterface -(maps to)-> Dependant
     * TerminusInterface -(is injected into)-> Dependant
     * TerminusInterface -(maps to)-> Terminus
     *
     * As a result:
     *
     * Terminus -(is injected into)-> Dependant
     */
    public function testDependency(): void
    {
        $container = $this->createContainer([
            DependantInterface::class => Dependant::class,
            TerminusInterface::class => Terminus::class,
        ]);

        $this->assertTrue(
            $container->has(DependantInterface::class)
        );

        /** @var DependantInterface */
        $dependant = $container->get(DependantInterface::class);

        $this->assertInstanceOf(Dependant::class, $dependant);
        $this->assertInstanceOf(Terminus::class, $dependant->dependency());
    }

    /**
     * Tests the class instantiation using a factory (an invokable class).
     *
     * DependantInterface -(maps to)-> DependantFactory
     * DependantFactory -(makes)-> Dependant
     * TerminusInterface -(is injected into)-> DependantFactory
     * TerminusInterface -(maps to)-> Terminus
     *
     * As a result:
     *
     * Terminus -(is injected into)-> Dependant
     */
    public function testFactory(): void
    {
        $container = $this->createContainer([
            TerminusInterface::class => Terminus::class,
            DependantInterface::class => DependantFactory::class,
        ]);

        $this->assertTrue(
            $container->has(DependantInterface::class)
        );

        /** @var DependantInterface */
        $dependant = $container->get(DependantInterface::class);

        $this->assertInstanceOf(Dependant::class, $dependant);
        $this->assertInstanceOf(Terminus::class, $dependant->dependency());
    }

    /**
     * Tests the class instantiation using a function (callable factory).
     *
     * This mapping is equivalent to a standalone factory, but the class
     * creation is made inline.
     *
     * DependantInterface -(maps to)-> function
     * function -(makes)-> Dependant
     * TerminusInterface -(is injected into)-> function
     * TerminusInterface -(maps to)-> Terminus
     *
     * As a result:
     *
     * Terminus -(is injected into)-> Dependant
     */
    public function testFunctionFactory(): void
    {
        $container = $this->createContainer([
            TerminusInterface::class => Terminus::class,
            DependantInterface::class => fn (TerminusInterface $t) => new Dependant($t),
        ]);

        $this->assertTrue(
            $container->has(DependantInterface::class)
        );

        /** @var DependantInterface */
        $dependant = $container->get(DependantInterface::class);

        $this->assertInstanceOf(Dependant::class, $dependant);
        $this->assertInstanceOf(Terminus::class, $dependant->dependency());
    }

    /**
     * This test checks that:
     * - An untyped parameter can be resolved using a custom resolver.
     * - A string key can be resoved even if it's not a class/interface name.
     *
     * TerminusInterface -(maps to)-> function with an untyped param
     * function -(resolves)-> aaa
     * aaa -(maps to)-> Terminus
     *
     * As a result:
     *
     * TerminusInterface -(maps to)-> Terminus
     */
    public function testParamResolver(): void
    {
        // add a resolver for the untyped `$container` param
        $this->autowirer->withUntypedParamResolver(
            new UntypedContainerParamResolver()
        );

        $outerContainer = $this->createContainer([
            TerminusInterface::class => fn ($container) => $container->get('aaa'),
            'aaa' => Terminus::class,
        ]);

        $this->assertInstanceOf(
            Terminus::class,
            $outerContainer->get(TerminusInterface::class)
        );
    }

    /**
     * Tests that an alias works.
     *
     * Usually it's used for interface1 -> interface2 mapping.
     *
     * aaa -(maps to)-> stdClass
     * bbb -(is aliased to)-> aaa
     * ccc -(resolves as)-> bbb
     *
     * As a result:
     *
     * aaa, bbb and ccc are resolved as the same object.
     */
    public function testAlias(): void
    {
        $container = $this->createContainer([
            'aaa' => new stdClass(),
            'bbb' => 'aaa',
            'ccc' => fn (ContainerInterface $c) => $c->get('bbb'), // equivalent to 'ccc' => 'bbb'
        ]);

        $aaa = $container->get('aaa');
        $bbb = $container->get('bbb');
        $ccc = $container->get('ccc');

        // all keys are resolved as the same object
        $this->assertSame($aaa, $bbb);
        $this->assertSame($bbb, $ccc);
        $this->assertSame($ccc, $aaa);
    }

    /**
     * Tests mapping to a concrete object.
     */
    public function testObject(): void
    {
        $container = $this->createContainer([
            'a' => new Dependant(new Terminus()),
            'b' => new DependantFactory(),
            DependantInterface::class => new Dependant(new Terminus()),
            Dependant::class => new DependantFactoryFactory(),
            TerminusInterface::class => Terminus::class,
        ]);

        // 'a' is just an object
        $this->assertInstanceOf(Dependant::class, $container->get('a'));

        // 'b' is a product of a factory
        $this->assertInstanceOf(Dependant::class, $container->get('b'));

        // just an object
        $this->assertInstanceOf(
            Dependant::class,
            $container->get(DependantInterface::class)
        );

        // the factory factory produces a factory which produces an object
        $this->assertInstanceOf(
            Dependant::class,
            $container->get(Dependant::class)
        );
    }

    public function testFactoryResolvedOrNot(): void
    {
        $container = $this->createContainer([
            TerminusInterface::class => Terminus::class,
            'a' => DependantFactory::class,
            'b' => new DependantFactory(),
            'c' => DependantFactoryFactory::class,
            'd' => new DependantFactoryFactory(),
            DependantInterface::class => DependantFactoryFactory::class,
            Dependant::class => fn () => new DependantFactoryFactory(),
        ]);

        // for string keys (not class or interface names) the callable resolution
        // should be done only *one time* because we do not know what the expected
        // result is
        //
        // if a key is mapped to a concrete object, it is returned as is
        // if a key is mapped to a callable, the callable is resolved once and returned
        //
        // as a result, DependantFactory produces Dependant, but DependantFactoryFactory
        // produces DependantFactory
        $this->assertInstanceOf(Dependant::class, $container->get('a'));
        $this->assertInstanceOf(Dependant::class, $container->get('b'));
        $this->assertInstanceOf(DependantFactory::class, $container->get('c'));
        $this->assertInstanceOf(DependantFactory::class, $container->get('d'));

        // for interfaces and classes the callable chain is resolved until it finds the
        // required class/interface or the object isn't invokable anymore
        $this->assertInstanceOf(
            Dependant::class,
            $container->get(DependantInterface::class)
        );

        $this->assertInstanceOf(
            Dependant::class,
            $container->get(Dependant::class)
        );
    }

    /**
     * Tests that upon a resolution of a chain of invokables the autowirer stops
     * when it finds the requested object and doesn't invoke it further even if the object
     * is invokable itself.
     */
    public function testInvokableIsntInvoked(): void
    {
        $container = $this->createContainer([
            InvokableInterface::class => InvokableFactory::class,
            TerminusInterface::class => Terminus::class,
        ]);

        /** @var InvokableInterface */
        $invokable = $container->get(InvokableInterface::class);

        $this->assertInstanceOf(InvokableInterface::class, $invokable);
        $this->assertInstanceOf(Invokable::class, $invokable);

        $this->assertIsCallable($invokable);

        $result = $this->autowirer->autowireCallable($container, $invokable);

        $this->assertInstanceOf(TerminusInterface::class, $result);
        $this->assertInstanceOf(Terminus::class, $result);
    }

    /**
     * Tests that an exception is thrown when the callable chain is resolved
     * as an incorrect class instance.
     */
    public function testIncorrectInvokable(): void
    {
        $container = $this->createContainer([
            InvokableInterface::class => DependantFactory::class,
            TerminusInterface::class => Terminus::class,
        ]);

        $this->expectException(ContainerExceptionInterface::class);

        $container->get(InvokableInterface::class);
    }
}
