import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createPrinterController } from '../../../resources/js/printing/qz.js';

function panel() {
    const state = createPrinterController({}, 1, null);
    state.remember = () => {};
    return state;
}

test('un evento duplicado no imprime dos veces y la cola procesa nuevos DTE', async () => {
    const state = panel();
    const printed = [];
    state.send = async id => {
        printed.push(id);
        if (id === 1) state.enqueue(2);
    };
    state.pending = [{ id: 1, state: 'pending' }];
    await state.drain();
    state.enqueue(1);
    await state.drain();
    assert.deepEqual(printed, [1, 2]);
    assert.equal(state.busy, false);
});

test('un error de impresión no se reintenta automáticamente', async () => {
    const state = panel();
    let calls = 0;
    state.send = async () => { calls++; throw new Error('offline'); };
    state.pending = [{ id: 1, state: 'pending' }];
    await state.drain();
    await state.drain();
    assert.equal(calls, 1);
    assert.equal(state.pending[0].state, 'uncertain');
    assert.match(state.notice, /cobro y la emisión se conservaron/);
});

test('un almacenamiento bloqueado impide enviar sin seguimiento local', async () => {
    const state = panel();
    state.remember = () => { throw new Error('blocked'); };
    let calls = 0;
    state.send = async () => { calls++; };
    state.pending = [{ id: 1, state: 'pending' }];
    await state.drain();
    assert.equal(calls, 0);
    assert.equal(state.busy, false);
});

test('firma presenta el JSON original y el ticket al servidor, no un hash arbitrario', async () => {
    const { default: qz } = await import('qz-tray');
    const { default: axios } = await import('axios');
    const { webcrypto, createHash } = await import('node:crypto');
    const originalCrypto = globalThis.crypto;
    globalThis.crypto = webcrypto;
    const originals = [qz.websocket.isActive, qz.websocket.connect, qz.api.setSha256Type,
        qz.security.setCertificatePromise, qz.security.setSignatureAlgorithm, qz.security.setSignaturePromise, axios.post];
    let hasher, signer, payload;
    try {
        qz.websocket.isActive = () => false;
        qz.websocket.connect = async () => {};
        qz.api.setSha256Type = callback => { hasher = callback; };
        qz.security.setCertificatePromise = () => {};
        qz.security.setSignatureAlgorithm = algorithm => assert.equal(algorithm, 'SHA512');
        qz.security.setSignaturePromise = callback => { signer = callback; };
        axios.post = async (_url, body) => { payload = body; return { data: 'signature' }; };
        const state = panel();
        await state.connect();
        state.ticket = 'ticket';
        const message = JSON.stringify({ call: 'print', params: { printer: { name: 'Epson' } }, timestamp: 123 });
        const hash = await hasher(message);
        assert.equal(hash, createHash('sha256').update(message).digest('hex'));
        assert.equal(await new Promise(signer(hash)), 'signature');
        assert.deepEqual(payload, { message, ticket: 'ticket' });
        await assert.rejects(new Promise(signer(hash)), /no autorizada/);
    } finally {
        [qz.websocket.isActive, qz.websocket.connect, qz.api.setSha256Type,
            qz.security.setCertificatePromise, qz.security.setSignatureAlgorithm, qz.security.setSignaturePromise, axios.post] = originals;
        globalThis.crypto = originalCrypto;
    }
});

test('un evento sin impresora configurada queda incierto sin reemitir DTE', async () => {
    const state = panel();
    state.pending = [{ id: 20, state: 'pending' }];
    await state.drain();
    assert.equal(state.pending[0].state, 'uncertain');
    assert.match(state.notice, /impresión sin confirmar/);
});
