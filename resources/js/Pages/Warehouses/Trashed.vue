<template>
    <AppLayout title="Gelöschte Lager">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gelöschte Lager
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <div class="mb-6">
                        <Link
                            :href="route('warehouses.index')"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            ← Zurück zur Lagerverwaltung
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
                                    Standort
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
                            <tr
                                v-for="warehouse in trashedWarehouses"
                                :key="warehouse.id"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ warehouse.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ warehouse.location }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ formatDate(warehouse.deleted_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="restore(warehouse)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Wiederherstellen
                                    </button>
                                    <button
                                        @click="
                                            openForceDeleteDialog(warehouse)
                                        "
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Endgültig löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="trashedWarehouses.length === 0">
                                <td
                                    colspan="4"
                                    class="px-6 py-4 text-center text-gray-500"
                                >
                                    Keine gelöschten Lager vorhanden
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
                                Lager endgültig löschen
                            </h2>
                            <p class="mt-3 text-sm text-gray-600">
                                Sind Sie sicher, dass Sie dieses Lager endgültig
                                löschen möchten? Diese Aktion kann nicht
                                rückgängig gemacht werden.
                            </p>
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
    trashedWarehouses: Array,
});

const showForceDeleteDialog = ref(false);
const warehouseToDelete = ref(null);

const form = useForm({});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("de-DE");
};

const restore = (warehouse) => {
    form.put(route("warehouses.restore", warehouse.id));
};

const openForceDeleteDialog = (warehouse) => {
    warehouseToDelete.value = warehouse;
    showForceDeleteDialog.value = true;
};

const closeForceDeleteDialog = () => {
    showForceDeleteDialog.value = false;
    warehouseToDelete.value = null;
};

const confirmForceDelete = () => {
    if (warehouseToDelete.value) {
        form.delete(
            route("warehouses.force-delete", warehouseToDelete.value.id),
            {
                onSuccess: () => closeForceDeleteDialog(),
            }
        );
    }
};
</script>
