<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras comuns ao cadastro e à edição de profissional, espelhando `schema.sql`
 * (tabela `profissional`) para que o erro chegue como mensagem e não como violação de
 * constraint. As constraints continuam sendo a garantia final.
 */
abstract class DadosProfissionalRequest extends FormRequest
{
    public const CATEGORIAS = ['MEDICO', 'ENFERMEIRO', 'TECNICO_ENFERMAGEM', 'LABORATORIO', 'RECEPCAO', 'FARMACIA', 'ADMIN'];

    public const CONSELHOS = ['CRM', 'COREN', 'CRF', 'CRBM', 'OUTRO'];

    public function authorize(): bool
    {
        return $this->user()?->can('usuario.gerenciar') ?? false;
    }

    /** Id do usuário em edição, para as regras `unique` o ignorarem. */
    abstract protected function usuarioId(): ?int;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->usuarioId();

        return [
            'nome_completo' => ['required', 'string', 'max:150'],
            // D-23: a equipe ainda autentica por e-mail, então ele é obrigatório aqui.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            // `users.login` = matrícula (schema.sql). Único entre TODOS os usuários,
            // inclusive o CPF dos pacientes.
            'matricula' => [
                'required', 'string', 'max:30',
                Rule::unique('users', 'login')->ignore($id),
                Rule::unique('profissional', 'matricula')->ignore($id, 'user_id'),
            ],
            'unidade_id' => ['required', Rule::exists('unidade', 'id')->where('ativo', true)],
            'categoria' => ['required', Rule::in(self::CATEGORIAS)],

            // ck_profissional_conselho: médico e enfermeiro exigem número de conselho.
            'conselho_numero' => [
                'nullable', 'string', 'max:20',
                Rule::requiredIf(in_array($this->input('categoria'), ['MEDICO', 'ENFERMEIRO'], strict: true)),
                // uk_profissional_conselho
                Rule::unique('profissional', 'conselho_numero')
                    ->where('conselho_tipo', $this->input('conselho_tipo'))
                    ->where('conselho_uf', $this->input('conselho_uf'))
                    ->ignore($id, 'user_id'),
            ],
            'conselho_tipo' => ['nullable', 'required_with:conselho_numero', Rule::in(self::CONSELHOS)],
            'conselho_uf' => ['nullable', 'required_with:conselho_numero', 'string', 'size:2', 'alpha'],

            'especialidade' => ['nullable', 'string', 'max:100'],
            // §7.4: teto de referência para o balanceamento da fila.
            'capacidade_fila' => ['required', 'integer', 'min:1', 'max:200'],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'conselho_uf' => $this->filled('conselho_uf') ? mb_strtoupper((string) $this->input('conselho_uf')) : null,
            'conselho_numero' => $this->filled('conselho_numero') ? trim((string) $this->input('conselho_numero')) : null,
            'conselho_tipo' => $this->filled('conselho_tipo') ? $this->input('conselho_tipo') : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome_completo' => 'nome completo',
            'matricula' => 'matrícula',
            'unidade_id' => 'unidade',
            'conselho_numero' => 'número do conselho',
            'conselho_tipo' => 'conselho',
            'conselho_uf' => 'UF do conselho',
            'capacidade_fila' => 'capacidade de fila',
            'roles' => 'perfis de acesso',
            'senha' => 'senha provisória',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conselho_numero.required' => 'Médico e enfermeiro exigem o número do conselho profissional.',
            'conselho_numero.unique' => 'Já existe profissional com este registro de conselho.',
            'matricula.unique' => 'Esta matrícula já está em uso por outro usuário.',
            'roles.required' => 'Selecione ao menos um perfil de acesso.',
        ];
    }
}
