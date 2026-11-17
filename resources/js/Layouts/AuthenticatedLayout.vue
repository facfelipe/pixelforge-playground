<script setup>
import { computed, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();
const user = computed(() => page.props.auth.user);
const appName = computed(() => page.props.appName || 'Pixelforge Playground');
</script>

<template>
    <div>
        <div class="min-h-screen bg-zinc-100">
            <nav class="border-b border-zinc-200 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link
                                    :href="route('studio')"
                                    class="text-sm font-semibold tracking-tight text-zinc-900"
                                >
                                    {{ appName }}
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    :href="route('studio')"
                                    :active="route().current('studio')"
                                >
                                    Studio
                                </NavLink>
                                <NavLink
                                    :href="route('credits.index')"
                                    :active="route().current('credits.*')"
                                >
                                    Credits
                                </NavLink>
                                <NavLink
                                    v-if="user.is_admin"
                                    :href="route('admin.report')"
                                    :active="route().current('admin.report')"
                                >
                                    Admin
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden items-center gap-4 sm:ms-6 sm:flex">
                            <Link
                                :href="route('credits.index')"
                                class="text-sm tabular-nums text-zinc-700"
                            >
                                Balance
                                <MoneyAmount class="font-semibold" :amount="user.credit_balance_display" />
                            </Link>

                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-zinc-500 transition hover:text-zinc-700 focus:outline-none"
                                            >
                                                {{ user.name }}
                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-500 focus:outline-none"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('studio')"
                            :active="route().current('studio')"
                        >
                            Studio
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('credits.index')"
                            :active="route().current('credits.*')"
                        >
                            Credits
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="user.is_admin"
                            :href="route('admin.report')"
                            :active="route().current('admin.report')"
                        >
                            Admin
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-zinc-200 pb-1 pt-4">
                        <div class="px-4 text-sm text-zinc-600">
                            Balance
                            <MoneyAmount :amount="user.credit_balance_display" />
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="bg-white shadow">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
