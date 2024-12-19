<script setup>
import WebLayout from "@/Layouts/WebLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    companyInfo: {
        type: Object,
        default: () => ({
            name: "Musterfirma GmbH",
            street: "Musterstraße 123",
            zip: "12345",
            city: "Musterstadt",
            country: "Deutschland",
            email: "info@musterfirma.de",
            phone: "+49 (0) 123 456789",
            fax: "+49 (0) 123 456788",
            managing_director: "Max Mustermann",
            register_court: "Amtsgericht Musterstadt",
            register_number: "HRB 12345",
            vat_id: "DE123456789",
            responsible_content: "Max Mustermann",
            data_protection_officer: "Maria Musterfrau",
        }),
    },
});
</script>

<template>
    <WebLayout title="Impressum">
        <!-- Content -->
        <div class="container mx-auto px-6 py-8">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="mb-12 text-center">
                    <h1
                        class="text-4xl font-bold text-gray-900 dark:text-white mb-4"
                    >
                        Impressum
                    </h1>
                    <div class="h-1 w-20 bg-indigo-600 mx-auto"></div>
                </div>

                <!-- Company Information -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 mb-8"
                >
                    <h2
                        class="text-2xl font-semibold text-gray-900 dark:text-white mb-6"
                    >
                        Angaben gemäß § 5 TMG
                    </h2>

                    <div class="space-y-6">
                        <!-- Company Address -->
                        <div>
                            <h3
                                class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                            >
                                Unternehmensanschrift
                            </h3>
                            <div
                                class="text-gray-600 dark:text-gray-300 space-y-1"
                            >
                                <p>{{ companyInfo.name }}</p>
                                <p>{{ companyInfo.street }}</p>
                                <p>
                                    {{ companyInfo.zip }} {{ companyInfo.city }}
                                </p>
                                <p>{{ companyInfo.country }}</p>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div>
                            <h3
                                class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                            >
                                Kontakt
                            </h3>
                            <div
                                class="text-gray-600 dark:text-gray-300 space-y-1"
                            >
                                <p>Telefon: {{ companyInfo.phone }}</p>
                                <p>Fax: {{ companyInfo.fax }}</p>
                                <p>E-Mail: {{ companyInfo.email }}</p>
                            </div>
                        </div>

                        <!-- Legal Information -->
                        <div>
                            <h3
                                class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                            >
                                Rechtliche Angaben
                            </h3>
                            <div
                                class="text-gray-600 dark:text-gray-300 space-y-1"
                            >
                                <p>
                                    Geschäftsführer:
                                    {{ companyInfo.managing_director }}
                                </p>
                                <p>
                                    Registergericht:
                                    {{ companyInfo.register_court }}
                                </p>
                                <p>
                                    Handelsregisternummer:
                                    {{ companyInfo.register_number }}
                                </p>
                                <p>Umsatzsteuer-ID: {{ companyInfo.vat_id }}</p>
                            </div>
                        </div>

                        <!-- Content Responsibility -->
                        <div>
                            <h3
                                class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                            >
                                Inhaltlich Verantwortlicher
                            </h3>
                            <div class="text-gray-600 dark:text-gray-300">
                                <p>{{ companyInfo.responsible_content }}</p>
                            </div>
                        </div>

                        <!-- Data Protection -->
                        <div>
                            <h3
                                class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                            >
                                Datenschutzbeauftragter
                            </h3>
                            <div class="text-gray-600 dark:text-gray-300">
                                <p>{{ companyInfo.data_protection_officer }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legal Notice -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
                    <h2
                        class="text-2xl font-semibold text-gray-900 dark:text-white mb-6"
                    >
                        Rechtliche Hinweise
                    </h2>

                    <div
                        class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300"
                    >
                        <p class="mb-4">
                            Trotz sorgfältiger inhaltlicher Kontrolle übernehmen
                            wir keine Haftung für die Inhalte externer Links.
                            Für den Inhalt der verlinkten Seiten sind
                            ausschließlich deren Betreiber verantwortlich.
                        </p>
                        <p>
                            Alle Inhalte dieser Webseite (Texte, Grafiken,
                            Fotos, Logos, etc.) sind urheberrechtlich geschützt.
                            Die Verwendung durch Dritte bedarf der
                            ausdrücklichen Zustimmung der
                            {{ companyInfo.name }}.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </WebLayout>
</template>
