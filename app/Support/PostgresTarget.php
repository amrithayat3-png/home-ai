<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Connection details for the PostgreSQL command line tools (pg_dump, pg_restore).
 * The password travels in an environment variable, never in the command line.
 */
class PostgresTarget
{
    /**
     * @param  array<string, string>  $env
     */
    public function __construct(
        public readonly array $env,
        public readonly string $host,
        public readonly string $database,
    ) {}

    public static function fromConnection(string $name): self
    {
        $config = config("database.connections.{$name}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException("The database connection '{$name}' is not PostgreSQL, so it cannot be backed up with pg_dump.");
        }

        $url = $config['url'] ?? null;

        if (is_string($url) && $url !== '') {
            return self::fromUrl($url, $config['sslmode'] ?? null);
        }

        return self::make(
            (string) ($config['host'] ?? '127.0.0.1'),
            (string) ($config['port'] ?? '5432'),
            (string) ($config['database'] ?? ''),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            $config['sslmode'] ?? null,
        );
    }

    public static function fromUrl(string $url, ?string $sslmode = null): self
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new InvalidArgumentException('The database URL is not valid. Expected postgresql://user:password@host:port/database');
        }

        parse_str($parts['query'] ?? '', $query);

        return self::make(
            $parts['host'],
            (string) ($parts['port'] ?? '5432'),
            urldecode(ltrim($parts['path'], '/')),
            urldecode($parts['user'] ?? ''),
            urldecode($parts['pass'] ?? ''),
            is_string($query['sslmode'] ?? null) ? $query['sslmode'] : $sslmode,
        );
    }

    private static function make(string $host, string $port, string $database, string $user, string $password, ?string $sslmode): self
    {
        if ($database === '') {
            throw new InvalidArgumentException('The database name is missing from the connection settings.');
        }

        return new self([
            'PGHOST' => $host,
            'PGPORT' => $port,
            'PGUSER' => $user,
            'PGPASSWORD' => $password,
            'PGDATABASE' => $database,
            'PGSSLMODE' => $sslmode ?: 'prefer',
        ], $host, $database);
    }
}
