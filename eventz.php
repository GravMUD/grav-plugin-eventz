<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Plugin\Eventz\MudEventzAdminBridgeController;
use Grav\Plugin\Eventz\MudEventzApiBridgeController;
use Grav\Plugin\Eventz\MudEventzFences;
use Grav\Plugin\Eventz\MudEventzRouter;
use Grav\Plugin\Eventz\MudEventzSite;
use RocketTheme\Toolbox\Event\Event;

class EventzPlugin extends Plugin
{
    public const ADMIN_PAGE_SLUG = 'eventz';

    public static function getSubscribedEvents(): array
    {
        $events = [
            'onPluginsInitializedEarly' => [
                ['interceptPublicApi', 100001],
                ['onPluginsInitializedEarly', 100000],
            ],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onPageNotFound' => ['onPagesInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onMudFenceRender' => ['onMudFenceRender', 0],
        ];

        $events['onApiRegisterRoutes'] = ['onApiRegisterRoutes', 0];
        $events['onApiCollectPublicRoutes'] = ['onApiCollectPublicRoutes', 0];
        $events['onApiSidebarItems'] = ['onApiSidebarItems', 0];
        $events['onApiPluginPageInfo'] = ['onApiPluginPageInfo', 0];

        return $events;
    }

    /** @return array<string, mixed> */
    public static function pluginConfig($grav): array
    {
        if (!is_object($grav) || !isset($grav['config'])) {
            return [];
        }

        return (array) $grav['config']->get('plugins.eventz', []);
    }

    public function onPluginsInitializedEarly(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        require_once __DIR__ . '/classes/MudEventzSite.php';
        require_once __DIR__ . '/classes/MudEventzRouter.php';
        require_once __DIR__ . '/classes/MudEventz.php';

        if (self::supportsGravApiBridge()) {
            require_once __DIR__ . '/classes/MudEventzApiBridgeController.php';
            require_once __DIR__ . '/classes/MudEventzAdminBridgeController.php';
        }

        $cfg = self::pluginConfig($this->grav);
        $path = trim((string) $this->grav['uri']->path(), '/');

        if (MudEventzSite::matchesPublicPath($path, $cfg)) {
            (new MudEventzRouter($this->grav, $cfg))->handle();
        }
    }

    /** Direct public JSON — bypass Grav API middleware when /api/v1/* bridge fails. */
    public function interceptPublicApi(): void
    {
        if (!$this->isEnabled() || $this->isAdmin()) {
            return;
        }

        $cfg = self::pluginConfig($this->grav);
        $legacy = trim((string) ($cfg['api_route'] ?? 'api/mud-eventz'), '/');
        $prefixes = array_values(array_unique([$legacy, MudEventzSite::apiRoute($cfg)]));
        $path = trim((string) $this->grav['uri']->path(), '/');

        foreach ($prefixes as $apiPrefix) {
            if ($apiPrefix === '' || ($path !== $apiPrefix && !str_starts_with($path, $apiPrefix . '/'))) {
                continue;
            }

            require_once __DIR__ . '/classes/MudEventz.php';
            $action = $path === $apiPrefix ? '' : trim(substr($path, strlen($apiPrefix)), '/');
            (new MudEventz($this->grav))->handle($action);
            exit;
        }
    }

    public function onPagesInitialized(): void
    {
        if (!$this->isEnabled() || $this->isAdmin()) {
            return;
        }

        $cfg = self::pluginConfig($this->grav);
        require_once __DIR__ . '/classes/MudEventzSite.php';
        require_once __DIR__ . '/classes/MudEventzRouter.php';
        (new MudEventzRouter($this->grav, $cfg))->handle();
    }

