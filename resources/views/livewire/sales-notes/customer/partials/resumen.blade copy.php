<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <h2 class="mb-3 font-semibold">Resumen de la compra</h2>
    <div class="space-y-2 text-sm">
        <div class="flex justify-between"><span>Subtotal</span><span x-text="formato(subtotal)"></span></div>
        <div class="flex justify-between"><span>Descuentos</span><span x-text="formato(totalDescuentos)"></span></div>
        <div class="flex justify-between border-t border-gray-200 pt-3 text-lg font-bold dark:border-gray-700">
            <span>TOTAL</span><span x-text="formato(total)"></span>
        </div>
    </div>
</section>
