<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

use Psr\Container\ContainerInterface;
use Throwable;
use WeakReference;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Factory\Exceptions\InvalidConfigException as FactoryInvalidConfigException;
use Yiisoft\Factory\Exceptions\NotInstantiableException;
use Yiisoft\Factory\Factory;
use Yiisoft\Factory\FactoryInterface;

use function array_key_exists;
use function get_class;
use function is_array;
use function is_object;

class ConnectionManager implements ConnectionManagerInterface
{
    private array $connections;
    private string $defaultConnection;
    private FactoryInterface $factory;
    private array $items = [];

    /**
     * ConnectionManager constructor.
     *
     * @param array $connections
     * @param FactoryInterface|null $factory
     * @param ContainerInterface|null $container
     *
     * @throws FactoryInvalidConfigException
     * @throws NotInstantiableException
     */
    public function __construct(array $connections, FactoryInterface $factory = null, ContainerInterface $container = null)
    {
        $this->defaultConnection = (string)static::DEFAULT_CONNECTION_ID;
        $this->factory = $factory ?? new Factory($container);
        $this->connections = $connections;
    }

    /**
     * @param string $id
     *
     * @return ConnectionInterface
     *
     * @throws FactoryInvalidConfigException
     * @throws InvalidConfigException
     */
    public function create(string $id): ConnectionInterface
    {
        $config = $this->getConnectionConfig($id);
        $isMaster = empty($config['slave']);
        $config = $isMaster ? $config['master'] : $config['slave'];
        if (empty($config)) {
            throw new InvalidConfigException("Configuration for connection with id \"$id\" in ConnectionManager not found.");
        }

        $conn = $this->factory->create($config);

        if ($conn instanceof ConnectionInterface) {
            $conn->setId($id);
            $conn->setMaster($isMaster ? true : null);

            return $conn;
        }

        $class = is_object($conn) ? get_class($conn) : 'Not Object';
        throw new InvalidConfigException("The ConnectionManager factory returned an object of class \"$class\" for configuration with id \"$id\", which does not support the ConnectionInterface.");
    }

    /**
     * @param string $id
     *
     * @return ConnectionInterface
     *
     * @throws FactoryInvalidConfigException
     * @throws InvalidConfigException
     */
    public function createMaster(string $id): ConnectionInterface
    {
        $config = $this->getConnectionConfig($id);
        if (empty($config['master'])) {
            throw new InvalidConfigException("Configuration for master connection with id \"$id\" in ConnectionManager not found.");
        }

        $conn = $this->factory->create($config['master']);

        if ($conn instanceof ConnectionInterface) {
            $conn->setId($id);
            $conn->setMaster(true);

            return $conn;
        }

        $class = is_object($conn) ? get_class($conn) : 'Not Object';
        throw new InvalidConfigException("The ConnectionManager factory returned an object of class \"$class\" for master configuration with id \"$id\", which does not support the ConnectionInterface.");
    }

    /**
     * @param string $id
     *
     * @return ConnectionInterface
     *
     * @throws FactoryInvalidConfigException
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function createSlave(string $id): ConnectionInterface
    {
        $config = $this->getConnectionConfig($id);
        if (empty($config['slave'])) {
            throw new InvalidArgumentException("Configuration for slave connection with id \"$id\" in ConnectionManager not found.");
        }

        $conn = $this->factory->create($config['slave']);
        if ($conn instanceof ConnectionInterface) {
            $conn->setId($id);
            $conn->setMaster(null);

            return $conn;
        }

        $class = is_object($conn) ? get_class($conn) : 'Not Object';
        throw new InvalidConfigException("The ConnectionManager factory returned an object of class \"$class\" for slave configuration with id \"$id\", which does not support the ConnectionInterface.");
    }

    /**
     * @param string|null $id
     *
     * @return ConnectionInterface
     *
     * @throws Throwable
     */
    public function get(string $id = null): ConnectionInterface
    {
        $id ??= $this->defaultConnection;
        $conn = $this->items["a$id"] ?? null;
        if ($conn !== null) {
            if ($conn instanceof Throwable) {
                throw $conn;
            }

            return $conn;
        }

        $config = $this->getConnectionConfig($id);
        try {
            return $this->items["a$id"] = empty($config['slave']) ? $this->getMaster($id) : $this->getSlave($id);
        } catch (Throwable $e) {
            throw $this->items["s$id"] = $e;
        }
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * @param string|null $id
     *
     * @return ConnectionInterface
     *
     * @throws Throwable
     */
    public function getMaster(string $id = null): ConnectionInterface
    {
        $id ??= $this->defaultConnection;
        $conn = $this->items["m$id"] ?? null;
        if ($conn !== null) {
            if ($conn instanceof Throwable) {
                throw $conn;
            }

            return $conn;
        }

        try {
            return $this->items["m$id"] = $this->createMaster($id);
        } catch (Throwable $e) {
            throw $this->items["m$id"] = $e;
        }
    }

    /**
     * @param string|null $id
     *
     * @return ConnectionInterface
     *
     * @throws Throwable
     */
    public function getSlave(string $id = null): ConnectionInterface
    {
        $id ??= $this->defaultConnection;
        $conn = $this->items["s$id"] ?? null;
        if ($conn !== null) {
            if ($conn instanceof Throwable) {
                throw $conn;
            }

            return $conn;
        }
        try {
            $conn = $this->items["s$id"] = $this->createSlave($id);
            $this->setMasterForSlaveConnection($conn, $id);

            return $conn;
        } catch (Throwable $e) {
            throw $this->items["s$id"] = $e;
        }
    }

    public function setDefaultConnection(string $defaultConnection): void
    {
        $this->defaultConnection = $defaultConnection;
    }

    /**
     * @param string $id
     *
     * @return array
     *
     * @throws InvalidConfigException
     */
    protected function getConnectionConfig(string $id): array
    {
        if (empty($this->connections[$id])) {
            throw new InvalidConfigException("The configuration with id \"$id\" was not found in the ConnectionManager.");
        }

        $conf = $this->connections[$id];
        if (!is_array($conf) || !array_key_exists('master', $conf)) {
            return ['master' => $conf];
        }

        return $conf;
    }

    /**
     * @param ConnectionInterface $conn
     * @param string $id
     *
     * @throws Throwable
     */
    protected function setMasterForSlaveConnection(ConnectionInterface $conn, string $id): void
    {
        if (isset($this->items["m$id"]) && ($this->items["m$id"] instanceof ConnectionInterface)) {
            $conn->setMaster($this->items["m$id"]);
        } else {
            $connManagerWeakRef = WeakReference::create($this);
            $conn->setMaster(function () use ($id, $connManagerWeakRef): ConnectionInterface {
                /** @var self $connManager */
                $connManager = $connManagerWeakRef->get();
                if (isset($connManager)) {
                    return $connManager->getMaster($id);
                }

                throw new Exception("Unable to create master connection with id \"$id\" because ConnectionManager has already been destroyed.");
            });
        }
    }
}
