<?php

namespace App\Application\Settings;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;

/**
 * Minimal, format-preserving reader/writer for the deployment's `.env` file.
 *
 * Only the lines belonging to the requested keys are rewritten: comments,
 * ordering and every other variable are left exactly as they were. Values are
 * written with the same quoting rules Laravel's dotenv parser understands, and
 * the previous file is kept next to it as `.env.bak`.
 */
final class EnvironmentFile
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function backupPath(): string
    {
        return $this->path.'.bak';
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function isWritable(): bool
    {
        return $this->exists()
            && is_writable($this->path)
            && is_writable(dirname($this->path));
    }

    public function contents(): string
    {
        return $this->exists() ? (string) file_get_contents($this->path) : '';
    }

    /**
     * Parsed values, exactly as Laravel would see them.
     *
     * Values that Laravel resolves to `null` (`KEY=`, `KEY=null`) are returned
     * as `null`, so the console reports them as "not set" instead of showing a
     * placeholder as if it were a credential.
     *
     * @return array<string, string|null>
     *
     * @throws InvalidFileException when the file cannot be parsed
     */
    public function values(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $values = [];

        // Laravel's own parser, so quoting, escaping and ${VAR} interpolation
        // resolve exactly as they do at boot.
        foreach (Dotenv::parse($this->contents()) as $key => $value) {
            $value = trim((string) $value);

            $values[$key] = ($value === '' || in_array(mb_strtolower($value), ['null', '(null)'], true))
                ? null
                : $value;
        }

        return $values;
    }

    public function get(string $key): ?string
    {
        return $this->values()[$key] ?? null;
    }

    /**
     * Write or remove variables.
     *
     * @param  array<string, string|null>  $values  `null` removes the line
     * @return list<string> keys whose line was written or removed
     */
    public function put(array $values): array
    {
        if ($values === []) {
            return [];
        }

        if (! $this->exists()) {
            throw new EnvironmentFileException('No .env file was found at '.$this->path.'. This deployment configures its environment another way, so credentials cannot be stored here.');
        }

        if (! $this->isWritable()) {
            throw new EnvironmentFileException('The .env file at '.$this->path.' is not writable by the web server. Fix the file permissions to manage credentials here.');
        }

        $lines = preg_split('/\r\n|\n|\r/', $this->contents()) ?: [];
        $pending = $values;
        $changed = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];
            if (! array_key_exists($key, $pending)) {
                continue;
            }

            $value = $pending[$key];
            $lines[$index] = $value === null ? null : $key.'='.$this->format($value);
            $changed[] = $key;
            unset($pending[$key]);
        }

        foreach ($pending as $key => $value) {
            // Removing a variable that is already absent is a no-op.
            if ($value === null) {
                continue;
            }

            $lines[] = $key.'='.$this->format($value);
            $changed[] = $key;
        }

        if ($changed === []) {
            return [];
        }

        $contents = implode("\n", array_values(array_filter($lines, static fn (?string $line): bool => $line !== null)));
        if ($contents !== '' && ! str_ends_with($contents, "\n")) {
            $contents .= "\n";
        }

        $this->write($contents);

        return $changed;
    }

    /** Quote a value the way dotenv expects, keeping simple values bare. */
    public function format(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Bare values must not contain whitespace, quotes, `#`, `$` or `=`,
        // otherwise the parser would trim, comment or interpolate them.
        if (preg_match('/^[A-Za-z0-9_\-.:\/@+*,]+$/', $value) === 1) {
            return $value;
        }

        // Double quotes are the only form dotenv can escape: single-quoted
        // values cannot contain a quote at all, and unquoted values stop at
        // whitespace or `#`.
        $escaped = str_replace(
            ['\\', '"', '$', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\$', '\\n', '\\r', '\\t'],
            $value,
        );

        return '"'.$escaped.'"';
    }

    private function write(string $contents): void
    {
        $directory = dirname($this->path);
        $temporary = tempnam($directory, '.env.tmp');
        if ($temporary === false) {
            throw new EnvironmentFileException('Could not create a temporary file next to '.$this->path.'.');
        }

        try {
            if (file_put_contents($temporary, $contents) === false) {
                throw new EnvironmentFileException('Could not write the new environment file.');
            }

            @chmod($temporary, fileperms($this->path) & 0777);
            @copy($this->path, $this->backupPath());

            if (! rename($temporary, $this->path)) {
                throw new EnvironmentFileException('Could not replace '.$this->path.' with the updated environment file.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
