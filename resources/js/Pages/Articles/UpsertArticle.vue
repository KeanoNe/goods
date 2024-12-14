<template>
    <AppLayout :title="article ? 'Artikel bearbeiten' : 'Neuer Artikel'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ article ? "Artikel bearbeiten" : "Neuer Artikel" }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
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

                            <!-- SKU -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700"
                                    >SKU</label
                                >
                                <input
                                    type="text"
                                    v-model="form.sku"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                />
                                <div
                                    v-if="form.errors.sku"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ form.errors.sku }}
                                </div>
                            </div>

                            <!-- Mindestbestand -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700"
                                    >Mindestbestand</label
                                >
                                <input
                                    type="number"
                                    v-model="form.minimum_stock"
                                    min="0"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                />
                                <div
                                    v-if="form.errors.minimum_stock"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ form.errors.minimum_stock }}
                                </div>
                            </div>

                            <!-- Barcode -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700"
                                    >Barcode</label
                                >
                                <input
                                    type="text"
                                    v-model="form.barcode"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <div
                                    v-if="form.errors.barcode"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ form.errors.barcode }}
                                </div>
                            </div>

                            <!-- QR Code -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700"
                                    >QR Code</label
                                >
                                <input
                                    type="text"
                                    v-model="form.qr_code"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <div
                                    v-if="form.errors.qr_code"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ form.errors.qr_code }}
                                </div>
                            </div>
                        </div>

                        <!-- Beschreibung -->
                        <div class="mt-6">
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

                        <!-- Notizen -->
                        <div class="mt-6">
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

                        <!-- Aktuelle Bestände (nur beim Bearbeiten) -->
                        <div
                            v-if="article && article.stocks?.length > 0"
                            class="mt-6"
                        >
                            <h3 class="text-lg font-medium text-gray-900">
                                Aktuelle Bestände
                            </h3>
                            <div class="mt-4 overflow-x-auto">
                                <table
                                    class="min-w-full divide-y divide-gray-200"
                                >
                                    <thead>
                                        <tr>
                                            <th
                                                class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                            >
                                                Lagerplatz
                                            </th>
                                            <th
                                                class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                            >
                                                Bestand
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="stock in article.stocks"
                                            :key="stock.id"
                                            class="bg-white"
                                        >
                                            <td
                                                class="px-6 py-4 text-sm text-gray-900"
                                            >
                                                {{
                                                    stock.storage_location.name
                                                }}
                                                ({{
                                                    stock.storage_location.shelf
                                                        .rack.warehouse.name
                                                }})
                                            </td>
                                            <td
                                                class="px-6 py-4 text-sm text-gray-900"
                                            >
                                                {{ stock.quantity }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="mt-6 flex justify-end space-x-3">
                            <Link
                                :href="route('articles.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
                            >
                                Abbrechen
                            </Link>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700"
                                :disabled="form.processing"
                            >
                                {{ article ? "Speichern" : "Erstellen" }}
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
    article: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.article?.name ?? "",
    sku: props.article?.sku ?? "",
    description: props.article?.description ?? "",
    minimum_stock: props.article?.minimum_stock ?? 0,
    barcode: props.article?.barcode ?? "",
    qr_code: props.article?.qr_code ?? "",
    notes: props.article?.notes ?? "",
});

const submit = () => {
    if (props.article) {
        form.put(route("articles.update", props.article.id));
    } else {
        form.post(route("articles.store"));
    }
};
</script>
