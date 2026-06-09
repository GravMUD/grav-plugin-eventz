<?php

namespace Grav\Plugin\Eventz;

use Grav\Common\Grav;

class MudEventzRsvp
{
    /** @param array<string, mixed> $eventMeta title, notify_email, rsvp_open */
    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function submit(Grav $grav, string $eventSlug, array $eventMeta, array $input): array
    {
        if (!self::rsvpOpen($eventMeta)) {
            return ['ok' => false, 'error' => 'RSVP is closed for this event'];
        }

        if (trim((string) ($input['website'] ?? '')) !== '') {
            return ['ok' => true];
        }

        $name = self::sanitizeMailText((string) ($input['name'] ?? ''));
        $email = strtolower(self::sanitizeMailText((string) ($input['email'] ?? '')));
        $guests = max(1, min(8, (int) ($input['guests'] ?? 1)));
        $note = self::sanitizeMailText(substr((string) ($input['note'] ?? ''), 0, 500));

        if ($name === '' || strlen($name) > 120) {
            return ['ok' => false, 'error' => 'Name is required'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Valid email required'];
        }

        $slug = self::normalizeSlug($eventSlug);
        $file = self::rsvpFile($grav, $slug);
        $data = self::readJson($file);

        if (!isset($data['entries']) || !is_array($data['entries'])) {
            $data['entries'] = [];
        }

        foreach ($data['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (strtolower((string) ($entry['email'] ?? '')) === $email) {
                return ['ok' => false, 'error' => 'Already RSVP’d with that email'];
            }
        }

        $capacity = max(0, (int) ($eventMeta['capacity'] ?? 0));
        $maxEntries = max(0, (int) ($eventMeta['max_rsvp_entries'] ?? 500));
        $entryCount = count($data['entries']);
        if ($maxEntries > 0 && $entryCount >= $maxEntries) {
            return ['ok' => false, 'error' => 'RSVP list is full'];
        }

        $currentHeadcount = 0;
        foreach ($data['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $currentHeadcount += max(1, (int) ($entry['guests'] ?? 1));
        }
        if ($capacity > 0 && ($currentHeadcount + $guests) > $capacity) {
            return ['ok' => false, 'error' => 'Not enough capacity remaining for this RSVP'];
        }

        $data['entries'][] = [
            'name' => $name,
            'email' => $email,
            'guests' => $guests,
            'note' => $note,
            'at' => gmdate('c'),
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ];
        $data['updated'] = gmdate('c');
        self::writeJson($file, $data);

        $summary = self::summary($grav, $slug);
        self::notifyOrganiser($grav, $eventMeta, $name, $email, $guests, $note, $summary);

        return ['ok' => true, 'summary' => $summary];
    }

    /** @return array<string, mixed> */
    public static function summary(Grav $grav, string $eventSlug): array
    {
        $slug = self::normalizeSlug($eventSlug);
        $file = self::rsvpFile($grav, $slug);
        $data = self::readJson($file);
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];

        $people = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $people += max(1, (int) ($entry['guests'] ?? 1));
        }

        return [
            'ok' => true,
            'event' => $slug,
            'rsvps' => count($entries),
            'headcount' => $people,
            'updated' => (string) ($data['updated'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $eventMeta */
    public static function rsvpOpen(array $eventMeta): bool
    {
        return (bool) ($eventMeta['rsvp_open'] ?? true);
    }

    /** @param array<string, mixed> $eventMeta @param array<string, mixed> $summary */
    private static function notifyOrganiser(Grav $grav, array $eventMeta, string $name, string $email, int $guests, string $note, array $summary): void
    {
        $to = trim((string) ($eventMeta['notify_email'] ?? ''));
        if ($to === '' || !function_exists('mail')) {
            return;
        }

        $title = self::sanitizeMailText((string) ($eventMeta['title'] ?? 'Event'));
        $safeName = self::sanitizeMailText($name);
        $safeEmail = self::sanitizeMailText($email);
        $subject = 'Event RSVP: ' . $safeName . ' (' . $guests . ')';
        $body = "New RSVP for {$title}\n\n"
            . "Name: {$safeName}\n"
            . "Email: {$safeEmail}\n"
            . "Guests: {$guests}\n"
            . "Note: " . ($note !== '' ? $note : '(none)') . "\n\n"
            . "Total RSVPs: " . ($summary['rsvps'] ?? 0) . "\n"
            . "Headcount: " . ($summary['headcount'] ?? 0) . "\n";

        $headers = [];
        $from = self::mailFromAddress($grav);
        if ($from !== '') {
            $headers[] = 'From: ' . $from;
        }
        if ($safeEmail !== '' && filter_var($safeEmail, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $safeEmail;
        }

        @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public static function sanitizeMailText(string $value): string
    {
        $value = trim($value);

        return preg_replace('/[\r\n\x00]+/', ' ', $value) ?? '';
    }

    public static function mailFromAddress(Grav $grav): string
    {
        $candidates = [
            (string) $grav['config']->get('plugins.email.from', ''),
            (string) $grav['config']->get('site.author.email', ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = self::sanitizeMailText($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        $host = parse_url((string) $grav['config']->get('site.url', ''), PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return 'noreply@' . $host;
        }

        return '';
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($slug))) ?? '';

        return $slug !== '' ? $slug : 'event';
    }

    public static function rsvpFile(Grav $grav, string $eventSlug): string
    {
        require_once __DIR__ . '/MudEventzData.php';

        return MudEventzData::dir($grav, 'rsvp') . '/' . self::normalizeSlug($eventSlug) . '.json';
    }

    /** @return array<string, mixed> */
    public static function readJson(string $file): array
    {
        if (!is_file($file)) {
            return ['entries' => []];
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return ['entries' => []];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : ['entries' => []];
    }

    /** @param array<string, mixed> $data */
    public static function writeJson(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
