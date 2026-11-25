import qz from 'qz-tray';
import axios from 'axios';

export function registerQzPrinting(Livewire) {
    const settings = window.safQzPrinting;
    if (!settings) return;

    const { endpoints, pendingDocument } = settings;
    let busy = false;
    let connecting = null;
    let currentTicket = null;

    async function connect() {
        if (qz.websocket.isActive()) return;
        if (connecting) return connecting;

        qz.security.setCertificatePromise(
            (resolve, reject) => {
                axios.get(endpoints.certificate, { responseType: 'text' })
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

            messages.set(hash, { message, ticket: currentTicket });
            return hash;
        });

        qz.security.setSignaturePromise(
            hash => (resolve, reject) => {
                const payload = messages.get(hash);
                messages.delete(hash);
                if (!payload) {
                    reject(new Error('Solicitud QZ no autorizada'));
                    return;
                }
                axios.post(endpoints.sign, payload, { responseType: 'text' })
                    .then(({ data }) => resolve(data))
                    .catch(reject);
            }
        );

        connecting = qz.websocket.connect({ retries: 0 })
            .finally(() => { connecting = null; });

        return connecting;
    }

    async function print(payload) {
        if (busy) {
            console.warn('QZ: ya hay una impresión en curso');
            window.alert('Hay una impresión en curso. Intenta nuevamente cuando termine.');
            return;
        }

        const recordId = Number(payload?.recordId ?? payload?.documentId);
        const template = payload?.template;
        const format = payload?.format;

        if (
            !Number.isSafeInteger(recordId) ||
            recordId <= 0 ||
            typeof template !== 'string' || !template.trim() ||
            typeof format !== 'string' || !format.trim()
        ) {
            console.error('QZ: solicitud inválida', payload);
            return;
        }

        busy = true;

        try {
            await connect();

            const { data: job } = await axios.post(endpoints.prepare, {
                document_id: recordId,
                template,
                format,
            });

            const printerName = job.params?.printer?.name;
            if (!printerName) {
                throw new Error('Laravel no indicó una impresora');
            }

            const printers = await qz.printers.find();
            if (!printers.includes(printerName)) {
                throw new Error(`La impresora ${printerName} no está disponible`);
            }

            const config = qz.configs.create(
                job.params.printer,
                job.params.options
            );

            currentTicket = job.ticket;
            await qz.print(config, job.params.data);
            console.info('QZ: trabajo aceptado', { recordId, template, format });
        } catch (error) {
            console.error('QZ: impresión no confirmada', error);
            window.alert(
                'No se confirmó la impresión. Comprueba si salió papel antes de reintentar. ' +
                (error.response?.data?.message || error.message)
            );
        } finally {
            currentTicket = null;
            busy = false;
        }
    }

    Livewire.on('qz-print', payload => {
        void print(payload);
    });

    // Caja separada: recuperar una sola vez la petición de la redirección.
    if (pendingDocument) {
        void print(pendingDocument);
    }
}
