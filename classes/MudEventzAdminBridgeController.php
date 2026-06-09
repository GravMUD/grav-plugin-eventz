<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudEventz;

use Grav\Common\Config\Config;
use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Response\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MudEventzAdminBridgeController extends AbstractApiController
{
    public const ADMIN_PAGE_SLUG = 'eventz';

    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');

        return ApiResponse::create($this->storage()->stats());
    }

    public function events(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');

        return ApiResponse::create($this->storage()->listEvents());
    }

    public function rsvps(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');
        $slug = (string) ($request->getAttribute('route_params')['slug'] ?? '');

        return ApiResponse::create($this->storage()->listRsvpEntries($slug));
    }

    public function rsvpCsv(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($request->getAttribute('route_params')['slug'] ?? ''))) ?: 'event';

        return ApiResponse::create([
            'ok' => true,
            'filename' => $slug . '-rsvps.csv',
            'csv' => $this->storage()->exportRsvpCsv($slug),
        ]);
    }

    public function setRsvpOpen(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');
        $slug = (string) ($request->getAttribute('route_params')['slug'] ?? '');
        $body = $this->getRequestBody($request);
        $body = is_array($body) ? $body : [];
        $open = !array_key_exists('open', $body) || !empty($body['open']);

        return ApiResponse::create($this->storage()->setRsvpOpen($slug, $open));
    }

    public function saveEvent(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');
        $body = $this->getRequestBody($request);
        if (!is_array($body)) {
            return ApiResponse::create(['ok' => false, 'error' => 'Expected JSON object.'], 422);
        }
        $wire = ($body['wire'] ?? true) !== false;

        return ApiResponse::create($this->storage()->saveEvent($body, $wire));
    }

    public function chapters(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        if ($request->getMethod() === 'GET') {
            $this->requirePermission($request, 'api.access');

            return ApiResponse::create($this->chaptersApi()->listChapters());
        }
        if ($request->getMethod() === 'POST') {
            $this->requirePermission($request, 'api.access');
            $body = $this->getRequestBody($request);
            if (!is_array($body)) {
                return ApiResponse::create(['ok' => false, 'error' => 'Expected JSON object.'], 422);
            }

            return ApiResponse::create($this->chaptersApi()->saveChapter($body));
        }

        return ApiResponse::create(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    public function chapter(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');
        $slug = (string) ($request->getAttribute('route_params')['slug'] ?? '');

        return ApiResponse::create($this->chaptersApi()->getChapter($slug, true));
    }

    public function spawnChapter(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::create(null, 204);
        }
        $this->requirePermission($request, 'api.access');
        $slug = (string) ($request->getAttribute('route_params')['slug'] ?? '');
        $body = $this->getRequestBody($request);
        $options = is_array($body) ? $body : [];

        return ApiResponse::create($this->chaptersApi()->spawnOccurrences($slug, $options));
    }

    /** @return array<string, mixed> */
    public static function pageDefinition(Config $config): array
    {
        return [
            'id' => self::ADMIN_PAGE_SLUG,
            'plugin' => self::ADMIN_PAGE_SLUG,
            'title' => 'Eventz',
            'icon' => 'fa-calendar-days',
            'page_type' => 'component',
            'has_custom_component' => true,
        ];
    }

    private function storage(): MudEventzStorage
    {
        require_once __DIR__ . '/MudEventzRsvp.php';
        require_once __DIR__ . '/MudEventzStorage.php';

        return new MudEventzStorage($this->grav);
    }

    private function chaptersApi(): MudEventzChapters
    {
        require_once __DIR__ . '/MudEventzChapters.php';
        require_once __DIR__ . '/MudEventzRecurrence.php';

        return new MudEventzChapters($this->grav, $this->storage());
    }
}
