import qz from 'qz-tray';
import axios from 'axios';

const PRINTERS = Object.freeze({
    standard: 'printerStandard',
    thermal: 'printerThermal',
});

export function registerQzPrinting(Livewire) {
    const settings = window.safQzPrinting;

    if (!settings) return;

    const { endpoints, userId, pendingDocument } = settings;
    const storageKey = `saf:qz:${userId}`;

    let pending;
    let busy = false;
    let connection = null;
    let ticket = null;

    // Sin seguimiento fiable, no se habilita la impresión.
    try {
        pending = JSON.parse(
            sessionStorage.getItem(storageKey) || '[]'
        );

        if (!Array.isArray(pending)) {
            throw new Error('Cola inválida');
        }

        pending.forEach(job => {
            if (job.state === 'pending') {
                job.state = 'uncertain';
            }
        });

        sessionStorage.setItem(
            storageKey,
            JSON.stringify(pending)
        );
    } catch (error) {
        console.error('QZ: cola inaccesible', error);
        return;
    }

    function remember() {
        sessionStorage.setItem(
            storageKey,
            JSON.stringify(pending)
        );
    }

    async function connect() {
        if (qz.websocket.isActive()) return;

        if (!connection) {
            qz.security.setCertificatePromise(
                (resolve, reject) => {
                    axios.get(endpoints.certificate, {
                        responseType: 'text',
                    })
                        .then(({ data }) => resolve(data))
                        .catch(reject);
                },
                { rejectOnFailure: true }
            );

            qz.security.setSignatureAlgorithm('SHA512');

            const messages = new Map();

            qz.api.setSha256Type(async message => {
                const bytes = new TextEncoder().encode(message);
                const digest = await crypto.subtle.digest(
                    'SHA-256',
                    bytes
                );

                const hash = Array.from(
                    new Uint8Array(digest),
                    byte => byte.toString(16).padStart(2, '0')
                ).join('');

                messages.set(hash, {
                    message,
                    ticket,
                });

                return hash;
            });

            qz.security.setSignaturePromise(
                hash => (resolve, reject) => {
                    const payload = messages.get(hash);
                    messages.delete(hash);

                    if (!payload) {
                        reject(new Error(
                            'Solicitud QZ no autorizada'
                        ));
                        return;
                    }

                    axios.post(endpoints.sign, payload, {
                        responseType: 'text',
                    })
                        .then(({ data }) => resolve(data))
                        .catch(reject);
                }
            );

            connection = qz.websocket.connect({
                retries: 0,
            }).finally(() => {
                connection = null;
            });
        }

        await connection;
    }

    async function send(job) {
        const printerName = PRINTERS[job.format];

        if (!printerName) {
            throw new Error('Formato no permitido');
        }

        await connect();

        // No se utiliza otra impresora como alternativa.
        const installed = await qz.printers.find();

        if (!installed.includes(printerName)) {
            throw new Error(
                `No existe la impresora ${printerName} en este puesto`
            );
        }

        const { data } = await axios.post(
            endpoints.prepare,
            {
                document_id: job.documentId,
                template: job.template,
                format: job.format,
            }
        );

        const config = qz.configs.create(
            data.params.printer,
            data.params.options
        );

        ticket = data.ticket;

        try {
            await qz.print(config, data.params.data);
        } finally {
            ticket = null;
        }
    }

    async function drain() {
        if (busy) return;

        busy = true;

        try {
            let job;

            while (
                (job = pending.find(
                    item => item.state === 'pending'
                ))
            ) {
                // Persistir antes de enviar para evitar
                // reimpresiones automáticas al recargar.
                job.state = 'uncertain';
                remember();

                try {
                    await send(job);

                    job.state = 'sent';
                    remember();

                    console.info(
                        `DTE ${job.documentId} enviado a QZ Tray`
                    );
                } catch (error) {
                    console.error(
                        `QZ Tray - DTE ${job.documentId}`,
                        error
                    );

                    window.alert(
                        `No se confirmó la impresión del DTE ` +
                        `${job.documentId}.\n` +
                        `${error.message}\n` +
                        `Comprueba el papel antes de reintentar.`
                    );
                }
            }
        } catch (error) {
            console.error('QZ: error de cola', error);

            window.alert(
                'No se pudo registrar el estado de impresión. ' +
                'No reimprimas sin verificar el documento.'
            );
        } finally {
            busy = false;
        }
    }

    function enqueue(payload) {
        const documentId = Number(payload.documentId);
        const template = payload.template;
        const format = payload.format;

        if (!Number.isSafeInteger(documentId) ||
            documentId <= 0 ||
            template !== 'document.dte' ||
            !Object.hasOwn(PRINTERS, format)
        ) {
            console.error('Solicitud QZ inválida', payload);
            return;
        }

        // Evita repetir automáticamente el mismo DTE.
        if (pending.some(job =>
            job.documentId === documentId
        )) {
            return;
        }

        const job = {
            documentId,
            template,
            format,
            state: 'pending',
        };

        pending.push(job);

        try {
            remember();
        } catch (error) {
            pending.pop();
            console.error('QZ: no se guardó el trabajo', error);
            return;
        }

        void drain();
    }

    Livewire.on('qz-print-dte', payload => {
        enqueue(payload);
    });

    if (pendingDocument) {
        // Compatibilidad con el flash anterior basado en ID.
        const payload = typeof pendingDocument === 'object'
            ? pendingDocument
            : {
                documentId: pendingDocument,
                template: 'document.dte',
                format: 'standard',
            };

        enqueue(payload);
    }
}