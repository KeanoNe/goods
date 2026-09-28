<template>
    <AppLayout :title="supplier ? 'Lieferant bearbeiten' : 'Neuer Lieferant'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ supplier ? "Lieferant bearbeiten" : "Neuer Lieferant" }}
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
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                required
                                />
                            <div
                                v-if="form.errors.name"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <!-- Ansprechpartner -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Ansprechpartner</label
                            >
                            <input
                                type="text"
                                v-model="form.contact_person"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.contact_person"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.contact_person }}
                            </div>
                        </div>

                        <!-- E-Mail -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >E-Mail</label
                            >
                            <input
                                type="email"
                                v-model="form.email"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.email"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <!-- Telefon -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Telefon</label
                            >
                            <input
                                type="text"
                                v-model="form.phone"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.phone"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.phone }}
                            </div>
                        </div>

                        <!-- Kundennummer -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Kundennummer</label
                            >
                            <input
                                type="text"
                                v-model="form.customer_number"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            <div
                                v-if="form.errors.customer_number"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.customer_number }}
                            </div>
                        </div>

                        <!-- Adresse -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Adresse</label
                            >
                            <textarea
                                v-model="form.address"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            <div
                                v-if="form.errors.address"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ form.errors.address }}
                            </div>
                        </div>

                        <!-- Notizen -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700"
                                >Notizen</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
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
                                :href="route('suppliers.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-xs hover:bg-gray-50"
                            >
                                Abbrechen
                            </Link>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700"
                                :disabled="form.processing"
                            >
                                {{ supplier ? "Speichern" : "Erstellen" }}
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
    supplier: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.supplier?.name ?? "",
    contact_person: props.supplier?.contact_person ?? "",
    email: props.supplier?.email ?? "",
    phone: props.supplier?.phone ?? "",
    customer_number: props.supplier?.customer_number ?? "",
    address: props.supplier?.address ?? "",
    notes: props.supplier?.notes ?? "",
});

const submit = () => {
    if (props.supplier) {
        form.put(route("suppliers.update", props.supplier.id));
    } else {
        form.post(route("suppliers.store"));
    }
};
</script>
