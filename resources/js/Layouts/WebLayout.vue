<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    appName: {
        type: String,
        default: "Goods",
    },
    title: String,
});
</script>

<template>
    <Head :title="title" />

    <div
        class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-900 dark:to-indigo-950"
    >
        <!-- Navigation -->
        <nav class="container mx-auto px-6 py-4">
            <div class="flex items-center">
                <div
                    class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"
                >
                    {{ appName }}
                </div>

                <Link
                    :href="route('welcome')"
                    :class="{
                        'ml-24 text-lg rounded-lg text-indigo-50 underline underline-offset-4':
                            route().current('welcome'),
                        'ml-24 text-lg rounded-lg text-indigo-600 hover:text-indigo-50':
                            !route().current('welcome'),
                    }"
                >
                    Home
                </Link>

                <div class="flex items-center space-x-4 ml-auto">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
                        >
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 rounded-lg border border-indigo-600 text-indigo-600 hover:bg-indigo-50 transition-colors dark:text-indigo-400 dark:border-indigo-400 dark:hover:bg-indigo-900"
                        >
                            Login
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
                        >
                            Registrieren
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <div class="container mx-auto px-6 py-16 text-center"><slot />></div>

        <!-- Footer -->
        <footer class="container mx-auto px-6 py-8">
            <div class="text-center text-gray-600 dark:text-gray-400">
                <div class="mb-4">
                    <Link
                        :href="route('impressum')"
                        class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors mx-2"
                    >
                        Impressum
                    </Link>
                    <span class="mx-2">|</span>
                    <Link
                        :href="route('privacy')"
                        class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors mx-2"
                    >
                        Datenschutzerklärung
                    </Link>
                </div>
                <div>
                    &copy; {{ new Date().getFullYear() }} {{ appName }}. Alle
                    Rechte vorbehalten.
                </div>
            </div>
        </footer>
    </div>
</template>
