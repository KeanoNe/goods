<script setup>
import { Head, Link } from "@inertiajs/vue3";
import WebLayout from "@/Layouts/WebLayout.vue";
import { ref } from "vue";

const sections = ref([
    {
        title: "1. Datenschutz auf einen Blick",
        content: `Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie persönlich identifiziert werden können. Ausführliche Informationen zum Thema Datenschutz entnehmen Sie unserer unter diesem Text aufgeführten Datenschutzerklärung.`,
        subsections: [
            {
                title: "Wer ist verantwortlich für die Datenerfassung?",
                content: `Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten können Sie dem Abschnitt „Hinweis zur Verantwortlichen Stelle" in dieser Datenschutzerklärung entnehmen.`,
            },
            {
                title: "Wie erfassen wir Ihre Daten?",
                content: `Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen. Hierbei kann es sich z.B. um Daten handeln, die Sie in ein Kontaktformular eingeben.

Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst. Das sind vor allem technische Daten (z.B. Internetbrowser, Betriebssystem oder Uhrzeit des Seitenaufrufs). Die Erfassung dieser Daten erfolgt automatisch, sobald Sie diese Website betreten.`,
            },
        ],
    },
    {
        title: "2. Hosting",
        content: `Wir hosten die Inhalte unserer Website bei folgendem Anbieter:`,
        subsections: [
            {
                title: "Strato",
                content: `Anbieter ist die Strato AG, Otto-Ostrowski-Straße 7, 10249 Berlin (nachfolgend „Strato"). Wenn Sie unsere Website besuchen, erfasst Strato verschiedene Logfiles inklusive Ihrer IP-Adressen.

Weitere Informationen entnehmen Sie der Datenschutzerklärung von Strato: https://www.strato.de/datenschutz/

Die Verwendung von Strato erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Wir haben ein berechtigtes Interesse an einer möglichst zuverlässigen Darstellung unserer Website.`,
            },
        ],
    },
    {
        title: "3. Allgemeine Hinweise und Pflichtinformationen",
        subsections: [
            {
                title: "Datenschutz",
                content: `Die Betreiber dieser Seiten nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend den gesetzlichen Datenschutzvorschriften sowie dieser Datenschutzerklärung.

Wenn Sie diese Website benutzen, werden verschiedene personenbezogene Daten erhoben. Die vorliegende Datenschutzerklärung erläutert, welche Daten wir erheben und wofür wir sie nutzen. Sie erläutert auch, wie und zu welchem Zweck das geschieht.`,
            },
            {
                title: "Hinweis zur verantwortlichen Stelle",
                content: `Die verantwortliche Stelle für die Datenverarbeitung auf dieser Website ist:

NewDot Digital
Keano Neumann
Hauptstraße 8
49326 Melle
Telefon: +49 (0) 5428 5016800
E-Mail: info@newdot.digital

Verantwortliche Stelle ist die natürliche oder juristische Person, die allein oder gemeinsam mit anderen über die Zwecke und Mittel der Verarbeitung von personenbezogenen Daten entscheidet.`,
            },
        ],
    },
    {
        title: "4. Datenerfassung auf dieser Website",
        subsections: [
            {
                title: "Cookies",
                content: `Unsere Internetseiten verwenden so genannte „Cookies". Cookies sind kleine Datenpakete und richten auf Ihrem Endgerät keinen Schaden an. Sie werden entweder vorübergehend für die Dauer einer Sitzung (Session-Cookies) oder dauerhaft (permanente Cookies) auf Ihrem Endgerät gespeichert. Session-Cookies werden nach Ende Ihres Besuchs automatisch gelöscht. Permanente Cookies bleiben auf Ihrem Endgerät gespeichert, bis Sie diese selbst löschen oder eine automatische Löschung durch Ihren Webbrowser erfolgt.

Cookies können von uns (First-Party-Cookies) oder von Drittunternehmen stammen (sog. Third-Party-Cookies). Third-Party-Cookies ermöglichen die Einbindung bestimmter Dienstleistungen von Drittunternehmen innerhalb von Webseiten (z.B. Cookies zur Abwicklung von Zahlungsdienstleistungen).`,
            },
            {
                title: "SSL- bzw. TLS-Verschlüsselung",
                content: `Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte, wie zum Beispiel Bestellungen oder Anfragen, die Sie an uns als Seitenbetreiber senden, eine SSL- bzw. TLS-Verschlüsselung. Eine verschlüsselte Verbindung erkennen Sie daran, dass die Adresszeile des Browsers von „http://" auf „https://" wechselt und an dem Schloss-Symbol in Ihrer Browserzeile.

Wenn die SSL- bzw. TLS-Verschlüsselung aktiviert ist, können die Daten, die Sie an uns übermitteln, nicht von Dritten mitgelesen werden.`,
            },
        ],
    },
]);
</script>

<template>
    <WebLayout title="Datenschutzerklärung">
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

                <!-- Footer -->
                <div class="mt-8 text-center text-gray-600 dark:text-gray-400">
                    Stand: {{ new Date().toLocaleDateString() }}
                </div>
            </div>
        </div>
    </WebLayout>
</template>
