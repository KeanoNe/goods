<script setup>
import WebLayout from "@/Layouts/WebLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    appName: {
        type: String,
        default: "Goods",
    },
});

const features = [
    {
        title: "Effiziente Lagerverwaltung",
        description:
            "Verwalten Sie Ihr Lager mit modernster Technologie. Behalten Sie den Überblick über Bestände, Bewegungen und Lagerorte.",
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>`,
    },
    {
        title: "Mobile Erfassung",
        description:
            "Erfassen Sie Warenbewegungen direkt vor Ort mit unserer mobilen Lösung. Schnell, einfach und fehlerfrei.",
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>`,
    },
    {
        title: "Echtzeit-Analyse",
        description:
            "Behalten Sie den Überblick mit Echtzeit-Statistiken und Analysen. Treffen Sie fundierte Entscheidungen basierend auf aktuellen Daten.",
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>`,
    },
];

const stats = [
    { number: "1.000+", text: "Aktive Nutzer" },
    { number: "5M+", text: "Buchungen pro Jahr" },
    { number: "250+", text: "Zufriedene Unternehmen" },
];
</script>

<template>
    <WebLayout
        :can-login="canLogin"
        :can-register="canRegister"
        app-name="Goods"
        title="Goods"
    >
        <!-- Hero Section -->
        <div class="container mx-auto px-6 py-16 text-center">
            <h1
                class="text-5xl md:text-6xl font-bold text-gray-900 dark:text-white mb-8"
            >
                Moderne Lagerverwaltung<br />
                <span class="text-indigo-600 dark:text-indigo-400"
                    >für Ihr Unternehmen</span
                >
            </h1>
            <p
                class="text-xl text-gray-600 dark:text-gray-300 mb-12 max-w-3xl mx-auto"
            >
                Optimieren Sie Ihre Lagerprozesse mit unserer intuitiven und
                leistungsstarken Lösung. Von der Einlagerung bis zur Analyse -
                alles in einer Hand.
            </p>
        </div>

        <!-- Features Section -->
        <div class="container mx-auto px-6 py-16">
            <div class="grid md:grid-cols-3 gap-8">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-shadow"
                >
                    <div
                        class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center text-indigo-600 dark:text-indigo-400 mb-4"
                        v-html="feature.icon"
                    ></div>
                    <h3
                        class="text-xl font-semibold text-gray-900 dark:text-white mb-2"
                    >
                        {{ feature.title }}
                    </h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        {{ feature.description }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="container mx-auto px-6 py-16">
            <div class="bg-indigo-600 dark:bg-indigo-900 rounded-2xl p-12">
                <div class="grid md:grid-cols-3 gap-8 text-center">
                    <div
                        v-for="stat in stats"
                        :key="stat.number"
                        class="text-white"
                    >
                        <div class="text-4xl font-bold mb-2">
                            {{ stat.number }}
                        </div>
                        <div class="text-indigo-200">{{ stat.text }}</div>
                    </div>
                </div>
            </div>
        </div>
    </WebLayout>
</template>
