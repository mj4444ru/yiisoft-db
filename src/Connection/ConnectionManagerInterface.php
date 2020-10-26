<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

interface ConnectionManagerInterface
{
    public const DEFAULT_CONNECTION_ID = 'default';

    public function create(string $id): ConnectionInterface;

    public function createMaster(string $id): ConnectionInterface;

    public function createSlave(string $id): ConnectionInterface;

    public function get(string $id = null): ConnectionInterface;

    public function getMaster(string $id = null): ConnectionInterface;

    public function getSlave(string $id = null): ConnectionInterface;
}
