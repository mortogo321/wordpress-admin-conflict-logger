import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';
import { beforeEach, describe, expect, it } from 'vitest';

const root = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../..',
);
const source = readFileSync(
    path.join(root, 'assets/js/error-logger.js'),
    'utf8',
);

function loadLogger() {
    const listeners = {};
    const posted = [];
    const windowStub = {
        ACL_INITIALIZED: undefined,
        location: { href: 'https://example.test/wp-admin/' },
        addEventListener: (type, fn) => {
            listeners[type] = listeners[type] || [];
            listeners[type].push(fn);
        },
        ACLLogger: undefined,
    };
    const sandbox = {
        window: windowStub,
        aclConfig: {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            nonce: 'test-nonce',
            activePlugins: [{ path: 'woocommerce/woocommerce.php' }],
            currentPage: 'toplevel_page_admin-conflict-logger',
            isAdmin: true,
        },
        FormData: class {
            constructor() {
                this.fields = {};
            }
            append(k, v) {
                this.fields[k] = v;
            }
        },
        fetch: (url, opts) => {
            posted.push({ url, body: opts.body.fields });
            return Promise.resolve({ ok: true });
        },
        console: { debug: () => {} },
        setTimeout: () => 0,
    };
    sandbox.globalThis = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox, { filename: 'error-logger.js' });
    return { sandbox, windowStub, listeners, posted };
}

describe('error-logger.js', () => {
    let ctx;

    beforeEach(() => {
        ctx = loadLogger();
    });

    it('exposes a testable ACLLogger namespace with documented limits', () => {
        const api = ctx.windowStub.ACLLogger;
        expect(api).toBeDefined();
        expect(api.limits.maxQueueSize).toBe(10);
        expect(api.limits.debounceMs).toBe(1000);
        expect(api.limits.maxMessageLen).toBe(2000);
        expect(api.limits.maxStackLen).toBe(10000);
    });

    it('ignores its own errors and common non-actionable noise', () => {
        const { shouldIgnore } = ctx.windowStub.ACLLogger;
        expect(
            shouldIgnore(
                'boom',
                'https://x/wp-content/plugins/acl/assets/js/error-logger.js',
            ),
        ).toBe(true);
        expect(
            shouldIgnore(
                'ResizeObserver loop completed with undelivered notifications.',
                '',
            ),
        ).toBe(true);
        expect(shouldIgnore('Script error.', '')).toBe(true);
        expect(shouldIgnore('Loading chunk 42 failed.', '')).toBe(false); // ChunkLoadError pattern covers this wording
        expect(
            shouldIgnore('Loading chunk 42 ChunkLoadError failed.', ''),
        ).toBe(true);
        expect(
            shouldIgnore(
                'TypeError: Cannot read properties of null',
                'https://x/plugin.js',
            ),
        ).toBe(false);
    });

    it('registers error and unhandledrejection listeners', () => {
        expect(ctx.listeners.error?.length).toBe(1);
        expect(ctx.listeners.unhandledrejection?.length).toBe(1);
    });

    it('deduplicates identical errors in the queue', () => {
        const { queueError, errorQueue } = ctx.windowStub.ACLLogger;
        queueError({
            message: 'boom',
            source: 'a.js',
            line: 1,
            column: 2,
            stack: '',
        });
        queueError({
            message: 'boom',
            source: 'a.js',
            line: 1,
            column: 2,
            stack: '',
        });
        queueError({
            message: 'other',
            source: 'a.js',
            line: 1,
            column: 2,
            stack: '',
        });
        // First item is shifted for sending immediately, the duplicate is dropped.
        expect(errorQueue.length).toBeLessThanOrEqual(2);
        expect(ctx.posted.length).toBe(1);
        expect(ctx.posted[0].body.message).toBe('boom');
    });

    it('caps the queue to prevent flooding', () => {
        const { queueError, errorQueue } = ctx.windowStub.ACLLogger;
        for (let i = 0; i < 50; i++) {
            queueError({
                message: `err-${i}`,
                source: 'a.js',
                line: 1,
                column: 0,
                stack: '',
            });
        }
        expect(errorQueue.length).toBeLessThanOrEqual(10);
    });

    it('truncates oversized payloads before sending', () => {
        const { truncate, limits } = ctx.windowStub.ACLLogger;
        expect(truncate('a'.repeat(5000), limits.maxMessageLen).length).toBe(
            2000,
        );
        expect(truncate('short', limits.maxMessageLen)).toBe('short');
    });

    it('forwards window errors to the log endpoint with page context', () => {
        ctx.listeners.error[0]({
            message: 'TypeError: x is null',
            filename:
                'https://example.test/wp-content/plugins/woocommerce/x.js',
            lineno: 10,
            colno: 5,
            error: { stack: 'TypeError: x is null\n    at foo' },
        });
        expect(ctx.posted.length).toBe(1);
        const body = ctx.posted[0].body;
        expect(body.action).toBe('acl_log_error');
        expect(body.nonce).toBe('test-nonce');
        expect(body.message).toBe('TypeError: x is null');
        expect(body.pageHook).toBe('toplevel_page_admin-conflict-logger');
        expect(body.isAdmin).toBe('1');
    });

    it('maps promise rejections to log entries', () => {
        ctx.listeners.unhandledrejection[0]({ reason: new Error('rejected!') });
        expect(ctx.posted.length).toBe(1);
        expect(ctx.posted[0].body.message).toBe('rejected!');
        expect(ctx.posted[0].body.source).toBe('Promise');
    });

    it('does not re-initialize when loaded twice', () => {
        vm.runInContext(source, ctx.sandbox, { filename: 'error-logger.js' });
        expect(ctx.listeners.error.length).toBe(1);
    });
});
