<template>
    <div>
        <div class="flex justify-between mb-4">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-medium">Lagerverwaltung</h3>
                <Link
                    :href="route('warehouses.trashed')"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    Papierkorb
                </Link>
            </div>
            <Link
                :href="route('warehouses.create')"
                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700"
            >
                Neues Lager anlegen
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
                <tr v-for="warehouse in warehouses" :key="warehouse.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ warehouse.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ warehouse.location }}
                    </td>
                    <td class="px-6 py-4">{{ warehouse.description }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <Link
                            :href="route('warehouses.edit', warehouse.id)"
                            class="text-indigo-600 hover:text-indigo-900 mr-2"
                        >
                            Bearbeiten
                        </Link>
                        <button
                            @click="openDeleteDialog(warehouse)"
                            class="text-red-600 hover:text-red-900"
                        >
                            Löschen
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <DeleteConfirmationDialog
            :show="showDeleteDialog"
            :warehouse="warehouseToDelete"
            @close="closeDeleteDialog"
        />
    </div>
</template>

<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import DeleteConfirmationDialog from "./DeleteConfirmationDialog.vue";

defineProps({
    warehouses: {
        type: Array,
        required: true,
    },
});

const showDeleteDialog = ref(false);
const warehouseToDelete = ref(null);

const openDeleteDialog = (warehouse) => {
    warehouseToDelete.value = warehouse;
    showDeleteDialog.value = true;
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    warehouseToDelete.value = null;
};
</script>
