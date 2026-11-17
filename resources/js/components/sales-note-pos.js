
/**
 * Mesón de ventas: estado y comportamiento de Alpine.js.
 * El componente Livewire conserva la persistencia y
 * las validaciones de negocio.
 */
export default function salesNotePos({ tipoDocumento = '2' } = {}) {
    return {
        // Estado del documento y sus productos
        tipoDocumento: String(tipoDocumento),
        diasValidez: '15',
        documentoId: null,
        detalle: [],

        // Estado de las ventanas del mesón
        modalAbierto: false,
        modalCancelarAbierto: false,
        productoActual: null,
        editando: null,

        // Edición del ítem
        cantidad: 1,
        descuentoTipo: 'porcentaje',
        descuentoValor: 0,

        // Mensajes y comportamiento de la interfaz
        errorModal: '',
        errorPersistencia: '',
        cargando: false,
        alturaPos: 650,

        ajustarAltura() {
            this.alturaPos = Math.max(420, window.innerHeight - 140);
        },

        formato(valor) {
            return '$' + Math.round(Number(valor) || 0)
                .toLocaleString('es-CL');
        },

        descuentoItem(item) {
            const base = Number(item.precio) * Number(item.cantidad);

            return item.descuentoTipo === 'porcentaje'
                ? Math.round(base * Number(item.descuentoValor) / 100)
                : Math.round(Number(item.descuentoValor));
        },

        get subtotal() {
            return this.detalle.reduce(
                (acumulado, item) =>
                    acumulado + Number(item.precio) * Number(item.cantidad),
                0
            );
        },

        get totalDescuentos() {
            return this.detalle.reduce(
                (acumulado, item) =>
                    acumulado + this.descuentoItem(item),
                0
            );
        },

        get total() {
            return this.subtotal - this.totalDescuentos;
        },

        // Modal de productos
        abrirModal(producto) {
            this.productoActual = { ...producto };
            this.editando = null;
            this.cantidad = 1;
            this.descuentoTipo = 'porcentaje';
            this.descuentoValor = 0;
            this.errorModal = '';
            this.modalAbierto = true;
        },

        editarItem(indice) {
            const item = this.detalle[indice];

            if (!item) return;

            this.productoActual = { ...item };
            this.editando = indice;
            this.cantidad = item.cantidad;
            this.descuentoTipo = item.descuentoTipo;
            this.descuentoValor = item.descuentoValor;
            this.errorModal = '';
            this.modalAbierto = true;
        },

        cerrarModal() {
            this.modalAbierto = false;
            this.productoActual = null;
            this.editando = null;
            this.errorModal = '';
        },

        get subtotalModal() {
            return this.productoActual
                ? Number(this.productoActual.precio) * Number(this.cantidad)
                : 0;
        },

        get descuentoModal() {
            return this.descuentoTipo === 'porcentaje'
                ? Math.round(
                    this.subtotalModal * Number(this.descuentoValor) / 100
                )
                : Math.round(Number(this.descuentoValor));
        },

        get totalModal() {
            return Math.max(0, this.subtotalModal - this.descuentoModal);
        },

        // Operaciones persistentes de productos
        async guardarItem() {
            if (this.cargando || !this.productoActual) return;

            const cantidad = Number(this.cantidad);
            const descuento = Number(this.descuentoValor);

            if (!Number.isFinite(cantidad) || cantidad <= 0) {
                this.errorModal = 'Ingrese una cantidad válida.';
                return;
            }

            if (
                !Number.isFinite(descuento) ||
                descuento < 0 ||
                (this.descuentoTipo === 'porcentaje' && descuento > 100) ||
                (this.descuentoTipo === 'monto' &&
                    descuento > this.subtotalModal)
            ) {
                this.errorModal = 'El descuento no es válido.';
                return;
            }

            this.cargando = true;
            this.errorModal = '';

            try {
                const datos = {
                    id: this.productoActual.id,
                    cantidad,
                    descuentoTipo: this.descuentoTipo,
                    descuentoValor: descuento,
                    tipoDocumento: Number(this.tipoDocumento),
                    diasValidez: Number(this.diasValidez),
                };

                const detalleId = this.editando === null
                    ? null
                    : this.detalle[this.editando].detalleId;

                const guardado = await this.$wire.guardarItem(
                    datos,
                    detalleId
                );

                if (!guardado?.detalleId || !guardado?.documentoId) {
                    throw new Error('No se confirmó el guardado.');
                }

                if (this.editando === null) {
                    this.detalle.push(guardado);
                } else {
                    this.detalle.splice(this.editando, 1, guardado);
                }

                this.documentoId = guardado.documentoId;
                this.cerrarModal();
            } catch (error) {
                this.errorModal = error?.message ||
                    'No se pudo guardar el producto.';
            } finally {
                this.cargando = false;
            }
        },

        async eliminarItem(indice) {
            if (this.cargando) return;

            const item = this.detalle[indice];
            if (!item?.detalleId) return;

            this.cargando = true;
            this.errorPersistencia = '';

            try {
                await this.$wire.eliminarItem(item.detalleId);
                this.detalle.splice(indice, 1);
            } catch (error) {
                this.errorPersistencia = error?.message ||
                    'No se pudo eliminar el producto.';
            } finally {
                this.cargando = false;
            }
        },

        // Acciones del documento
        limpiarPos() {
            this.detalle = [];
            this.documentoId = null;
            this.cerrarModal();
            this.modalCancelarAbierto = false;
        },

        async ejecutarAccion(accion) {
            if (this.cargando || !this.documentoId) return;

            this.cargando = true;
            this.errorPersistencia = '';

            try {
                const ok = await this.$wire[accion]();

                if (ok !== true) {
                    throw new Error(
                        'El servidor no confirmó la operación.'
                    );
                }

                this.limpiarPos();
            } catch (error) {
                this.errorPersistencia = error?.message ||
                    'No fue posible completar la operación.';
            } finally {
                this.cargando = false;
            }
        },

        async recuperarNota(id) {
            if (this.cargando) return;

            if (this.documentoId) {
                this.errorPersistencia =
                    'Deje la nota actual en espera antes de recuperar otra.';
                return;
            }

            this.cargando = true;
            this.errorPersistencia = '';

            try {
                const nota = await this.$wire.recuperar(id);

                if (!Array.isArray(nota?.detalle)) {
                    throw new Error('No se pudo cargar la nota.');
                }

                this.detalle = nota.detalle;
                this.documentoId = nota.documentoId;
            } catch (error) {
                this.errorPersistencia = error?.message ||
                    'No se pudo recuperar la nota.';
            } finally {
                this.cargando = false;
            }
        },
    };
}
