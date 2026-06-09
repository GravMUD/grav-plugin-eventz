<?php

namespace Grav\Plugin\GravMudEventz;

use Grav\Common\Grav;

class MudEventz
{
    private Grav $grav;
    private MudEventzStorage $storage;
    private bool $bridgeMode = false;
    private int $bridgeHttpCode = 200;
    /** @var array<string, mixed>|null */
    private ?array $jsonBodyOverride = null;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        require_once __DIR__ . '/MudEventzStorage.php';
        require_once __DIR__ . '/MudEventzRsvp.php';
        $this->storage = new MudEventzStorage($grav);
    }

    public function setBridgeMode(bool $enabled): void
    {
        $this->bridgeMode = $enabled;
    }

    public function getBridgeHttpCode(): int
    {
        return $this->bridgeHttpCode;
    }

    /** @param array<string, mixed> $body */
    public function setJsonBodyOverride(array $body): void
    {
        $this->jsonBodyOverride = $body;
    }

    public function handle(string $action): void
    {
        $this->bridgeHttpCode = 200;

        if (!$this->bridgeMode) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('X-Content-Type-Options: nosniff');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            if (!$this->bridgeMode) {
                http_response_code(204);
            }
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $action = trim($action, '/');

        try {
            if ($action === '' || $action === 'events') {
                $this->requireMethod($method, 'GET');
                $this->respond($this->storage->listEvents());
                return;
            }

            if (preg_match('#^event/([a-z0-9_-]+)$#', $action, $m)) {
                $this->requireMethod($method, 'GET');
                $this->respond($this->storage->getEvent($m[1]));
                return;
            }

            if (preg_match('#^rsvp/([a-z0-9_-]+)/summary$#', $action, $m)) {
                $this->requireMethod($method, 'GET');
                $this->respond(MudEventzRsvp::summary($this->grav, $m[1]));
                return;
            }

            if (preg_match('#^rsvp/([a-z0-9_-]+)$#', $action, $m)) {
                $this->requireMethod($method, 'POST');
                $this->respond($this->submitRsvp($m[1]));
                return;
            }

            if (preg_match('#^ics/([a-z0-9_-]+)$#', $action, $m)) {
                $this->requireMethod($method, 'GET');
                $this->serveIcs($m[1]);
                return;
            }

            if (preg_match('#^series/([a-z0-9_-]+)$#', $action, $m)) {
                $this->requireMethod($method, 'GET');
                $this->respond($this->storage->listEventsBySeries($m[1]));
                return;
            }

            if ($action === 'chapters') {
                $this->requireMethod($method, 'GET');
                require_once __DIR__ . '/MudEventzChapters.php';
                $this->respond((new MudEventzChapters($this->grav, $this->storage))->listChapters());
                return;
            }

            if (preg_match('#^chapter/([a-z0-9_-]+)$#', $action, $m)) {
                $this->requireMethod($method, 'GET');
                require_once __DIR__ . '/MudEventzChapters.php';
                $this->respond((new MudEventzChapters($this->grav, $this->storage))->getChapter($m[1], true));
                return;
            }

            if (preg_match('#^chapter/([a-z0-9_-]+)/events$#', $action, $m)) {
                $this->requireMethod($method, 'GET');
                $this->respond($this->storage->listEventsByChapter($m[1]));
                return;
            }

            $this->fail('Not found', 404);
        } catch (\Throwable $e) {
            $code = (int) $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            $this->fail($e->getMessage(), $code);
        }
    }

    /** @return array<string, mixed> */
    private function submitRsvp(string $slug): array
    {
        $payload = $this->storage->getEvent($slug);
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $meta = [
            'title' => (string) ($event['title'] ?? 'Event'),
            'notify_email' => (string) ($event['notify_email'] ?? $this->storage->defaultNotifyEmail()),
            'rsvp_open' => (bool) ($event['rsvp_open'] ?? true),
        ];

        return MudEventzRsvp::submit($this->grav, $slug, $meta, $this->readJsonBody());
    }

    private function serveIcs(string $slug): void
    {
        $payload = $this->storage->getEvent($slug);
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $title = (string) ($event['title'] ?? 'Event');
        $starts = (string) ($event['starts_at'] ?? '');
        $ends = (string) ($event['ends_at'] ?? $starts);
        $location = (string) ($event['venue'] ?? '');
        $desc = (string) ($event['description'] ?? '');
        $uid = $slug . '@gravmud.eventz';

        if (!$this->bridgeMode) {
            header('Content-Type: text/calendar; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $slug . '.ics"');
        }

        $dtStart = $this->icsDate($starts);
        $dtEnd = $this->icsDate($ends !== '' ? $ends : $starts);

        echo "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//GravMUD Eventz//EN\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:{$uid}\r\n"
            . "SUMMARY:" . $this->icsEscape($title) . "\r\n"
            . "DESCRIPTION:" . $this->icsEscape($desc) . "\r\n"
            . "LOCATION:" . $this->icsEscape($location) . "\r\n"
            . ($dtStart !== '' ? "DTSTART:{$dtStart}\r\n" : '')
            . ($dtEnd !== '' ? "DTEND:{$dtEnd}\r\n" : '')
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";
    }

    private function icsDate(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return '';
        }

        return gmdate('Ymd\THis\Z', $ts);
    }

    private function icsEscape(string $value): string
    {
        return str_replace(["\r", "\n", ',', ';'], ['', '\\n', '\\,', '\\;'], $value);
    }

    /** @return array<string, mixed> */
    private function readJsonBody(): array
    {
        if (is_array($this->jsonBodyOverride)) {
            return $this->jsonBodyOverride;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $_POST;
    }

    /** @param array<string, mixed> $payload */
    private function respond(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function fail(string $message, int $code): void
    {
        $this->bridgeHttpCode = $code;
        if (!$this->bridgeMode) {
            http_response_code($code);
        }
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    }

    private function requireMethod(string $actual, string $expected): void
    {
        if ($actual !== $expected) {
            throw new \RuntimeException('Method not allowed', 405);
        }
    }
}
