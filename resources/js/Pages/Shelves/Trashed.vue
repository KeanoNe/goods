<template>
    <AppLayout title="Gelöschte Regalböden">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gelöschte Regalböden
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <div class="mb-6">
                        <Link
                            :href="
                                route('warehouses.index', { tab: 'shelves' })
                            "
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            ← Zurück zur Regalbodenverwaltung
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
                                    Gelöscht am
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="shelf in trashedShelves" :key="shelf.id">
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
                                    {{ shelf.storage_locations_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ formatDate(shelf.deleted_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="restore(shelf)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Wiederherstellen
                                    </button>
                                    <button
                                        @click="openForceDeleteDialog(shelf)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Endgültig löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="trashedShelves.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-4 text-center text-gray-500"
                                >
                                    Keine gelöschten Regalböden vorhanden
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Force Delete Confirmation Dialog -->
                    <Modal
                        :show="showForceDeleteDialog"
                        @close="closeForceDeleteDialog"
                    >
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-red-900">
                                Regalboden endgültig löschen
                            </h2>

                            <div class="mt-3" v-if="shelfToDelete">
                                <p class="text-sm text-gray-600">
                                    Sind Sie sicher, dass Sie diesen Regalboden
                                    endgültig löschen möchten? Diese Aktion kann
                                    nicht rückgängig gemacht werden.
                                </p>

                                <div class="mt-4 bg-gray-50 rounded-md p-4">
                                    <h3
                                        class="text-sm font-medium text-gray-900"
                                    >
                                        Abhängige Elemente:
                                    </h3>
                                    <ul
                                        class="mt-2 list-disc list-inside text-sm text-gray-600"
                                    >
                                        <li>
                                            {{
                                                shelfToDelete.storage_locations_count ||
                                                0
                                            }}
                                            Lagerplätze
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                                    @click="closeForceDeleteDialog"
                                >
                                    Abbrechen
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700"
                                    @click="confirmForceDelete"
                                    :disabled="form.processing"
                                >
                                    Endgültig löschen
                                </button>
                            </div>
                        </div>
                    </Modal>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    trashedShelves: {
        type: Array,
        required: true,
    },
});

const showForceDeleteDialog = ref(false);
const shelfToDelete = ref(null);
const form = useForm({});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("de-DE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const restore = (shelf) => {
    form.put(route("shelves.restore", shelf.id));
};

const openForceDeleteDialog = (shelf) => {
    shelfToDelete.value = shelf;
    showForceDeleteDialog.value = true;
};

const closeForceDeleteDialog = () => {
    showForceDeleteDialog.value = false;
    shelfToDelete.value = null;
};

const confirmForceDelete = () => {
    if (shelfToDelete.value) {
        form.delete(route("shelves.force-delete", shelfToDelete.value.id), {
            onSuccess: () => closeForceDeleteDialog(),
        });
    }
};
</script>
