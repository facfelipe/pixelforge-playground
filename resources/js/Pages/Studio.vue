<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    generations: { type: Array, default: () => [] },
    hasActiveGenerations: { type: Boolean, default: false },
    defaults: { type: Object, required: true },
});

const page = usePage();
const balance = computed(() => Number(page.props.auth.user.credit_balance));

const form = useForm({
    prompt: '',
    size: props.defaults.size,
    quality: props.defaults.quality,
    reference_images: [''],
});

const estimate = ref({
    estimated_user_cost_display: '0.00',
    estimated_user_cost_max_display: '0.00',
    estimated_user_cost: '0',
    estimated_user_cost_max: '0',
});

let estimateTimer = null;
let pollTimer = null;

const canAfford = computed(() => balance.value >= Number(estimate.value.estimated_user_cost_max));

const statusClass = (status) => {
    return (
        {
            queued: 'bg-zinc-100 text-zinc-700',
            processing: 'bg-amber-100 text-amber-800',
            succeeded: 'bg-emerald-100 text-emerald-800',
            failed: 'bg-red-100 text-red-800',
        }[status] || 'bg-zinc-100 text-zinc-700'
    );
};

const referenceImagesPayload = () =>
    form.reference_images.map((u) => u.trim()).filter((u) => u.length > 0);

const refreshEstimate = async () => {
    const body = {
        prompt: form.prompt,
        size: form.size,
        quality: form.quality,
        reference_images: referenceImagesPayload(),
    };

    const response = await window.axios.post(route('studio.estimate'), body);
    estimate.value = response.data;
};

const scheduleEstimate = () => {
    clearTimeout(estimateTimer);
    estimateTimer = setTimeout(() => {
        refreshEstimate().catch(() => {});
    }, 250);
};

watch(
    () => [form.prompt, form.size, form.quality, ...form.reference_images],
    scheduleEstimate,
    { deep: true },
);

const addReference = () => {
    if (form.reference_images.length < 4) {
        form.reference_images.push('');
    }
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            reference_images: referenceImagesPayload(),
        }))
        .post(route('studio.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.prompt = '';
                form.reference_images = [''];
                scheduleEstimate();
            },
        });
};

onMounted(() => {
    scheduleEstimate();
    pollTimer = setInterval(() => {
        if (props.hasActiveGenerations) {
            router.reload({ only: ['generations', 'hasActiveGenerations', 'auth'], preserveScroll: true });
        }
    }, 2000);
});

onUnmounted(() => {
    clearTimeout(estimateTimer);
    clearInterval(pollTimer);
});
</script>

<template>
    <Head title="Studio" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-zinc-800">Studio</h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-8 py-8 sm:px-6 lg:px-8">
            <section class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-zinc-100 px-4 py-3 sm:px-6">
                    <h3 class="text-sm font-medium text-zinc-900">Your generations</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">
                            <tr>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Prompt</th>
                                <th class="px-4 py-3">Estimated</th>
                                <th class="px-4 py-3">Actual</th>
                                <th class="px-4 py-3">Image</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 bg-white">
                            <tr v-if="generations.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                    No generations yet. Create one below.
                                </td>
                            </tr>
                            <tr v-for="row in generations" :key="row.uuid">
                                <td class="px-4 py-3 align-top">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        :class="statusClass(row.status)"
                                    >
                                        {{ row.status }}
                                    </span>
                                    <p v-if="row.error_message" class="mt-1 max-w-xs text-xs text-red-600">
                                        {{ row.error_message }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 align-top text-zinc-800">
                                    <span class="line-clamp-2">{{ row.prompt }}</span>
                                    <p class="mt-1 text-xs text-zinc-400">{{ row.size }} · {{ row.quality }}</p>
                                </td>
                                <td class="px-4 py-3 align-top text-zinc-700">
                                    <MoneyAmount :amount="row.estimated_user_cost_display" />
                                </td>
                                <td class="px-4 py-3 align-top text-zinc-700">
                                    <MoneyAmount :amount="row.actual_user_cost_display" />
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <img
                                        v-if="row.image_url"
                                        :src="row.image_url"
                                        alt=""
                                        class="h-16 w-16 rounded border border-zinc-200 object-cover"
                                    />
                                    <span v-else class="text-xs text-zinc-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-medium text-zinc-900">Generate</h3>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="prompt" value="Prompt" />
                        <textarea
                            id="prompt"
                            v-model="form.prompt"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-zinc-500 focus:ring-zinc-500"
                            placeholder="A lighthouse on a cliff at dawn… Try [fail] to force a free failure."
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.prompt" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="size" value="Size" />
                            <select
                                id="size"
                                v-model="form.size"
                                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-zinc-500 focus:ring-zinc-500"
                            >
                                <option value="1024x1024">1024x1024</option>
                                <option value="1536x1024">1536x1024</option>
                                <option value="1024x1536">1024x1536</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="quality" value="Quality" />
                            <select
                                id="quality"
                                v-model="form.quality"
                                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-zinc-500 focus:ring-zinc-500"
                            >
                                <option value="low">low</option>
                                <option value="medium">medium</option>
                                <option value="high">high</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Reference image URLs (optional, max 4)" />
                        <div
                            v-for="(url, index) in form.reference_images"
                            :key="index"
                            class="mt-2"
                        >
                            <TextInput
                                v-model="form.reference_images[index]"
                                type="url"
                                class="block w-full"
                                placeholder="https://example.com/style.png"
                            />
                        </div>
                        <button
                            v-if="form.reference_images.length < 4"
                            type="button"
                            class="mt-2 text-sm text-zinc-600 underline"
                            @click="addReference"
                        >
                            + Add URL
                        </button>
                    </div>

                    <div class="rounded-md bg-zinc-50 px-4 py-3 text-sm text-zinc-700">
                        <p>
                            Estimated
                            <MoneyAmount
                                class="font-semibold"
                                :amount="estimate.estimated_user_cost_display"
                            />
                            · Up to
                            <MoneyAmount
                                class="font-semibold"
                                :amount="estimate.estimated_user_cost_max_display"
                            />
                        </p>
                    </div>

                    <InputError :message="form.errors.credits" />

                    <div v-if="!canAfford" class="text-sm text-amber-800">
                        Not enough credits for this generation (up to
                        <MoneyAmount :amount="estimate.estimated_user_cost_max_display" />).
                        <Link :href="route('credits.index')" class="underline">Add credits</Link>.
                    </div>

                    <PrimaryButton
                        :disabled="form.processing || !canAfford || !form.prompt.trim()"
                        class="bg-zinc-900"
                    >
                        Generate
                    </PrimaryButton>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
