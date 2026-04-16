<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, ShieldCheck, Sparkles, Waves } from 'lucide-vue-next';
import logoUrl from '../../../../public/eastwestbpo-logo.png';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="relative min-h-svh overflow-hidden bg-[#f5f7ff] text-zinc-950 dark:bg-[#050816] dark:text-zinc-50">
        <Head title="Log in" />

        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.28),_transparent_32%),radial-gradient(circle_at_80%_20%,_rgba(168,85,247,0.25),_transparent_28%),radial-gradient(circle_at_50%_100%,_rgba(14,165,233,0.18),_transparent_35%)]"></div>
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.28)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.28)_1px,transparent_1px)] bg-[size:72px_72px] opacity-20 dark:opacity-[0.08]"></div>
        <div class="pointer-events-none absolute left-[-10rem] top-[-8rem] h-80 w-80 rounded-full bg-cyan-300/30 blur-3xl dark:bg-cyan-500/20"></div>
        <div class="pointer-events-none absolute bottom-[-10rem] right-[-8rem] h-96 w-96 rounded-full bg-fuchsia-300/25 blur-3xl dark:bg-fuchsia-500/20"></div>

        <div class="absolute right-4 top-4 z-30 md:right-8 md:top-8">
            <ThemeToggle />
        </div>

        <div class="relative z-10 mx-auto flex min-h-svh w-full max-w-7xl items-center px-0 py-0 sm:px-0 lg:px-8 lg:py-5">
            <div class="grid w-full gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                <section
                    class="order-2 hidden overflow-hidden rounded-[2rem] border border-white/40 bg-white/35 p-6 shadow-[0_30px_80px_rgba(79,70,229,0.15)] backdrop-blur-2xl dark:border-white/10 dark:bg-white/[0.06] dark:shadow-[0_30px_80px_rgba(2,6,23,0.6)] sm:p-7 lg:order-1 lg:block lg:min-h-[640px] lg:p-8"
                >
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/80 to-transparent dark:via-white/30"></div>
                    <div class="absolute right-8 top-8 hidden rounded-full border border-white/50 bg-white/50 px-4 py-2 text-xs font-medium text-zinc-600 backdrop-blur md:flex md:items-center md:gap-2 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300">
                        <Sparkles class="size-3.5" />
                        Incident command center
                    </div>

                    <div class="flex h-full flex-col justify-between gap-8">
                        <div class="space-y-6">
                            <Link :href="route('home')" class="inline-flex">
                                <img :src="logoUrl" alt="EastWest BPO" class="h-11 w-auto object-contain sm:h-12" />
                            </Link>

                            <div class="max-w-xl space-y-4">
                                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200/70 bg-white/60 px-4 py-2 text-xs font-semibold uppercase tracking-[0.28em] text-indigo-700 backdrop-blur dark:border-indigo-400/20 dark:bg-white/5 dark:text-indigo-200">
                                    <Waves class="size-3.5" />
                                    Incident Management System
                                </div>
                                <div class="space-y-3">
                                    <h1 class="max-w-xl text-3xl font-semibold leading-tight tracking-[-0.04em] text-zinc-950 dark:text-white sm:text-4xl lg:text-5xl">
                                        Manage incidents from one clear workspace.
                                    </h1>
                                    <p class="max-w-lg text-sm leading-6 text-zinc-600 dark:text-zinc-300 sm:text-base">
                                        Sign in to the Incident Management System used by EastWest BPO teams for ticket handling, escalation, and response.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-3xl border border-white/50 bg-white/60 p-4 backdrop-blur dark:border-white/10 dark:bg-white/[0.05]">
                                <div class="mb-3 inline-flex rounded-2xl bg-emerald-500/15 p-2.5 text-emerald-600 dark:text-emerald-300">
                                    <ShieldCheck class="size-5" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Secure incident access</p>
                                <p class="mt-1.5 text-xs leading-5 text-zinc-600 dark:text-zinc-400">Protected sign-in for reports, escalations, and response workflows.</p>
                            </div>
                            <div class="rounded-3xl border border-white/50 bg-white/60 p-4 backdrop-blur dark:border-white/10 dark:bg-white/[0.05]">
                                <div class="mb-3 inline-flex rounded-2xl bg-sky-500/15 p-2.5 text-sky-600 dark:text-sky-300">
                                    <Sparkles class="size-5" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Faster triage flow</p>
                                <p class="mt-1.5 text-xs leading-5 text-zinc-600 dark:text-zinc-400">Jump straight into tickets, priorities, and assigned work.</p>
                            </div>
                            <div class="rounded-3xl border border-white/50 bg-white/60 p-4 backdrop-blur dark:border-white/10 dark:bg-white/[0.05]">
                                <div class="mb-3 inline-flex rounded-2xl bg-violet-500/15 p-2.5 text-violet-600 dark:text-violet-300">
                                    <Waves class="size-5" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Operational clarity</p>
                                <p class="mt-1.5 text-xs leading-5 text-zinc-600 dark:text-zinc-400">A refined layout that keeps the system clear and easy to use.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="order-1 flex min-h-svh items-center lg:order-2 lg:min-h-0">
                    <div class="w-full px-5 pt-24 sm:px-6 lg:rounded-[2rem] lg:border lg:border-white/50 lg:bg-white/75 lg:p-8 lg:shadow-[0_24px_80px_rgba(15,23,42,0.14)] lg:backdrop-blur-2xl lg:dark:border-white/10 lg:dark:bg-[#0b1225]/80 lg:dark:shadow-[0_24px_80px_rgba(2,6,23,0.65)]">
                        <div class="mb-6 flex items-start justify-between gap-4">
                            <div class="space-y-2">
                                <p class="text-sm font-medium uppercase tracking-[0.24em] text-indigo-600 dark:text-indigo-300">Welcome back</p>
                                <h2 class="text-2xl font-semibold tracking-[-0.03em] text-zinc-950 dark:text-white sm:text-3xl">Sign in to the Incident Management System</h2>
                                <p class="max-w-sm text-sm leading-5 text-zinc-600 dark:text-zinc-400">
                                    Access incident records, queues, and response tools.
                                </p>
                            </div>
                            <div class="hidden rounded-2xl border border-white/60 bg-white/70 p-3 text-zinc-700 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 sm:block">
                                <ShieldCheck class="size-5" />
                            </div>
                        </div>

                        <div
                            v-if="status"
                            class="mb-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="email" class="text-zinc-700 dark:text-zinc-200">Email address</Label>
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autofocus
                                        :tabindex="1"
                                        autocomplete="email"
                                        placeholder="name@eastwestbpo.com"
                                        class="h-12 rounded-2xl border-white/60 bg-white/75 px-4 shadow-sm ring-0 backdrop-blur-sm transition focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-white/10 dark:bg-white/[0.05]"
                                    />
                                    <InputError :message="form.errors.email" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="password" class="text-zinc-700 dark:text-zinc-200">Password</Label>
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        required
                                        tabindex="2"
                                        autocomplete="current-password"
                                        placeholder="Enter your password"
                                        class="h-12 rounded-2xl border-white/60 bg-white/75 px-4 shadow-sm ring-0 backdrop-blur-sm transition focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-white/10 dark:bg-white/[0.05]"
                                    />
                                    <InputError :message="form.errors.password" />
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/50 bg-white/50 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
                                <Label for="remember" class="flex items-center gap-3 text-sm font-medium text-zinc-600 dark:text-zinc-300">
                                    <Checkbox
                                        id="remember"
                                        v-model:checked="form.remember"
                                        :tabindex="3"
                                        class="border-zinc-300 data-[state=checked]:border-indigo-600 data-[state=checked]:bg-indigo-600 dark:border-zinc-700"
                                    />
                                    <span>Keep me signed in</span>
                                </Label>
                                <span class="hidden text-xs uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500 sm:inline">Protected</span>
                            </div>

                            <Button
                                type="submit"
                                class="h-12 w-full rounded-2xl bg-gradient-to-r from-indigo-600 via-violet-600 to-sky-500 text-sm font-semibold text-white shadow-[0_16px_40px_rgba(99,102,241,0.35)] transition hover:scale-[1.01] hover:from-indigo-500 hover:via-violet-500 hover:to-sky-400 active:scale-[0.99]"
                                :tabindex="4"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                                Log in to IMS
                            </Button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
