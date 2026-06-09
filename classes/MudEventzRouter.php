<?php

namespace Grav\Plugin\Eventz;

use Grav\Common\Grav;

class MudEventzRouter
{
    private Grav $grav;
    /** @var array<string, mixed> */
    private array $config;
    private string $pluginRoot;

    /** @param array<string, mixed> $config */
    public function __construct(Grav $grav, array $config)
    {
        $this->grav = $grav;
        $this->config = $config;
        $this->pluginRoot = dirname(__DIR__);
    }

    public function handle(): void
    {
        $path = trim((string) $this->grav['uri']->path(), '/');
        $apiRoute = trim((string) ($this->config['api_route'] ?? 'api/mud-eventz'), '/');

        if ($path === $apiRoute || str_starts_with($path, $apiRoute . '/')) {
            return;
        }

        if (!MudEventzSite::matchesPublicPath($path, $this->config)) {
            return;
        }

        $route = MudEventzSite::publicRoute($this->config);
        $rest = $path === $route ? '' : substr($path, strlen($route) + 1);

        if ($rest === 'embed.js') {
            $this->serveText('public/embed.js', 'application/javascript; charset=UTF-8', true);
            exit;
        }

        $assetsPrefix = 'assets/';
        if ($rest === 'assets/mud-eventz.js') {
            $this->serveText('assets/mud-eventz.js', 'application/javascript; charset=UTF-8', true);
            exit;
        }
        if ($rest === 'assets/mud-eventz.css') {
            $this->serveText('assets/mud-eventz.css', 'text/css; charset=UTF-8', true);
            exit;
        }

        if ($rest === 'embed') {
            $this->serveHtml('public/embed.html');
            exit;
        }

        if ($rest === '' || $rest === 'events') {
            $this->serveHtml('public/events.html');
            exit;
        }

        if (preg_match('#^event/([a-z0-9_-]+)$#', $rest, $m)) {
            $this->serveHtml('public/events.html', ['event' => $m[1]]);
            exit;
        }

        if (preg_match('#^series/([a-z0-9_-]+)$#', $rest, $m)) {
            $this->serveHtml('public/events.html', ['series' => $m[1]]);
            exit;
        }

        if (preg_match('#^chapter/([a-z0-9_-]+)$#', $rest, $m)) {
            $this->serveHtml('public/events.html', ['chapter' => $m[1]]);
            exit;
        }
    }

    /** @param array<string, string> $inject */
    private function serveHtml(string $relative, array $inject = []): void
    {
        $file = $this->pluginRoot . '/' . $relative;
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Eventz page not found';
            return;
        }

        $html = (string) file_get_contents($file);
        $html = $this->applyPlaceholders($html, $inject);

        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: public, max-age=120');
        echo $html;
    }

    private function serveText(string $relative, string $mime, bool $cache): void
    {
        $file = $this->pluginRoot . '/' . $relative;
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $mime);
        if ($cache) {
            header('Cache-Control: public, max-age=3600');
        }
        readfile($file);
    }

    /** @param array<string, string> $inject */
    private function applyPlaceholders(string $html, array $inject): string
    {
        $publicBase = MudEventzSite::publicBaseUrl($this->grav, $this->config);
        $api = MudEventzSite::apiBaseUrl($this->grav, $this->config);

        $mode = 'list';
        $event = $inject['event'] ?? '';
        $series = $inject['series'] ?? '';
        $chapter = $inject['chapter'] ?? '';

        if ($event !== '') {
            $mode = 'event';
        } elseif ($chapter !== '') {
            $mode = 'chapter';
        } elseif ($series !== '') {
            $mode = 'series';
        }

        $replacements = [
            '<!-- EVENTZ_PUBLIC_BASE -->' => htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'),
            '<!-- EVENTZ_API -->' => htmlspecialchars($api, ENT_QUOTES, 'UTF-8'),
            '<!-- EVENTZ_MODE -->' => htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'),
            '<!-- EVENTZ_EVENT -->' => htmlspecialchars($event, ENT_QUOTES, 'UTF-8'),
            '<!-- EVENTZ_SERIES -->' => htmlspecialchars($series, ENT_QUOTES, 'UTF-8'),
            '<!-- EVENTZ_CHAPTER -->' => htmlspecialchars($chapter, ENT_QUOTES, 'UTF-8'),
            '<!-- EVENTZ_TITLE -->' => $event !== ''
                ? htmlspecialchars('Event RSVP', ENT_QUOTES, 'UTF-8')
                : htmlspecialchars('Eventz', ENT_QUOTES, 'UTF-8'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
}
