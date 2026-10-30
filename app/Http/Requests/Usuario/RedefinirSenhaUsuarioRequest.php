<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RedefinirSenhaUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('usuario.gerenciar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'senha' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['senha' => 'senha provisória'];
    }
}
