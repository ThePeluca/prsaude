<?php

declare(strict_types=1);

namespace App\Actions\Usuario;

use App\Events\SituacaoUsuarioAlterada;
use App\Exceptions\UsuarioInvalidoException;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * doc §2.3, "Gestão de usuários e perfis" (D -- exclusão lógica).
 *
 * D-18 / D-08: conta da equipe nunca é excluída fisicamente. `registro_clinico.autor_id`
 * é NOT NULL e a trilha de auditoria precisa continuar dizendo quem agiu; desativar é a
 * forma de tirar o acesso sem apagar a autoria.
 */
final class AlterarSituacaoUsuarioAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function execute(User $usuario, bool $ativo, User $autor): User
    {
        if ($usuario->ehPaciente()) {
            throw UsuarioInvalidoException::pacienteForaDoModulo();
        }

        if (! $ativo && $usuario->is($autor)) {
            throw UsuarioInvalidoException::autoDesativacao();
        }

        return DB::transaction(function () use ($usuario, $ativo, $autor) {
            $usuario->forceFill(['ativo' => $ativo])->save();

            // `profissional.ativo` acompanha a conta: Profissional::emPlantao() o exige,
            // então quem é desativado sai do mínimo vital e da quebra de sigilo (§13.5).
            Profissional::query()->where('user_id', $usuario->id)->update(['ativo' => $ativo]);

            if (! $ativo) {
                $usuario->encerrarSessoes();
            }

            $this->auditoria->registrar(
                acao: $ativo ? 'usuario.reativar' : 'usuario.desativar',
                entidade: 'User',
                entidadeId: $usuario->id,
                antes: ['ativo' => ! $ativo],
                depois: ['ativo' => $ativo],
                usuario: $autor,
            );

            SituacaoUsuarioAlterada::dispatch($usuario, $ativo);

            return $usuario;
        });
    }
}
