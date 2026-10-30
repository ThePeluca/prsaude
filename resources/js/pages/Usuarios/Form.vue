<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { rotuloCategoria, rotuloRole } from '@/lib/rotulosEquipe';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleOff, KeyRound, LoaderCircle, Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface UsuarioEdicao {
    id: number;
    nome_completo: string;
    email: string | null;
    matricula: string;
    unidade_id: number | null;
    categoria: string | null;
    conselho_tipo: string | null;
    conselho_numero: string | null;
    conselho_uf: string | null;
    especialidade: string | null;
    capacidade_fila: number;
    roles: string[];
    ativo: boolean;
    bloqueado: boolean;
    senha_provisoria: boolean;
    tem_registro_profissional: boolean;
    ehVoce: boolean;
}

const props = defineProps<{
    usuario: UsuarioEdicao | null;
    unidades: { id: number; nome: string }[];
    roles: string[];
    categorias: string[];
    conselhos: string[];
}>();

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status);
const alerta = computed(() => page.props.flash?.alerta);

const editando = computed(() => props.usuario !== null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuários', href: '/usuarios' },
    props.usuario ? { title: props.usuario.nome_completo, href: '#' } : { title: 'Novo profissional', href: '/usuarios/novo' },
];

/**
 * doc §2.3, "Gestão de usuários e perfis". Toda validação é do servidor
 * (DadosProfissionalRequest); aqui só feedback visual.
 */
const form = useForm({
    nome_completo: props.usuario?.nome_completo ?? '',
    email: props.usuario?.email ?? '',
    matricula: props.usuario?.matricula ?? '',
    unidade_id: props.usuario?.unidade_id ?? props.unidades[0]?.id ?? null,
    categoria: props.usuario?.categoria ?? 'RECEPCAO',
    conselho_tipo: props.usuario?.conselho_tipo ?? '',
    conselho_numero: props.usuario?.conselho_numero ?? '',
    conselho_uf: props.usuario?.conselho_uf ?? '',
    especialidade: props.usuario?.especialidade ?? '',
    capacidade_fila: props.usuario?.capacidade_fila ?? 20,
    roles: [...(props.usuario?.roles ?? [])] as string[],
    senha: '',
    senha_confirmation: '',
});

// Só indicação visual; quem recusa é o servidor (ck_profissional_conselho).
const exigeConselho = computed(() => ['MEDICO', 'ENFERMEIRO'].includes(form.categoria));

const enviar = () => {
    if (props.usuario) {
        form.put(route('usuarios.update', props.usuario.id), { preserveScroll: true });
    } else {
        form.post(route('usuarios.store'));
    }
};

const formSituacao = useForm({ ativo: false as boolean });
const confirmandoDesativacao = ref(false);

const alterarSituacao = (ativo: boolean) => {
    if (!props.usuario) return;
    formSituacao.ativo = ativo;
    formSituacao.patch(route('usuarios.situacao', props.usuario.id), {
        preserveScroll: true,
        onFinish: () => (confirmandoDesativacao.value = false),
    });
};

const formSenha = useForm({ senha: '', senha_confirmation: '' });

const redefinirSenha = () => {
    if (!props.usuario) return;
    formSenha.put(route('usuarios.senha', props.usuario.id), {
        preserveScroll: true,
        onSuccess: () => formSenha.reset(),
    });
};
</script>