    public function onApiRegisterRoutes(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        require_once __DIR__ . '/classes/MudEventzApiBridgeController.php';
        require_once __DIR__ . '/classes/MudEventzAdminBridgeController.php';

        $routes = $event['routes'];
        $controller = [MudEventzApiBridgeController::class, 'handle'];
        $admin = MudEventzAdminBridgeController::class;

        $routes->addRoute(['GET', 'OPTIONS'], '/eventz/admin/stats', [$admin, 'stats']);
        $routes->addRoute(['GET', 'OPTIONS'], '/eventz/admin/events', [$admin, 'events']);
        $routes->addRoute(['POST', 'OPTIONS'], '/eventz/admin/event', [$admin, 'saveEvent']);
        $routes->addRoute(['GET', 'POST', 'OPTIONS'], '/eventz/admin/chapters', [$admin, 'chapters']);
        $routes->addRoute(['GET', 'OPTIONS'], '/eventz/admin/chapters/{slug}', [$admin, 'chapter']);
        $routes->addRoute(['POST', 'OPTIONS'], '/eventz/admin/chapters/{slug}/spawn', [$admin, 'spawnChapter']);
        $routes->addRoute(['GET', 'OPTIONS'], '/eventz/admin/rsvps/{slug}', [$admin, 'rsvps']);
        $routes->addRoute(['GET', 'OPTIONS'], '/eventz/admin/rsvps/{slug}/csv', [$admin, 'rsvpCsv']);
        $routes->addRoute(['POST', 'OPTIONS'], '/eventz/admin/event/{slug}/rsvp-open', [$admin, 'setRsvpOpen']);

        $routes->addRoute(['GET', 'POST', 'OPTIONS'], '/mud-eventz', $controller);
        $routes->addRoute(['GET', 'POST', 'OPTIONS'], '/mud-eventz/{subpath:.+}', $controller);
    }

    public function onApiSidebarItems(Event $event): void
    {
        if (!$this->isEnabled() || !$this->canUseAdmin2($event['user'] ?? null)) {
            return;
        }

        $items = $event['items'] ?? [];
        $items[] = [
            'id' => self::ADMIN_PAGE_SLUG,
            'plugin' => self::ADMIN_PAGE_SLUG,
            'label' => 'Eventz',
            'icon' => 'fa-calendar-days',
            'route' => '/plugin/' . self::ADMIN_PAGE_SLUG,
            'priority' => 84,
        ];
        $event['items'] = $items;
    }

    public function onApiPluginPageInfo(Event $event): void
    {
        $plugin = (string) ($event['plugin'] ?? '');
        if (!$this->isEnabled() || $plugin !== self::ADMIN_PAGE_SLUG) {
            return;
        }

        if (!$this->canUseAdmin2($event['user'] ?? null)) {
            return;
        }

        $event['definition'] = MudEventzAdminBridgeController::pageDefinition($this->grav['config']);
    }

    /** @param mixed $user */
    private function canUseAdmin2($user): bool
    {
        if (!$user || !is_object($user) || !method_exists($user, 'get')) {
            return false;
        }

        if ($user->get('access.api.super')) {
            return true;
        }

        return (bool) ($user->get('access.api.config.read') || $user->get('access.api.config.write'));
    }

    public function onApiCollectPublicRoutes(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $apiBase = (string) ($event['api_base'] ?? '/api/v1');
        $prefixes = (array) ($event['prefixes'] ?? []);
        $prefixes[] = rtrim($apiBase, '/') . '/mud-eventz';
        $event['prefixes'] = $prefixes;
    }

    public function onTwigSiteVariables(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $cfg = self::pluginConfig($this->grav);
        $route = MudEventzSite::apiRoute($cfg);
        $publicRoute = MudEventzSite::publicRoute($cfg);

        $this->grav['twig']->twig_vars['eventz'] = [
            'enabled' => true,
            'name' => 'Eventz',
            'version' => '0.4.2',
            'api_route' => $route,
            'api' => MudEventzSite::apiBaseUrl($this->grav, $cfg),
            'public_route' => $publicRoute,
            'url' => MudEventzSite::publicBaseUrl($this->grav, $cfg),
            'embedScript' => MudEventzSite::publicBaseUrl($this->grav, $cfg) . '/embed.js',
        ];
        $this->grav['twig']->twig_vars['grav_mud_eventz'] = $this->grav['twig']->twig_vars['eventz'];
    }

    private function isEnabled(): bool
    {
        $cfg = self::pluginConfig($this->grav);

        return (bool) ($cfg['enabled'] ?? false);
    }

    /** @param Event $event */
    public function onMudFenceRender(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        require_once __DIR__ . '/classes/MudEventzFences.php';

        $html = MudEventzFences::render(
            strtolower((string) ($event['type'] ?? '')),
            (array) ($event['node'] ?? []),
            (array) ($event['attrs'] ?? []),
            (string) ($event['body'] ?? ''),
            (array) ($event['data'] ?? [])
        );

        if ($html !== null && $html !== '') {
            $event['html'] = $html;
        }
    }

    private static function supportsGravApiBridge(): bool
    {
        return class_exists(\Grav\Plugin\Api\ApiRouteCollector::class);
    }
}
