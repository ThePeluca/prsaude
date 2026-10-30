<?php

declare(strict_types=1);

namespace App\Actions\Usuario;

use App\Events\ProfissionalCadastrado;
use App\Models\Profissional;
use App\Models\User;
use App\Services\Auditoria\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * doc §2.3, "Gestão de usuários e perfis" (C). Cria a conta da equipe e a
 * especialização `profissional` em uma única transação: um `users` sem `profissional`
 * é uma conta que entra no sistema e não consegue assinar nenhum ato clínico.
 */
final class CadastrarProfissionalAction
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** @param  array<string, mixed>  $dados  Já validado por CadastrarProfissionalRequest. */
    public function execute(array $dados, User $autor): Profissional
    {
        return DB::transaction(function () use ($dados, $autor) {
            $roles = array_values($dados['roles']);

            $usuario = User::create([
                // D-03: `users.name` é rótulo; o nome oficial é `profissional.nome_completo`.
                'name' => $dados['nome_completo'],
                'email' => $dados['email'],
                'password' => $dados['senha'],
                // schema.sql: o login do profissional é a matrícula.
                'login' => $dados['matricula'],
                'tipo' => User::tipoPorRoles($roles),
                // RN-06: a senha entregue pelo administrador é provisória.
                'senha_provisoria' => true,
                'ativo' => true,
            ]);

            $usuario->syncRoles($roles);

            $profissional = Profissional::create([
                'user_id' => $usuario->id,
                'unidade_id' => $dados['unidade_id'],
                'nome_completo' => $dados['nome_completo'],
                'matricula' => $dados['matricula'],
                'categoria' => $dados['categoria'],
                'conselho_tipo' => $dados['conselho_tipo'] ?? null,
                'conselho_numero' => $dados['conselho_numero'] ?? null,
                'conselho_uf' => $dados['conselho_uf'] ?? null,
                'especialidade' => $dados['especialidade'] ?? null,
                'capacidade_fila' => $dados['capacidade_fila'] ?? 20,
                'ativo' => true,
            ]);

            $this->auditoria->registrar(
                acao: 'usuario.criar',
                entidade: 'Profissional',
                entidadeId: $usuario->id,
                depois: [...$profissional->getAttributes(), 'email' => $usuario->email, 'roles' => $roles],
                usuario: $autor,
            );

            ProfissionalCadastrado::dispatch($profissional);

            return $profissional;
        });
    }
}
