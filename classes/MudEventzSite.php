<?php

namespace Grav\Plugin\GravMudEventz;

use Grav\Common\Grav;

class MudEventzSite
{
    /** @param array<string, mixed> $config */
    public static function supportsGravApiBridge(): bool
    {
        return class_exists(\Grav\Plugin\Api\ApiRouteCollector::class);
    }

    /** @param array<string, mixed> $config */
    public static function apiRoute(array $config): string
    {
        $route = trim((string) ($config['api_route'] ?? 'api/mud-eventz'), '/');
        if (self::supportsGravApiBridge()) {
            return 'api/v1/mud-eventz';
        }

        return $route;
    }

    /** @param array<string, mixed> $config */
    public static function apiPath(array $config): string
    {
        return '/' . self::apiRoute($config);
    }

    /** @param array<string, mixed> $config */
    public static function apiBaseUrl(Grav $grav, array $config): string
    {
        $siteUrl = trim((string) ($config['site_url'] ?? ''));
        $base = $siteUrl !== '' ? rtrim($siteUrl, '/') : rtrim((string) $grav['base_url'], '/');

        return $base . self::apiPath($config);
    }

    /** @param array<string, mixed> $config */
    public static function publicRoute(array $config): string
    {
        return trim((string) ($config['public_route'] ?? 'eventz'), '/');
    }

    /** @param array<string, mixed> $config */
    public static function matchesPublicPath(string $path, array $config): bool
    {
        $route = self::publicRoute($config);
        if ($route === '') {
            return false;
        }

        return $path === $route || str_starts_with($path, $route . '/');
    }

    /** @param array<string, mixed> $config */
    public static function uiPath(array $config, string $suffix = ''): string
    {
        $base = self::publicRoute($config);
        $suffix = trim($suffix, '/');

        return $suffix !== '' ? $base . '/' . $suffix : $base;
    }

    /** @param array<string, mixed> $config */
    public static function publicBaseUrl(Grav $grav, array $config): string
    {
        $siteUrl = trim((string) ($config['site_url'] ?? ''));
        $base = $siteUrl !== '' ? rtrim($siteUrl, '/') : rtrim((string) $grav['base_url'], '/');
        $route = self::publicRoute($config);

        return $route !== '' ? $base . '/' . $route : $base;
    }
}
