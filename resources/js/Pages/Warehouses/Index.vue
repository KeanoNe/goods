<template>
    <AppLayout title="Lager">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lager
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                :class="[
                                    activeTab === tab.key
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm',
                                ]"
                            >
                                {{ tab.name }}
                            </button>
                        </nav>
                    </div>

                    <div class="mt-6">
                        <!-- Debug-Ausgabe -->
                        <!-- <pre class="text-xs">{{ warehouses }}</pre> -->

                        <!-- Lager Tab -->
                        <div v-if="activeTab === 'warehouses'">
                            <WarehouseTable :warehouses="warehouses" />
                        </div>

                        <!-- Regale Tab -->
                        <div v-if="activeTab === 'racks'">
                            <RackTable :racks="racks" />
                        </div>

                        <!-- Regalböden Tab -->
                        <div v-if="activeTab === 'shelves'">
                            <ShelfTable :shelves="shelves" />
                        </div>

                        <!-- Lagerplätze Tab -->
                        <div v-if="activeTab === 'locations'">
                            <StorageLocationTable :locations="locations" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import WarehouseTable from "./Components/WarehouseTable.vue";
import RackTable from "./Components/RackTable.vue";
import ShelfTable from "./Components/ShelfTable.vue";
import StorageLocationTable from "./Components/StorageLocationTable.vue";

const props = defineProps({
    warehouses: Array,
    racks: Array,
    shelves: Array,
    locations: Array,
});

const activeTab = ref(route().params.tab ?? "warehouses");

const tabs = [
    { key: "warehouses", name: "Lager" },
    { key: "racks", name: "Regale" },
    { key: "shelves", name: "Regalböden" },
    { key: "locations", name: "Lagerplätze" },
];
</script>
