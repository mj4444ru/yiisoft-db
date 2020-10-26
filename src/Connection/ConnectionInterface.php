<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

use Throwable;
use Yiisoft\Db\Command\Command;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Schema\TableSchema;

interface ConnectionInterface
{
    /**
     * Creates a command for execution.
     *
     * @param string|null $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     *
     * @return Command the DB command
     * @throws InvalidConfigException
     *
     * @throws Exception
     */
    public function createCommand(?string $sql = null, array $params = []): Command;

    /**
     * Returns the name of the DB driver.
     *
     * @return string name of the DB driver
     */
    public function getDriverName(): string;

    /**
     * @return string the Data Source Name, or DSN, contains the information required to connect to the database.
     *
     * Please refer to the [PHP manual](https://secure.php.net/manual/en/pdo.construct.php) on the format of the DSN
     * string.
     *
     * For [SQLite](https://secure.php.net/manual/en/ref.pdo-sqlite.connection.php) you may use a
     * [path alias](guide:concept-aliases) for specifying the database path, e.g. `sqlite:@app/data/db.sql`.
     *
     * {@see charset}
     */
    public function getDsn(): string;

    /**
     * Returns the schema information for the database opened by this connection.
     *
     * @return Schema the schema information for the database opened by this connection.
     */
    public function getSchema(): Schema;

    /**
     * Returns a server version as a string comparable by {@see \version_compare()}.
     *
     * @return string server version as a string.
     */
    public function getServerVersion(): string;

    /**
     * Obtains the schema information for the named table.
     *
     * @param string $name table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     *
     * @return TableSchema|null
     */
    public function getTableSchema(string $name, $refresh = false): ?TableSchema;

    /**
     * Establishes a master connection or indicates to a connection that it is a master connection.
     * True - This is the master connection.
     * Null - The master connection is not available, trying to get it will throw an exception.
     * Object - A previously created master connection.
     * Callback function - A function that creates a master connection.
     *
     * @param true|self|callable|null $value
     *
     * @return void
     */
    public function setMaster($value): void;

    /**
     * Returns the master connection.
     * Returns itself if itself is the master connection. If it is impossible to return the master connection, an exception is thrown.
     *
     * @return self
     *
     * @throws Throwable
     */
    public function getMaster(): self;
}
