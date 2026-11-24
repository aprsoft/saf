import qz from 'qz-tray';
import axios from 'axios';

// Adaptador del puesto: JavaScript nativo, sin componentes ni directivas Alpine.
export function createPrinterController(endpoints, userId, pendingDocument, ui = () => {}) {
    const state = {
        printer: '', printers: [], busy: false, notice: '', pending: [],
        storageKey: `saf:qz:${userId}`, connection: null, ticket: null,
        notify() { ui(this); },
        init() {
            try {
                this.printer = localStorage.getItem('saf:qz:printer') || '';
                this.pending = JSON.parse(sessionStorage.getItem(this.storageKey) || '[]');
                if (!Array.isArray(this.pending)) this.pending = [];
                this.pending.forEach(job => { if (job.state === 'pending') job.state = 'uncertain'; });
            } catch { this.notice = 'No se pudo recuperar la configuración del puesto.'; }
            this.notify();
            if (pendingDocument) this.enqueue(pendingDocument);
        },
        save(printer) {
            this.printer = printer;
            try { localStorage.setItem('saf:qz:printer', printer); }
            catch { this.notice = 'El navegador no permite guardar la impresora.'; }
            this.notify();
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
                this.connection = qz.websocket.connect({ retries: 0 })
                    .finally(() => { this.connection = null; });
            }
            await this.connection;
        },
        async findPrinters() {
            await this.connect();
            this.printers = await qz.printers.find();
            return this.printers;
        },
        async detect() {
            if (this.busy) return;
            this.busy = true;
            this.notify();
            try {
                await this.findPrinters();
                this.notice = !this.printers.length ? 'No hay impresoras instaladas.'
                    : this.printer && !this.printers.includes(this.printer)
                        ? 'La impresora guardada no está disponible. Selecciona otra.'
                        : 'Impresoras detectadas. La selección se guarda en este navegador.';
            } catch (error) {
                console.error('QZ Tray - detección:', error);
                this.notice = 'No se pudieron detectar impresoras. Revisa la consola.';
            } finally {
                this.busy = false;
                this.notify();
                void this.drain();
            }
        },
        async send(documentId) {
            if (!this.printer) throw new Error('Selecciona primero una impresora.');
            const printers = await this.findPrinters();
            if (!printers.includes(this.printer)) throw new Error('La impresora seleccionada no está disponible.');
            const { data: job } = await axios.post(endpoints.prepare, {
                document_id: documentId, printer: this.printer,
            });
            const config = qz.configs.create(job.params.printer, job.params.options);
            this.ticket = job.ticket;
            try { await qz.print(config, job.params.data); }
            finally { this.ticket = null; }
        },
        async test() {
            if (this.busy) return;
            this.busy = true;
            this.notify();
            try {
                await this.send(null);
                this.notice = 'Prueba enviada a QZ Tray. Comprueba el papel.';
            } catch (error) {
                console.error('QZ Tray - prueba:', error);
                this.notice = 'Falló la impresión de prueba. Revisa la consola.';
            } finally {
                this.busy = false;
                this.notify();
                void this.drain();
            }
        },
        enqueue(documentId) {
            const id = Number(documentId);
            if (!Number.isSafeInteger(id) || id <= 0 || this.pending.some(job => job.id === id)) return;
            this.pending.push({ id, state: 'pending' });
            try { this.remember(); }
            catch {
                this.pending = this.pending.filter(job => job.id !== id);
                this.notice = 'No se pudo guardar el estado de impresión.';
                this.notify();
                return;
            }
            this.notify();
            void this.drain();
        },
        async drain() {
            if (this.busy) return;
            this.busy = true;
            this.notify();
            try {
                let job;
                while ((job = this.pending.find(item => item.state === 'pending'))) {
                    job.state = 'uncertain';
                    this.remember(); // Antes de imprimir: no reenviar automáticamente tras recargar.
                    try {
                        await this.send(job.id);
                        job.state = 'sent';
                        this.notice = `DTE ${job.id} enviado a QZ Tray. Verifica el papel.`;
                    } catch (error) {
                        console.error(`QZ Tray - DTE ${job.id}:`, error);
                        this.notice = `DTE ${job.id}: impresión sin confirmar. El cobro y la emisión se conservaron.`;
                    }
                    this.remember();
                    this.notify();
                }
            } catch (error) {
                console.error('QZ Tray - cola:', error);
                this.notice = 'Error guardando el estado de impresión. Comprueba el papel antes de reintentar.';
            } finally {
                this.busy = false;
                this.notify();
            }
        },
        retry(job) {
            if (this.busy || !confirm('Comprueba que el documento no se imprimió. ¿Enviar otra copia?')) return;
            job.state = 'pending';
            try { this.remember(); }
            catch {
                job.state = 'uncertain';
                this.notice = 'No se pudo guardar el reintento.';
                this.notify();
                return;
            }
            this.notify();
            void this.drain();
        },
    };
    return state;
}

// Registrar una sola vez: Livewire únicamente emite el evento del documento confirmado.
export function registerQzPrinting(Livewire) {
    let controller = null;
    let root = null;
    const boot = () => {
        const element = document.querySelector('[data-qz-panel]');
        if (!element || element === root) return;
        root = element;
        const select = element.querySelector('[data-qz-printer]');
        const detectButton = element.querySelector('[data-qz-detect]');
        const testButton = element.querySelector('[data-qz-test]');
        const notice = element.querySelector('[data-qz-notice]');
        const pendingContainer = element.querySelector('[data-qz-pending]');

        const render = state => {
            notice.textContent = state.notice;
            detectButton.disabled = state.busy;
            testButton.disabled = state.busy || !state.printer;
            select.disabled = state.busy;
            select.replaceChildren(new Option('Seleccionar', ''));
            const names = [...state.printers];
            if (state.printer && !names.includes(state.printer)) names.unshift(state.printer);
            for (const name of names) select.add(new Option(name, name));
            select.value = state.printer;
            pendingContainer.replaceChildren();
            for (const job of state.pending.filter(item => item.state === 'uncertain')) {
                const line = document.createElement('div');
                line.className = 'mt-2 flex items-center gap-3';
                const label = document.createElement('span');
                label.textContent = `DTE ${job.id}: impresión sin confirmar.`;
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'rounded border px-3 py-2 disabled:opacity-50';
                retry.textContent = 'Reintentar solo impresión';
                retry.disabled = state.busy;
                retry.addEventListener('click', () => state.retry(job));
                line.append(label, retry);
                pendingContainer.append(line);
            }
        };
        controller = createPrinterController({
            certificate: element.dataset.qzCertificate,
            prepare: element.dataset.qzPrepare,
            sign: element.dataset.qzSign,
        }, element.dataset.qzUser, element.dataset.qzPendingDocument, render);
        detectButton.addEventListener('click', () => void controller.detect());
        testButton.addEventListener('click', () => void controller.test());
        select.addEventListener('change', () => controller.save(select.value));
        controller.init();
    };

    Livewire.on('qz-print-dte', ({ documentId }) => {
        // No disparar impresión sin panel activo (página incorrecta).
        if (!controller || !root?.isConnected) boot();
        controller?.enqueue(documentId);
    });
    document.addEventListener('livewire:navigated', boot);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
}
