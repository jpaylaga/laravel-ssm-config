<?php

namespace Jpaylaga\SsmConfig\Support;

use InvalidArgumentException;

/** The Parameter Store path a host reads from, e.g. "/app/tenant-a". */
final class ParameterPath
{
    private string $path;

    public function __construct(?string $path)
    {
        $path = rtrim((string) $path, '/');

        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('ssm.path must be an absolute Parameter Store path such as /app/tenant-a.');
        }

        $this->path = $path;
    }

    /** The parameter name relative to this path: "/app/tenant-a/DB_PASSWORD" becomes "DB_PASSWORD". */
    public function nameOf(string $fullName): string
    {
        $prefix = $this->path.'/';

        return str_starts_with($fullName, $prefix) ? substr($fullName, strlen($prefix)) : ltrim($fullName, '/');
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
