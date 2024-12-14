<template>
    <div>
        <div class="flex justify-between mb-4">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-medium">Regalbodenverwaltung</h3>
                <Link
                    :href="route('shelves.trashed')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Papierkorb
                </Link>
            </div>
            <Link
                :href="route('shelves.create')"
                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700"
            >
                Neuen Regalboden anlegen
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
                        Lagerplätze
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Beschreibung
                    </th>
                    <th
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Aktionen
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="shelf in shelves" :key="shelf.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ shelf.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ shelf.rack.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ shelf.rack.warehouse.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ shelf.storage_locations.length }}
                    </td>
                    <td class="px-6 py-4">{{ shelf.description }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <Link
                            :href="route('shelves.edit', shelf.id)"
                            class="text-indigo-600 hover:text-indigo-900 mr-2"
                        >
                            Bearbeiten
                        </Link>
                        <button
                            @click="openDeleteDialog(shelf)"
                            class="text-red-600 hover:text-red-900"
                        >
                            Löschen
                        </button>
                    </td>
                </tr>
                <tr v-if="shelves.length === 0">
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        Keine Regalböden vorhanden
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Delete Confirmation Dialog -->
        <Modal :show="showDeleteDialog" @close="closeDeleteDialog">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Regalboden löschen
                </h2>

                <div class="mt-3" v-if="shelfToDelete">
                    <p class="text-sm text-gray-600">
                        Sind Sie sicher, dass Sie diesen Regalboden löschen
                        möchten? Der Regalboden wird in den Papierkorb
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
                                {{ shelfToDelete.storage_locations_count || 0 }}
                                Lagerplätze
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
                        Regalboden in Papierkorb verschieben
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
    shelves: {
        type: Array,
        required: true,
    },
});

const showDeleteDialog = ref(false);
const shelfToDelete = ref(null);
const form = useForm({});

const openDeleteDialog = (shelf) => {
    shelfToDelete.value = shelf;
    showDeleteDialog.value = true;
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    shelfToDelete.value = null;
};

const confirmDelete = () => {
    if (shelfToDelete.value) {
        form.delete(route("shelves.destroy", shelfToDelete.value.id), {
            onSuccess: () => closeDeleteDialog(),
        });
    }
};
</script>
