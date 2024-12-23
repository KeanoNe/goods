<script setup>
import WebLayout from "@/Layouts/WebLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    companyInfo: {
        type: Object,
        default: () => ({
            company: "NewDot Digital",
            name: "Keano Neumann",
            street: "Hauptstraße 8",
            zip: "49326",
            city: "Melle",
            country: "Deutschland",
            email: "info@newdot.digital",
            phone: "+49 (0) 5428 5016800",
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
                                <p>{{ companyInfo.company }}</p>
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
                                <p>E-Mail: {{ companyInfo.email }}</p>
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
                        <h3
                            class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                        >
                            Verbraucher­streit­beilegung/Universal­schlichtungs­stelle
                        </h3>
                        <p class="mb-4">
                            Wir sind nicht bereit oder verpflichtet, an
                            Streitbeilegungsverfahren vor einer
                            Verbraucherschlichtungsstelle teilzunehmen.
                        </p>
                        <h3
                            class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                        >
                            Haftung für Inhalte
                        </h3>
                        <p class="mb-4">
                            Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für
                            eigene Inhalte auf diesen Seiten nach den
                            allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis
                            10 TMG sind wir als Diensteanbieter jedoch nicht
                            verpflichtet, übermittelte oder gespeicherte fremde
                            Informationen zu überwachen oder nach Umständen zu
                            forschen, die auf eine rechtswidrige Tätigkeit
                            hinweisen. Verpflichtungen zur Entfernung oder
                            Sperrung der Nutzung von Informationen nach den
                            allgemeinen Gesetzen bleiben hiervon unberührt. Eine
                            diesbezügliche Haftung ist jedoch erst ab dem
                            Zeitpunkt der Kenntnis einer konkreten
                            Rechtsverletzung möglich. Bei Bekanntwerden von
                            entsprechenden Rechtsverletzungen werden wir diese
                            Inhalte umgehend entfernen.
                        </p>
                        <h3
                            class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                        >
                            Haftung für Links
                        </h3>
                        <p class="mb-4">
                            Unser Angebot enthält Links zu externen Websites
                            Dritter, auf deren Inhalte wir keinen Einfluss
                            haben. Deshalb können wir für diese fremden Inhalte
                            auch keine Gewähr übernehmen. Für die Inhalte der
                            verlinkten Seiten ist stets der jeweilige Anbieter
                            oder Betreiber der Seiten verantwortlich. Die
                            verlinkten Seiten wurden zum Zeitpunkt der
                            Verlinkung auf mögliche Rechtsverstöße überprüft.
                            Rechtswidrige Inhalte waren zum Zeitpunkt der
                            Verlinkung nicht erkennbar. Eine permanente
                            inhaltliche Kontrolle der verlinkten Seiten ist
                            jedoch ohne konkrete Anhaltspunkte einer
                            Rechtsverletzung nicht zumutbar. Bei Bekanntwerden
                            von Rechtsverletzungen werden wir derartige Links
                            umgehend entfernen.
                        </p>
                        <h3
                            class="text-lg font-semibold text-indigo-600 dark:text-indigo-400 mb-2"
                        >
                            Urheberrecht
                        </h3>
                        <p class="mb-4">
                            Die durch die Seitenbetreiber erstellten Inhalte und
                            Werke auf diesen Seiten unterliegen dem deutschen
                            Urheberrecht. Die Vervielfältigung, Bearbeitung,
                            Verbreitung und jede Art der Verwertung außerhalb
                            der Grenzen des Urheberrechtes bedürfen der
                            schriftlichen Zustimmung des jeweiligen Autors bzw.
                            Erstellers. Downloads und Kopien dieser Seite sind
                            nur für den privaten, nicht kommerziellen Gebrauch
                            gestattet. Soweit die Inhalte auf dieser Seite nicht
                            vom Betreiber erstellt wurden, werden die
                            Urheberrechte Dritter beachtet. Insbesondere werden
                            Inhalte Dritter als solche gekennzeichnet. Sollten
                            Sie trotzdem auf eine Urheberrechtsverletzung
                            aufmerksam werden, bitten wir um einen
                            entsprechenden Hinweis. Bei Bekanntwerden von
                            Rechtsverletzungen werden wir derartige Inhalte
                            umgehend entfernen.
                        </p>
                        <p>
                            Quelle:
                            <a href="https://www.e-recht24.de"
                                >https://www.e-recht24.de</a
                            >
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </WebLayout>
</template>
