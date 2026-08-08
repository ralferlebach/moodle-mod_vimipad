<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_vimipad\local\service;

/**
 * Optional real-time push via a Mercure hub.
 *
 * The plugin never bundles a push server. When an administrator points
 * `pushendpoint` at a running Mercure hub and sets the shared `pushjwtkey`, the
 * server publishes a lightweight "new revision" event to a per-workspace topic
 * after each committed operation, and clients subscribe with a scoped subscriber
 * token to be woken for an immediate poll. Publishing is best-effort with tight
 * timeouts: if the hub is slow or down, the edit still succeeds and polling
 * remains the transport. Mercure is a self-contained daemon (Caddy-based), so no
 * dependency on the site's web server (nginx/apache) is introduced.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_service {
    /** @var int Publisher/subscriber token lifetime in seconds. */
    private const TOKEN_TTL = 3600;

    /**
     * Whether push is enabled and sufficiently configured to be usable.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return ((int) get_config('mod_vimipad', 'pushenabled') === 1)
            && (string) get_config('mod_vimipad', 'pushendpoint') !== ''
            && (string) get_config('mod_vimipad', 'pushjwtkey') !== '';
    }

    /**
     * The Mercure topic for a workspace.
     *
     * @param int $workspaceid The workspace id.
     * @return string
     */
    public static function topic(int $workspaceid): string {
        return 'vimipad/workspace/' . $workspaceid;
    }

    /**
     * A short-lived subscriber token scoped to a single workspace topic, for the
     * client's EventSource authorization. Empty string when push is off.
     *
     * @param int $workspaceid The workspace id.
     * @return string
     */
    public static function subscriber_token(int $workspaceid): string {
        if (!self::is_enabled()) {
            return '';
        }
        return self::encode_jwt(
            ['mercure' => ['subscribe' => [self::topic($workspaceid)]]],
            (string) get_config('mod_vimipad', 'pushjwtkey')
        );
    }

    /**
     * Build the HTTP request (url, headers, body) that publishes a new-revision
     * event for a workspace. Separated from the transport for testability.
     *
     * @param int $workspaceid The workspace id.
     * @param int $revision The new current revision.
     * @return array{url: string, headers: string[], body: string}
     */
    public static function publish_request(int $workspaceid, int $revision): array {
        $publishurl = (string) get_config('mod_vimipad', 'pushpublishurl');
        if ($publishurl === '') {
            $publishurl = (string) get_config('mod_vimipad', 'pushendpoint');
        }
        $token = self::encode_jwt(
            ['mercure' => ['publish' => ['*']]],
            (string) get_config('mod_vimipad', 'pushjwtkey')
        );
        $body = http_build_query([
            'topic' => self::topic($workspaceid),
            'data' => json_encode(['revision' => $revision]),
        ]);
        return [
            'url' => $publishurl,
            'headers' => ['Authorization: Bearer ' . $token],
            'body' => $body,
        ];
    }

    /**
     * Publish a new-revision event for a workspace. Best-effort: never throws,
     * short timeouts, silent on failure (polling is the fallback).
     *
     * @param int $workspaceid The workspace id.
     * @param int $revision The new current revision.
     * @return void
     */
    public static function publish(int $workspaceid, int $revision): void {
        if (!self::is_enabled()) {
            return;
        }
        try {
            $req = self::publish_request($workspaceid, $revision);
            $curl = new \curl();
            $curl->setHeader($req['headers']);
            $curl->post($req['url'], $req['body'], [
                'CURLOPT_CONNECTTIMEOUT' => 1,
                'CURLOPT_TIMEOUT' => 2,
            ]);
            if ($curl->get_errno()) {
                debugging('vimipad push publish failed: ' . $curl->error, DEBUG_DEVELOPER);
            }
        } catch (\Throwable $e) {
            // Never let a push failure affect the edit.
            debugging('vimipad push publish exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Encode a compact HS256 JWT with the given claims (plus iat/exp).
     *
     * @param array $claims The claim set (merged with iat/exp).
     * @param string $key The shared HMAC secret.
     * @return string The signed token, or '' if no key.
     */
    public static function encode_jwt(array $claims, string $key): string {
        if ($key === '') {
            return '';
        }
        $now = time();
        $claims += ['iat' => $now, 'exp' => $now + self::TOKEN_TTL];
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            self::base64url((string) json_encode($header)),
            self::base64url((string) json_encode($claims)),
        ];
        $signing = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing, $key, true);
        $segments[] = self::base64url($signature);
        return implode('.', $segments);
    }

    /**
     * URL-safe base64 without padding.
     *
     * @param string $data Raw bytes.
     * @return string
     */
    private static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
