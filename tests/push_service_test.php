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

namespace mod_vimipad;

use mod_vimipad\local\service\push_service;

/**
 * Tests for the optional Mercure push service.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\push_service
 */
final class push_service_test extends \advanced_testcase {
    /**
     * Decode a JWT into [header, payload] and verify its HS256 signature.
     *
     * @param string $jwt The token.
     * @param string $key The shared secret.
     * @return array{0: array, 1: array}
     */
    private function decode(string $jwt, string $key): array {
        [$h, $p, $s] = explode('.', $jwt);
        $b64 = static fn(string $x): string => base64_decode(strtr($x, '-_', '+/'));
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", $key, true)), '+/', '-_'), '=');
        $this->assertSame($expected, $s, 'JWT signature must verify');
        return [json_decode($b64($h), true), json_decode($b64($p), true)];
    }

    /**
     * Off unless enabled, endpoint and key are all set.
     *
     * @return void
     */
    public function test_gating(): void {
        $this->resetAfterTest();
        $this->assertFalse(push_service::is_enabled());
        $this->assertSame('', push_service::subscriber_token(7));

        set_config('pushenabled', 1, 'mod_vimipad');
        set_config('pushendpoint', 'https://hub.example/.well-known/mercure', 'mod_vimipad');
        $this->assertFalse(push_service::is_enabled(), 'still off without a key');
        set_config('pushjwtkey', 'shared-secret', 'mod_vimipad');
        $this->assertTrue(push_service::is_enabled());
    }

    /**
     * The topic is per-workspace and stable.
     *
     * @return void
     */
    public function test_topic(): void {
        $this->assertSame('vimipad/workspace/42', push_service::topic(42));
    }

    /**
     * The subscriber token is a valid JWT scoped to exactly this workspace topic.
     *
     * @return void
     */
    public function test_subscriber_token_is_scoped(): void {
        $this->resetAfterTest();
        set_config('pushenabled', 1, 'mod_vimipad');
        set_config('pushendpoint', 'https://hub.example/.well-known/mercure', 'mod_vimipad');
        set_config('pushjwtkey', 'shared-secret', 'mod_vimipad');

        $jwt = push_service::subscriber_token(7);
        [$header, $payload] = $this->decode($jwt, 'shared-secret');
        $this->assertSame('HS256', $header['alg']);
        $this->assertSame(['vimipad/workspace/7'], $payload['mercure']['subscribe']);
        $this->assertArrayNotHasKey('publish', $payload['mercure']);
        $this->assertGreaterThan(time(), $payload['exp']);
    }

    /**
     * The publish request targets the publish URL (falling back to the
     * endpoint), carries a publisher-scoped bearer token, and a revision payload.
     *
     * @return void
     */
    public function test_publish_request(): void {
        $this->resetAfterTest();
        set_config('pushenabled', 1, 'mod_vimipad');
        set_config('pushendpoint', 'https://public.example/.well-known/mercure', 'mod_vimipad');
        set_config('pushjwtkey', 'shared-secret', 'mod_vimipad');

        // With no distinct publish URL, publishing goes to the endpoint.
        $req = push_service::publish_request(7, 99);
        $this->assertSame('https://public.example/.well-known/mercure', $req['url']);
        $this->assertStringStartsWith('Authorization: Bearer ', $req['headers'][0]);
        $token = substr($req['headers'][0], strlen('Authorization: Bearer '));
        [, $payload] = $this->decode($token, 'shared-secret');
        $this->assertSame(['*'], $payload['mercure']['publish']);
        $this->assertStringContainsString('topic=vimipad%2Fworkspace%2F7', $req['body']);
        $this->assertStringContainsString('data=' . urlencode(json_encode(['revision' => 99])), $req['body']);

        // A distinct internal publish URL is used when set.
        set_config('pushpublishurl', 'http://127.0.0.1:3000/.well-known/mercure', 'mod_vimipad');
        $req2 = push_service::publish_request(7, 100);
        $this->assertSame('http://127.0.0.1:3000/.well-known/mercure', $req2['url']);
    }

    /**
     * publish() is a safe no-op when push is disabled (no exception, no attempt).
     *
     * @return void
     */
    public function test_publish_noop_when_disabled(): void {
        $this->resetAfterTest();
        // Must simply return without error.
        push_service::publish(7, 1);
        $this->assertFalse(push_service::is_enabled());
    }
}
