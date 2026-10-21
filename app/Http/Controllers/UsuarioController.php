<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Mail\LinkSenhaMail;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\LinksSenha;
use App\Support\Perfis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

// UC02 – Cadastrar usuários (RF03, RN08, RN10, RN11) e UC13 – Cadastrar perfis de usuário (RF25, RN23, RN24)
class UsuarioController extends Controller
{
    private const ENTIDADE = 'USUARIO';

    private const CAMPOS_AUDITADOS = ['nome', 'email', 'ativo', 'perfis'];

    public function __construct(private readonly Auditoria $auditoria) {}

    public function index(Request $request): View
    {
        $busca = trim((string) $request->query('busca'));
        $situacao = in_array($request->query('situacao'), ['ativos', 'inativos', 'todos'], true)
            ? $request->query('situacao')
            : 'ativos';

        $perfil = array_key_exists($request->query('perfil'), Perfis::NOMES) ? $request->query('perfil') : '';

        $usuarios = Usuario::with('perfis')
            ->when($situacao !== 'todos', fn ($q) => $q->where('ativo', $situacao === 'ativos'))
            ->when($busca !== '', function ($q) use ($busca) {
                $termo = '%'.mb_strtolower($busca).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(nome) LIKE ?', [$termo])->orWhere('email', 'like', $termo));
            })
            ->when($perfil, fn ($q) => $q->whereHas('perfis', fn ($q) => $q->where('perfil', $perfil)))
            ->orderByNome()
            ->paginate(20)
            ->withQueryString();

        return view('usuarios.index', compact('usuarios', 'busca', 'situacao', 'perfil'));
    }

    public function create(): View
    {
        return view('usuarios.form', ['usuario' => new Usuario]);
    }

    /** Cadastra o usuário e envia o e-mail para ele criar a própria senha. */
    public function store(UsuarioRequest $request, LinksSenha $links): RedirectResponse
    {
        $usuario = DB::transaction(function () use ($request) {
            // Senha aleatória que ninguém conhece: o acesso só começa após o usuário criar a dele.
            $usuario = Usuario::create([...$request->safe()->only(['nome', 'email']), 'senha' => Str::random(40)]);
            $usuario->definirPerfis($request->validated('perfis'));
            $this->auditoria->registrar(self::ENTIDADE, $usuario->id, Auditoria::INCLUSAO, [], $this->valores($usuario), self::CAMPOS_AUDITADOS);

            return $usuario;
        });

        $link = $links->enviar($usuario, LinkSenhaMail::CONVITE);

        return redirect()->route('usuarios.index')
            ->with('sucesso', "Usuário cadastrado. O link para criar a senha foi enviado para {$usuario->email}.")
            ->with('link_local', LinksSenha::exibirNaTela($link));
    }

    public function edit(Usuario $usuario): View
    {
        return view('usuarios.form', compact('usuario'));
    }

    public function update(UsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $perfis = Perfis::normalizar($request->validated('perfis'));
        if ($erro = $this->impedimentoPerfis($request->user(), $usuario, $perfis)) {
            return back()->withInput()->withErrors(['perfis' => $erro]);
        }

        DB::transaction(function () use ($request, $usuario, $perfis) {
            $antes = $this->valores($usuario);
            $usuario->update($request->safe()->only(['nome', 'email']));
            $usuario->definirPerfis($perfis);
            $this->auditoria->registrar(self::ENTIDADE, $usuario->id, Auditoria::ALTERACAO, $antes, $this->valores($usuario), self::CAMPOS_AUDITADOS);
        });

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário atualizado.');
    }

    /** RN10: usuário inativo não acessa o sistema. Ninguém pode inativar a si mesmo. */
    public function situacao(Request $request, Usuario $usuario): RedirectResponse
    {
        $ativo = $request->boolean('ativo');
        if (! $ativo && $usuario->is($request->user())) {
            return back()->with('erro', 'Você não pode inativar o seu próprio usuário.');
        }
        if ($usuario->ativo === $ativo) {
            return back();
        }
        if (! $ativo && $this->ultimoAdministrador($usuario)) {
            return back()->with('erro', 'Este é o último administrador ativo e não pode ser inativado.');
        }

        DB::transaction(function () use ($usuario, $ativo) {
            $antes = $this->valores($usuario);
            $usuario->update(['ativo' => $ativo]);
            $this->auditoria->registrar(
                self::ENTIDADE, $usuario->id, $ativo ? Auditoria::REATIVACAO : Auditoria::INATIVACAO,
                $antes, $this->valores($usuario), self::CAMPOS_AUDITADOS,
            );
        });

        return back()->with('sucesso', $ativo ? 'Usuário reativado.' : 'Usuário inativado.');
    }

    public function historico(Usuario $usuario): View
    {
        $registros = RegistroAuditoria::with('responsavel')
            ->where(['entidade' => self::ENTIDADE, 'registro_id' => $usuario->id])
            ->orderByDesc('data_hora')
            ->orderByDesc('id')
            ->paginate(30);

        return view('usuarios.historico', compact('usuario', 'registros'));
    }

    /**
     * RN24: ninguém altera os próprios perfis, e o último administrador ativo não perde o perfil Administrador.
     *
     * @param  list<string>  $perfis
     */
    private function impedimentoPerfis(Usuario $responsavel, Usuario $usuario, array $perfis): ?string
    {
        if ($perfis === $usuario->listaPerfis()) {
            return null;
        }
        if ($usuario->is($responsavel)) {
            return 'Você não pode alterar os próprios perfis. Peça a outro administrador.';
        }
        if (! in_array(Perfis::ADMINISTRADOR, $perfis, true) && $this->ultimoAdministrador($usuario)) {
            return 'Este é o último administrador ativo: o perfil Administrador não pode ser retirado.';
        }

        return null;
    }

    /** RN24: deve existir sempre ao menos um usuário ativo com perfil Administrador. */
    private function ultimoAdministrador(Usuario $usuario): bool
    {
        return $usuario->ativo && $usuario->temPerfil(Perfis::ADMINISTRADOR)
            && ! Usuario::ativosComPerfil(Perfis::ADMINISTRADOR)->whereKeyNot($usuario->id)->exists();
    }

    private function valores(Usuario $usuario): array
    {
        return [...$usuario->only(['nome', 'email', 'ativo']), 'perfis' => $usuario->nomesPerfis()];
    }
}
