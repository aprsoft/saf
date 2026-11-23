import qz from 'qz-tray';
import axios from 'axios';

export default function printerSettings(endpoints, userId, pendingDocument) {
    return {
        printer: '', printers: [], busy: false, notice: '', pending: [],
        storageKey: `saf:qz:${userId}`, connection: null, ticket: null,
        init() {
            try {
                this.printer = localStorage.getItem('saf:qz:printer') || '';
                this.pending = JSON.parse(sessionStorage.getItem(this.storageKey) || '[]');
                this.pending.forEach(job => { if (job.state === 'pending') job.state = 'uncertain'; });
            } catch { this.notice = 'No se pudo recuperar la configuración del puesto.'; }
            if (pendingDocument) this.enqueue(pendingDocument);
        },
        save() {
            try { localStorage.setItem('saf:qz:printer', this.printer); }
            catch { this.notice = 'El navegador no permite guardar la impresora.'; }
        },
        remember() { sessionStorage.setItem(this.storageKey, JSON.stringify(this.pending)); },
        async connect() {
            if (qz.websocket.isActive()) return;
            if (!this.connection) {
                qz.security.setCertificatePromise((resolve, reject) => {
                    axios.get(endpoints.certificate, { responseType: 'text' })
                        .then(({ data }) => resolve(data)).catch(reject);
                }, { rejectOnFailure: true });
                qz.security.setSignatureAlgorithm('SHA512');
                const messages = new Map();
                qz.api.setSha256Type(async message => {
                    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(message));
                    const hash = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
                    messages.set(hash, { message, ticket: this.ticket });
                    return hash;
                });
                qz.security.setSignaturePromise(hash => (resolve, reject) => {
                    const payload = messages.get(hash);
                    messages.delete(hash);
                    if (!payload) { reject(new Error('Solicitud no autorizada')); return; }
                    axios.post(endpoints.sign, payload, { responseType: 'text' })
                        .then(({ data }) => resolve(data)).catch(reject);
                });
                this.connection = qz.websocket.connect({ retries: 0 }).finally(() => { this.connection = null; });
            }
            await this.connection;
        },
        async detect() {
            if (this.busy) return;
            this.busy = true;
            try {
                await this.connect();
                this.printers = await qz.printers.find();
                this.notice = this.printers.length ? 'Selecciona la impresora de este puesto.' : 'No hay impresoras instaladas.';
            } catch { this.notice = 'No se pudo conectar. Revisa QZ Tray y los certificados del puesto.'; }
            finally { this.busy = false; this.drain(); }
        },
        async send(documentId) {
            if (!this.printer) throw new Error('Selecciona primero una impresora.');
            await this.connect();
            const { data: job } = await axios.post(endpoints.prepare, { document_id: documentId, printer: this.printer });
            const config = qz.configs.create(job.params.printer, job.params.options);
            this.ticket = job.ticket;
            try { await qz.print(config, job.params.data); }
            finally { this.ticket = null; }
        },
        async test() {
            if (this.busy) return;
            this.busy = true;
            try { await this.send(null); this.notice = 'Prueba enviada a QZ Tray. Comprueba el papel.'; }
            catch { this.notice = 'No se pudo completar la prueba. Revisa impresora, QZ Tray y certificados.'; }
            finally { this.busy = false; this.drain(); }
        },
        enqueue(documentId) {
            const id = Number(documentId);
            if (!Number.isSafeInteger(id) || id <= 0 || this.pending.some(job => job.id === id)) return;
            this.pending.push({ id, state: 'pending' });
            try { this.remember(); } catch { this.notice = 'No se pudo guardar el estado de impresión.'; return; }
            this.drain();
        },
        async drain() {
            if (this.busy) return;
            this.busy = true;
            try {
                let job;
                while ((job = this.pending.find(item => item.state === 'pending'))) {
                    // Guardar antes del envío impide repetir automáticamente al recargar.
                    job.state = 'uncertain';
                    this.remember();
                    try {
                        await this.send(job.id);
                        job.state = 'sent';
                        this.notice = `DTE ${job.id} enviado a QZ Tray. Esto no confirma la salida del papel.`;
                    } catch {
                        this.notice = `No se confirmó la impresión del DTE ${job.id}. El cobro y la emisión se conservaron. Revisa el papel antes de reintentar.`;
                    }
                    this.remember();
                }
            } catch { this.notice = 'No se pudo guardar el estado de impresión. Revisa el documento antes de reintentar.'; }
            finally { this.busy = false; }
        },
        retry(job) {
            if (this.busy || !window.confirm('Comprueba que el documento no se imprimió. ¿Enviar otra copia?')) return;
            job.state = 'pending';
            this.remember();
            this.drain();
        },
    };
}
