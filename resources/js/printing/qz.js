
import qz from 'qz-tray';
import axios from 'axios';

export function registerQzPrinting(Livewire) {
    const settings = window.safQzPrinting;

    if (!settings) return;

    const { endpoints, userId, pendingDocument } = settings;
    const storageKey = `saf:qz:${userId}`;

    let pending = [];
    let busy = false;
    let connection = null;
    let ticket = null;

    // Recuperar trabajos sin repetir aquellos
    // cuyo resultado quedó incierto.
    try {
        pending = JSON.parse(
            sessionStorage.getItem(storageKey) || '[]'
        );

        if (!Array.isArray(pending)) {
            throw new Error('Cola de impresión inválida');
        }

        pending.forEach(job => {
            if (job.state === 'pending') {
                job.state = 'uncertain';
            }
        });

        remember();
    } catch (error) {
        console.error('QZ: almacenamiento inaccesible', error);
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

        if (connection) {
            await connection;
            return;
        }

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
            const digest = await crypto.subtle.digest(
                'SHA-256',
                new TextEncoder().encode(message)
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
                    reject(new Error('Solicitud no autorizada'));
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

        await connection;
    }

    async function send(job) {
        await connect();

        // Laravel valida la plantilla y el formato
        // y devuelve la impresora autorizada.
        const { data: response } = await axios.post(
            endpoints.prepare,
            {
                document_id: job.documentId,
                template: job.template,
                format: job.format,
            }
        );

        const printerName = response.params?.printer?.name;

        if (!printerName) {
            throw new Error(
                'Laravel no indicó una impresora válida'
            );
        }

        // Comprobar que la impresora exista en el puesto.
        const installed = await qz.printers.find();

        if (!installed.includes(printerName)) {
            throw new Error(
                `La impresora ${printerName} no está instalada`
            );
        }

        const config = qz.configs.create(
            response.params.printer,
            response.params.options
        );

        ticket = response.ticket;

        try {
            await qz.print(
                config,
                response.params.data
            );
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
                // Registrar estado incierto antes del envío
                // para evitar reimpresiones automáticas.
                job.state = 'uncertain';
                remember();

                try {
                    await send(job);

                    job.state = 'sent';
                    remember();
                } catch (error) {
                    console.error(
                        'QZ: impresión sin confirmar',
                        job,
                        error
                    );

                    window.alert(
                        `Impresión sin confirmar.\n` +
                        `Documento: ${job.documentId}\n` +
                        `${error.message}\n` +
                        `Verifica el papel antes de reintentar.`
                    );
                }
            }
        } catch (error) {
            console.error('QZ: error de cola', error);

            window.alert(
                'No se pudo guardar el estado de impresión. ' +
                'Verifica el documento antes de reintentar.'
            );
        } finally {
            busy = false;
        }
    }

    function enqueue(payload) {
        if (!payload || typeof payload !== 'object') {
            console.error('QZ: solicitud inválida', payload);
            return;
        }

        const documentId = Number(payload.documentId);
        const template = payload.template;
        const format = payload.format;

        if (
            !Number.isSafeInteger(documentId) ||
            documentId <= 0 ||
            typeof template !== 'string' ||
            !template.trim() ||
            typeof format !== 'string' ||
            !format.trim()
        ) {
            console.error('QZ: solicitud inválida', payload);
            return;
        }

        // Evitar reenvíos automáticos del mismo trabajo.
        if (pending.some(job =>
            job.documentId === documentId &&
            job.template === template &&
            job.format === format
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

            console.error(
                'QZ: no se guardó el trabajo',
                error
            );
            return;
        }

        void drain();
    }

    // Registrar el listener antes de Livewire.start().
    Livewire.on('qz-print-dte', payload => {
        enqueue(payload);
    });

    // Procesar un trabajo recibido tras una redirección.
    if (pendingDocument) {
        enqueue(pendingDocument);
    }
}
