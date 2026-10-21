<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NovaSenhaController;
use App\Http\Controllers\Auth\RecuperarSenhaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ColetaController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\EnvioOrcamentoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\LegislacaoController;
use App\Http\Controllers\LimiteController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\PacoteController;
use App\Http\Controllers\ParametroController;
use App\Http\Controllers\PontoColetaController;
use App\Http\Controllers\RegistroColetaController;
use App\Http\Controllers\RespostaOrcamentoController;
use App\Http\Controllers\TipoAmostraController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\UsuarioAtivo;
use Illuminate\Support\Facades\Route;

// MOD01 – Acesso (RF01, RF02)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/recuperar-senha', [RecuperarSenhaController::class, 'create'])->name('recuperar-senha');
    Route::post('/recuperar-senha', [RecuperarSenhaController::class, 'store'])->middleware('throttle:5,1');
});

// O link do e-mail funciona mesmo com alguém conectado no navegador.
Route::get('/nova-senha', [NovaSenhaController::class, 'create'])->name('nova-senha');
Route::post('/nova-senha', [NovaSenhaController::class, 'store']);

Route::middleware(['auth', UsuarioAtivo::class])->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('sair');

    Route::get('/', InicioController::class)->name('inicio');

    // RF26 / RN26: cada grupo exige a permissão da matriz (seção 7.4); sem ela, a tela de acesso negado (403).

    // MOD01 – Usuários (RF03) e MOD07 – Perfis (RF25)
    Route::middleware('can:usuarios')->group(function () {
        Route::get('/usuarios/{usuario}/historico', [UsuarioController::class, 'historico'])->name('usuarios.historico');
        Route::patch('/usuarios/{usuario}/situacao', [UsuarioController::class, 'situacao'])->name('usuarios.situacao');
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'usuario']);
    });

    // MOD02 – Clientes (RF04–RF08) e ficha do cliente (RF23, RF24)
    // MOD03 – Pontos de coleta (RF09–RF11): cadastrados a partir da ficha do cliente
    Route::middleware('can:clientes.editar')->group(function () {
        Route::patch('/clientes/{cliente}/situacao', [ClienteController::class, 'situacao'])->name('clientes.situacao');
        Route::resource('clientes', ClienteController::class)->only(['create', 'store', 'edit', 'update']);

        Route::get('/clientes/{cliente}/pontos/create', [PontoColetaController::class, 'create'])->name('pontos.create');
        Route::post('/clientes/{cliente}/pontos', [PontoColetaController::class, 'store'])->name('pontos.store');
        Route::get('/pontos/{ponto}/edit', [PontoColetaController::class, 'edit'])->name('pontos.edit');
        Route::put('/pontos/{ponto}', [PontoColetaController::class, 'update'])->name('pontos.update');
        Route::patch('/pontos/{ponto}/situacao', [PontoColetaController::class, 'situacao'])->name('pontos.situacao');
    });
    Route::resource('clientes', ClienteController::class)->only(['index', 'show'])->middleware('can:clientes.consultar');

    // MOD04 – Catálogo técnico: consulta para todos os perfis, alteração só pelo Administrador
    Route::view('/catalogo', 'catalogo.index')->name('catalogo')->middleware('can:catalogo.consultar');
    Route::prefix('catalogo')->name('catalogo.')->group(function () {
        Route::middleware('can:historico')->group(function () {
            Route::get('/tipos-amostra/{tipo}/historico', [TipoAmostraController::class, 'historico'])->name('tipos-amostra.historico');
            Route::get('/parametros/{parametro}/historico', [ParametroController::class, 'historico'])->name('parametros.historico');
            Route::get('/pacotes/{pacote}/historico', [PacoteController::class, 'historico'])->name('pacotes.historico');
            Route::get('/legislacoes/{legislacao}/historico', [LegislacaoController::class, 'historico'])->name('legislacoes.historico');
        });

        Route::middleware('can:catalogo.editar')->group(function () {
            Route::patch('/tipos-amostra/{tipo}/situacao', [TipoAmostraController::class, 'situacao'])->name('tipos-amostra.situacao');
            Route::resource('tipos-amostra', TipoAmostraController::class)->only(['create', 'store', 'edit', 'update'])
                ->parameters(['tipos-amostra' => 'tipo']);

            Route::patch('/parametros/{parametro}/situacao', [ParametroController::class, 'situacao'])->name('parametros.situacao');
            Route::resource('parametros', ParametroController::class)->only(['create', 'store', 'edit', 'update']);

            Route::patch('/pacotes/{pacote}/situacao', [PacoteController::class, 'situacao'])->name('pacotes.situacao');
            Route::resource('pacotes', PacoteController::class)->only(['create', 'store', 'edit', 'update']);

            Route::resource('legislacoes', LegislacaoController::class)->only(['create', 'store', 'edit', 'update'])
                ->parameters(['legislacoes' => 'legislacao']);
            Route::get('/legislacoes/{legislacao}/limites/create', [LimiteController::class, 'create'])->name('limites.create');
            Route::post('/legislacoes/{legislacao}/limites', [LimiteController::class, 'store'])->name('limites.store');
            Route::get('/limites/{limite}/edit', [LimiteController::class, 'edit'])->name('limites.edit');
            Route::put('/limites/{limite}', [LimiteController::class, 'update'])->name('limites.update');
            Route::delete('/limites/{limite}', [LimiteController::class, 'destroy'])->name('limites.destroy');
        });

        Route::middleware('can:catalogo.consultar')->group(function () {
            Route::get('/tipos-amostra', [TipoAmostraController::class, 'index'])->name('tipos-amostra.index');
            Route::get('/parametros', [ParametroController::class, 'index'])->name('parametros.index');
            Route::get('/pacotes', [PacoteController::class, 'index'])->name('pacotes.index');
            Route::resource('legislacoes', LegislacaoController::class)->only(['index', 'show'])
                ->parameters(['legislacoes' => 'legislacao']);
        });
    });

    // MOD05 – Orçamentos (RF17–RF21) e configurações comerciais (RF22)
    Route::middleware('can:orcamentos')->group(function () {
        Route::get('/orcamentos/buscar-clientes', [OrcamentoController::class, 'buscarClientes'])->name('orcamentos.buscar-clientes');
        Route::post('/orcamentos/{orcamento}/duplicar', [OrcamentoController::class, 'duplicar'])->name('orcamentos.duplicar');
        Route::post('/orcamentos/{orcamento}/revisao', [OrcamentoController::class, 'novaRevisao'])->name('orcamentos.revisao');
        Route::get('/orcamentos/{orcamento}/pdf', [EnvioOrcamentoController::class, 'pdf'])->name('orcamentos.pdf');
        Route::get('/orcamentos/{orcamento}/envio', [EnvioOrcamentoController::class, 'create'])->name('orcamentos.envio');
        Route::post('/orcamentos/{orcamento}/envio', [EnvioOrcamentoController::class, 'store']);
        Route::post('/orcamentos/{orcamento}/resposta', [RespostaOrcamentoController::class, 'store'])->name('orcamentos.resposta');
        Route::resource('orcamentos', OrcamentoController::class)->except('destroy');
    });

    // MOD08 – Agendamento de coletas (RF27–RF30): a partir do orçamento aprovado
    Route::middleware('can:coletas.agendar')->group(function () {
        Route::get('/orcamentos/{orcamento}/coletas/create', [ColetaController::class, 'create'])->name('orcamentos.coletas.create');
        Route::post('/orcamentos/{orcamento}/coletas', [ColetaController::class, 'store'])->name('orcamentos.coletas.store');
        Route::get('/coletas/{coleta}/edit', [ColetaController::class, 'edit'])->name('coletas.edit');
        Route::put('/coletas/{coleta}', [ColetaController::class, 'update'])->name('coletas.update');
        Route::post('/coletas/{coleta}/cancelar', [ColetaController::class, 'cancelar'])->name('coletas.cancelar');
    });

    // MOD08 – Agenda e ficha de coleta (RF31, RF32); o coletor vê só as próprias (ColetaPolicy)
    Route::middleware('can:coletas.agenda')->group(function () {
        Route::get('/coletas', AgendaController::class)->name('coletas.index');
        Route::get('/coletas/{coleta}', [ColetaController::class, 'show'])->name('coletas.show');
        Route::get('/coletas/{coleta}/ficha', [ColetaController::class, 'ficha'])->name('coletas.ficha');
    });

    // MOD09 – Registro da coleta (RF33): coletor responsável ou Administrador (ColetaPolicy)
    Route::middleware('can:coletas.registrar')->group(function () {
        Route::get('/coletas/{coleta}/registro', [RegistroColetaController::class, 'create'])->name('coletas.registro');
        Route::post('/coletas/{coleta}/registro', [RegistroColetaController::class, 'store']);
    });

    // MOD05 – Configurações do orçamento (RF22) e critérios de recebimento (RF35)
    Route::middleware('can:configuracoes')->group(function () {
        Route::get('/configuracoes', [ConfiguracaoController::class, 'edit'])->name('configuracoes');
        Route::put('/configuracoes', [ConfiguracaoController::class, 'update']);
    });
});
