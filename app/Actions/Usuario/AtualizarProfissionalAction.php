<?php

declare(strict_types=1);

namespace App\Actions\Usuario;

use App\Events\ProfissionalAtualizado;
use App\Exceptions\UsuarioInvalidoException;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * doc §2.3, "Gestão de usuários e perfis" (U).
 *
 * Atua sobre o `User`, não sobre o `Profissional`: a conta administrativa semeada por
 * `UsuarioAdministradorSeeder` nasce sem registro profissional (D-28), e é aqui que ela
 * ganha um.
 *
 * Os registros clínicos já assinados não mudam: eles guardam snapshot do nome e do
 * conselho do autor no momento do ato.
 */
final class AtualizarProfissionalAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** @param  array<string, mixed>  $dados  Já validado por AtualizarProfissionalRequest. */
    public function execute(User $usuario, array $dados, User $autor): Profissional
    {
        if ($usuario->ehPaciente()) {
            throw UsuarioInvalidoException::pacienteForaDoModulo();
        }

        $roles = array_values($dados['roles']);

        // Sem esta trava, o único administrador consegue se trancar fora da gestão de
        // usuários -- e a única saída passa a ser escrever direto no banco.
        if ($usuario->is($autor) && $usuario->hasRole('admin') && ! in_array('admin', $roles, strict: true)) {
            throw UsuarioInvalidoException::autoRebaixamento();
        }

        return DB::transaction(function () use ($usuario, $dados, $roles, $autor) {
            $profissional = Profissional::withTrashed()->firstOrNew(['user_id' => $usuario->id]);

            $antes = [
                ...$profissional->getAttributes(),
                'email' => $usuario->email,
                'roles' => $usuario->getRoleNames()->all(),
            ];

            $usuario->forceFill([
                // D-03: a Action mantém `users.name` em sincronia com o nome oficial.
                'name' => $dados['nome_completo'],
                'email' => $dados['email'],
                'login' => $dados['matricula'],
                'tipo' => User::tipoPorRoles($roles),
            ])->save();

            $usuario->syncRoles($roles);

            $profissional->fill([
                'unidade_id' => $dados['unidade_id'],
                'nome_completo' => $dados['nome_completo'],
                'matricula' => $dados['matricula'],
                'categoria' => $dados['categoria'],
                'conselho_tipo' => $dados['conselho_tipo'] ?? null,
                'conselho_numero' => $dados['conselho_numero'] ?? null,
                'conselho_uf' => $dados['conselho_uf'] ?? null,
                'especialidade' => $dados['especialidade'] ?? null,
                'capacidade_fila' => $dados['capacidade_fila'] ?? 20,
            ]);

            if (! $profissional->exists) {
                $profissional->ativo = $usuario->ativo;
            }

            $profissional->save();

            $this->auditoria->registrar(
                acao: 'usuario.atualizar',
                entidade: 'Profissional',
                entidadeId: $usuario->id,
                antes: $antes,
                depois: [...$profissional->getAttributes(), 'email' => $usuario->email, 'roles' => $roles],
                usuario: $autor,
            );

            ProfissionalAtualizado::dispatch($profissional);

            return $profissional;
        });
    }
}
