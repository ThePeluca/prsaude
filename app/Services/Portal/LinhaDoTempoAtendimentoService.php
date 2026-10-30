<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\StatusAtendimento;
use App\Models\Atendimento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "Evolução do atendimento" do portal (doc §12.3): tudo o que aconteceu com o paciente
 * no episódio, em ordem cronológica e em linguagem acessível.
 *
 * O que fica de fora, de propósito:
 * - registro sigiloso (RF-73/RF-77), sem contador nem placeholder;
 * - resultado de exame ainda não liberado (RN-24): a solicitação aparece, o resultado não;
 * - texto interno da equipe: observação de mudança de status, justificativa de
 *   transferência ou de classificação, indicação clínica do exame;
 * - quem executou cada ato, exceto o autor do registro clínico, que já aparecia antes.
 *
 * Só leitura. Toda consulta parte do atendimento já resolvido pelo
 * DoPacienteAutenticadoScope (RN-26), então nenhum evento de outro paciente entra.
 */
final class LinhaDoTempoAtendimentoService
{
    /**
     * @return Collection<int, array{id: string, quando: string|null, categoria: string, titulo: string, texto: string|null}>
     */
    public function montar(Atendimento $atendimento): Collection
    {
        $atendimento->loadMissing([
            'statusHistorico',
            'filaItens.classificacaoRisco',
            'triagens.classificacaoRisco',
            'exameSolicitacoes.exame',
            'exameSolicitacoes.resultado',
            'prescricoes.itens',
            'administracoes.prescricaoItem.medicamento',
            'registrosClinicos',
        ]);

        return collect([
            ...$this->status($atendimento),
            ...$this->fila($atendimento),
            ...$this->triagens($atendimento),
            ...$this->exames($atendimento),
            ...$this->prescricoes($atendimento),
            ...$this->medicamentos($atendimento),
            ...$this->registros($atendimento),
        ])
            ->filter(fn (array $evento) => $evento['em'] !== null)
            // Empate no mesmo instante: mantém a ordem de inclusão acima (estável).
            ->sortBy(fn (array $evento) => $evento['em']->format('Y-m-d H:i:s.u'))
            ->values()
            ->map(fn (array $evento) => [
                'id' => $evento['id'],
                'quando' => $evento['em']->format('d/m/Y H:i'),
                'categoria' => $evento['categoria'],
                'titulo' => $evento['titulo'],
                'texto' => $evento['texto'] ?? null,
            ]);
    }

    /** RN-15: cada transição gravada no histórico vira um marco do episódio. */
    private function status(Atendimento $atendimento): array
    {
        return $atendimento->statusHistorico->map(fn ($h) => $this->evento(
            "status-{$h->id}",
            $h->criado_em,
            'status',
            StatusAtendimento::tryFrom($h->status_novo)?->rotuloPaciente() ?? 'Situação atualizada',
        ))->all();
    }

    private function fila(Atendimento $atendimento): array
    {
        $eventos = [];

        foreach ($atendimento->filaItens as $item) {
            $eventos[] = $item->transferido_de_id === null
                ? $this->evento(
                    "fila-{$item->id}", $item->entrou_em, 'fila', 'Entrou na fila de atendimento',
                    $item->classificacaoRisco ? "Prioridade: {$item->classificacaoRisco->nome}" : null,
                )
                : $this->evento("fila-{$item->id}", $item->entrou_em, 'fila', 'Encaminhado para outro profissional');

            if ($item->chamado_em !== null) {
                $eventos[] = $this->evento("fila-chamado-{$item->id}", $item->chamado_em, 'fila', 'Chamado para atendimento');
            }
        }

        return $eventos;
    }

