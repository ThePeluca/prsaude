<?php

declare(strict_types=1);

namespace App\Actions\Usuario;

use App\Events\SenhaUsuarioRedefinida;
use App\Exceptions\UsuarioInvalidoException;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * Redefinição de senha da equipe pelo administrador. A senha do paciente é reset da
 * recepção (`paciente.resetar_senha`, doc §2.3) e não passa por aqui.
 */
final class RedefinirSenhaUsuarioAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(User $usuario, string $senha, User $autor): User
    {
        if ($usuario->ehPaciente()) {
            throw UsuarioInvalidoException::pacienteForaDoModulo();
        }

        return DB::transaction(function () use ($usuario, $senha, $autor) {
            $usuario->forceFill([
                'password' => $senha,
                // RN-06: quem recebe a senha do administrador troca no primeiro acesso.
                'senha_provisoria' => true,
                'senha_alterada_em' => null,
                // RNF-08: a redefinição é também o desbloqueio administrativo.
                'tentativas_falhas' => 0,
                'bloqueado_ate' => null,
            ])->save();

            $usuario->encerrarSessoes();

            // A senha nunca chega ao log: nenhum dado de credencial é passado aqui.
            $this->auditoria->registrar(
                acao: 'usuario.senha_redefinida',
                entidade: 'User',
                entidadeId: $usuario->id,
                usuario: $autor,
            );

            SenhaUsuarioRedefinida::dispatch($usuario);

            return $usuario;
        });
    }
}
