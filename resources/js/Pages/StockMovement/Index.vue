<template>
    <AppLayout>
        <template #header>
            <div class="text-center w-full">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Bestandsänderungen buchen
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <!-- Scanner Button -->
                    <div class="mb-4 text-right w-full">
                        <button
                            @click="startScanner"
                            class="w-full justify-center inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 mr-2"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zm-2 7a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zm8-12a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2h1v1h-1V5zm-2 5a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3zm2 2h1v1h-1v-1z"
                                />
                            </svg>
                            Scanner öffnen
                        </button>
                    </div>

                    <!-- Location and Article Info -->
                    <div v-if="location" class="mb-6">
                        <h3 class="text-lg font-medium mb-4">
                            Lagerort: {{ location.name }}
                        </h3>

                        <div v-if="location.stocks.length" class="space-y-4">
                            <div
                                v-for="stock in location.stocks"
                                :key="stock.id"
                                class="bg-gray-50 p-4 rounded-lg"
                            >
                                <h4 class="font-medium mb-2">
                                    Artikel Details
                                </h4>
                                <p class="mb-1">
                                    <strong>Name:</strong> {{ stock.name }}
                                </p>
                                <p>
                                    <strong>Aktueller Bestand:</strong>
                                    {{ stock.current_stock }}
                                </p>

                                <!-- Stock Movement Form for each article -->
                                <form
                                    @submit.prevent="submitMovement(stock.id)"
                                    class="mt-4 space-y-4"
                                >
                                    <div>
                                        <label
                                            for="quantity"
                                            class="block text-sm font-medium text-gray-700"
                                            >Menge</label
                                        >
                                        <input
                                            id="quantity"
                                            type="number"
                                            v-model="quantities[stock.id]"
                                            min="1"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            required
                                        />
                                    </div>

                                    <div class="flex space-x-4">
                                        <button
                                            type="button"
                                            @click="
                                                submitMovement(stock.id, 'add')
                                            "
                                            class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 mr-2"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            Hinzufügen
                                        </button>
                                        <button
                                            type="button"
                                            @click="
                                                submitMovement(
                                                    stock.id,
                                                    'remove'
                                                )
                                            "
                                            class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 mr-2"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            Entfernen
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div v-else class="bg-yellow-50 p-4 rounded-lg">
                            <p class="text-yellow-700">
                                Keine Artikel an diesem Lagerort
                            </p>
                        </div>
                    </div>

                    <!-- Initial State or Error -->
                    <div v-else class="text-center py-12">
                        <p class="text-gray-500">
                            Bitte scannen Sie einen Lagerplatz-QR-Code
                        </p>
                    </div>

                    <!-- Flash Messages -->
                    <div
                        v-if="flash.message || flash.warning"
                        class="space-y-4 mt-4 text-center"
                    >
                        <div
                            v-if="flash.message"
                            class="p-4 rounded-md"
                            :class="
                                flash.type === 'success'
                                    ? 'bg-green-50 text-green-700'
                                    : 'bg-red-50 text-red-700'
                            "
                        >
                            {{ flash.message }}
                        </div>
                        <div
                            v-if="flash.warning"
                            class="p-4 rounded-md bg-yellow-50 text-yellow-700"
                        >
                            {{ flash.warning }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Scanner Component -->
        <QrScanner
            v-model:showScanner="showScanner"
            @code-scanned="handleScannedCode"
            @error="handleScannerError"
        />
    </AppLayout>
</template>

<script>
import { defineComponent } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import QrScanner from "@/Components/QrScanner.vue";
import Pagination from "@/Components/Pagination.vue";

export default defineComponent({
    components: {
        AppLayout,
        QrScanner,
        Pagination,
    },

    props: {
        movements: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            location: null,
            quantities: {},
            showScanner: false,
            flash: {
                message: null,
                warning: null,
                type: null,
            },
        };
    },

    methods: {
        formatDate(date) {
            return new Date(date).toLocaleString();
        },

        getTypeClass(type) {
            return (
                {
                    in: "text-green-600",
                    out: "text-red-600",
                    transfer: "text-blue-600",
                    correction: "text-yellow-600",
                }[type] || "text-gray-600"
            );
        },

        getTypeLabel(type) {
            return (
                {
                    in: "Eingang",
                    out: "Ausgang",
                    transfer: "Transfer",
                    correction: "Korrektur",
                }[type] || type
            );
        },

        getLocationName(movement) {
            if (movement.type === "in") {
                return movement.to_storage_location?.name || "-";
            }
            return movement.from_storage_location?.name || "-";
        },

        startScanner() {
            this.showScanner = true;
        },

        handleScannedCode(code) {
            try {
                const locationId = code.includes(":")
                    ? code.split(":")[1]
                    : code;
                this.loadLocationData(locationId);
            } catch (error) {
                this.showFlash("Ungültiger QR-Code", "error");
            }
        },

        handleScannerError(error) {
            this.showFlash(error, "error");
        },

        async loadLocationData(locationId) {
            try {
                const response = await axios.get(
                    route("stock.api.location.show", locationId)
                );
                this.location = response.data.location;
                // Initialize quantities for each stock
                this.location.stocks?.forEach((stock) => {
                    this.quantities[stock.id] = 1;
                });
            } catch (error) {
                this.showFlash(
                    error.response?.data?.message ||
                        "Fehler beim Laden der Daten",
                    "error"
                );
            }
        },

        async submitMovement(articleId, moveType) {
            if (!this.quantities[articleId] || this.quantities[articleId] < 1) {
                this.showFlash(
                    "Bitte geben Sie eine gültige Menge ein",
                    "error"
                );
                return;
            }

            try {
                const response = await axios.post(
                    route("stock.api.movement.store"),
                    {
                        location_id: this.location.id,
                        article_id: articleId,
                        quantity: this.quantities[articleId],
                        type: moveType,
                    }
                );

                const data = response.data;

                // Update the stock quantity in the UI
                const stockIndex = this.location.stocks.findIndex(
                    (s) => s.id === articleId
                );
                if (stockIndex !== -1) {
                    this.location.stocks[stockIndex].current_stock =
                        data.current_stock;
                }

                this.showFlash(data.message, "success");

                if (data.warning) {
                    this.flash.warning = data.warning;
                }

                this.quantities[articleId] = 1;
            } catch (error) {
                this.showFlash(
                    error.response?.data?.message ||
                        "Fehler bei der Bestandsänderung",
                    "error"
                );
            }
        },

        showFlash(message, type = "success") {
            this.flash.message = message;
            this.flash.type = type;
            setTimeout(() => {
                this.flash.message = null;
                this.flash.warning = null;
                this.flash.type = null;
            }, 5000);
        },
    },
});
</script>
