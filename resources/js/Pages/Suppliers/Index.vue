<template>
    <AppLayout title="Lieferanten">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lieferanten
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="mb-6 flex justify-between items-center">
                        <Link
                            :href="route('suppliers.create')"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700"
                        >
                            Neuer Lieferant
                        </Link>
                        <Link
                            :href="route('suppliers.trashed')"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            Papierkorb
                        </Link>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ansprechpartner
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    E-Mail
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kundennummer
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Artikel
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="supplier in suppliers" :key="supplier.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.contact_person || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.email || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.customer_number || "-" }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ supplier.articles_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link
                                        :href="route('suppliers.edit', supplier.id)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                    >
                                        Bearbeiten
                                    </Link>
                                    <button
                                        @click="openDeleteDialog(supplier)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Löschen
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="suppliers.length === 0">
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    Keine Lieferanten vorhanden
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <Modal :show="showDeleteDialog" @close="closeDeleteDialog">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-red-900">
                                Lieferant löschen
                            </h2>
                            <p class="mt-3 text-sm text-gray-600">
                                Der Lieferant wandert in den Papierkorb und kann
                                dort wiederhergestellt werden.
                            </p>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                                    @click="closeDeleteDialog"
                                >
                                    Abbrechen
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-xs hover:bg-red-700"
                                    @click="confirmDelete"
                                >
                                    Löschen
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
    suppliers: Array,
});

const showDeleteDialog = ref(false);
const supplierToDelete = ref(null);
const form = useForm({});

const openDeleteDialog = (supplier) => {
    supplierToDelete.value = supplier;
    showDeleteDialog.value = true;
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    supplierToDelete.value = null;
};

const confirmDelete = () => {
    if (supplierToDelete.value) {
        form.delete(route("suppliers.destroy", supplierToDelete.value.id), {
            onSuccess: () => closeDeleteDialog(),
        });
    }
};
</script>
