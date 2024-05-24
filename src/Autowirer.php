<?php

namespace Plasticode\DI;

use Closure;
use Exception;
use Plasticode\DI\Exceptions\InvalidConfigurationException;
use Plasticode\DI\Interfaces\ParamFactoryResolverInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The automatic object creator (aka "the abstract factory") that uses the container's definitions.
 *
 * Can:
 *
 * - Return a created object.
 * - Return a callable (a factory) that creates an object.
 * - Check if an object can be created.
 */
class Autowirer
{
    /** @var ParamFactoryResolverInterface[] */
    protected array $untypedParamResolvers = [];

    /**
     * @return $this
     */
    public function withUntypedParamResolver(ParamFactoryResolverInterface $resolver): self
    {
        $this->untypedParamResolvers[] = $resolver;
        return $this;
    }

    /**
     * Creates an object based on the container's definitions.
     *
     * - If unable to autowire, throws {@see InvalidConfigurationException}.
     * - If the object's creation fails, throws a generic {@see Exception}.
     *
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function autowire(ContainerInterface $container, string $className): object
    {
        $factory = $this->autoFactory($container, $className);
        return ($factory)($container);
    }

    /**
     * Checks if an object can be created based on the container's definitions.
     */
    public function canAutowire(ContainerInterface $container, string $className): bool
    {
        try {
            // if a factory can't be created, the exception is thrown
            $this->autoFactory($container, $className);
            return true;
        } catch (InvalidConfigurationException $ex) {
            return false;
        }
    }

    /**
     * Creates a callable (a factory) that creates an object based on container's definitions.
     *
     * In case of failure throws {@see InvalidConfigurationException}.
     *
     * @throws InvalidConfigurationException
     */
    public function autoFactory(ContainerInterface $container, string $className): callable
    {
        if (!class_exists($className)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Class %s doesn't exist and can't be autowired.",
                    $className
                )
            );
        }

        $class = new ReflectionClass($className);

        // check for interface & abstract class
        // they can't be instantiated
        if ($class->isAbstract() || $class->isInterface()) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Can't autowire class %s, because it's an interface or an abstract class.",
                    $className
                )
            );
        }

        $constructor = $class->getConstructor();

        // no constructor, just create an object
        if ($constructor === null) {
            return fn (ContainerInterface $c) => $class->newInstanceWithoutConstructor();
        }

        $params = $constructor->getParameters();
        $args = $this->paramAutoFactories($container, $params);

        return fn (ContainerInterface $container) =>
            $class->newInstanceArgs(
                array_map(
                    $this->containerApplicator($container),
                    $args
                )
            );
    }

    /**
     * @param ReflectionParameter[] $params
     * @return object[]
     */
    public function autowireParams(ContainerInterface $container, array $params): array
    {
        return array_map(
            $this->containerApplicator($container),
            $this->paramAutoFactories($container, $params)
        );
    }

    /**
     * @return callable
     */
    private function containerApplicator(ContainerInterface $container)
    {
        return fn (?callable $func) => $func ? ($func)($container) : null;
    }

    /**
     * Resolves the params list based on the container's definitions as callables.
     *
     * @param ReflectionParameter[] $params
     * @return callable[]
     */
    public function paramAutoFactories(ContainerInterface $container, array $params): array
    {
        return array_map(
            fn (ReflectionParameter $param) => $this->paramAutoFactory($container, $param),
            $params
        );
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function paramAutoFactory(
        ContainerInterface $container,
        ReflectionParameter $param
    ): ?callable
    {
        /** @var ReflectionNamedType|null */
        $paramType = $param->getType();

        // no typehint => such a param can't be autowired
        // if it's nullable, set null, otherwise throw an exception
        if ($paramType === null) {
            $factory = $this->untypedParamAutoFactory($container, $param);

            if ($factory !== null) {
                return $factory;
            }

            if ($param->allowsNull()) {
                return null;
            }

            throw new InvalidConfigurationException(
                sprintf(
                    "Can't autowire parameter [%s] \"%s\", provide a typehint or make it nullable.",
                    $param->getPosition(),
                    $param->getName()
                )
            );
        }

        $paramClassName = $paramType->getName();

        // check if the container is able to provide the param
        if ($container->has($paramClassName)) {
            return fn (ContainerInterface $c) => $c->get($paramClassName);
        }

        // or set it to null if it's nullable
        if ($paramType->allowsNull()) {
            return null;
        }

        throw new InvalidConfigurationException(
            sprintf(
                "Can't autowire parameter [%s] \"%s\" of class \"%s\", it can't be found in the container and is not nullable (add it to the container or make nullable).",
                $param->getPosition(),
                $param->getName(),
                $paramClassName
            )
        );
    }

    /**
     * @return mixed
     */
    public function autowireCallable(ContainerInterface $container, callable $callable)
    {
        $closure = Closure::fromCallable($callable);
        $function = new ReflectionFunction($closure);
        $params = $function->getParameters();

        $args = $this->autowireParams($container, $params);

        return ($callable)(...$args);
    }

    protected function untypedParamAutoFactory(
        ContainerInterface $container,
        ReflectionParameter $param
    ): ?callable
    {
        foreach ($this->untypedParamResolvers as $resolver) {
            $factory = ($resolver)($container, $param);

            if ($factory !== null) {
                return $factory;
            }
        }

        return null;
    }
}
