<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const { updateAppearance } = useAppearance();

// A preferência salva pode ser 'system'; o tema efetivo só é conhecido pela classe já aplicada no <html>.
const escuro = ref(false);

onMounted(() => {
    escuro.value = document.documentElement.classList.contains('dark');
});

const rotulo = computed(() => (escuro.value ? 'Ativar modo claro' : 'Ativar modo escuro'));

function alternar() {
    escuro.value = !escuro.value;
    updateAppearance(escuro.value ? 'dark' : 'light');
}
</script>

<template>
    <Button variant="ghost" size="icon" class="rounded-xl" :aria-label="rotulo" :title="rotulo" @click="alternar">
        <Sun v-if="escuro" aria-hidden="true" />
        <Moon v-else aria-hidden="true" />
    </Button>
</template>
