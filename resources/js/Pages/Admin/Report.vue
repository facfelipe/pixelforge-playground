<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    from: String,
    to: String,
    summary: Object,
    byDay: Array,
    byUser: Array,
});

const from = ref(props.from);
const to = ref(props.to);

const apply = () => {
    router.get(route('admin.report'), { from: from.value, to: to.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Admin report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-zinc-800">Admin · Usage & profit</h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-8 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end gap-3 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <label class="block text-xs text-zinc-500">From</label>
                    <input v-model="from" type="date" class="mt-1 rounded-md border-zinc-300 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-zinc-500">To</label>
                    <input v-model="to" type="date" class="mt-1 rounded-md border-zinc-300 text-sm" />
                </div>
                <button
                    type="button"
                    class="rounded-md bg-zinc-900 px-3 py-2 text-sm text-white"
                    @click="apply"
                >
                    Apply
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-xs uppercase text-zinc-500">User spend</p>
                    <p class="mt-1 text-2xl font-semibold">
                        <MoneyAmount :amount="summary.user_spend_display" />
                    </p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-xs uppercase text-zinc-500">Provider cost</p>
                    <p class="mt-1 text-2xl font-semibold">
                        <MoneyAmount :amount="summary.provider_cost_display" />
                    </p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-xs uppercase text-zinc-500">Profit</p>
                    <p class="mt-1 text-2xl font-semibold">
                        <MoneyAmount :amount="summary.profit_display" />
                    </p>
                </div>
            </div>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <h3 class="border-b border-zinc-100 px-4 py-3 text-sm font-medium">By day</h3>
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="px-4 py-2">Day</th>
                            <th class="px-4 py-2">Gens</th>
                            <th class="px-4 py-2">Spend</th>
                            <th class="px-4 py-2">Provider</th>
                            <th class="px-4 py-2">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr v-for="row in byDay" :key="row.day">
                            <td class="px-4 py-2">{{ row.day }}</td>
                            <td class="px-4 py-2">{{ row.generations }}</td>
                            <td class="px-4 py-2">
                                <MoneyAmount :amount="row.user_spend_display" />
                            </td>
                            <td class="px-4 py-2">
                                <MoneyAmount :amount="row.provider_cost_display" />
                            </td>
                            <td class="px-4 py-2">
                                <MoneyAmount :amount="row.profit_display" />
                            </td>
                        </tr>
                        <tr v-if="byDay.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-zinc-500">No data</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <h3 class="border-b border-zinc-100 px-4 py-3 text-sm font-medium">By user</h3>
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="px-4 py-2">User</th>
                            <th class="px-4 py-2">Gens</th>
                            <th class="px-4 py-2">Spend</th>
                            <th class="px-4 py-2">Provider</th>
                            <th class="px-4 py-2">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr v-for="row in byUser" :key="row.user_id">
                            <td class="px-4 py-2">
                                <div>{{ row.name }}</div>
                                <div class="text-xs text-zinc-500">{{ row.email }}</div>
                            </td>
                            <td class="px-4 py-2">{{ row.generations }}</td>
                            <td class="px-4 py-2">
                                <MoneyAmount :amount="row.user_spend_display" />
                            </td>
                            <td class="px-4 py-2">
                                <MoneyAmount :amount="row.provider_cost_display" />
                            </td>
                            <td class="px-4 py-2">
                                <MoneyAmount :amount="row.profit_display" />
                            </td>
                        </tr>
                        <tr v-if="byUser.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-zinc-500">No data</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
