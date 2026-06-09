<?php

namespace Grav\Plugin\GravMudEventz;

use Grav\Common\Grav;

class MudEventzChapters
{
    private Grav $grav;
    private MudEventzStorage $storage;

    public function __construct(Grav $grav, MudEventzStorage $storage)
    {
        $this->grav = $grav;
        $this->storage = $storage;
    }

    /** @return array<string, mixed> */
    public function listChapters(): array
    {
        $chapters = [];
        foreach ($this->chapterFiles() as $file) {
            $chapter = $this->readChapterFile($file);
            if ($chapter === null) {
                continue;
            }
            $slug = (string) ($chapter['slug'] ?? basename($file, '.json'));
            $chapter['event_count'] = count($this->storage->listEventsByChapter($slug, false)['events'] ?? []);
            $chapters[] = $chapter;
        }

        usort($chapters, static fn ($a, $b) => strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));

        return ['ok' => true, 'chapters' => $chapters];
    }

    /** @return array<string, mixed> */
    public function getChapter(string $slug, bool $withEvents = true): array
    {
        $slug = MudEventzRsvp::normalizeSlug($slug);
        $file = $this->chapterPath($slug);
        if (!is_file($file)) {
            throw new \RuntimeException('Chapter not found', 404);
        }

        $chapter = $this->readChapterFile($file);
        if ($chapter === null) {
            throw new \RuntimeException('Invalid chapter file', 500);
        }

        $result = ['ok' => true, 'chapter' => $chapter];
        if ($withEvents) {
            $result['events'] = $this->storage->listEventsByChapter($slug)['events'] ?? [];
        }

        return $result;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function saveChapter(array $payload): array
    {
        $slug = MudEventzRsvp::normalizeSlug((string) ($payload['slug'] ?? ''));
        if ($slug === 'event' || $slug === '') {
            throw new \InvalidArgumentException('Chapter slug is required.');
        }

        $existing = is_file($this->chapterPath($slug))
            ? ($this->readChapterFile($this->chapterPath($slug)) ?? [])
            : [];

        $data = array_merge($existing, $payload);
        $data['slug'] = $slug;
        $data['title'] = trim((string) ($data['title'] ?? $slug));
        $data['description'] = trim((string) ($data['description'] ?? ''));
        $data['city'] = trim((string) ($data['city'] ?? ''));
        $data['venue'] = trim((string) ($data['venue'] ?? ''));
        $data['series'] = MudEventzRsvp::normalizeSlug((string) ($data['series'] ?? 'getgrav-global'));
        $data['default_chat_group'] = MudEventzRsvp::normalizeSlug((string) ($data['default_chat_group'] ?? ('getgrav-' . $slug)));
        $data['default_forum_board'] = MudEventzRsvp::normalizeSlug((string) ($data['default_forum_board'] ?? 'general'));
        $data['capacity'] = max(0, (int) ($data['capacity'] ?? 40));
        $data['notify_email'] = trim((string) ($data['notify_email'] ?? $this->storage->defaultNotifyEmail()));
        $data['recurrence'] = MudEventzRecurrence::normalize(is_array($data['recurrence'] ?? null) ? $data['recurrence'] : []);

        if ($data['title'] === '') {
            throw new \InvalidArgumentException('Chapter title is required.');
        }

        $this->writeChapterFile($slug, $data);

        $result = $this->getChapter($slug, true);
        $eventCount = count($result['events'] ?? []);
        if ($eventCount === 0 && MudEventzRecurrence::isEnabled($data['recurrence'])) {
            $spawn = $this->spawnOccurrences($slug, [
                'count' => 1,
                'wire' => ($payload['wire'] ?? true) !== false,
            ]);
            $result = $this->getChapter($slug, true);
            $result['spawn'] = $spawn;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function spawnOccurrences(string $chapterSlug, array $options = []): array
    {
        require_once __DIR__ . '/MudEventzRecurrence.php';

        $payload = $this->getChapter($chapterSlug, true);
        $chapter = is_array($payload['chapter'] ?? null) ? $payload['chapter'] : [];
        $slug = (string) ($chapter['slug'] ?? $chapterSlug);
        $count = max(1, min(12, (int) ($options['count'] ?? 1)));
        $wire = ($options['wire'] ?? true) !== false;
        $recurrence = is_array($chapter['recurrence'] ?? null) ? $chapter['recurrence'] : [];

        $afterIso = trim((string) ($options['after'] ?? ''));
        if ($afterIso === '') {
            $afterIso = $this->latestChapterStart($slug);
        }
        if ($afterIso === '' && !empty($recurrence['anchor'])) {
            $afterIso = (string) $recurrence['anchor'];
        }
        if ($afterIso === '') {
            $afterIso = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
        }

        $startsList = [];
        if (!empty($options['starts_at'])) {
            $startsList[] = trim((string) $options['starts_at']);
        } elseif (MudEventzRecurrence::isEnabled($recurrence)) {
            $startsList = MudEventzRecurrence::nextOccurrences($recurrence, $afterIso, $count);
        } else {
            throw new \InvalidArgumentException('Chapter recurrence is disabled — pass starts_at or enable recurrence.');
        }

        $created = [];
        $skipped = [];
        $tz = (string) ($recurrence['timezone'] ?? 'UTC');

        foreach ($startsList as $startsAt) {
            if ($startsAt === '') {
                continue;
            }
            try {
                $start = new \DateTimeImmutable($startsAt);
            } catch (\Throwable $e) {
                $skipped[] = ['starts_at' => $startsAt, 'reason' => 'invalid date'];
                continue;
            }

            $eventSlug = trim((string) ($options['event_slug'] ?? ''));
            if ($eventSlug === '') {
                $eventSlug = MudEventzRecurrence::occurrenceSlug($slug, $start);
            }
            $eventSlug = MudEventzRsvp::normalizeSlug($eventSlug);

            if ($this->storage->eventExists($eventSlug)) {
                $skipped[] = ['slug' => $eventSlug, 'reason' => 'exists'];
                continue;
            }

            $durationHours = (int) ($recurrence['duration_hours'] ?? 2);
            $endsAt = $start->modify('+' . $durationHours . ' hours')->format(\DateTimeInterface::ATOM);

            $event = [
                'slug' => $eventSlug,
                'title' => (string) ($options['title'] ?? ($chapter['title'] ?? $slug)),
                'description' => (string) ($chapter['description'] ?? ''),
                'starts_at' => $start->format(\DateTimeInterface::ATOM),
                'ends_at' => $endsAt,
                'date_label' => MudEventzRecurrence::occurrenceLabel($start, $tz),
                'venue' => (string) ($chapter['venue'] ?? ''),
                'city' => (string) ($chapter['city'] ?? ''),
                'capacity' => (int) ($chapter['capacity'] ?? 40),
                'rsvp_open' => true,
                'notify_email' => (string) ($chapter['notify_email'] ?? ''),
                'series' => (string) ($chapter['series'] ?? 'getgrav-global'),
                'chapter' => $slug,
                'occurrence_of' => $slug,
                'forum_board' => (string) ($chapter['default_forum_board'] ?? 'general'),
                'chat_group' => (string) ($chapter['default_chat_group'] ?? ('getgrav-' . $slug)),
            ];

            $saved = $this->storage->saveEvent($event, $wire);
            $created[] = [
                'slug' => $eventSlug,
                'starts_at' => $event['starts_at'],
                'created' => !empty($saved['created']),
            ];
        }

        return [
            'ok' => true,
            'chapter' => $slug,
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        return ['chapters' => count($this->chapterFiles())];
    }

    private function latestChapterStart(string $chapterSlug): string
    {
        $events = $this->storage->listEventsByChapter($chapterSlug, false)['events'] ?? [];
        $latest = '';
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $starts = (string) ($event['starts_at'] ?? '');
            if ($starts !== '' && strcmp($starts, $latest) > 0) {
                $latest = $starts;
            }
        }

        return $latest;
    }

    /** @return list<string> */
    private function chapterFiles(): array
    {
        $dir = $this->chaptersDir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json');

        return is_array($files) ? $files : [];
    }

    /** @return array<string, mixed>|null */
    private function readChapterFile(string $file): ?array
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
    private function writeChapterFile(string $slug, array $data): void
    {
        $dir = $this->chaptersDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->chapterPath($slug),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    private function chapterPath(string $slug): string
    {
        return $this->chaptersDir() . '/' . MudEventzRsvp::normalizeSlug($slug) . '.json';
    }

    private function chaptersDir(): string
    {
        $dir = GRAV_ROOT . '/user/data/mud-eventz/chapters';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
