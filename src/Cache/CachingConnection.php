<?php

declare(strict_types=1);

namespace Jurager\Filterable\Cache;

use BadMethodCallException;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;

class CachingConnection implements ConnectionInterface
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly array $tags,
        private readonly int $ttl,
    ) {
    }

    /** Run a select statement, caching the result by SQL and bindings. */
    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        $key = 'filterable:'.hash('xxh3', serialize([$query, $bindings]));

        try {
            return Cache::tags($this->tags)->remember(
                $key,
                $this->ttl,
                fn () => $this->connection->select($query, $bindings, $useReadPdo, $fetchUsing),
            );
        } catch (BadMethodCallException) {
            // Ignore drivers that do not support tags and execute directly
            return $this->connection->select($query, $bindings, $useReadPdo, $fetchUsing);
        }
    }

    public function table($table, $as = null)
    {
        return $this->connection->table($table, $as);
    }

    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        return $this->connection->selectOne($query, $bindings, $useReadPdo);
    }

    public function scalar($query, $bindings = [], $useReadPdo = true)
    {
        return $this->connection->scalar($query, $bindings, $useReadPdo);
    }

    public function cursor($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        return $this->connection->cursor($query, $bindings, $useReadPdo, $fetchUsing);
    }

    public function insert($query, $bindings = [])
    {
        return $this->connection->insert($query, $bindings);
    }

    public function update($query, $bindings = [])
    {
        return $this->connection->update($query, $bindings);
    }

    public function delete($query, $bindings = [])
    {
        return $this->connection->delete($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        return $this->connection->statement($query, $bindings);
    }

    public function affectingStatement($query, $bindings = [])
    {
        return $this->connection->affectingStatement($query, $bindings);
    }

    public function unprepared($query)
    {
        return $this->connection->unprepared($query);
    }

    public function prepareBindings(array $bindings)
    {
        return $this->connection->prepareBindings($bindings);
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        return $this->connection->transaction($callback, $attempts);
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    public function transactionLevel()
    {
        return $this->connection->transactionLevel();
    }

    public function pretend(Closure $callback)
    {
        return $this->connection->pretend($callback);
    }

    public function getDatabaseName()
    {
        return $this->connection->getDatabaseName();
    }

    /** Proxy anything outside the formal interface (grammar, processor, PDO accessors, ...). */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection->{$method}(...$parameters);
    }
}
