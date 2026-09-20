<template>
    <div class="bg-white shadow-sm sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
                Lieferanten &amp; Preise
            </h3>
        </div>

        <div class="px-4 pb-5 sm:px-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Lieferant
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stückpreis
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Standard
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aktionen
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="supplier in article.suppliers" :key="supplier.id">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ supplier.name }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                :value="preise[supplier.id]"
                                @input="preise[supplier.id] = $event.target.value"
                                class="w-32 rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <input
                                type="radio"
                                :checked="Boolean(supplier.pivot.is_default)"
                                @change="alsStandardSetzen(supplier)"
                                class="text-indigo-600 focus:ring-indigo-500"
                            />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button
                                @click="preisSpeichern(supplier)"
                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                                Preis speichern
                            </button>
                            <button
                                @click="zuordnungEntfernen(supplier)"
                                class="text-red-600 hover:text-red-900"
                            >
                                Entfernen
                            </button>
                        </td>
                    </tr>
                    <tr v-if="article.suppliers.length === 0">
                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">
                            Diesem Artikel ist noch kein Lieferant zugeordnet
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-4 flex items-end space-x-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Lieferant
                    </label>
                    <select
                        v-model="neuerLieferantId"
                        class="mt-1 block w-64 rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">Bitte wählen</option>
                        <option
                            v-for="option in nichtZugeordnete"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Stückpreis
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        v-model="neuerPreis"
                        class="mt-1 block w-32 rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>
                <button
                    @click="zuordnen"
                    :disabled="!neuerLieferantId"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-xs hover:bg-indigo-700 disabled:opacity-50"
                >
                    Zuordnen
                </button>
            </div>

            <div
                v-if="$page.props.errors.supplier_id"
                class="mt-3 text-red-500 text-sm"
            >
                {{ $page.props.errors.supplier_id }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    article: Object,
    availableSuppliers: Array,
});

const preise = reactive(
    Object.fromEntries(
        props.article.suppliers.map((supplier) => [
            supplier.id,
            supplier.pivot.price,
        ])
    )
);

const neuerLieferantId = ref("");
const neuerPreis = ref(0);

const nichtZugeordnete = computed(() => {
    const zugeordnet = props.article.suppliers.map((supplier) => supplier.id);
    return props.availableSuppliers.filter(
        (supplier) => !zugeordnet.includes(supplier.id)
    );
});

const zuordnen = () => {
    router.post(
        route("articles.suppliers.store", { article: props.article.id }),
        {
            supplier_id: neuerLieferantId.value,
            price: neuerPreis.value,
        },
        {
            onSuccess: () => {
                neuerLieferantId.value = "";
                neuerPreis.value = 0;
            },
        }
    );
};

const preisSpeichern = (supplier) => {
    router.put(
        route("articles.suppliers.update", {
            article: props.article.id,
            supplier: supplier.id,
        }),
        {
            price: preise[supplier.id],
        }
    );
};

const alsStandardSetzen = (supplier) => {
    router.put(
        route("articles.suppliers.update", {
            article: props.article.id,
            supplier: supplier.id,
        }),
        {
            price: preise[supplier.id],
            is_default: true,
        }
    );
};

const zuordnungEntfernen = (supplier) => {
    if (!confirm("Möchten Sie diese Lieferantenzuordnung wirklich entfernen?")) {
        return;
    }

    router.delete(
        route("articles.suppliers.destroy", {
            article: props.article.id,
            supplier: supplier.id,
        })
    );
};
</script>
