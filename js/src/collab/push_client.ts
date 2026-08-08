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

/**
 * Optional real-time push client (Mercure/SSE).
 *
 * When an administrator has configured a Mercure hub, this subscribes to the
 * per-workspace topic and, on each "new revision" event, wakes an immediate
 * poll so changes arrive without waiting for the poll interval. It is purely an
 * accelerator: if the hub is unavailable or push is off, nothing happens and the
 * normal poll loop remains the transport (the fallback). The event only carries
 * a revision number; the actual operations are still fetched through the
 * server-authoritative get_operations path, so no trust is placed in the hub.
 *
 * Server→client only — the client never publishes — so SSE (EventSource) is
 * sufficient and no WebSocket is used.
 *
 * @module     mod_vimipad/collab/push_client
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The subset of CollabConfig this client needs. */
export interface PushConfig {
    pushenabled?: number;
    pushendpoint?: string;
    pushtopic?: string;
    pushtoken?: string;
}

/**
 * Whether the given config has everything needed to attempt a push connection.
 *
 * @param config The collaboration config.
 * @returns True if push is enabled and a hub, topic and token are present.
 */
export function pushAvailable(config: PushConfig | undefined): boolean {
    return !!config
        && config.pushenabled === 1
        && !!config.pushendpoint
        && !!config.pushtopic
        && !!config.pushtoken;
}

/** Minimal EventSource shape, so tests can inject a fake. */
export interface EventSourceLike {
    onmessage: ((ev: {data: string}) => void) | null;
    onerror: ((ev: unknown) => void) | null;
    close(): void;
}

/** Factory for an EventSource (overridable in tests / when unsupported). */
export type EventSourceFactory = (url: string) => EventSourceLike;

/** Default factory using the browser EventSource with credentials. */
function defaultFactory(url: string): EventSourceLike {
    // withCredentials lets the mercureAuthorization cookie reach the hub when it
    // is a separate (same-site) origin with CORS credentials enabled.
    const Ctor = (globalThis as unknown as {
        EventSource: new (u: string, init?: {withCredentials: boolean}) => EventSourceLike;
    }).EventSource;
    return new Ctor(url, {withCredentials: true});
}

/**
 * Subscribe to a workspace's push topic and wake a poll on each event.
 */
export class PushClient {
    private es: EventSourceLike | null = null;
    private lastRevision = 0;

    /**
     * @param config The collaboration config (must satisfy pushAvailable).
     * @param onWake Called with the announced revision to trigger an immediate poll.
     * @param factory EventSource factory (defaults to the browser's).
     * @param setCookie Cookie setter (defaults to document.cookie); testable.
     */
    constructor(
        private readonly config: PushConfig,
        private readonly onWake: (revision: number) => void,
        private readonly factory: EventSourceFactory = defaultFactory,
        private readonly setCookie: (value: string) => void =
            (v) => { (globalThis as unknown as {document?: {cookie: string}}).document!.cookie = v; }
    ) {}

    /**
     * Open the subscription. No-op (and safe) if push is unavailable or the
     * environment lacks EventSource; the poll loop then remains the sole path.
     *
     * @returns void
     */
    public start(): void {
        if (!pushAvailable(this.config)) {
            return;
        }
        // Mercure reads the subscriber JWT from the `mercureAuthorization` cookie.
        // Same-origin (reverse-proxied) or a same-site subdomain hub receives it.
        try {
            const secure = (globalThis as unknown as {location?: {protocol: string}})
                .location?.protocol === 'https:' ? '; secure' : '';
            this.setCookie(`mercureAuthorization=${this.config.pushtoken}; path=/; samesite=lax${secure}`);
        } catch {
            // Non-browser or restricted context: fall through; connect may still
            // work for an anonymous hub, otherwise it simply errors and we stop.
        }
        const url = `${this.config.pushendpoint}?topic=${encodeURIComponent(this.config.pushtopic as string)}`;
        try {
            const es = this.factory(url);
            es.onmessage = (ev) => this.onEvent(ev.data);
            es.onerror = () => this.stop(); // give up quietly; polling covers us
            this.es = es;
        } catch {
            this.es = null;
        }
    }

    /**
     * Handle one SSE payload: `{"revision": N}`. Wakes a poll when N advances.
     *
     * @param data The raw event data.
     * @returns void
     */
    private onEvent(data: string): void {
        let revision = 0;
        try {
            revision = Number((JSON.parse(data) as {revision?: number}).revision) || 0;
        } catch {
            return;
        }
        if (revision > this.lastRevision) {
            this.lastRevision = revision;
            this.onWake(revision);
        }
    }

    /**
     * Close the subscription.
     *
     * @returns void
     */
    public stop(): void {
        if (this.es) {
            try {
                this.es.close();
            } catch {
                // ignore.
            }
            this.es = null;
        }
    }
}
