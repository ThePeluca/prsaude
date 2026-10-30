<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use Illuminate\Validation\Rules\Password;

final class CadastrarProfissionalRequest extends DadosProfissionalRequest
{
    protected function usuarioId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // RN-06: provisória, trocada no primeiro acesso. Definida pelo administrador,
            // não gerada e exibida: a sessão não é criptografada e a senha gerada ficaria
            // em texto claro na tabela `sessions` até a próxima requisição (D-62).
            'senha' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
