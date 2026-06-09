<?php

namespace Grav\Plugin\GravMudEventz;

use Grav\Common\Grav;

class MudEventzStorage
{
    private Grav $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /** @return array<string, mixed> */
    public function listEvents(): array
    {
        $events = [];
        foreach ($this->eventFiles() as $file) {
            $event = $this->readEventFile($file);
            if ($event === null) {
                continue;
            }
            $slug = (string) ($event['slug'] ?? basename($file, '.json'));
            $event['rsvp'] = MudEventzRsvp::summary($this->grav, $slug);
            $events[] = $event;
        }

        usort($events, static function ($a, $b) {
            return strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? ''));
        });

        return ['ok' => true, 'events' => $events];
    }

    /** @return array<string, mixed> */
    public function listEventsBySeries(string $series): array
    {
        $series = $this->normalizeSeries($series);
        $events = [];
        foreach ($this->eventFiles() as $file) {
            $event = $this->readEventFile($file);
            if ($event === null) {
                continue;
            }
            if ($this->normalizeSeries((string) ($event['series'] ?? '')) !== $series) {
                continue;
            }
            $slug = (string) ($event['slug'] ?? basename($file, '.json'));
            $event['rsvp'] = MudEventzRsvp::summary($this->grav, $slug);
            $events[] = $event;
        }

        usort($events, static function ($a, $b) {
            return strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? ''));
        });

        return ['ok' => true, 'series' => $series, 'events' => $events];
    }

    /** @return array<string, mixed> */
    public function listEventsByChapter(string $chapter, bool $withRsvp = true): array
    {
        $chapter = MudEventzRsvp::normalizeSlug($chapter);
        $events = [];
        foreach ($this->eventFiles() as $file) {
            $event = $this->readEventFile($file);
            if ($event === null) {
                continue;
            }
            if (MudEventzRsvp::normalizeSlug((string) ($event['chapter'] ?? '')) !== $chapter) {
                continue;
            }
            $slug = (string) ($event['slug'] ?? basename($file, '.json'));
            if ($withRsvp) {
                $event['rsvp'] = MudEventzRsvp::summary($this->grav, $slug);
            }
            $events[] = $event;
        }

        usort($events, static function ($a, $b) {
            return strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? ''));
        });

        return ['ok' => true, 'chapter' => $chapter, 'events' => $events];
    }

    public function eventExists(string $slug): bool
    {
        return is_file($this->eventPath(MudEventzRsvp::normalizeSlug($slug)));
    }

    /** @return array<string, mixed> */
    public function getEvent(string $slug): array
    {
        $slug = MudEventzRsvp::normalizeSlug($slug);
        $file = $this->eventPath($slug);
        if (!is_file($file)) {
            throw new \RuntimeException('Event not found', 404);
        }

        $event = $this->readEventFile($file);
        if ($event === null) {
            throw new \RuntimeException('Invalid event file', 500);
        }

        $event['rsvp'] = MudEventzRsvp::summary($this->grav, $slug);

        return ['ok' => true, 'event' => $event];
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    public function saveEvent(array $event, bool $wireIntegrations = true): array
    {
        $slug = MudEventzRsvp::normalizeSlug((string) ($event['slug'] ?? ''));
        if ($slug === 'event') {
            throw new \InvalidArgumentException('Event slug is required.');
        }

        $isNew = !is_file($this->eventPath($slug));
        $existing = $isNew ? [] : ($this->readEventFile($this->eventPath($slug)) ?? []);

        $payload = array_merge($existing, $event);
        $payload['slug'] = $slug;
        $payload['title'] = trim((string) ($payload['title'] ?? $slug));
        $payload['description'] = trim((string) ($payload['description'] ?? ''));
        $payload['starts_at'] = trim((string) ($payload['starts_at'] ?? ''));
        $payload['ends_at'] = trim((string) ($payload['ends_at'] ?? ''));
        $payload['date_label'] = trim((string) ($payload['date_label'] ?? ''));
        $payload['venue'] = trim((string) ($payload['venue'] ?? ''));
        $payload['city'] = trim((string) ($payload['city'] ?? ''));
        $payload['capacity'] = max(0, (int) ($payload['capacity'] ?? 0));
        $payload['rsvp_open'] = !empty($payload['rsvp_open']);
        $payload['notify_email'] = trim((string) ($payload['notify_email'] ?? $this->defaultNotifyEmail()));
        $payload['series'] = $this->normalizeSeries((string) ($payload['series'] ?? ''));
        $payload['chapter'] = MudEventzRsvp::normalizeSlug((string) ($payload['chapter'] ?? ''));
        if ($payload['series'] === '' && $payload['chapter'] !== '' && $payload['chapter'] !== 'event') {
            $payload['series'] = $this->seriesForChapter($payload['chapter']);
        }
        if ($payload['series'] === '') {
            $payload['series'] = $this->normalizeSeries((string) ($this->pluginDefaults()['default_series'] ?? 'getgrav-global'));
        }
        $payload['occurrence_of'] = MudEventzRsvp::normalizeSlug((string) ($payload['occurrence_of'] ?? ''));
        $payload['forum_board'] = MudEventzRsvp::normalizeSlug((string) ($payload['forum_board'] ?? 'general'));
        $payload['chat_group'] = MudEventzRsvp::normalizeSlug((string) ($payload['chat_group'] ?? $this->defaultChatGroup($slug)));

        if ($payload['title'] === '') {
            throw new \InvalidArgumentException('Event title is required.');
        }

        $this->writeEventFile($slug, $payload);

        $wire = ['messenger' => null, 'forum' => null];
        if ($wireIntegrations) {
            require_once __DIR__ . '/MudEventzWire.php';
            $wire = MudEventzWire::ensureIntegrations($this->grav, $payload, $isNew);
        }

        $result = $this->getEvent($slug);
        $result['created'] = $isNew;
        $result['wire'] = $wire;

        return $result;
    }

    /** @return array<string, mixed> */
    public function setRsvpOpen(string $slug, bool $open): array
    {
        $slug = MudEventzRsvp::normalizeSlug($slug);
        $file = $this->eventPath($slug);
        if (!is_file($file)) {
            throw new \RuntimeException('Event not found', 404);
        }

        $event = $this->readEventFile($file);
        if ($event === null) {
            throw new \RuntimeException('Invalid event file', 500);
        }

        $event['rsvp_open'] = $open;
        $this->writeEventFile($slug, $event);

        return [
            'ok' => true,
            'slug' => $slug,
            'rsvp_open' => $open,
            'message' => $open ? 'RSVP reopened.' : 'RSVP closed.',
        ];
    }

    /** @return array<string, mixed> */
    public function listRsvpEntries(string $slug): array
    {
        $slug = MudEventzRsvp::normalizeSlug($slug);
        $this->getEvent($slug);

        $file = MudEventzRsvp::rsvpFile($this->grav, $slug);
        $data = MudEventzRsvp::readJson($file);
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];

        return [
            'ok' => true,
            'event' => $slug,
            'updated' => (string) ($data['updated'] ?? ''),
            'entries' => $entries,
            'summary' => MudEventzRsvp::summary($this->grav, $slug),
        ];
    }

    public function exportRsvpCsv(string $slug): string
    {
        $payload = $this->listRsvpEntries($slug);
        $rows = ['name,email,guests,note,at,ip'];
        foreach ($payload['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rows[] = implode(',', [
                $this->csvCell((string) ($entry['name'] ?? '')),
                $this->csvCell((string) ($entry['email'] ?? '')),
                (string) max(1, (int) ($entry['guests'] ?? 1)),
                $this->csvCell((string) ($entry['note'] ?? '')),
                $this->csvCell((string) ($entry['at'] ?? '')),
                $this->csvCell((string) ($entry['ip'] ?? '')),
            ]);
        }

        return implode("\n", $rows) . "\n";
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $events = 0;
        $open = 0;
        $rsvps = 0;
        $headcount = 0;

        foreach ($this->eventFiles() as $file) {
            $event = $this->readEventFile($file);
            if ($event === null) {
                continue;
            }
            $events++;
            if (!empty($event['rsvp_open'])) {
                $open++;
            }
            $slug = (string) ($event['slug'] ?? basename($file, '.json'));
            $summary = MudEventzRsvp::summary($this->grav, $slug);
            $rsvps += (int) ($summary['rsvps'] ?? 0);
            $headcount += (int) ($summary['headcount'] ?? 0);
        }

        return [
            'events' => $events,
            'open' => $open,
            'rsvps' => $rsvps,
            'headcount' => $headcount,
            'chapters' => $this->chapterStatsCount(),
        ];
    }

    private function chapterStatsCount(): int
    {
        require_once __DIR__ . '/MudEventzChapters.php';
        $stats = (new MudEventzChapters($this->grav, $this))->stats();

        return (int) ($stats['chapters'] ?? 0);
    }

    /** @return array<string, mixed> */
    public function pluginDefaults(): array
    {
        $cfg = $this->grav['config']->get('plugins.grav-mud-eventz');

        return is_array($cfg) ? $cfg : [];
    }

    public function defaultNotifyEmail(): string
    {
        return trim((string) ($this->pluginDefaults()['notify_email'] ?? ''));
    }

    public function defaultMessengerPrefix(): string
    {
        return trim((string) ($this->pluginDefaults()['default_messenger_group_prefix'] ?? 'event-'));
    }

    private function defaultChatGroup(string $slug): string
    {
        $prefix = $this->defaultMessengerPrefix();
        if ($prefix !== '' && !str_ends_with($prefix, '-')) {
            $prefix .= '-';
        }

        return MudEventzRsvp::normalizeSlug($prefix . $slug);
    }

    private function seriesForChapter(string $chapter): string
    {
        require_once __DIR__ . '/MudEventzChapters.php';
        try {
            $payload = (new MudEventzChapters($this->grav, $this))->getChapter($chapter, false);
            $ch = is_array($payload['chapter'] ?? null) ? $payload['chapter'] : [];

            return $this->normalizeSeries((string) ($ch['series'] ?? 'getgrav-global'));
        } catch (\Throwable $e) {
            return 'getgrav-global';
        }
    }

    private function normalizeSeries(string $series): string
    {
        $trimmed = strtolower(trim($series));
        if ($trimmed === '') {
            return '';
        }

        return MudEventzRsvp::normalizeSlug($trimmed);
    }

    /**
     * @return array{slug: string, event: array<string, mixed>}
     */
    public function resolveChapterEvent(string $chapter, string $fallbackSlug = ''): array
    {
        $chapter = MudEventzRsvp::normalizeSlug($chapter);
        $fallbackSlug = MudEventzRsvp::normalizeSlug($fallbackSlug);
        $events = $this->listEventsByChapter($chapter, false)['events'] ?? [];
        $now = time();
        $upcoming = [];
        $tba = [];

        foreach ($events as $event) {
            if (!is_array($event) || ($event['rsvp_open'] ?? true) === false) {
                continue;
            }
            $starts = trim((string) ($event['starts_at'] ?? ''));
            if ($starts === '') {
                $tba[] = $event;
                continue;
            }
            $ts = strtotime($starts);
            if ($ts !== false && $ts >= $now) {
                $upcoming[] = $event;
            }
        }

        usort($upcoming, static fn ($a, $b) => strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? '')));

        $pick = $upcoming[0] ?? $tba[0] ?? null;
        if ($pick !== null) {
            return [
                'slug' => (string) ($pick['slug'] ?? $fallbackSlug),
                'event' => $pick,
            ];
        }

        if ($fallbackSlug !== '' && $fallbackSlug !== 'event') {
            try {
                $payload = $this->getEvent($fallbackSlug);

                return [
                    'slug' => $fallbackSlug,
                    'event' => is_array($payload['event'] ?? null) ? $payload['event'] : [],
                ];
            } catch (\Throwable $e) {
            }
        }

        return ['slug' => $fallbackSlug, 'event' => []];
    }

    private function csvCell(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /** @return list<string> */
    private function eventFiles(): array
    {
        $dir = $this->eventsDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.json');

        return is_array($files) ? $files : [];
    }

    /** @return array<string, mixed>|null */
    private function readEventFile(string $file): ?array
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        if (empty($data['slug'])) {
            $data['slug'] = basename($file, '.json');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function writeEventFile(string $slug, array $data): void
    {
        $dir = $this->eventsDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->eventPath($slug),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    private function eventPath(string $slug): string
    {
        return $this->eventsDir() . '/' . MudEventzRsvp::normalizeSlug($slug) . '.json';
    }

    private function eventsDir(): string
    {
        $dir = GRAV_ROOT . '/user/data/mud-eventz/events';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
