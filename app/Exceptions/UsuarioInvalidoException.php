<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Operação de gestão de usuários recusada por regra (doc §2.3, "Gestão de usuários e
 * perfis"). Ver DECISOES.md D-62.
 */
final class UsuarioInvalidoException extends DominioException
{
    public static function pacienteForaDoModulo(): self
    {
        return new self('Conta de paciente é gerida pela recepção, não pela gestão de usuários da equipe.');
    }

    public static function autoDesativacao(): self
    {
        return new self('Você não pode desativar a própria conta.');
    }

    public static function autoRebaixamento(): self
    {
        return new self('Você não pode remover o próprio perfil de administrador.');
    }
}
