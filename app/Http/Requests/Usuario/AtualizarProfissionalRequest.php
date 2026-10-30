<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use App\Models\User;

final class AtualizarProfissionalRequest extends DadosProfissionalRequest
{
    protected function usuarioId(): ?int
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User ? $usuario->id : null;
    }
}
