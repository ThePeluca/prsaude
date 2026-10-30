<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Activity, ClipboardList, FileText, FlaskConical, ListOrdered, Pill, Stethoscope, type LucideIcon } from 'lucide-vue-next';

type Categoria = 'status' | 'fila' | 'triagem' | 'exame' | 'prescricao' | 'medicamento' | 'registro';

defineProps<{
    atendimento: { numero: string; status: string; admitido_em: string; finalizado_em: string | null; desfecho: string | null };
    evolucao: { id: string; quando: string; categoria: Categoria; titulo: string; texto: string | null }[];
}>();

// Ícone + rótulo textual por categoria: a cor nunca é o único indicador (RNF-15).
const categorias: Record<Categoria, { rotulo: string; icone: LucideIcon; classe: string }> = {
    status: { rotulo: 'Situação', icone: Activity, classe: 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100' },
    fila: { rotulo: 'Fila', icone: ListOrdered, classe: 'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-100' },
    triagem: { rotulo: 'Triagem', icone: Stethoscope, classe: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100' },
    exame: { rotulo: 'Exame', icone: FlaskConical, classe: 'bg-cyan-100 text-cyan-900 dark:bg-cyan-950 dark:text-cyan-100' },
    prescricao: { rotulo: 'Prescrição', icone: ClipboardList, classe: 'bg-green-100 text-green-900 dark:bg-green-950 dark:text-green-100' },
    medicamento: { rotulo: 'Medicação', icone: Pill, classe: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100' },
    registro: { rotulo: 'Registro clínico', icone: FileText, classe: 'bg-neutral-200 text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' },
};
</script>

<template>
    <Head title="Evolução do atendimento" /><PortalLayout
        ><main class="mx-auto flex max-w-3xl flex-col gap-5 p-4">
            <header>
                <h1 class="text-2xl font-bold">Evolução do atendimento</h1>
                <p>
                    {{ atendimento.status }} · <span class="font-mono text-sm">{{ atendimento.numero }}</span>
                </p>
                <p class="text-sm text-muted-foreground">
                    Início: {{ atendimento.admitido_em
                    }}<template v-if="atendimento.finalizado_em"> · Término: {{ atendimento.finalizado_em }}</template>
                    <template v-if="atendimento.desfecho"> · {{ atendimento.desfecho }}</template>
                </p>
            </header>
            <ol v-if="evolucao.length" class="grid gap-3">
                <li v-for="item in evolucao" :key="item.id" class="rounded-xl border bg-white p-4 dark:bg-neutral-900">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold"
                            :class="categorias[item.categoria].classe"
                        >
                            <component :is="categorias[item.categoria].icone" class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ categorias[item.categoria].rotulo }}
                        </span>
                        <time>{{ item.quando }}</time>
                    </div>
                    <p class="mt-2 font-medium">{{ item.titulo }}</p>
                    <p v-if="item.texto" class="mt-1 whitespace-pre-line text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                        {{ item.texto }}
                    </p>
                </li>
            </ol>
            <p v-else class="rounded-xl border border-dashed p-6 text-muted-foreground">Ainda não há evolução disponível.</p>
        </main></PortalLayout
    >
</template>
