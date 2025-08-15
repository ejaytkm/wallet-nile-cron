<?php

if (!defined('SWOOLE_HOOK_ALL')) define('SWOOLE_HOOK_ALL', 0xFFFFFF);
if (!defined('SWOOLE_HOOK_NATIVE_CURL')) define('SWOOLE_HOOK_NATIVE_CURL', 0x2000);
function cacheDataFile()
{
    $args = func_get_args();
    $key = array_shift($args);
    $data = array_shift($args);
    $ttl = array_shift($args) ?? 60;

    $cacheFile = __DIR__ . '/../storage/cache/' . md5($key) . '.php';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cacheFile, true);
        }
        return include $cacheFile;
    }

    // Write the data to the cache file
    $cacheContent = '<?php return ' . var_export($data, true) . ';';
    file_put_contents($cacheFile, $cacheContent);

    // Compile the file into OPcache
    if (function_exists('opcache_compile_file')) {
        opcache_compile_file($cacheFile);
    }

    return $data;
}

function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}