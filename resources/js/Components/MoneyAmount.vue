<script setup>
defineProps({
    /** Pre-formatted display string from the backend (`Money::formatDisplay`). */
    amount: {
        type: String,
        default: null,
    },
    empty: {
        type: String,
        default: '—',
    },
});
</script>

<template>
    <span class="tabular-nums" :class="{ 'text-zinc-400': amount == null }">
        <template v-if="amount == null">{{ empty }}</template>
        <template v-else>${{ amount }}</template>
    </span>
</template>
