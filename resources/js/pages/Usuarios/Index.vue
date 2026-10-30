<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { rotuloCategoria, rotuloRole } from '@/lib/rotulosEquipe';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, CircleOff, KeyRound, Lock, Search, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

interface UsuarioLinha {
    id: number;
    nome: string;
    email: string | null;
    matricula: string;
    categoria: string | null;
    conselho: string | null;
    unidade: string | null;
    roles: string[];
    tipo: 'PROFISSIONAL' | 'ADMIN';
    ativo: boolean;
    bloqueado: boolean;
    senha_provisoria: boolean;
}

const props = defineProps<{
    usuarios: { data: UsuarioLinha[]; links: { url: string | null; label: string; active: boolean }[] };
    filtros: { busca: string; situacao: string };
}>();

const busca = ref(props.filtros.busca);
const situacao = ref(props.filtros.situacao);

const buscar = () => {
    router.get(route('usuarios.index'), { busca: busca.value, situacao: situacao.value }, { preserveState: true, replace: true });
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usuários', href: '/usuarios' }];
</script>

<template>
    <Head title="Usuários" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <form class="flex w-full max-w-2xl flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="buscar">
                    <div class="w-full min-w-0 flex-1">
                        <Label for="busca">Buscar usuário</Label>
                        <Input id="busca" v-model="busca" type="search" class="mt-1" placeholder="Nome, e-mail ou matrícula" />
                    </div>
                    <div>
                        <Label for="situacao">Situação</Label>
                        <select
                            id="situacao"
                            v-model="situacao"
                            class="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm sm:w-36"
                        >
                            <option value="">Todas</option>
                            <option value="ativos">Ativos</option>
                            <option value="inativos">Inativos</option>
                        </select>
                    </div>
                    <Button type="submit" class="w-full sm:w-auto">
                        <Search class="h-4 w-4" aria-hidden="true" />
                        Buscar
                    </Button>
                </form>

                <Button as-child class="w-full sm:w-auto">
                    <Link :href="route('usuarios.create')">
                        <UserPlus class="h-4 w-4" aria-hidden="true" />
                        Novo profissional
                    </Link>
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                Contas da equipe. Pacientes são cadastrados pela recepção e não aparecem aqui. Contas não são excluídas: quem sai da equipe é
                desativado, preservando a autoria dos registros clínicos.
            </p>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <caption class="sr-only">
                        Usuários da equipe
                    </caption>
                    <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nome</th>
                            <th scope="col" class="px-4 py-3">Matrícula</th>
                            <th scope="col" class="px-4 py-3">Categoria</th>
                            <th scope="col" class="px-4 py-3">Perfis</th>
                            <th scope="col" class="px-4 py-3">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr v-for="usuario in usuarios.data" :key="usuario.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                            <td class="px-4 py-3">
                                <Link :href="route('usuarios.edit', usuario.id)" class="font-medium underline-offset-4 hover:underline">
                                    {{ usuario.nome }}
                                </Link>
                                <div class="text-xs text-muted-foreground">{{ usuario.email }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ usuario.matricula }}</td>
                            <td class="px-4 py-3">
                                <span v-if="usuario.categoria">{{ rotuloCategoria[usuario.categoria] ?? usuario.categoria }}</span>
                                <span v-else class="text-xs text-amber-800 dark:text-amber-200">sem registro profissional</span>
                                <div v-if="usuario.conselho" class="text-xs text-muted-foreground">{{ usuario.conselho }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in usuario.roles"
                                        :key="role"
                                        class="rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] font-medium text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        {{ rotuloRole[role] ?? role }}
                                    </span>
                                    <span v-if="!usuario.roles.length" class="text-xs text-muted-foreground">nenhum</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <!-- RNF-15: cor + rótulo + ícone. -->
                                <div class="flex flex-col items-start gap-1">
                                    <span
                                        v-if="usuario.ativo"
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-900 dark:bg-green-950 dark:text-green-100"
                                    >
                                        <CircleCheck class="h-3.5 w-3.5" aria-hidden="true" />
                                        Ativo
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-semibold text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        <CircleOff class="h-3.5 w-3.5" aria-hidden="true" />
                                        Inativo
                                    </span>
                                    <span v-if="usuario.bloqueado" class="inline-flex items-center gap-1 text-xs text-red-800 dark:text-red-200">
                                        <Lock class="h-3.5 w-3.5" aria-hidden="true" />
                                        Bloqueado por tentativas
                                    </span>
                                    <span
                                        v-if="usuario.senha_provisoria"
                                        class="inline-flex items-center gap-1 text-xs text-amber-800 dark:text-amber-200"
                                    >
                                        <KeyRound class="h-3.5 w-3.5" aria-hidden="true" />
                                        Senha provisória
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!usuarios.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Nenhum usuário encontrado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="usuarios.links.length > 3" class="flex flex-wrap gap-1" aria-label="Paginação">
                <Link
                    v-for="link in usuarios.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="[
                        link.active
                            ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                            : 'hover:bg-neutral-100 dark:hover:bg-neutral-800',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
            </nav>
        </div>
    </AppLayout>
</template>
