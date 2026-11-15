<div x-data="{
            tipoDocumento: @js((string) ($this->tipoDocumentos[0]['id'] ?? 2)),
            documentoId: null,
            detalle: [],
            modalAbierto: false,
            modalCancelarAbierto: false,
            productoActual: null,
            editando: null,
            cantidad: 1,
            descuentoTipo: 'porcentaje',
            descuentoValor: 0,
            errorModal: '',
            errorPersistencia: '',
            cargando: false,
            alturaPos: 650,
            ajustarAltura() { this.alturaPos = Math.max(420, window.innerHeight - 140); },
            formato(v) { return '$' + Math.round(Number(v) || 0).toLocaleString('es-CL'); },
            descuentoItem(item) {
                const base = Number(item.precio) * Number(item.cantidad);
                return item.descuentoTipo === 'porcentaje'
                    ? Math.round(base * Number(item.descuentoValor) / 100)
                    : Math.round(Number(item.descuentoValor));
            },
            get subtotal() { return this.detalle.reduce((n, d) => n + Number(d.precio) * Number(d.cantidad), 0); },
            get totalDescuentos() { return this.detalle.reduce((n, d) => n + this.descuentoItem(d), 0); },
            get total() { return this.subtotal - this.totalDescuentos; },
            abrirModal(producto) {
                this.productoActual = {...producto}; this.editando = null;
                this.cantidad = 1; this.descuentoTipo = 'porcentaje';
                this.descuentoValor = 0; this.errorModal = ''; this.modalAbierto = true;
            },
            editarItem(indice) {
                const item = this.detalle[indice]; if (!item) return;
                this.productoActual = {...item}; this.editando = indice;
                this.cantidad = item.cantidad; this.descuentoTipo = item.descuentoTipo;
                this.descuentoValor = item.descuentoValor;
                this.errorModal = ''; this.modalAbierto = true;
            },
            cerrarModal() { this.modalAbierto = false; this.productoActual = null; this.editando = null; this.errorModal = ''; },
            get subtotalModal() { return this.productoActual ? Number(this.productoActual.precio) * Number(this.cantidad) : 0; },
            get descuentoModal() { return this.descuentoTipo === 'porcentaje'
                ? Math.round(this.subtotalModal * Number(this.descuentoValor) / 100)
                : Math.round(Number(this.descuentoValor)); },
            get totalModal() { return Math.max(0, this.subtotalModal - this.descuentoModal); },
            async guardarItem() {
                if (this.cargando || !this.productoActual) return;
                const cantidad = Number(this.cantidad), descuento = Number(this.descuentoValor);
                if (!Number.isFinite(cantidad) || cantidad <= 0) { this.errorModal = 'Ingrese una cantidad válida.'; return; }
                if (!Number.isFinite(descuento) || descuento < 0 ||
                    (this.descuentoTipo === 'porcentaje' && descuento > 100) ||
                    (this.descuentoTipo === 'monto' && descuento > this.subtotalModal)) {
                    this.errorModal = 'El descuento no es válido.'; return;
                }
                this.cargando = true; this.errorModal = '';
                try {
                    const datos = {id: this.productoActual.id, cantidad,
                        descuentoTipo: this.descuentoTipo, descuentoValor: descuento};
                    const id = this.editando === null ? null : this.detalle[this.editando].detalleId;
                    const guardado = await $wire.guardarItem(datos, id);
                    if (!guardado?.detalleId || !guardado?.documentoId) throw new Error('No se confirmó el guardado.');
                    if (this.editando === null) this.detalle.push(guardado);
                    else this.detalle.splice(this.editando, 1, guardado);
                    this.documentoId = guardado.documentoId;
                    this.cerrarModal();
                } catch (e) { this.errorModal = e?.message || 'No se pudo guardar el producto.'; }
                finally { this.cargando = false; }
            },
            async eliminarItem(indice) {
                if (this.cargando) return;
                const item = this.detalle[indice]; if (!item?.detalleId) return;
                this.cargando = true; this.errorPersistencia = '';
                try { await $wire.eliminarItem(item.detalleId); this.detalle.splice(indice, 1); }
                catch(e) { this.errorPersistencia = e?.message || 'No se pudo eliminar el producto.'; }
                finally { this.cargando = false; }
            },
            limpiarPos() { this.detalle = []; this.documentoId = null; this.cerrarModal(); this.modalCancelarAbierto = false; },
            async ejecutarAccion(accion) {
                if (this.cargando || !this.documentoId) return;
                this.cargando = true; this.errorPersistencia = '';
                try {
                    const ok = await $wire[accion]();
                    if (ok !== true) throw new Error('El servidor no confirmó la operación.');
                    this.limpiarPos();
                } catch(e) { this.errorPersistencia = e?.message || 'No fue posible completar la operación.'; }
                finally { this.cargando = false; }
            },
            async recuperarNota(id) {
                if (this.cargando) return;
                if (this.documentoId) { this.errorPersistencia = 'Deje la nota actual en espera antes de recuperar otra.'; return; }
                this.cargando = true; this.errorPersistencia = '';
                try {
                    const nota = await $wire.recuperar(id);
                    if (!Array.isArray(nota?.detalle)) throw new Error('No se pudo cargar la nota.');
                    this.detalle = nota.detalle; this.documentoId = nota.documentoId;
                } catch(e) { this.errorPersistencia = e?.message || 'No se pudo recuperar la nota.'; }
                finally { this.cargando = false; }
            },
        }" x-init="ajustarAltura()" x-on:resize.window="ajustarAltura()"
     class="flex flex-col gap-3 xl:min-h-[70vh] text-gray-900 dark:text-gray-100">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-xl font-bold">Crear documento</h1>
            <p class="text-xs text-gray-500">Ventas / Nuevo documento</p></div>
        <span x-show="documentoId" x-text="'Nota N.º ' + documentoId"
              class="rounded-full bg-blue-50 px-3 py-1 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-200"></span>
    </header>
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(340px,1fr)]">
        <main class="min-w-0 space-y-3">
            @include('livewire.document.customer.partials.catalogo')
            @include('livewire.document.customer.partials.detalle')
        </main>
        <aside class="min-w-0 space-y-3">
            @include('livewire.document.customer.partials.documento')
            @include('livewire.document.customer.partials.cliente')
            @include('livewire.document.customer.partials.resumen')
            @include('livewire.document.customer.partials.acciones')
        </aside>
    </div>
    @include('livewire.document.customer.partials.modals.producto')
    @include('livewire.document.customer.partials.modals.recuperar-nota')
    @include('livewire.document.customer.partials.modals.cancelar-nota')
</div>
