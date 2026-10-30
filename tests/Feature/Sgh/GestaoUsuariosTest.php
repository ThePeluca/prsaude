<?php

declare(strict_types=1);

use App\Actions\Usuario\AlterarSituacaoUsuarioAction;
use App\Actions\Usuario\AtualizarProfissionalAction;
use App\Events\ProfissionalCadastrado;
use App\Exceptions\UsuarioInvalidoException;
use App\Models\AuditoriaLog;
use App\Models\Profissional;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

// D-62: gestão de usuários da equipe (doc §2.3, "Gestão de usuários e perfis").

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('admin');
    $this->unidade = Unidade::factory()->create();
});

/** @return array<string, mixed> */
function dadosProfissional(array $sobrescreve = []): array
{
    return array_merge([
        'nome_completo' => 'Juliana Prado',
        'email' => 'juliana.prado@prsaude.com',
        'matricula' => 'MAT-9001',
        'unidade_id' => test()->unidade->id,
        'categoria' => 'MEDICO',
        'conselho_tipo' => 'CRM',
        'conselho_numero' => '123456',
        'conselho_uf' => 'pr',
        'especialidade' => 'Clínica médica',
        'capacidade_fila' => 15,
        'roles' => ['medico'],
        'senha' => 'Provisoria#2026',
        'senha_confirmation' => 'Provisoria#2026',
    ], $sobrescreve);
}

// =====================================================================
// Autorização: só usuario.gerenciar (doc §2.3)
// =====================================================================

it('nega a gestao de usuarios a quem nao tem usuario.gerenciar', function () {
    $recepcao = User::factory()->profissional()->create();
    $recepcao->assignRole('recepcao');

    $this->actingAs($recepcao)->get(route('usuarios.index'))->assertForbidden();
    $this->actingAs($recepcao)->post(route('usuarios.store'), dadosProfissional())->assertForbidden();

    expect(User::where('login', 'MAT-9001')->exists())->toBeFalse();
});

it('nao expoe rota de exclusao de usuario', function () {
    // D-18 / D-08: quem assinou ato clínico não é apagado; sai por desativação.
    $alvo = Profissional::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/usuarios/{$alvo->user_id}")
        ->assertStatus(405);
});

it('nao lista nem edita conta de paciente', function () {
    $paciente = User::factory()->paciente()->create(['name' => 'Paciente Qualquer']);

    $this->actingAs($this->admin)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertDontSee('Paciente Qualquer');

    $this->actingAs($this->admin)->get(route('usuarios.edit', $paciente))->assertNotFound();
    $this->actingAs($this->admin)
        ->patch(route('usuarios.situacao', $paciente), ['ativo' => false])
        ->assertNotFound();

    expect($paciente->fresh()->ativo)->toBeTrue();
});

// =====================================================================
// Cadastro
// =====================================================================

it('cadastra usuario, registro profissional e perfil na mesma transacao', function () {
    Event::fake([ProfissionalCadastrado::class]);

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional())
        ->assertRedirect();

    $usuario = User::where('login', 'MAT-9001')->firstOrFail();

    expect($usuario->tipo)->toBe('PROFISSIONAL')
        ->and($usuario->name)->toBe('Juliana Prado')
        ->and($usuario->email)->toBe('juliana.prado@prsaude.com')
        // RN-06: senha entregue pelo administrador é provisória.
        ->and($usuario->senha_provisoria)->toBeTrue()
        // RNF-07
        ->and($usuario->password)->toStartWith('$argon2id$')
        ->and(Hash::check('Provisoria#2026', $usuario->password))->toBeTrue()
        ->and($usuario->hasRole('medico'))->toBeTrue();

    $profissional = $usuario->profissional;
    expect($profissional->categoria)->toBe('MEDICO')
        ->and($profissional->matricula)->toBe('MAT-9001')
        ->and($profissional->conselho_uf)->toBe('PR')
        ->and($profissional->conselhoFormatado())->toBe('CRM/PR 123456')
        ->and($profissional->capacidade_fila)->toBe(15);

    Event::assertDispatched(ProfissionalCadastrado::class);
});

it('audita o cadastro sem gravar a senha', function () {
    $this->actingAs($this->admin)->post(route('usuarios.store'), dadosProfissional());

    $log = AuditoriaLog::where('acao', 'usuario.criar')->latest('id')->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and(json_encode($log->dados_depois))->not->toContain('Provisoria#2026')
        ->and($log->dados_depois['roles'])->toBe(['medico']);
});

