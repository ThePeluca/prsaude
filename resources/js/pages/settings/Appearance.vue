<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configurações de aparência',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configurações de aparência" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Configurações de aparência" description="Escolha o tema claro, escuro ou o padrão do sistema" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
