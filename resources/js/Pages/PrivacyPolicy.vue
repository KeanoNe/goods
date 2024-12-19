<script setup>
import { Head, Link } from "@inertiajs/vue3";
import WebLayout from "@/Layouts/WebLayout.vue";
import { ref } from "vue";

const sections = ref([
    {
        title: "Allgemeine Hinweise",
        content: `Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie persönlich identifiziert werden können.`,
    },
    {
        title: "Datenerfassung auf dieser Website",
        subsections: [
            {
                title: "Wer ist verantwortlich für die Datenerfassung?",
                content: `Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Die Kontaktdaten können Sie dem Impressum dieser Website entnehmen.`,
            },
            {
                title: "Wie erfassen wir Ihre Daten?",
                content: `Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen. Hierbei kann es sich z.B. um Daten handeln, die Sie in ein Kontaktformular eingeben.

Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst. Das sind vor allem technische Daten (z.B. Internetbrowser, Betriebssystem oder Uhrzeit des Seitenaufrufs).`,
            },
            {
                title: "Wofür nutzen wir Ihre Daten?",
                content: `Ein Teil der Daten wird erhoben, um eine fehlerfreie Bereitstellung der Website zu gewährleisten. Andere Daten können zur Analyse Ihres Nutzerverhaltens verwendet werden.`,
            },
        ],
    },
    {
        title: "Ihre Rechte",
        subsections: [
            {
                title: "Auskunftsrecht",
                content: `Sie haben das Recht, jederzeit unentgeltlich Auskunft über Herkunft, Empfänger und Zweck Ihrer gespeicherten personenbezogenen Daten zu erhalten.`,
            },
            {
                title: "Recht auf Widerruf",
                content: `Sie haben das Recht, eine bereits erteilte Einwilligung in die Verarbeitung von Daten jederzeit zu widerrufen.`,
            },
            {
                title: "Recht auf Datenübertragbarkeit",
                content: `Sie haben das Recht, zu verlangen, dass wir die personenbezogenen Daten, die wir von Ihnen verarbeiten, an eine andere Stelle übertragen.`,
            },
        ],
    },
    {
        title: "Analyse-Tools und Tools von Drittanbietern",
        content: `Beim Besuch dieser Website kann Ihr Surf-Verhalten statistisch ausgewertet werden. Das geschieht vor allem mit sogenannten Analyseprogrammen.
        
        Die Analyse Ihres Surf-Verhaltens erfolgt in der Regel anonym; das Surf-Verhalten kann nicht zu Ihnen zurückverfolgt werden.`,
        subsections: [
            {
                title: "Cookies",
                content: `Unsere Website verwendet Cookies. Hierbei handelt es sich um kleine Textdateien, die Ihr Webbrowser auf Ihrem Endgerät speichert. Cookies helfen uns dabei, unser Angebot nutzerfreundlicher, effektiver und sicherer zu machen.`,
            },
            {
                title: "Server-Log-Dateien",
                content: `Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten Server-Log-Dateien, die Ihr Browser automatisch an uns übermittelt. Dies sind:
                - Browsertyp und Browserversion
                - Verwendetes Betriebssystem
                - Referrer URL
                - Hostname des zugreifenden Rechners
                - Uhrzeit der Serveranfrage
                - IP-Adresse`,
            },
        ],
    },
]);
</script>

<template>
    <WebLayout title="Impressum">
        <!-- Content -->
        <div class="container mx-auto px-6 py-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="mb-12 text-center">
                    <h1
                        class="text-4xl font-bold text-gray-900 dark:text-white mb-4"
                    >
                        Datenschutzerklärung
                    </h1>
                    <div class="h-1 w-20 bg-indigo-600 mx-auto"></div>
                </div>

                <!-- Introduction -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 mb-8"
                >
                    <p class="text-gray-600 dark:text-gray-300">
                        Der Schutz Ihrer persönlichen Daten ist uns ein
                        besonderes Anliegen. In dieser Datenschutzerklärung
                        informieren wir Sie über die wichtigsten Aspekte der
                        Datenverarbeitung im Rahmen unserer Website.
                    </p>
                </div>

                <!-- Main Content -->
                <div class="space-y-8">
                    <div
                        v-for="(section, index) in sections"
                        :key="index"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8"
                    >
                        <h2
                            class="text-2xl font-semibold text-gray-900 dark:text-white mb-6"
                        >
                            {{ section.title }}
                        </h2>

                        <div class="prose dark:prose-invert max-w-none">
                            <p
                                v-if="section.content"
                                class="text-gray-600 dark:text-gray-300 mb-6 whitespace-pre-line"
                            >
                                {{ section.content }}
                            </p>

                            <div v-if="section.subsections" class="space-y-6">
                                <div
                                    v-for="(
                                        subsection, subIndex
                                    ) in section.subsections"
                                    :key="subIndex"
                                    class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6"
                                >
                                    <h3
                                        class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-3"
                                    >
                                        {{ subsection.title }}
                                    </h3>
                                    <p
                                        class="text-gray-600 dark:text-gray-300 whitespace-pre-line"
                                    >
                                        {{ subsection.content }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 mt-8"
                >
                    <h2
                        class="text-2xl font-semibold text-gray-900 dark:text-white mb-6"
                    >
                        Kontakt für Datenschutz
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300">
                        Bei Fragen zur Erhebung, Verarbeitung oder Nutzung Ihrer
                        personenbezogenen Daten, bei Auskünften, Berichtigung,
                        Sperrung oder Löschung von Daten wenden Sie sich bitte
                        an unseren Datenschutzbeauftragten:
                    </p>
                    <div class="mt-4 text-gray-600 dark:text-gray-300">
                        <p>E-Mail: datenschutz@example.com</p>
                        <p>Telefon: +49 (0) 123 456789</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="container mx-auto px-6 py-8">
            <div class="text-center text-gray-600 dark:text-gray-400">
                Stand: {{ new Date().toLocaleDateString() }}
            </div>
        </footer>
    </WebLayout>
</template>