it('exige conselho para medico e enfermeiro', function (string $categoria) {
    // ck_profissional_conselho, antecipado como mensagem de validação.
    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional([
            'categoria' => $categoria, 'conselho_tipo' => '', 'conselho_numero' => '', 'conselho_uf' => '',
        ]))
        ->assertSessionHasErrors('conselho_numero');
})->with(['MEDICO', 'ENFERMEIRO']);

it('dispensa conselho para recepcao', function () {
    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional([
            'categoria' => 'RECEPCAO', 'conselho_tipo' => '', 'conselho_numero' => '', 'conselho_uf' => '',
            'roles' => ['recepcao'],
        ]))
        ->assertSessionHasNoErrors();

    expect(Profissional::where('matricula', 'MAT-9001')->value('conselho_numero'))->toBeNull();
});

it('recusa matricula ja usada como login, inclusive CPF de paciente', function () {
    User::factory()->paciente()->create(['login' => '12345678909']);

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional(['matricula' => '12345678909']))
        ->assertSessionHasErrors('matricula');
});

it('recusa registro de conselho duplicado', function () {
    Profissional::factory()->create(['conselho_tipo' => 'CRM', 'conselho_numero' => '123456', 'conselho_uf' => 'PR']);

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional())
        ->assertSessionHasErrors('conselho_numero');
});

it('recusa perfil inexistente e cadastro sem perfil', function () {
    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional(['roles' => ['superusuario']]))
        ->assertSessionHasErrors('roles.0');

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional(['roles' => []]))
        ->assertSessionHasErrors('roles');
});

it('deriva o tipo ADMIN do perfil administrador', function () {
    // D-62 / D-20: tipo ADMIN é superacesso; não pode divergir da role.
    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), dadosProfissional([
            'categoria' => 'ADMIN', 'conselho_tipo' => '', 'conselho_numero' => '', 'conselho_uf' => '',
            'roles' => ['admin'],
        ]));

    expect(User::where('login', 'MAT-9001')->value('tipo'))->toBe('ADMIN');
});

// =====================================================================
// Edição
// =====================================================================

it('atualiza cadastro, perfis e mantem users.name em sincronia', function () {
    $alvo = Profissional::factory()->recepcao()->create();
    $alvo->user->assignRole('recepcao');

    $this->actingAs($this->admin)
        ->put(route('usuarios.update', $alvo->user_id), dadosProfissional([
            'nome_completo' => 'Nome Corrigido',
            'categoria' => 'ENFERMEIRO', 'conselho_tipo' => 'COREN',
            'roles' => ['enfermeiro_triagem', 'enfermeiro_assistencial'],
        ]))
        ->assertSessionHasNoErrors();

    $usuario = $alvo->user->fresh();
    expect($usuario->name)->toBe('Nome Corrigido')
        ->and($usuario->login)->toBe('MAT-9001')
        ->and($usuario->getRoleNames()->sort()->values()->all())->toBe(['enfermeiro_assistencial', 'enfermeiro_triagem'])
        ->and($alvo->fresh()->nome_completo)->toBe('Nome Corrigido')
        ->and($alvo->fresh()->categoria)->toBe('ENFERMEIRO');

    $log = AuditoriaLog::where('acao', 'usuario.atualizar')->latest('id')->firstOrFail();
    expect($log->dados_antes['roles'])->toBe(['recepcao']);
});

it('permite manter o proprio email e matricula ao editar', function () {
    $alvo = Profissional::factory()->create(['matricula' => 'MAT-9001', 'conselho_numero' => '123456', 'conselho_uf' => 'PR']);
    $alvo->user->forceFill(['login' => 'MAT-9001', 'email' => 'juliana.prado@prsaude.com'])->save();

    $this->actingAs($this->admin)
        ->put(route('usuarios.update', $alvo->user_id), dadosProfissional())
        ->assertSessionHasNoErrors();
});

it('cria o registro profissional da conta que nao tem um', function () {
    // D-28: a conta administrativa semeada nasce sem `profissional`.
    $semRegistro = User::factory()->admin()->create();
    $semRegistro->assignRole('admin');

    $this->actingAs($this->admin)
        ->put(route('usuarios.update', $semRegistro), dadosProfissional([
            'categoria' => 'ADMIN', 'conselho_tipo' => '', 'conselho_numero' => '', 'conselho_uf' => '',
            'roles' => ['admin'],
        ]))
        ->assertSessionHasNoErrors();

    expect($semRegistro->fresh()->profissional)->not->toBeNull();
});

