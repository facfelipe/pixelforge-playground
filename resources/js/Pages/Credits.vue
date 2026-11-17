<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    balance_display: { type: String, required: true },
    pack_display: { type: String, required: true },
});

const form = useForm({});

const buy = () => {
    form.post(route('credits.store'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Credits" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-zinc-800">Credits</h2>
        </template>

        <div class="mx-auto max-w-lg py-8 sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="text-sm text-zinc-500">Current balance</p>
                <p class="mt-1 text-3xl font-semibold text-zinc-900">
                    <MoneyAmount :amount="balance_display" />
                </p>
                <p class="mt-4 text-sm text-zinc-600">
                    Demo credits only — no real payment. Click again to add more.
                </p>
                <form class="mt-6" @submit.prevent="buy">
                    <PrimaryButton :disabled="form.processing" class="bg-zinc-900">
                        Add <MoneyAmount :amount="pack_display" />
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
