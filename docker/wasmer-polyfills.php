<?php

// El PHP compilado a WebAssembly de Wasmer no incluye mb_split() (requiere
// soporte mbregex, que ese build no trae). Laravel la usa en Str::studly(),
// Str::title() y Str::headline() con patrones simples, así que la emulamos
// con preg_split antes de que arranque el framework.
if (!function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        return preg_split('/'.$pattern.'/u', $string, $limit);
    }
}
