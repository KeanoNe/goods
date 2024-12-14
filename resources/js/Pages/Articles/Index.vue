<template>
    <AppLayout title="Artikel">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Artikel
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                >
                    <!-- Suchleiste und Filter -->
                    <div class="border-b border-gray-200 pb-4">
                        <div class="sm:flex sm:items-center sm:justify-between">
                            <div
                                class="flex items-center space-x-4 w-full sm:w-auto"
                            >
                                <!-- Suchfeld -->
                                <div class="w-full sm:w-auto">
                                    <input
                                        type="text"
                                        placeholder="Suche nach Artikeln..."
                                        v-model="search"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>

                            <div class="mt-4 sm:mt-0">
                                <!-- Neuen Artikel Button -->
                                <Link
                                    :href="route('articles.create')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                                >
                                    Neuer Artikel
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Artikeltabelle -->
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        SKU
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Gesamtbestand
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Mindestbestand
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Lagerplätze
                                    </th>
                                    <th
                                        class="px-6 py-3 bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="article in filteredArticles"
                                    :key="article.id"
                                >
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                    >
                                        {{ article.sku }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"
                                    >
                                        {{ article.name }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"
                                    >
                                        <span :class="getStockClass(article)">
                                            {{
                                                article.stocks_sum_quantity || 0
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"
                                    >
                                        {{ article.minimum_stock }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"
                                    >
                                        {{ article.stocks_count }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
                                    >
                                        <div class="flex space-x-3 justify-end">
                                            <Link
                                                :href="
                                                    route(
                                                        'articles.show',
                                                        article.id
                                                    )
                                                "
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Details
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredArticles.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-6 py-4 text-center text-gray-500"
                                    >
                                        Keine Artikel gefunden
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { Link } from "@inertiajs/vue3";
// import { PlusIcon } from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    articles: {
        type: Array,
        required: true,
    },
});

const search = ref("");
const showDeleteModal = ref(false);
const articleToDelete = ref(null);

// Gefilterte Artikel basierend auf der Suche
const filteredArticles = computed(() => {
    if (!search.value) return props.articles;

    const searchTerm = search.value.toLowerCase();
    return props.articles.filter(
        (article) =>
            article.name.toLowerCase().includes(searchTerm) ||
            article.sku.toLowerCase().includes(searchTerm)
    );
});

// CSS-Klasse für Bestandsanzeige
const getStockClass = (article) => {
    const currentStock = article.stocks_sum_quantity || 0;
    if (currentStock <= article.minimum_stock) {
        return "text-red-600 font-bold";
    }
    if (currentStock <= article.minimum_stock * 1.2) {
        return "text-yellow-600 font-bold";
    }
    return "text-green-600";
};
</script>
