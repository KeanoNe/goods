<template>
    <AppLayout :title="article.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Artikel: {{ article.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Aktions-Leiste -->
                <div
                    class="bg-white shadow sm:rounded-lg p-4 mb-6 flex justify-between items-center"
                >
                    <Link
                        :href="route('articles.index', { tab: 'shelves' })"
                        class="text-indigo-600 hover:text-indigo-900"
                    >
                        ← Zurück zur Artikelübersicht
                    </Link>

                    <div class="flex space-x-3">
                        <Link
                            :href="route('articles.edit', article.id)"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                        >
                            Bearbeiten
                        </Link>
                        <button
                            @click="confirmDelete(article)"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700"
                        >
                            Löschen
                        </button>
                    </div>
                </div>

                <!-- Artikel Details -->
                <div class="bg-white shadow sm:rounded-lg mb-6">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            Artikeldetails
                        </h3>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <dl
                            class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2"
                        >
                            <div class="sm:col-span-1">
                                <dt class="text-sm font-medium text-gray-500">
                                    SKU
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ article.sku }}
                                </dd>
                            </div>
                            <div class="sm:col-span-1">
                                <dt class="text-sm font-medium text-gray-500">
                                    Mindestbestand
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ article.minimum_stock }}
                                </dd>
                            </div>
                            <div class="sm:col-span-1">
                                <dt class="text-sm font-medium text-gray-500">
                                    Barcode
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ article.barcode || "Nicht verfügbar" }}
                                </dd>
                            </div>
                            <div class="sm:col-span-1">
                                <dt class="text-sm font-medium text-gray-500">
                                    QR Code
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ article.qr_code || "Nicht verfügbar" }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">
                                    Beschreibung
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{
                                        article.description ||
                                        "Keine Beschreibung verfügbar"
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Aktuelle Bestände -->
                <div class="bg-white shadow sm:rounded-lg mb-6">
                    <div
                        class="px-4 py-5 sm:px-6 flex justify-between items-center"
                    >
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            Aktuelle Bestände
                        </h3>
                        <button
                            @click="showAddLocationModal = true"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                        >
                            Lagerplatz zuweisen
                        </button>
                    </div>
                    <div class="border-t border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Lagerplatz
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Regal
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Lager
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Bestand
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-right"
                                    ></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="stock in article.stocks"
                                    :key="stock.id"
                                >
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ stock.storage_location.name }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{
                                            stock.storage_location.shelf.rack
                                                .name
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{
                                            stock.storage_location.shelf.rack
                                                .warehouse.name
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ stock.quantity }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex space-x-2 justify-end"
                                    >
                                        <button
                                            @click="
                                                removeStorageLocation(
                                                    stock.storage_location.id
                                                )
                                            "
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            Entfernen
                                        </button>
                                        <button
                                            @click="openCorrectionModal(stock)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Korrigieren
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="article.stocks.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-6 py-4 text-center text-gray-500"
                                    >
                                        Keine Bestände vorhanden
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Diagramm mit Nettobestandsänderungen -->
                <div class="bg-white shadow sm:rounded-lg p-4 mb-6">
                    <h3
                        class="text-lg font-medium leading-6 text-gray-900 mb-4"
                    >
                        Nettobestandsänderungen der letzten 14 Tage
                    </h3>
                    <apexchart
                        type="bar"
                        :options="barChartOptions"
                        :series="barChartSeries"
                        height="300"
                    ></apexchart>
                </div>

                <!-- Liniendiagramm mit tatsächlichen Beständen -->
                <div class="bg-white shadow sm:rounded-lg p-4 mb-6">
                    <h3
                        class="text-lg font-medium leading-6 text-gray-900 mb-4"
                    >
                        Tatsächliche Bestände der letzten 14 Tage
                    </h3>
                    <apexchart
                        type="line"
                        :options="lineChartOptions"
                        :series="lineChartSeries"
                        height="300"
                    ></apexchart>
                </div>

                <!-- Bestandsbewegungen -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            Bestandsbewegungen
                        </h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Datum
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Typ
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Von
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Nach
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Menge
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Benutzer
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="movement in stockMovements.data"
                                    :key="movement.id"
                                >
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ formatDate(movement.created_at) }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ movement.type }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{
                                            movement.from_storage_location
                                                ? movement.from_storage_location
                                                      .name
                                                : ""
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{
                                            movement.to_storage_location
                                                ? movement.to_storage_location
                                                      .name
                                                : ""
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ movement.quantity }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ movement.user.name }}
                                    </td>
                                </tr>
                                <tr v-if="stockMovements.data.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-6 py-4 text-center text-gray-500"
                                    >
                                        Keine Bestandsbewegungen vorhanden
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div
                            v-if="stockMovements.data.length > 0"
                            class="px-6 py-4 bg-gray-50"
                        >
                            <Pagination :links="stockMovements.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal zum Zuweisen eines Lagerplatzes -->
        <Modal :show="showAddLocationModal" @close="closeAddLocationModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Lagerplatz zuweisen
                </h2>

                <form @submit.prevent="assignStorageLocation">
                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Lagerplatz</label
                        >
                        <select
                            v-model="newStorageLocationId"
                            class="border border-gray-300 rounded-md p-2 w-full"
                        >
                            <option value="" disabled>Bitte wählen</option>
                            <option
                                v-for="location in availableStorageLocations"
                                :key="location.id"
                                :value="location.id"
                            >
                                {{ location.name }} ({{
                                    location.shelf.rack.warehouse.name
                                }})
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Menge</label
                        >
                        <input
                            v-model.number="newStorageQuantity"
                            type="number"
                            min="0"
                            class="border border-gray-300 rounded-md p-2 w-full"
                            placeholder="Menge"
                        />
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                            @click="closeAddLocationModal"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700"
                        >
                            Hinzufügen
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal für Bestandskorrektur -->
        <Modal :show="showCorrectionModal" @close="closeCorrectionModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Bestandskorrektur für
                    {{ correctionStock?.storage_location.name }}
                </h2>

                <form @submit.prevent="submitCorrection">
                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Neuer Bestand</label
                        >
                        <input
                            v-model.number="correctionQuantity"
                            type="number"
                            min="0"
                            class="border border-gray-300 rounded-md p-2 w-full"
                            placeholder="Neue Bestandsmenge"
                        />
                    </div>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Notizen (optional)</label
                        >
                        <textarea
                            v-model="correctionNotes"
                            class="border border-gray-300 rounded-md p-2 w-full"
                            placeholder="Grund oder Kommentar zur Korrektur"
                        >
                        </textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                            @click="closeCorrectionModal"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700"
                        >
                            Korrigieren
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Lösch-Dialog -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Artikel löschen
                </h2>

                <p class="mt-3 text-sm text-gray-600">
                    Sind Sie sicher, dass Sie diesen Artikel löschen möchten?
                    Diese Aktion kann rückgängig gemacht werden.
                </p>

                <div class="mt-4">
                    <div class="bg-gray-50 rounded-md p-4">
                        <h3 class="text-sm font-medium text-gray-900">
                            Zu löschender Artikel:
                        </h3>
                        <div class="mt-2 text-sm text-gray-600">
                            <p>
                                <strong>SKU:</strong> {{ articleToDelete?.sku }}
                            </p>
                            <p>
                                <strong>Name:</strong>
                                {{ articleToDelete?.name }}
                            </p>
                            <p>
                                <strong>Gesamtbestand:</strong>
                                {{ articleToDelete?.stocks_sum_quantity || 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                        @click="closeDeleteModal"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700"
                        @click="deleteArticle"
                    >
                        Löschen
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import Modal from "@/Components/Modal.vue";
import Pagination from "@/Components/Pagination.vue";
import VueApexCharts from "vue3-apexcharts";

const props = defineProps({
    article: Object,
    stockMovements: Object,
    availableStorageLocations: Array,
    dailyChanges: Array,
    cumulativeStockData: Array,
});

const barChartLabels = props.dailyChanges.map((d) => d.date);
const barChartData = props.dailyChanges.map((d) => d.net_change);

const barChartOptions = ref({
    chart: {
        toolbar: { show: false },
    },
    xaxis: {
        categories: barChartLabels,
        labels: { rotate: -45 },
    },
    yaxis: { title: { text: "Nettobestandsänderung" } },
    title: { text: "Nettobestandsänderungen pro Tag", align: "center" },
    colors: [
        function ({ value }) {
            return value < 0 ? "#EF4444" : "#10B981";
        },
    ],
    plotOptions: {
        bar: {
            distributed: false,
        },
    },
});

const barChartSeries = ref([{ name: "Änderung", data: barChartData }]);

// Daten für das Line-Chart (Tatsächliche Bestände)
const lineChartLabels = props.cumulativeStockData.map((d) => d.date);
const lineChartData = props.cumulativeStockData.map((d) => d.stock);

const lineChartOptions = ref({
    chart: {
        toolbar: { show: false },
    },
    xaxis: {
        categories: lineChartLabels,
        labels: { rotate: -45 },
    },
    yaxis: { title: { text: "Bestand" } },
    title: { text: "Täglicher Endbestand", align: "center" },
});

const lineChartSeries = ref([{ name: "Bestand", data: lineChartData }]);

const showDeleteModal = ref(false);
const articleToDelete = ref(null);

const showAddLocationModal = ref(false);
const newStorageLocationId = ref("");
const newStorageQuantity = ref(0);

const showCorrectionModal = ref(false);
const correctionStock = ref(null);
const correctionQuantity = ref(null);
const correctionNotes = ref("");

// Löschen
const confirmDelete = (article) => {
    articleToDelete.value = article;
    showDeleteModal.value = true;
};
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    articleToDelete.value = null;
};
const deleteArticle = () => {
    if (articleToDelete.value) {
        router.delete(route("articles.destroy", articleToDelete.value.id), {
            onSuccess: () => {
                closeDeleteModal();
            },
        });
    }
};

// Zuweisen von Lagerplätzen
const assignStorageLocation = () => {
    if (!newStorageLocationId.value) {
        alert("Bitte wählen Sie einen Lagerplatz aus.");
        return;
    }

    router.post(
        route("articles.storage-locations.store", {
            article: props.article.id,
        }),
        {
            storage_location_id: newStorageLocationId.value,
            quantity: newStorageQuantity.value,
        },
        {
            onSuccess: () => {
                newStorageLocationId.value = "";
                newStorageQuantity.value = 0;
                closeAddLocationModal();
            },
        }
    );
};
const closeAddLocationModal = () => {
    showAddLocationModal.value = false;
    newStorageLocationId.value = "";
    newStorageQuantity.value = 0;
};

// Entfernen von Lagerplatz-Zuordnungen
const removeStorageLocation = (storageLocationId) => {
    if (!confirm("Möchten Sie diesen Lagerplatz wirklich entfernen?")) {
        return;
    }
    router.delete(
        route("articles.storage-locations.destroy", {
            article: props.article.id,
            storageLocation: storageLocationId,
        })
    );
};

// Bestandskorrektur
const openCorrectionModal = (stock) => {
    correctionStock.value = stock;
    correctionQuantity.value = stock.quantity;
    correctionNotes.value = "";
    showCorrectionModal.value = true;
};

const closeCorrectionModal = () => {
    showCorrectionModal.value = false;
    correctionStock.value = null;
    correctionQuantity.value = null;
    correctionNotes.value = "";
};

const submitCorrection = () => {
    if (correctionStock.value && correctionQuantity.value != null) {
        router.post(
            route("articles.storage-locations.correction", {
                article: props.article.id,
                storageLocation: correctionStock.value.storage_location.id,
            }),
            {
                new_quantity: correctionQuantity.value,
                notes: correctionNotes.value,
            },
            {
                onSuccess: () => {
                    closeCorrectionModal();
                },
            }
        );
    }
};

const formatDate = (date) => {
    return new Date(date).toLocaleString("de-DE");
};
</script>
