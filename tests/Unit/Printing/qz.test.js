import { test } from 'node:test';
import assert from 'node:assert/strict';
import { registerQzPrinting } from '../../../resources/js/printing/qz.js';

test('sin configuración no se registra impresión', () => {
    const previous = globalThis.window;
    try {
        globalThis.window = {};
        let subscriptions = 0;
        registerQzPrinting({ on() { subscriptions++; } });
        assert.equal(subscriptions, 0);
    } finally {
        globalThis.window = previous;
    }
});

test('registrar impresión genérica no inicia conexión ni envía PDF', () => {
    const previous = globalThis.window;
    try {
        globalThis.window = {
            safQzPrinting: {
                endpoints: {
                    certificate: '/printing/qz/certificate',
                    prepare: '/printing/qz/prepare',
                    sign: '/printing/qz/sign',
                },
                pendingDocument: null,
            },
        };

        const handlers = new Map();
        registerQzPrinting({
            on(event, callback) {
                assert.equal(handlers.has(event), false);
                handlers.set(event, callback);
            },
        });

        assert.deepEqual([...handlers.keys()], ['qz-print']);
        assert.equal(typeof handlers.get('qz-print'), 'function');
    } finally {
        globalThis.window = previous;
    }
});
