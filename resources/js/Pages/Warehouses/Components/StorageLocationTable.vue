<template>
    <div>
        <div class="flex justify-between mb-4">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-medium">Lagerplatzverwaltung</h3>
                <Link
                    :href="route('storage-locations.trashed')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Papierkorb
                </Link>
            </div>
            <Link
                :href="route('storage-locations.create')"
                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700"
            >
                Neuen Lagerplatz anlegen
            </Link>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Name
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Regalboden
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Regal
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Lager
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Artikel
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        QR/Barcode
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Aktionen
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="location in locations" :key="location.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ location.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ location.shelf.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ location.shelf.rack.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ location.shelf.rack.warehouse.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{
                            location.articles.length == 1
                                ? location.articles[0].name
                                : ""
                        }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span
                            v-if="location.qr_code || location.barcode"
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                        >
                            Vorhanden
                        </span>
                        <span
                            v-else
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800"
                        >
                            Nicht vorhanden
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <Link
                            :href="route('storage-locations.edit', location.id)"
                            class="text-indigo-600 hover:text-indigo-900 mr-2"
                        >
                            Bearbeiten
                        </Link>
                        <button
                            @click="openDeleteDialog(location)"
                            class="text-red-600 hover:text-red-900"
                        >
                            Löschen
                        </button>
                    </td>
                </tr>
                <tr v-if="locations.length === 0">
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        Keine Lagerplätze vorhanden
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Delete Confirmation Dialog -->
        <Modal :show="showDeleteDialog" @close="closeDeleteDialog">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Lagerplatz löschen
                </h2>

                <div class="mt-3" v-if="locationToDelete">
                    <p class="text-sm text-gray-600">
                        Sind Sie sicher, dass Sie diesen Lagerplatz löschen
                        möchten? Der Lagerplatz wird in den Papierkorb
                        verschoben und kann wiederhergestellt werden.
                    </p>

                    <div class="mt-4 bg-gray-50 rounded-md p-4">
                        <h3 class="text-sm font-medium text-gray-900">
                            Abhängige Elemente:
                        </h3>
                        <ul
                            class="mt-2 list-disc list-inside text-sm text-gray-600"
                        >
                            <li>
                                {{ locationToDelete.stocks_count || 0 }}
                                gelagerte Artikel
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                        @click="closeDeleteDialog"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700"
                        @click="confirmDelete"
                        :disabled="form.processing"
                    >
                        Lagerplatz in Papierkorb verschieben
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    locations: {
        type: Array,
        required: true,
    },
});

const showDeleteDialog = ref(false);
const locationToDelete = ref(null);
const form = useForm({});

const openDeleteDialog = (location) => {
    locationToDelete.value = location;
    showDeleteDialog.value = true;
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    locationToDelete.value = null;
};

const confirmDelete = () => {
    if (locationToDelete.value) {
        form.delete(
            route("storage-locations.destroy", locationToDelete.value.id),
            {
                onSuccess: () => closeDeleteDialog(),
            }
        );
    }
};
</script>
