<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Usuario\AlterarSituacaoUsuarioAction;
use App\Actions\Usuario\AtualizarProfissionalAction;
use App\Actions\Usuario\CadastrarProfissionalAction;
use App\Actions\Usuario\RedefinirSenhaUsuarioAction;
use App\Exceptions\UsuarioInvalidoException;
use App\Http\Requests\Usuario\AtualizarProfissionalRequest;
use App\Http\Requests\Usuario\CadastrarProfissionalRequest;
use App\Http\Requests\Usuario\DadosProfissionalRequest;
use App\Http\Requests\Usuario\RedefinirSenhaUsuarioRequest;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * doc §2.3, "Gestão de usuários e perfis": C R U D exclusivo do administrador
 * (`usuario.gerenciar`). Só contas da equipe: o paciente é cadastrado pela recepção
 * (UC-01) e não aparece aqui. Ver DECISOES.md D-62.
 */
final class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('usuario.gerenciar'), 403);

        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'situacao' => ['nullable', 'in:ativos,inativos'],
        ]);
        $termo = trim($filtros['busca'] ?? '');

        $usuarios = User::query()
            ->where('tipo', '!=', 'PACIENTE')
            ->with(['profissional.unidade:id,nome', 'roles:id,name'])
            ->when($termo !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$termo}%")
                ->orWhere('email', 'like', "%{$termo}%")
                ->orWhere('login', 'like', "%{$termo}%")))
            ->when(($filtros['situacao'] ?? null) === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when(($filtros['situacao'] ?? null) === 'inativos', fn ($q) => $q->where('ativo', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'matricula' => $usuario->login,
                'categoria' => $usuario->profissional?->categoria,
                'conselho' => $usuario->profissional?->conselhoFormatado(),
                'unidade' => $usuario->profissional?->unidade?->nome,
                'roles' => $usuario->roles->pluck('name'),
                'tipo' => $usuario->tipo,
                'ativo' => $usuario->ativo,
                'bloqueado' => $usuario->estaBloqueado(),
                'senha_provisoria' => $usuario->senha_provisoria,
            ]);

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'filtros' => ['busca' => $termo, 'situacao' => $filtros['situacao'] ?? ''],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('usuario.gerenciar'), 403);

        return Inertia::render('Usuarios/Form', [...$this->opcoes(), 'usuario' => null]);
    }

    public function store(CadastrarProfissionalRequest $request, CadastrarProfissionalAction $cadastrar): RedirectResponse
    {
        $profissional = $cadastrar->execute($request->validated(), $request->user());

        return redirect()
            ->route('usuarios.edit', $profissional->user_id)
            ->with('status', 'Profissional cadastrado. Entregue a senha provisória: a troca é exigida no primeiro acesso.');
    }

    public function edit(Request $request, User $usuario): Response
    {
        abort_unless($request->user()->can('usuario.gerenciar'), 403);
        abort_if($usuario->ehPaciente(), 404);

        $usuario->load(['profissional', 'roles:id,name']);
        $profissional = $usuario->profissional;

        return Inertia::render('Usuarios/Form', [
            ...$this->opcoes(),
            'usuario' => [
                'id' => $usuario->id,
                'nome_completo' => $profissional?->nome_completo ?? $usuario->name,
                'email' => $usuario->email,
                'matricula' => $profissional?->matricula ?? $usuario->login,
                'unidade_id' => $profissional?->unidade_id,
                'categoria' => $profissional?->categoria,
                'conselho_tipo' => $profissional?->conselho_tipo,
                'conselho_numero' => $profissional?->conselho_numero,
                'conselho_uf' => $profissional?->conselho_uf,
                'especialidade' => $profissional?->especialidade,
                'capacidade_fila' => $profissional?->capacidade_fila ?? 20,
                'roles' => $usuario->roles->pluck('name'),
                'ativo' => $usuario->ativo,
                'bloqueado' => $usuario->estaBloqueado(),
                'senha_provisoria' => $usuario->senha_provisoria,
                'tem_registro_profissional' => $profissional !== null,
                'ehVoce' => $usuario->is($request->user()),
            ],
        ]);
    }

    public function update(AtualizarProfissionalRequest $request, User $usuario, AtualizarProfissionalAction $atualizar): RedirectResponse
    {
        abort_if($usuario->ehPaciente(), 404);

        try {
            $atualizar->execute($usuario, $request->validated(), $request->user());
        } catch (UsuarioInvalidoException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        return back()->with('status', 'Cadastro atualizado.');
    }

    public function situacao(Request $request, User $usuario, AlterarSituacaoUsuarioAction $alterar): RedirectResponse
    {
        abort_unless($request->user()->can('usuario.gerenciar'), 403);
        abort_if($usuario->ehPaciente(), 404);

        $ativo = $request->validate(['ativo' => ['required', 'boolean']])['ativo'];

        try {
            $alterar->execute($usuario, (bool) $ativo, $request->user());
        } catch (UsuarioInvalidoException $e) {
            return back()->with('alerta', $e->getMessage());
        }

        return back()->with('status', $ativo
            ? 'Conta reativada.'
            : 'Conta desativada. As sessões abertas foram encerradas; a autoria dos registros foi preservada.');
    }

    public function senha(RedefinirSenhaUsuarioRequest $request, User $usuario, RedefinirSenhaUsuarioAction $redefinir): RedirectResponse
    {
        abort_if($usuario->ehPaciente(), 404);

        $redefinir->execute($usuario, $request->validated('senha'), $request->user());

        return back()->with('status', 'Senha provisória definida. A troca é exigida no próximo acesso.');
    }

    /** @return array<string, mixed> */
    private function opcoes(): array
    {
        return [
            'unidades' => Unidade::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'categorias' => DadosProfissionalRequest::CATEGORIAS,
            'conselhos' => DadosProfissionalRequest::CONSELHOS,
        ];
    }
}
