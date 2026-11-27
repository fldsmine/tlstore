<?php
/**
 * Generates vendor/autoload.php + vendor/composer/autoload_{psr4,namespaces,classmap,files}.php
 * from composer.json / composer.lock, replacing `composer dump-autoload` (Composer itself
 * cannot be installed in this sandbox because packagist.org is unreachable).
 *
 * Run with: .tools/bin/php .tools/gen-autoload.php
 */

$root = dirname(__DIR__);
$vendor = $root . '/vendor';
$composerDir = $vendor . '/composer';

$installed = json_decode(file_get_contents($composerDir . '/installed.json'), true);
$rootComposer = json_decode(file_get_contents($root . '/composer.json'), true);

$psr4 = [];
$psr0 = [];
$classmap = [];
$files = [];

$addAutoload = function (array $autoload, string $basePath, string $packageName) use (&$psr4, &$psr0, &$classmap, &$files) {
    foreach ($autoload['psr-4'] ?? [] as $ns => $paths) {
        foreach ((array) $paths as $path) {
            $psr4[$ns][] = rtrim($basePath . '/' . $path, '/');
        }
    }
    foreach ($autoload['psr-0'] ?? [] as $ns => $paths) {
        foreach ((array) $paths as $path) {
            $psr0[$ns][] = rtrim($basePath . '/' . $path, '/');
        }
    }
    foreach ($autoload['classmap'] ?? [] as $path) {
        $classmap[] = $basePath . '/' . $path;
    }
    foreach ($autoload['files'] ?? [] as $path) {
        $files[] = $basePath . '/' . $path;
    }
};

foreach ($installed['packages'] as $package) {
    $base = $vendor . '/' . $package['name'];
    $addAutoload($package['autoload'] ?? [], $base, $package['name']);
}

// Root package (project) autoload, dev included.
$addAutoload($rootComposer['autoload'] ?? [], $root, '__root__');
if ($installed['dev'] ?? false) {
    $addAutoload($rootComposer['autoload-dev'] ?? [], $root, '__root__');
}

/** Collects class => file for every class-like declaration inside the given paths. */
function scanClassmap(array $paths): array
{
    $map = [];
    foreach ($paths as $path) {
        $iterator = is_dir($path)
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            )
            : [new SplFileInfo($path)];

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $className = parseClass($file->getPathname());
            if ($className) {
                $map[$className] = $file->getPathname();
            }
        }
    }

    return $map;
}

function parseClass(string $path): ?string
{
    $code = file_get_contents($path);
    if ($code === false) {
        return null;
    }
    $tokens = @token_get_all($code);
    $namespace = '';
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        if ($token[0] === T_NAMESPACE) {
            $namespace = '';
            for ($j = $i + 1; $j < $count; $j++) {
                $t = $tokens[$j];
                if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $namespace .= $t[1];
                    continue;
                }
                break;
            }
            continue;
        }
        if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            // Skip "::class" and anonymous classes.
            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                $prev--;
            }
            if ($prev >= 0 && is_array($tokens[$prev])
                && in_array($tokens[$prev][0], [T_DOUBLE_COLON, T_NEW], true)) {
                continue;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    return $namespace === '' ? $tokens[$j][1] : $namespace . '\\' . $tokens[$j][1];
                }
                break;
            }
        }
    }

    return null;
}

$classmapEntries = scanClassmap($classmap);

// Classmap entries win over PSR-4 lookups in Composer; keep them sorted like Composer does.
ksort($classmapEntries);

$files = array_values(array_unique($files));
// Polyfills must load before anything that may call their functions.
usort($files, function ($a, $b) {
    $aPoly = str_contains($a, '/polyfill') ? 0 : 1;
    $bPoly = str_contains($b, '/polyfill') ? 0 : 1;

    return [$aPoly, $a] <=> [$bPoly, $b];
});

ksort($psr4);
ksort($psr0);

$export = function (array $data): string {
    $out = "<?php\n\n// Generated by .tools/gen-autoload.php. Do not edit.\n\nreturn [\n";
    foreach ($data as $key => $value) {
        $out .= "    " . var_export((string) $key, true) . ' => ' . var_export($value, true) . ",\n";
    }
    return $out . "];\n";
};

file_put_contents($composerDir . '/autoload_psr4.php', $export($psr4));
file_put_contents($composerDir . '/autoload_namespaces.php', $export($psr0));
file_put_contents($composerDir . '/autoload_classmap.php', $export($classmapEntries));
file_put_contents($composerDir . '/autoload_files.php', $export(array_combine(array_map('md5', $files), $files)));

file_put_contents($vendor . '/autoload.php', <<<'PHP'
<?php

// Composer-compatible autoloader generated by .tools/gen-autoload.php.
require_once __DIR__ . '/composer/ClassLoader.php';

$loader = new \Composer\Autoload\ClassLoader(__DIR__);
foreach (require __DIR__ . '/composer/autoload_psr4.php' as $namespace => $paths) {
    $loader->setPsr4($namespace, $paths);
}
foreach (require __DIR__ . '/composer/autoload_namespaces.php' as $namespace => $paths) {
    $loader->set($namespace, $paths);
}
$loader->addClassMap(require __DIR__ . '/composer/autoload_classmap.php');
$loader->register(true);

foreach (require __DIR__ . '/composer/autoload_files.php' as $file) {
    require_once $file;
}

return $loader;
PHP);

printf(
    "autoload written: %d psr-4 prefixes, %d psr-0 prefixes, %d classmap classes, %d files\n",
    count($psr4),
    count($psr0),
    count($classmapEntries),
    count($files)
);
