<template>
    <AppLayout :title="shelf ? 'Regalboden bearbeiten' : 'Neuer Regalboden'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ shelf ? "Regalboden bearbeiten" : "Neuer Regalboden" }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <form @submit.prevent="submit">
                        <!-- Name -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Name</label
                            >
                            <input
                                type="text"
                                v-model="form.name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <div
                                v-if="form.errors.name"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <!-- Rack Selection -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Regal</label
                            >
                            <select
                                v-model="form.rack_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Regal auswählen</option>
                                <option
                                    v-for="rack in racks"
                                    :key="rack.id"
                                    :value="rack.id"
                                >
                                    {{ rack.name }} ({{ rack.warehouse.name }})
                                </option>
                            </select>
                            <div
                                v-if="form.errors.rack_id"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.rack_id }}
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Beschreibung</label
                            >
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <div
                                v-if="form.errors.description"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.description }}
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Notizen</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <div
                                v-if="form.errors.notes"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.notes }}
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end space-x-3">
                            <Link
                                :href="
                                    route('warehouses.index', {
                                        tab: 'shelves',
                                    })
                                "
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                            >
                                Abbrechen
                            </Link>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700"
                                :disabled="form.processing"
                            >
                                {{ shelf ? "Speichern" : "Erstellen" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    shelf: {
        type: Object,
        default: null,
    },
    racks: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: props.shelf?.name ?? "",
    rack_id: props.shelf?.rack_id ?? "",
    description: props.shelf?.description ?? "",
    notes: props.shelf?.notes ?? "",
});

const submit = () => {
    if (props.shelf) {
        form.put(route("shelves.update", props.shelf.id));
    } else {
        form.post(route("shelves.store"));
    }
};
</script>
