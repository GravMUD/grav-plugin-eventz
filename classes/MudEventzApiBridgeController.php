<?php

declare(strict_types=1);

namespace Grav\Plugin\GravMudEventz;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MudEventzApiBridgeController
{
    public function __construct(
        protected readonly Grav $grav,
        protected readonly Config $config,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response(204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ]);
        }

        $_SERVER['REQUEST_METHOD'] = $request->getMethod();

        parse_str($request->getUri()->getQuery(), $query);
        foreach ($query as $key => $value) {
            if (is_string($key)) {
                $_GET[$key] = $value;
            }
        }

        $params = $request->getAttribute('route_params', []);
        $action = isset($params['subpath']) ? trim((string) $params['subpath'], '/') : '';

        require_once __DIR__ . '/MudEventz.php';
        $eventz = new MudEventz($this->grav);
        $eventz->setBridgeMode(true);

        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            $eventz->setJsonBodyOverride($parsed);
        } else {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $eventz->setJsonBodyOverride($decoded);
                }
            }
        }

        $level = ob_get_level();
        ob_start();
        try {
            $eventz->handle($action);
        } finally {
            $output = (string) ob_get_clean();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        $code = $eventz->getBridgeHttpCode();
        if ($output === '') {
            return new Response($code >= 400 ? $code : 204, ['Access-Control-Allow-Origin' => '*']);
        }

        $contentType = str_starts_with(trim($output), 'BEGIN:VCALENDAR')
            ? 'text/calendar; charset=UTF-8'
            : 'application/json; charset=UTF-8';

        return new Response($code, [
            'Content-Type' => $contentType,
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ], $output);
    }
}
