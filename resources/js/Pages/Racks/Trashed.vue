<template>
    <AppLayout title="Gelöschte Regale">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gelöschte Regale
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <div class="mb-6">
                        <Link
                            :href="route('warehouses.index', { tab: 'racks' })"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            ← Zurück zur Regalverwaltung
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
                                    Lager
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    Regalböden
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
                            <tr v-for="rack in trashedRacks" :key="rack.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ rack.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ rack.warehouse.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ rack.shelves_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ rack.storage_locations_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ formatDate(rack.deleted_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="restore(rack)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Wiederherstellen
                                    </button>
                                    <button
                                        @click="openForceDeleteDialog(rack)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Endgültig löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="trashedRacks.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-4 text-center text-gray-500"
                                >
                                    Keine gelöschten Regale vorhanden
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
                                Regal endgültig löschen
                            </h2>

                            <div class="mt-3" v-if="rackToDelete">
                                <p class="text-sm text-gray-600">
                                    Sind Sie sicher, dass Sie dieses Regal
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
                                                rackToDelete.shelves_count || 0
                                            }}
                                            Regalböden
                                        </li>
                                        <li>
                                            {{
                                                rackToDelete.storage_locations_count ||
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
    trashedRacks: {
        type: Array,
        required: true,
    },
});

const showForceDeleteDialog = ref(false);
const rackToDelete = ref(null);
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

const restore = (rack) => {
    form.put(route("racks.restore", rack.id));
};

const openForceDeleteDialog = (rack) => {
    rackToDelete.value = rack;
    showForceDeleteDialog.value = true;
};

const closeForceDeleteDialog = () => {
    showForceDeleteDialog.value = false;
    rackToDelete.value = null;
};

const confirmForceDelete = () => {
    if (rackToDelete.value) {
        form.delete(route("racks.force-delete", rackToDelete.value.id), {
            onSuccess: () => closeForceDeleteDialog(),
        });
    }
};
</script>
