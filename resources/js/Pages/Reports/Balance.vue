<template>
    <AppLayout title="Bilanz">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Bestandsbilanz
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-6 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Von
                            </label>
                            <input
                                type="date"
                                v-model="von"
                                class="mt-1 block rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Bis
                            </label>
                            <input
                                type="date"
                                v-model="bis"
                                class="mt-1 block rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <button
                            @click="aktualisieren"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700"
                        >
                            Anzeigen
                        </button>
                        <button
                            @click="exportieren('pdf')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                        >
                            PDF
                        </button>
                        <button
                            @click="exportieren('xlsx')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                        >
                            Excel
                        </button>
                    </div>

                    <div
                        v-if="$page.props.errors.to"
                        class="mb-4 p-4 rounded-md bg-red-50 text-red-700"
                    >
                        {{ $page.props.errors.to }}
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Artikelnummer
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bezeichnung
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Lieferant
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Menge
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Stückpreis
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Gesamtwert
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template
                                v-for="artikel in articles"
                                :key="artikel.article_id"
                            >
                                <tr
                                    v-for="(zeile, index) in artikel.rows"
                                    :key="artikel.article_id + '-' + index"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ index === 0 ? artikel.sku : "" }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ index === 0 ? artikel.name : "" }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ zeile.supplier }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{ zeile.quantity }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{ waehrung(zeile.unit_price) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        {{ waehrung(zeile.total) }}
                                    </td>
                                </tr>
                                <tr class="bg-gray-50 font-medium">
                                    <td colspan="5" class="px-4 py-2 text-right">
                                        Zwischensumme {{ artikel.name }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ waehrung(artikel.subtotal) }}
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="articles.length === 0">
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                    Im gewählten Zeitraum gibt es keine
                                    Bestandsbewegungen
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 font-semibold">
                                <td colspan="5" class="px-4 py-3 text-right">
                                    Gesamtsumme
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ waehrung(grandTotal) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    from: String,
    to: String,
    articles: Array,
    grandTotal: Number,
});

const page = usePage();

const von = ref(props.from);
const bis = ref(props.to);

// Der Server kann einen anderen Zeitraum verwenden als eingegeben wurde
// (z. B. den Standardzeitraum, wenn beide Felder geleert werden). Da
// router.get mit preserveState arbeitet und die Komponente nicht neu
// gemountet wird, müssen die Eingabefelder bei neuen Props aktiv
// nachgezogen werden, damit sie nie von der angezeigten Tabelle abweichen.
//
// Ausnahme: Bei einer fehlgeschlagenen Validierung leitet Laravel auf die
// zuletzt erfolgreich aufgerufene URL zurück, sodass from/to hier die alten,
// gültigen Werte enthalten. In dem Fall dürfen die Eingabefelder nicht
// überschrieben werden, sonst verschwinden die vom Benutzer eingetippten
// (fehlerhaften) Werte, obwohl die Fehlermeldung noch angezeigt wird.
watch(
    () => props.from,
    (wert) => {
        if (!page.props.errors.from && !page.props.errors.to) {
            von.value = wert;
        }
    }
);

watch(
    () => props.to,
    (wert) => {
        if (!page.props.errors.from && !page.props.errors.to) {
            bis.value = wert;
        }
    }
);

const aktualisieren = () => {
    router.get(
        route("reports.balance.index"),
        { from: von.value, to: bis.value },
        { preserveState: true, preserveScroll: true }
    );
};

const exportieren = (format) => {
    window.location.href = route("reports.balance.export", {
        from: von.value,
        to: bis.value,
        format,
    });
};

const waehrung = (wert) =>
    new Intl.NumberFormat("de-DE", {
        style: "currency",
        currency: "EUR",
    }).format(wert);
</script>