<template>
    <Head :title="usuario ? usuario.nome_completo : 'Novo profissional'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4">
            <div
                v-if="status"
                class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-900 dark:bg-green-950 dark:text-green-100"
                role="status"
            >
                {{ status }}
            </div>
            <div
                v-if="alerta"
                class="rounded-md bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                role="alert"
            >
                {{ alerta }}
            </div>

            <div v-if="usuario" class="flex flex-wrap items-center gap-2">
                <!-- RNF-15: cor + rótulo + ícone. -->
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
                <span v-if="usuario.bloqueado" class="inline-flex items-center gap-1 text-xs font-medium text-red-800 dark:text-red-200">
                    <Lock class="h-3.5 w-3.5" aria-hidden="true" />
                    Bloqueado por tentativas falhas
                </span>
                <span v-if="usuario.senha_provisoria" class="inline-flex items-center gap-1 text-xs font-medium text-amber-800 dark:text-amber-200">
                    <KeyRound class="h-3.5 w-3.5" aria-hidden="true" />
                    Senha provisória pendente de troca
                </span>
            </div>

            <div
                v-if="usuario && !usuario.tem_registro_profissional"
                class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100"
            >
                Esta conta ainda não tem registro profissional. Ao salvar, ele é criado — sem ele, a conta não assina atos clínicos.
            </div>

            <form class="flex flex-col gap-8" @submit.prevent="enviar">
                <section class="grid gap-4">
                    <h2 class="text-lg font-semibold">Identificação</h2>

                    <div class="grid gap-2">
                        <Label for="nome_completo">Nome completo</Label>
                        <Input id="nome_completo" v-model="form.nome_completo" required autofocus />
                        <InputError :message="form.errors.nome_completo" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">E-mail</Label>
                            <Input id="email" v-model="form.email" type="email" required />
                            <p class="text-xs text-muted-foreground">É com ele que o profissional entra no sistema.</p>
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="matricula">Matrícula</Label>
                            <Input id="matricula" v-model="form.matricula" required />
                            <InputError :message="form.errors.matricula" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-4">
                    <h2 class="text-lg font-semibold">Registro profissional</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="categoria">Categoria</Label>
                            <select
                                id="categoria"
                                v-model="form.categoria"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option v-for="categoria in categorias" :key="categoria" :value="categoria">
                                    {{ rotuloCategoria[categoria] ?? categoria }}
                                </option>
                            </select>
                            <InputError :message="form.errors.categoria" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="unidade_id">Unidade</Label>
                            <select
                                id="unidade_id"
                                v-model="form.unidade_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option v-for="unidade in unidades" :key="unidade.id" :value="unidade.id">{{ unidade.nome }}</option>
                            </select>
                            <InputError :message="form.errors.unidade_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[10rem_1fr_6rem]">
                        <div class="grid gap-2">
                            <Label for="conselho_tipo">Conselho</Label>
                            <select
                                id="conselho_tipo"
                                v-model="form.conselho_tipo"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                            >
                                <option value="">—</option>
                                <option v-for="conselho in conselhos" :key="conselho" :value="conselho">{{ conselho }}</option>
                            </select>
                            <InputError :message="form.errors.conselho_tipo" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="conselho_numero">Número do conselho {{ exigeConselho ? '(obrigatório)' : '' }}</Label>
                            <Input id="conselho_numero" v-model="form.conselho_numero" />
                            <InputError :message="form.errors.conselho_numero" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="conselho_uf">UF</Label>
                            <Input id="conselho_uf" v-model="form.conselho_uf" maxlength="2" />
                            <InputError :message="form.errors.conselho_uf" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                        <div class="grid gap-2">
                            <Label for="especialidade">Especialidade</Label>
                            <Input id="especialidade" v-model="form.especialidade" />
                            <InputError :message="form.errors.especialidade" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="capacidade_fila">Capacidade de fila</Label>
                            <Input id="capacidade_fila" v-model.number="form.capacidade_fila" type="number" min="1" />
                            <InputError :message="form.errors.capacidade_fila" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-3">
                    <h2 class="text-lg font-semibold">Perfis de acesso</h2>
                    <p class="-mt-1 text-xs text-muted-foreground">
                        O perfil define o que a conta pode fazer em princípio (doc §2.3). O acesso a cada paciente continua dependendo do vínculo
                        assistencial. O perfil <strong>Administrador</strong> concede acesso irrestrito.
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="role in roles"
                            :key="role"
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                        >
                            <input v-model="form.roles" type="checkbox" :value="role" class="h-4 w-4" />
                            {{ rotuloRole[role] ?? role }}
                        </label>
                    </div>
                    <InputError :message="form.errors.roles" />
                </section>

                <section v-if="!editando" class="grid gap-4">
                    <h2 class="text-lg font-semibold">Senha provisória</h2>
                    <p class="-mt-2 text-xs text-muted-foreground">
                        Entregue pessoalmente ao profissional. A troca é exigida no primeiro acesso (RN-06).
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="senha">Senha</Label>
                            <Input id="senha" v-model="form.senha" type="password" autocomplete="new-password" />
                            <InputError :message="form.errors.senha" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="senha_confirmation">Confirmação</Label>
                            <Input id="senha_confirmation" v-model="form.senha_confirmation" type="password" autocomplete="new-password" />
                        </div>
                    </div>
                </section>

                <div class="flex justify-end gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        {{ editando ? 'Salvar alterações' : 'Cadastrar profissional' }}
                    </Button>
                </div>
            </form>

            <template v-if="usuario">
                <section class="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-lg font-semibold">Redefinir senha</h2>
                    <p class="-mt-2 text-xs text-muted-foreground">
                        Define uma nova senha provisória, encerra as sessões abertas e desfaz o bloqueio por tentativas falhas.
                    </p>
                    <form class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="redefinirSenha">
                        <div class="grid gap-2">
                            <Label for="nova_senha">Nova senha provisória</Label>
                            <Input id="nova_senha" v-model="formSenha.senha" type="password" autocomplete="new-password" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nova_senha_confirmation">Confirmação</Label>
                            <Input id="nova_senha_confirmation" v-model="formSenha.senha_confirmation" type="password" autocomplete="new-password" />
                        </div>
                        <Button type="submit" variant="outline" :disabled="formSenha.processing">
                            <KeyRound class="h-4 w-4" aria-hidden="true" />
                            Redefinir
                        </Button>
                    </form>
                    <InputError :message="formSenha.errors.senha" />
                </section>

                <section class="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 class="text-lg font-semibold">Situação da conta</h2>

                    <template v-if="usuario.ativo">
                        <p class="text-sm text-muted-foreground">
                            Desativar impede o login e encerra as sessões abertas. Nada é excluído: os registros clínicos continuam assinados por esta
                            conta.
                        </p>
                        <p v-if="usuario.ehVoce" class="text-sm text-muted-foreground">Você não pode desativar a própria conta.</p>
                        <div v-else-if="!confirmandoDesativacao">
                            <Button type="button" variant="destructive" @click="confirmandoDesativacao = true">
                                <CircleOff class="h-4 w-4" aria-hidden="true" />
                                Desativar conta
                            </Button>
                        </div>
                        <div v-else class="flex flex-wrap items-center gap-2 rounded-md bg-red-50 p-3 dark:bg-red-950/40">
                            <span class="text-sm font-medium text-red-900 dark:text-red-100"
                                >Confirmar desativação de {{ usuario.nome_completo }}?</span
                            >
                            <Button type="button" variant="destructive" size="sm" :disabled="formSituacao.processing" @click="alterarSituacao(false)">
                                Desativar
                            </Button>
                            <Button type="button" variant="ghost" size="sm" @click="confirmandoDesativacao = false">Cancelar</Button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm text-muted-foreground">A conta está desativada e não consegue entrar no sistema.</p>
                        <div>
                            <Button type="button" variant="outline" :disabled="formSituacao.processing" @click="alterarSituacao(true)">
                                <CircleCheck class="h-4 w-4" aria-hidden="true" />
                                Reativar conta
                            </Button>
                        </div>
                    </template>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