it('impede o administrador de remover o proprio perfil admin', function () {
    expect(fn () => app(AtualizarProfissionalAction::class)->execute(
        $this->admin,
        dadosProfissional(['categoria' => 'ADMIN', 'conselho_numero' => null, 'roles' => ['auditor']]),
        $this->admin,
    ))->toThrow(UsuarioInvalidoException::class);

    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue()
        ->and($this->admin->fresh()->tipo)->toBe('ADMIN');
});

// =====================================================================
// Desativação (exclusão lógica)
// =====================================================================

it('desativa a conta, encerra sessoes e impede o login', function () {
    $alvo = Profissional::factory()->create();
    $usuario = $alvo->user;
    $tokenAntes = $usuario->remember_token;

    DB::table('sessions')->insert([
        'id' => 'sessao-aberta', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time(),
    ]);

    $this->actingAs($this->admin)
        ->patch(route('usuarios.situacao', $usuario), ['ativo' => false])
        ->assertSessionHasNoErrors();

    $usuario->refresh();
    expect($usuario->ativo)->toBeFalse()
        ->and($alvo->fresh()->ativo)->toBeFalse()
        // Profissional::emPlantao() exige ativo: sai do mínimo vital (§13.5).
        ->and($alvo->fresh()->emPlantao())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $usuario->id)->exists())->toBeFalse()
        // "Lembrar de mim" não pode reautenticar a conta.
        ->and($usuario->remember_token)->not->toBe($tokenAntes)
        // Exclusão lógica, nunca física (D-08).
        ->and(User::find($usuario->id))->not->toBeNull();

    expect(AuditoriaLog::where('acao', 'usuario.desativar')->where('entidade_id', $usuario->id)->exists())->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => $usuario->email, 'password' => 'password']);
    $this->assertGuest();
});

it('reativa a conta desativada', function () {
    $alvo = Profissional::factory()->inativo()->create();
    $alvo->user->forceFill(['ativo' => false])->save();

    $this->actingAs($this->admin)->patch(route('usuarios.situacao', $alvo->user_id), ['ativo' => true]);

    expect($alvo->user->fresh()->ativo)->toBeTrue()
        ->and($alvo->fresh()->ativo)->toBeTrue();
});

it('impede o administrador de desativar a propria conta', function () {
    expect(fn () => app(AlterarSituacaoUsuarioAction::class)->execute($this->admin, false, $this->admin))
        ->toThrow(UsuarioInvalidoException::class);

    $this->actingAs($this->admin)
        ->patch(route('usuarios.situacao', $this->admin), ['ativo' => false])
        ->assertSessionHas('alerta');

    expect($this->admin->fresh()->ativo)->toBeTrue();
});

it('impede paciente de entrar pelo login da equipe', function () {
    $paciente = User::factory()->paciente()->create();

    $this->post('/login', ['email' => $paciente->email, 'password' => 'password']);

    $this->assertGuest();
});

// =====================================================================
// Redefinição de senha
// =====================================================================

it('redefine a senha como provisoria e desfaz o bloqueio', function () {
    $usuario = User::factory()->profissional()->bloqueado()->create();
    DB::table('sessions')->insert([
        'id' => 'sessao-antiga', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time(),
    ]);

    $this->actingAs($this->admin)
        ->put(route('usuarios.senha', $usuario), ['senha' => 'NovaSenha#99', 'senha_confirmation' => 'NovaSenha#99'])
        ->assertSessionHasNoErrors();

    $usuario->refresh();
    expect(Hash::check('NovaSenha#99', $usuario->password))->toBeTrue()
        ->and($usuario->senha_provisoria)->toBeTrue()
        ->and($usuario->estaBloqueado())->toBeFalse()
        ->and($usuario->tentativas_falhas)->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $usuario->id)->exists())->toBeFalse();

    $log = AuditoriaLog::where('acao', 'usuario.senha_redefinida')->latest('id')->firstOrFail();
    expect(json_encode($log->getAttributes()))->not->toContain('NovaSenha#99');
});

it('exige confirmacao e tamanho minimo na redefinicao de senha', function () {
    $usuario = User::factory()->profissional()->create();

    $this->actingAs($this->admin)
        ->put(route('usuarios.senha', $usuario), ['senha' => 'curta', 'senha_confirmation' => 'outra'])
        ->assertSessionHasErrors('senha');
});
