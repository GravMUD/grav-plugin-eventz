<?php

namespace Grav\Plugin\Eventz;

/**
 * Eventz MUD fences — embed RSVP / event lists in .mud pages (NOT core GravMUD spec).
 * Registered via onMudFenceRender from eventz.
 */
class MudEventzFences
{
    /** @param array<string, mixed> $node */
    public static function render(string $type, array $node, array $attrs, string $body, array $data): ?string
    {
        return match ($type) {
            'eventz', 'mud-eventz', 'eventz-rsvp', 'mud-eventz-rsvp' => self::renderEmbed($attrs, $data),
            default => null,
        };
    }

    /** @param array<string, mixed> $attrs @param array<string, mixed> $data */
    private static function renderEmbed(array $attrs, array $data): string
    {
        $slug = trim((string) ($attrs['slug'] ?? $attrs['event'] ?? $data['slug'] ?? $data['event'] ?? ''));
        $series = trim((string) ($attrs['series'] ?? $data['series'] ?? ''));
        $chapter = trim((string) ($attrs['chapter'] ?? $data['chapter'] ?? ''));
        $mode = trim((string) ($attrs['mode'] ?? $data['mode'] ?? ''));
        if ($mode === '') {
            $mode = $slug !== '' ? 'event' : ($series !== '' ? 'series' : 'list');
        }

        $publicRoute = trim((string) ($attrs['public-route'] ?? $data['public-route'] ?? 'eventz'), '/');
        $api = trim((string) ($attrs['api'] ?? $data['api'] ?? '/api/v1/mud-eventz'));
        $assetBase = '/' . $publicRoute;
        $wrapClass = trim((string) ($attrs['wrap-class'] ?? $data['wrap-class'] ?? 'gg-meetup-rsvp gg-campaign-card'));
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' && $mode === 'event') {
            $title = 'RSVP — free pizza needs a headcount 🍕';
        }

        $mountAttrs = 'class="mud-eventz" data-mud-eventz data-mode="' . self::esc($mode) . '" data-api="' . self::esc($api) . '"';
        if ($slug !== '') {
            $mountAttrs .= ' data-event="' . self::esc($slug) . '" data-slug="' . self::esc($slug) . '"';
        }
        if ($series !== '') {
            $mountAttrs .= ' data-series="' . self::esc($series) . '"';
        }
        if ($chapter !== '') {
            $mountAttrs .= ' data-chapter="' . self::esc($chapter) . '"';
        }

        $head = $title !== '' ? '<h2>' . self::esc($title) . '</h2>' : '';
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '' && $mode === 'event') {
            $note = 'Flat-file RSVP via Eventz — zero Meetup tax · Chief reads the JSON.';
        }
        $foot = $note !== '' ? '<p class="gg-muted-sm" style="margin-top:1rem;">' . self::inline($note) . '</p>' : '';

        return '<link rel="stylesheet" href="' . self::esc($assetBase) . '/assets/mud-eventz.css">'
            . '<section class="' . self::esc($wrapClass) . '">'
            . $head
            . '<div ' . $mountAttrs . '></div>'
            . $foot
            . '</section>'
            . '<script>window.GRAVMUD_EVENTZ_API=' . json_encode($api, JSON_UNESCAPED_SLASHES) . ';</script>'
            . '<script src="' . self::esc($assetBase) . '/assets/mud-eventz.js"></script>';
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function inline(string $value): string
    {
        $value = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $value) ?? $value;
        $value = preg_replace('/`([^`]+)`/', '<code>$1</code>', $value) ?? $value;

        return $value;
    }
}
