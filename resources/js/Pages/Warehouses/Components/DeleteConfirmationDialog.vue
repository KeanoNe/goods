<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Lager löschen</h2>

            <div class="mt-3">
                <p class="text-sm text-gray-600">
                    Sind Sie sicher, dass Sie dieses Lager löschen möchten? Das
                    Lager wird in den Papierkorb verschoben und kann
                    wiederhergestellt werden.
                </p>

                <div class="mt-4 bg-gray-50 rounded-md p-4">
                    <h3 class="text-sm font-medium text-gray-900">
                        Abhängige Elemente:
                    </h3>
                    <ul
                        class="mt-2 list-disc list-inside text-sm text-gray-600"
                    >
                        <li>{{ warehouse.racks_count || 0 }} Regale</li>
                        <li>{{ warehouse.total_shelves || 0 }} Regalböden</li>
                        <li>
                            {{
                                warehouse.storage_locations_count || 0
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
                    @click="closeModal"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700"
                    @click="confirmDelete"
                    :disabled="form.processing"
                >
                    Lager in Papierkorb verschieben
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    warehouse: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["close"]);

const form = useForm({});

const closeModal = () => {
    emit("close");
};

const confirmDelete = () => {
    form.delete(route("warehouses.destroy", props.warehouse.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>
