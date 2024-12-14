<!-- resources/js/Components/QrScanner.vue -->
<template>
    <div>
        <div
            v-show="showScanner"
            class="fixed inset-0 bg-black bg-opacity-50 z-50"
        >
            <div
                class="bg-white max-w-lg mx-auto mt-10 rounded-lg overflow-hidden"
            >
                <div class="p-4 bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-medium">QR-Code Scanner</h3>
                    <button
                        @click="stopScanner"
                        class="p-2 hover:bg-gray-200 rounded-full"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div class="relative">
                    <div id="reader"></div>
                    <div
                        v-if="scanning"
                        class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50"
                    >
                        <div class="text-white text-center">
                            <svg
                                class="animate-spin h-10 w-10 mx-auto mb-2"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <p>Scanner wird initialisiert...</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50">
                    <p class="text-sm text-gray-600">
                        Bitte halten Sie die Kamera auf den QR-Code des
                        Lagerplatzes
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Html5Qrcode } from "html5-qrcode";

export default {
    name: "QrScanner",

    props: {
        showScanner: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            html5QrCode: null,
            scanning: false,
        };
    },

    watch: {
        showScanner(newVal) {
            if (newVal) {
                this.initializeScanner();
            } else {
                this.stopScanner();
            }
        },
    },

    methods: {
        async initializeScanner() {
            this.scanning = true;

            try {
                this.html5QrCode = new Html5Qrcode("reader");

                const config = {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0,
                };

                await this.html5QrCode.start(
                    { facingMode: "environment" },
                    config,
                    this.onScanSuccess,
                    this.onScanError
                );
            } catch (error) {
                console.error("Error starting scanner:", error);
                this.$emit("error", "Fehler beim Starten des Scanners");
            } finally {
                this.scanning = false;
            }
        },

        async stopScanner() {
            if (this.html5QrCode && this.html5QrCode.isScanning) {
                try {
                    await this.html5QrCode.stop();
                    this.html5QrCode = null;
                } catch (error) {
                    console.error("Error stopping scanner:", error);
                }
            }
            this.$emit("update:showScanner", false);
        },

        onScanSuccess(decodedText) {
            this.$emit("code-scanned", decodedText);
            this.stopScanner();
        },

        onScanError(error) {
            // Wir ignorieren die meisten Fehler, da sie auftreten,
            // wenn kein QR-Code im Bild ist
            if (error?.message?.includes("NotFoundException")) {
                return;
            }
            console.warn(`QR Error: ${error}`);
        },
    },

    beforeUnmount() {
        this.stopScanner();
    },
};
</script>

<style scoped>
#reader {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
}
#reader video {
    width: 100% !important;
}
</style>
