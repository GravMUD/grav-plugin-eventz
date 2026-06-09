<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Plugin\GravMudEventz\MudEventzAdminBridgeController;
use Grav\Plugin\GravMudEventz\MudEventzApiBridgeController;
use Grav\Plugin\GravMudEventz\MudEventzRouter;
use Grav\Plugin\GravMudEventz\MudEventzSite;
use RocketTheme\Toolbox\Event\Event;

class GravMudEventzPlugin extends Plugin
{
    public const ADMIN_PAGE_SLUG = 'eventz';

    public static function getSubscribedEvents(): array
    {
        $events = [
            'onPluginsInitialized' => [['onPluginsInitializedEarly', 100000]],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onPageNotFound' => ['onPagesInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onMudFenceRender' => ['onMudFenceRender', 0],
        ];

        if (self::supportsGravApiBridge()) {
            $events['onApiRegisterRoutes'] = ['onApiRegisterRoutes', 0];
            $events['onApiCollectPublicRoutes'] = ['onApiCollectPublicRoutes', 0];
            $events['onApiSidebarItems'] = ['onApiSidebarItems', 0];
            $events['onApiPluginPageInfo'] = ['onApiPluginPageInfo', 0];
        }

        return $events;
    }

    public function onPluginsInitializedEarly(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        require_once __DIR__ . '/classes/MudEventzSite.php';
        require_once __DIR__ . '/classes/MudEventzRouter.php';

        if (self::supportsGravApiBridge()) {
            require_once __DIR__ . '/classes/MudEventzApiBridgeController.php';
            require_once __DIR__ . '/classes/MudEventzAdminBridgeController.php';
            require_once __DIR__ . '/classes/MudEventz.php';
        }

        $cfg = (array) $this->grav['config']->get('plugins.grav-mud-eventz', []);
        $path = trim((string) $this->grav['uri']->path(), '/');

        if (MudEventzSite::matchesPublicPath($path, $cfg)) {
            (new MudEventzRouter($this->grav, $cfg))->handle();
        }
    }

    public function onPagesInitialized(): void
    {
        if (!$this->isEnabled() || $this->isAdmin()) {
            return;
        }

        $cfg = (array) $this->grav['config']->get('plugins.grav-mud-eventz', []);
        require_once __DIR__ . '/classes/MudEventzSite.php';
        require_once __DIR__ . '/classes/MudEventzRouter.php';
        (new MudEventzRouter($this->grav, $cfg))->handle();

        $action = $this->apiAction();
        if ($action === null) {
            return;
        }

        if (class_exists(\Grav\Plugin\Api\ApiRouteCollector::class)) {
            return;
        }

        require_once __DIR__ . '/classes/MudEventz.php';
        (new \Grav\Plugin\GravMudEventz\MudEventz($this->grav))->handle($action);
        exit;
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
        if (!$this->isEnabled() || !in_array($plugin, [self::ADMIN_PAGE_SLUG, 'grav-mud-eventz'], true)) {
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

        return (bool) ($user->get('access.api.access') || $user->get('access.api.system.read'));
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

        $cfg = (array) $this->grav['config']->get('plugins.grav-mud-eventz', []);
        $route = MudEventzSite::apiRoute($cfg);
        $base = rtrim((string) $this->grav['base_url'], '/');
        $publicRoute = MudEventzSite::publicRoute($cfg);

        $this->grav['twig']->twig_vars['grav_mud_eventz'] = [
            'enabled' => true,
            'name' => 'GravMUD Eventz',
            'version' => '0.3.0',
            'api_route' => $route,
            'api' => MudEventzSite::apiBaseUrl($this->grav, $cfg),
            'public_route' => $publicRoute,
            'url' => MudEventzSite::publicBaseUrl($this->grav, $cfg),
            'embedScript' => MudEventzSite::publicBaseUrl($this->grav, $cfg) . '/embed.js',
        ];
    }

    private function isEnabled(): bool
    {
        return (bool) $this->grav['config']->get('plugins.grav-mud-eventz.enabled', false);
    }

    /** @param Event $event */
    public function onMudFenceRender(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        require_once __DIR__ . '/classes/MudEventzFences.php';

        $html = \Grav\Plugin\GravMudEventz\MudEventzFences::render(
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

    private function apiAction(): ?string
    {
        $route = trim((string) $this->grav['config']->get('plugins.grav-mud-eventz.api_route', 'api/mud-eventz'), '/');
        $path = trim((string) $this->grav['uri']->path(), '/');

        if ($path === $route) {
            return '';
        }

        if (!str_starts_with($path, $route . '/')) {
            return null;
        }

        return trim(substr($path, strlen($route)), '/');
    }

    private static function supportsGravApiBridge(): bool
    {
        return class_exists(\Grav\Plugin\Api\ApiRouteCollector::class);
    }
}
