<?php

namespace Grav\Plugin\Eventz;

use Grav\Common\Grav;

class MudEventzWire
{
    /** @param array<string, mixed> $event @return array<string, mixed> */
    public static function ensureIntegrations(Grav $grav, array $event, bool $isNew): array
    {
        $result = [
            'messenger' => null,
            'forum' => null,
        ];

        $chatGroup = MudEventzRsvp::normalizeSlug((string) ($event['chat_group'] ?? ''));
        if ($chatGroup !== '') {
            $result['messenger'] = self::ensureMessengerGroup($grav, $chatGroup, $event);
        }

        $board = MudEventzRsvp::normalizeSlug((string) ($event['forum_board'] ?? ''));
        $slug = MudEventzRsvp::normalizeSlug((string) ($event['slug'] ?? ''));
        if ($isNew && $board !== '' && $slug !== '' && self::forumzInstalled()) {
            $result['forum'] = self::ensureForumThread($grav, $board, $slug, $event);
        }

        return $result;
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private static function ensureMessengerGroup(Grav $grav, string $groupId, array $event): array
    {
        if (!self::messengerInstalled()) {
            return ['ok' => false, 'reason' => 'messenger not installed'];
        }

        $cfg = (array) $grav['config']->get('plugins.messenger', []);
        if ($cfg === []) {
            $cfg = (array) $grav['config']->get('plugins.grav-mud-messenger', []);
        }
        $groups = $cfg['groups'] ?? null;
        if (is_array($groups) && isset($groups[$groupId])) {
            return ['ok' => true, 'group' => $groupId, 'action' => 'exists'];
        }

        $title = trim((string) ($event['title'] ?? $groupId));
        $city = trim((string) ($event['city'] ?? ''));
        $description = $city !== '' ? $title . ' · ' . $city : $title;

        require_once GRAV_ROOT . '/user/plugins/messenger/classes/MudMessengerGroups.php';
        $written = \Grav\Plugin\Messenger\MudMessengerGroups::upsert(
            $grav,
            $groupId,
            $title,
            $description,
            '📅'
        );
        if (!$written) {
            return ['ok' => false, 'group' => $groupId, 'reason' => 'Could not update messenger config'];
        }

        return ['ok' => true, 'group' => $groupId, 'action' => 'created'];
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private static function ensureForumThread(Grav $grav, string $board, string $slug, array $event): array
    {
        $threadFile = GRAV_ROOT . '/user/data/mud-forumz/' . $board . '/' . $slug . '.json';
        if (is_file($threadFile)) {
            return ['ok' => true, 'board' => $board, 'thread' => $slug, 'action' => 'exists'];
        }

        require_once GRAV_ROOT . '/user/plugins/grav-mud-forumz/classes/MudForumzStorage.php';
        $storage = new \Grav\Plugin\GravMudForumz\MudForumzStorage($grav);

        $title = trim((string) ($event['title'] ?? $slug));
        $body = trim((string) ($event['description'] ?? ''));
        if ($body === '') {
            $body = 'Event discussion thread for **' . $title . '**. RSVP on the events page — chat in Messenger.';
        }

        $created = $storage->createThread([
            'board' => $board,
            'title' => $title,
            'body' => $body,
            'author' => 'Chief',
            'authorSlug' => 'chief',
            'website' => '',
        ], 'chief');

        return [
            'ok' => !empty($created['ok']),
            'board' => $board,
            'thread' => (string) ($created['threadId'] ?? $slug),
            'action' => 'created',
            'message' => (string) ($created['message'] ?? ''),
        ];
    }

    private static function messengerInstalled(): bool
    {
        return is_file(GRAV_ROOT . '/user/plugins/messenger/messenger.php')
            || is_file(GRAV_ROOT . '/user/plugins/grav-mud-messenger/grav-mud-messenger.php');
    }

    private static function forumzInstalled(): bool
    {
        return is_file(GRAV_ROOT . '/user/plugins/grav-mud-forumz/classes/MudForumzStorage.php');
    }
}
