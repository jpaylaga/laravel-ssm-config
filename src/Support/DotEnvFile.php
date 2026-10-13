<?php

namespace Jpaylaga\SsmConfig\Support;

/** Renders and updates the lines of a .env file. */
final class DotEnvFile
{
    /**
     * Replaces the lines of the given keys in place and appends the keys that are new,
     * leaving every other line (comments, other variables) untouched.
     *
     * @param  array<string, string>  $values
     */
    public static function update(string $contents, array $values): string
    {
        $lines = $contents === '' ? [] : (preg_split("/\r?\n/", rtrim($contents, "\r\n")) ?: []);
        $pending = $values;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $m) === 1 && array_key_exists($m[1], $pending)) {
                $lines[$i] = self::line($m[1], $pending[$m[1]]);
                unset($pending[$m[1]]);
            }
        }

        foreach ($pending as $key => $value) {
            $lines[] = self::line($key, $value);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /** One KEY=value line, quoted whenever the value would not survive unquoted. */
    public static function line(string $key, string $value): string
    {
        if ($value !== '' && preg_match('/^[A-Za-z0-9_.\/:@+-]+$/', $value) === 1) {
            return "{$key}={$value}";
        }

        return $key.'="'.addcslashes($value, "\\\"\$\n").'"';
    }
}