    private function triagens(Atendimento $atendimento): array
    {
        return $atendimento->triagens->map(fn ($t) => $this->evento(
            "triagem-{$t->id}",
            $t->criado_em,
            'triagem',
            ($t->reclassificacao ? 'Classificação de risco revisada: ' : 'Classificação de risco: ')
                .($t->classificacaoRisco?->nome ?? '—'),
            $t->reclassificacao ? null : "Queixa: {$t->queixa_principal}",
        ))->all();
    }

    private function exames(Atendimento $atendimento): array
    {
        $eventos = [];

        foreach ($atendimento->exameSolicitacoes as $s) {
            $nome = $s->exame?->nome ?? 'Exame';

            $eventos[] = $this->evento(
                "exame-{$s->id}", $s->solicitado_em, 'exame', "Exame solicitado: {$nome}",
                $s->carater === 'URGENTE' ? 'Caráter de urgência' : null,
            );

            if ($s->coletado_em !== null) {
                $eventos[] = $this->evento("exame-coleta-{$s->id}", $s->coletado_em, 'exame', "Material coletado: {$nome}");
            }

            if ($s->cancelado_em !== null) {
                $eventos[] = $this->evento("exame-cancelado-{$s->id}", $s->cancelado_em, 'exame', "Exame cancelado: {$nome}");
            }

            // RN-24: só depois da liberação explícita.
            if ($s->resultado?->visivel_ao_paciente) {
                $eventos[] = $this->evento(
                    "exame-resultado-{$s->id}", $s->resultado->liberado_em, 'exame',
                    "Resultado disponível: {$nome}", 'Consulte a seção Exames.',
                );
            }
        }

        return $eventos;
    }

    private function prescricoes(Atendimento $atendimento): array
    {
        $eventos = [];

        foreach ($atendimento->prescricoes as $p) {
            $quantidade = $p->itens->count();
            $eventos[] = $this->evento(
                "prescricao-{$p->id}", $p->criado_em, 'prescricao', 'Prescrição médica registrada',
                $quantidade === 1 ? '1 medicamento' : "{$quantidade} medicamentos",
            );

            if ($p->suspensa_em !== null) {
                $eventos[] = $this->evento("prescricao-suspensa-{$p->id}", $p->suspensa_em, 'prescricao', 'Prescrição médica suspensa');
            }
        }

        return $eventos;
    }

    /** Mesmo critério da seção Medicamentos do portal: só dose de fato administrada. */
    private function medicamentos(Atendimento $atendimento): array
    {
        return $atendimento->administracoes
            ->filter(fn ($a) => $a->foiAdministrada())
            ->map(function ($a) {
                $medicamento = $a->prescricaoItem?->medicamento;
                $dose = $a->dose_administrada !== null
                    ? rtrim(rtrim((string) $a->dose_administrada, '0'), '.').' '.$a->unidade_dose
                    : null;

                return $this->evento(
                    "medicamento-{$a->id}", $a->administrado_em, 'medicamento',
                    'Medicação administrada: '.($medicamento?->nome_comercial ?? 'medicamento'),
                    collect([$dose, $a->via?->rotuloPaciente()])->filter()->implode(' · ') ?: null,
                );
            })->all();
    }

    /** RF-73/RF-77: sigiloso é omitido sem deixar rastro. */
    private function registros(Atendimento $atendimento): array
    {
        return $atendimento->registrosClinicos
            ->where('sigiloso', false)
            ->map(fn ($r) => $this->evento(
                "registro-{$r->id}",
                $r->criado_em,
                'registro',
                $r->tipo->rotulo(),
                $r->conteudo_livre ?: collect([$r->subjetivo, $r->objetivo, $r->avaliacao, $r->plano])->filter()->implode("\n\n"),
            ))->all();
    }

    /** @return array{id: string, em: CarbonInterface|null, categoria: string, titulo: string, texto: string|null} */
    private function evento(string $id, ?CarbonInterface $em, string $categoria, string $titulo, ?string $texto = null): array
    {
        return compact('id', 'em', 'categoria', 'titulo', 'texto');
    }
}
