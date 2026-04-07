@php
    $itemDescriptions = old('item_description', $quote->exists ? $quote->items->pluck('description')->all() : ['']);
    $itemQuantities = old('item_quantity', $quote->exists ? $quote->items->pluck('quantity')->all() : [1]);
    $itemUnitPrices = old('item_unit_price', $quote->exists ? $quote->items->pluck('unit_price')->all() : [0]);
    $selectedContactId = (int) old('contact_id', $quote->contact_id ?? 0);
    $selectedContact = collect($contacts ?? [])->firstWhere('id', $selectedContactId);
    $rowCount = max(1, count($itemDescriptions), count($itemQuantities), count($itemUnitPrices));
    $conceptRows = [];

    for ($i = 0; $i < $rowCount; $i++) {
        $conceptRows[] = [
            'description' => (string) ($itemDescriptions[$i] ?? ''),
            'quantity' => (string) ($itemQuantities[$i] ?? 1),
            'unit_price' => (string) ($itemUnitPrices[$i] ?? 0),
        ];
    }
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="reference_code" value="Código de pedido" />
        <x-text-input id="reference_code" name="reference_code" type="text" class="mt-1 block w-full" :value="old('reference_code', $quote->reference_code)" />
        <x-input-error :messages="$errors->get('reference_code')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="issued_at" value="Fecha de emisión" />
        <x-text-input id="issued_at" name="issued_at" type="date" class="mt-1 block w-full" :value="old('issued_at', optional($quote->issued_at)->format('Y-m-d'))" required />
        <x-input-error :messages="$errors->get('issued_at')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="client_name" value="Cliente" />
        <x-text-input id="client_name" name="client_name" type="text" class="mt-1 block w-full" :value="old('client_name', $quote->client_name)" />
        <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="client_rfc" value="RFC del cliente" />
        <x-text-input id="client_rfc" name="client_rfc" type="text" class="mt-1 block w-full" :value="old('client_rfc', $quote->client_rfc)" />
        <x-input-error :messages="$errors->get('client_rfc')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="location" value="Lugar" />
        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location', $quote->location)" placeholder="Tecámac, Edo. México" />
        <x-input-error :messages="$errors->get('location')" class="mt-2" />
    </div>
</div>

<div>
    <h3 class="text-lg font-semibold text-slate-800">Conceptos</h3>
    <p class="text-sm text-slate-500">Captura los conceptos tal como deseas que aparezcan en el formato de cotización. Puedes agregar tantos conceptos como necesites. Los importes se manejarán en formato monto + IVA (sin cálculo de IVA).</p>

    <div
        class="mt-3 overflow-x-auto"
        x-data="{
            rows: @js($conceptRows),
            errors: @js($errors->getMessages()),
            addRow() {
                this.rows.push({ description: '', quantity: '1', unit_price: '0' });
            },
            removeRow(index) {
                if (this.rows.length === 1) {
                    return;
                }

                this.rows.splice(index, 1);
            },
            firstError(field, index) {
                const key = `${field}.${index}`;
                const messages = this.errors[key] ?? [];

                return messages.length > 0 ? messages[0] : '';
            }
        }"
    >
        <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-lg overflow-hidden">
            <thead class="bg-slate-50 text-slate-600 text-sm">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Descripción</th>
                    <th class="px-3 py-2 text-left font-semibold">Cantidad</th>
                    <th class="px-3 py-2 text-left font-semibold">Precio unitario</th>
                    <th class="px-3 py-2 text-left font-semibold w-28">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template x-for="(row, index) in rows" :key="index">
                    <tr>
                        <td class="px-3 py-2 align-top">
                            <input
                                name="item_description[]"
                                type="text"
                                x-model="row.description"
                                class="w-full rounded-lg border-slate-300 bg-white/90 text-slate-900 placeholder:text-slate-400 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                placeholder="Ejemplo: Configuración de 1272 posiciones..."
                            >
                            <p x-show="firstError('item_description', index)" class="mt-1 text-sm font-medium text-rose-600" x-text="firstError('item_description', index)"></p>
                        </td>
                        <td class="px-3 py-2 align-top w-40">
                            <input
                                name="item_quantity[]"
                                type="number"
                                min="0"
                                step="0.01"
                                x-model="row.quantity"
                                class="w-full rounded-lg border-slate-300 bg-white/90 text-slate-900 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                            >
                            <p x-show="firstError('item_quantity', index)" class="mt-1 text-sm font-medium text-rose-600" x-text="firstError('item_quantity', index)"></p>
                        </td>
                        <td class="px-3 py-2 align-top w-52">
                            <input
                                name="item_unit_price[]"
                                type="number"
                                min="0"
                                step="0.01"
                                x-model="row.unit_price"
                                class="w-full rounded-lg border-slate-300 bg-white/90 text-slate-900 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                            >
                            <p x-show="firstError('item_unit_price', index)" class="mt-1 text-sm font-medium text-rose-600" x-text="firstError('item_unit_price', index)"></p>
                        </td>
                        <td class="px-3 py-2 align-top w-28">
                            <button
                                type="button"
                                @click="removeRow(index)"
                                :disabled="rows.length === 1"
                                class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 border border-rose-300 hover:bg-rose-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="mt-3">
            <button
                type="button"
                @click="addRow()"
                class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-sky-700 border border-sky-300 hover:bg-sky-50 transition"
            >
                Agregar concepto
            </button>
        </div>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="terms" value="Condiciones" />
        <textarea id="terms" name="terms" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 bg-white/90 text-slate-900 placeholder:text-slate-400 shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('terms', $quote->terms) }}</textarea>
        <x-input-error :messages="$errors->get('terms')" class="mt-2" />
    </div>

    <div class="grid gap-4">
        <div>
            <x-input-label for="contact_id" value="Contacto" />
            <select
                id="contact_id"
                name="contact_id"
                class="mt-1 block w-full rounded-lg border-slate-300 bg-white/90 text-slate-900 shadow-sm focus:border-sky-500 focus:ring-sky-500"
            >
                <option value="">Sin contacto asignado</option>
                @foreach ($contacts as $contact)
                    <option value="{{ $contact->id }}" @selected((int) $contact->id === $selectedContactId)>
                        {{ $contact->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('contact_id')" class="mt-2" />
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-700 space-y-1">
            <p class="font-semibold text-slate-900">Datos del contacto seleccionado</p>
            <p><span class="font-medium">Nombre:</span> {{ $selectedContact?->name ?? 'Sin dato' }}</p>
            <p><span class="font-medium">Correo:</span> {{ $selectedContact?->email ?? 'Sin dato' }}</p>
            <p><span class="font-medium">Teléfono:</span> {{ $selectedContact?->phone ?? 'Sin dato' }}</p>
            <p class="text-xs text-slate-500">Los datos del contacto se guardarán en la cotización para conservar el histórico del PDF.</p>
        </div>
    </div>
</div>
