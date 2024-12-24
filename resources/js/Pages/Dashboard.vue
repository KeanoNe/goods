<script setup>
import { ref, onMounted } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import {
    InformationCircleIcon,
    ExclamationCircleIcon,
    ArchiveBoxIcon,
    ClipboardDocumentIcon,
} from "@heroicons/vue/24/outline";

// Props, die vom Controller übergeben werden
const props = defineProps({
    totalStock: Number,
    articlesUnderMinimumCount: Number,
    lastMovements: Array,
    articlesWithoutMovementCount: Number,
    totalArticlesCount: Number,
    articlesUnderMinimumList: Array, // Neue Prop für die Liste der Artikel unter Mindestbestand
});

// Debugging: Ausgabe der Props in der Konsole
onMounted(() => {
    console.log("Dashboard Props:", props);
});

// Hilfsfunktion zum Formatieren des Bewegungstyps
const formatMovementType = (type) => {
    const types = {
        in: "Eingang",
        out: "Ausgang",
        transfer: "Umlagerung",
        correction: "Korrektur",
    };
    return types[type] || type;
};

const isMobile = () => {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent
    );
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-8 bg-gray-100">
            <div
                class="max-w-7xl mb-8 mx-4 sm:px-6 lg:px-8 space-y-8"
                v-if="isMobile()"
            >
                <Link href="/stock/movements">
                    <button
                        @click="route('stock/movements')"
                        class="w-full justify-center inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 mr-2"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zm-2 7a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zm8-12a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2h1v1h-1V5zm-2 5a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3zm2 2h1v1h-1v-1z"
                            />
                        </svg>
                        Bestandsbewegungen buchen
                    </button>
                </Link>
            </div>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- Statistische Kennzahlen -->
                <div
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
                >
                    <!-- Gesamtbestand aller Artikel -->
                    <div
                        class="bg-white shadow rounded-lg p-6 flex items-center"
                    >
                        <div class="p-3 rounded-full bg-green-100">
                            <InformationCircleIcon
                                class="h-6 w-6 text-green-600"
                            />
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">
                                Gesamtbestand aller Artikel
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold text-gray-900"
                            >
                                {{ totalStock }}
                            </div>
                        </div>
                    </div>

                    <!-- Artikel unter Mindestbestand -->
                    <div
                        class="bg-white shadow rounded-lg p-6 flex items-center"
                    >
                        <div class="p-3 rounded-full bg-red-100">
                            <ExclamationCircleIcon
                                class="h-6 w-6 text-red-600"
                            />
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">
                                Artikel unter Mindestbestand
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold text-gray-900"
                            >
                                {{ articlesUnderMinimumCount }}
                            </div>
                        </div>
                    </div>

                    <!-- Artikel ohne Bewegung -->
                    <div
                        class="bg-white shadow rounded-lg p-6 flex items-center"
                    >
                        <div class="p-3 rounded-full bg-yellow-100">
                            <ArchiveBoxIcon class="h-6 w-6 text-yellow-600" />
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">
                                Artikel ohne Bewegung
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold text-gray-900"
                            >
                                {{ articlesWithoutMovementCount }}
                            </div>
                        </div>
                    </div>

                    <!-- Anzahl aller Artikel -->
                    <div
                        class="bg-white shadow rounded-lg p-6 flex items-center"
                    >
                        <div class="p-3 rounded-full bg-blue-100">
                            <ClipboardDocumentIcon
                                class="h-6 w-6 text-blue-600"
                            />
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">
                                Anzahl aller Artikel
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold text-gray-900"
                            >
                                {{ totalArticlesCount }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Letzte Bestandsbewegungen -->
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            Letzte Bestandsbewegungen
                        </h3>
                        <!-- Optional: Link zu allen Bewegungen -->
                        <!-- <Link href="/stock-movements" class="text-indigo-600 hover:text-indigo-900">Alle ansehen</Link> -->
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Datum
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Artikel
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Typ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Menge
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Benutzer
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="(movement, index) in lastMovements"
                                    :key="index"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{
                                            new Date(
                                                movement.date
                                            ).toLocaleDateString("de-DE")
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ movement.article }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ formatMovementType(movement.type) }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ movement.quantity }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ movement.user }}
                                    </td>
                                </tr>
                                <tr v-if="lastMovements.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-6 py-4 text-center text-gray-500"
                                    >
                                        Keine Bewegungen vorhanden
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabelle der Artikel unter Mindestbestand -->
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            Artikel unter Mindestbestand
                        </h3>
                        <!-- Optional: Link zu allen Artikeln unter Mindestbestand -->
                        <!-- <Link href="/articles?filter=under_minimum" class="text-indigo-600 hover:text-indigo-900">Alle ansehen</Link> -->
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Artikel-ID
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Aktueller Bestand
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Mindestbestand
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="article in articlesUnderMinimumList"
                                    :key="article.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ article.id }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ article.name }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ article.current_stock }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ article.minimum_stock }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="articlesUnderMinimumList.length === 0"
                                >
                                    <td
                                        colspan="4"
                                        class="px-6 py-4 text-center text-gray-500"
                                    >
                                        Keine Artikel unter Mindestbestand
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
