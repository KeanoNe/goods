<template>
    <AppLayout title="Gelöschte Lieferanten">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gelöschte Lieferanten
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <div class="mb-6">
                        <Link
                            :href="route('suppliers.index')"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            ← Zurück zur Lieferantenverwaltung
                        </Link>
                    </div>

                    <!-- forceDelete lehnt verwendete Lieferanten mit einem
                         Validierungsfehler ab. -->
                    <div
                        v-if="$page.props.errors.supplier"
                        class="mb-4 p-4 rounded-md bg-red-50 text-red-700"
                    >
                        {{ $page.props.errors.supplier }}
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
                                    Kundennummer
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
                                v-for="supplier in trashedSuppliers"
                                :key="supplier.id"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.customer_number || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ formatDate(supplier.deleted_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="restore(supplier)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Wiederherstellen
                                    </button>
                                    <button
                                        @click="openForceDeleteDialog(supplier)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Endgültig löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="trashedSuppliers.length === 0">
                                <td
                                    colspan="4"
                                    class="px-6 py-4 text-center text-gray-500"
                                >
                                    Keine gelöschten Lieferanten vorhanden
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <Modal
                        :show="showForceDeleteDialog"
                        @close="closeForceDeleteDialog"
                    >
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-red-900">
                                Lieferant endgültig löschen
                            </h2>
                            <p class="mt-3 text-sm text-gray-600">
                                Sind Sie sicher, dass Sie diesen Lieferanten
                                endgültig löschen möchten? Diese Aktion kann
                                nicht rückgängig gemacht werden.
                            </p>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                                    @click="closeForceDeleteDialog"
                                >
                                    Abbrechen
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-xs hover:bg-red-700"
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

defineProps({
    trashedSuppliers: Array,
});

const showForceDeleteDialog = ref(false);
const supplierToDelete = ref(null);

const form = useForm({});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("de-DE");
};

const restore = (supplier) => {
    form.put(route("suppliers.restore", supplier.id));
};

const openForceDeleteDialog = (supplier) => {
    supplierToDelete.value = supplier;
    showForceDeleteDialog.value = true;
};

const closeForceDeleteDialog = () => {
    showForceDeleteDialog.value = false;
    supplierToDelete.value = null;
};

const confirmForceDelete = () => {
    if (supplierToDelete.value) {
        form.delete(
            route("suppliers.force-delete", supplierToDelete.value.id),
            {
                onSuccess: () => closeForceDeleteDialog(),
            }
        );
    }
};
</script>
