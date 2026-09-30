{{-- RF23 – ficha do cliente; RF24 – histórico de alterações --}}
@php
    $abas = [
        'dados' => 'Dados',
        'contatos' => 'Contatos ('.$cliente->contatos->count().')',
        'pontos' => 'Pontos de coleta ('.$cliente->pontosColeta->where('ativo', true)->count().')',
        'orcamentos' => 'Orçamentos',
        'historico' => 'Histórico',
    ];
    $camposHistorico = [
        'interessado' => 'Tipo de cadastro', 'tipo_pessoa' => 'Pessoa', 'nome' => 'Nome / razão social',
        'nome_fantasia' => 'Nome fantasia', 'documento' => 'CPF/CNPJ', 'cep' => 'CEP', 'logradouro' => 'Logradouro',
        'numero' => 'Número', 'complemento' => 'Complemento', 'bairro' => 'Bairro', 'cidade' => 'Cidade', 'uf' => 'UF',
        'observacoes' => 'Observações', 'ativo' => 'Situação',
        // Campos do ponto de coleta
        'identificacao' => 'Identificação', 'tipo_amostra' => 'Tipo de amostra', 'referencia' => 'Referência',
        'latitude' => 'Latitude', 'longitude' => 'Longitude',
    ];
@endphp

<x-layout :titulo="$cliente->nome">
    <a href="{{ route('clientes.index') }}" class="text-sm text-teal-700 hover:underline">← Clientes</a>

    <div class="cartao space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold">{{ $cliente->nome }}</h1>
                    <x-selo-cliente :cliente="$cliente" />
                    <x-selo-situacao :ativo="$cliente->ativo" />
                </div>
                <p class="text-sm text-slate-500">
                    <span class="font-mono">{{ $cliente->documentoFormatado() ?: 'Sem CPF/CNPJ' }}</span>
                    @if ($cliente->cidade) · {{ $cliente->cidade }}/{{ $cliente->uf }} @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('clientes.edit', $cliente) }}" class="botao botao-secundario">Editar</a>
                @if ($cliente->ativo)
                    <button type="button" class="botao botao-perigo" data-abrir-dialogo="dialogo-inativar">Inativar</button>
                @else
                    <form method="POST" action="{{ route('clientes.situacao', $cliente) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="ativo" value="1">
                        <button type="submit" class="botao bg-teal-50 text-teal-700 hover:bg-teal-100">Reativar</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($cliente->interessado)
            <div class="alerta border-amber-200 bg-amber-50 text-amber-900">
                Cadastro rápido de interessado. CPF/CNPJ e endereço serão exigidos na aprovação do orçamento.
                <a href="{{ route('clientes.edit', $cliente) }}" class="font-medium underline">Completar cadastro</a>
            </div>
        @endif
        @unless ($cliente->ativo)
            <div class="alerta border-slate-200 bg-slate-50 text-slate-700">
                Cliente inativo: não pode ser usado em novos orçamentos, mas continua disponível para consulta.
            </div>
        @endunless

        <nav class="flex flex-wrap gap-1 border-b border-slate-200" aria-label="Seções da ficha">
            @foreach ($abas as $chave => $rotulo)
                <a href="{{ route('clientes.show', [$cliente, 'aba' => $chave]) }}"
                   @class([
                       '-mb-px border-b-2 px-3 py-2 text-sm',
                       'border-teal-600 font-semibold text-slate-900' => $aba === $chave,
                       'border-transparent text-slate-500 hover:text-slate-800' => $aba !== $chave,
                   ])
                   @if ($aba === $chave) aria-current="page" @endif>{{ $rotulo }}</a>
            @endforeach
        </nav>

        @switch($aba)
            @case('dados')
                <dl class="grid gap-x-8 gap-y-4 text-sm md:grid-cols-2">
                    <div><dt class="text-slate-500">Pessoa</dt><dd>{{ $cliente->tipo_pessoa === 'F' ? 'Física' : 'Jurídica' }}</dd></div>
                    <div><dt class="text-slate-500">{{ $cliente->tipo_pessoa === 'F' ? 'CPF' : 'CNPJ' }}</dt><dd class="font-mono">{{ $cliente->documentoFormatado() ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ $cliente->tipo_pessoa === 'F' ? 'Nome' : 'Razão social' }}</dt><dd>{{ $cliente->nome }}</dd></div>
                    @if ($cliente->tipo_pessoa === 'J')
                        <div><dt class="text-slate-500">Nome fantasia</dt><dd>{{ $cliente->nome_fantasia ?: '—' }}</dd></div>
                    @endif
                    <div class="md:col-span-2"><dt class="text-slate-500">Endereço</dt><dd>{{ $cliente->enderecoCompleto() ?: '—' }}</dd></div>
                    <div class="md:col-span-2"><dt class="text-slate-500">Observações</dt><dd class="whitespace-pre-line">{{ $cliente->observacoes ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Cadastrado em</dt><dd>{{ $cliente->criado_em?->format('d/m/Y H:i') }}</dd></div>
                </dl>
                @break

            @case('contatos')
                <div class="overflow-x-auto">
                    <table class="tabela min-w-[640px]">
                        <thead><tr><th>Nome</th><th>Cargo</th><th>Telefone</th><th>E-mail</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($cliente->contatos as $contato)
                                <tr>
                                    <td>{{ $contato->nome }}</td>
                                    <td>{{ $contato->cargo ?: '—' }}</td>
                                    <td class="font-mono">{{ $contato->telefoneFormatado() ?: '—' }}</td>
                                    <td>@if ($contato->email)<a href="mailto:{{ $contato->email }}" class="text-teal-700 hover:underline">{{ $contato->email }}</a>@else — @endif</td>
                                    <td>@if ($contato->principal)<span class="selo bg-teal-100 text-teal-800">Principal</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @break

            @case('pontos')
                {{-- RF11: pontos de coleta do cliente, cada um com o tipo de amostra padrão (RN03) --}}
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-slate-500">Cada ponto tem um tipo de amostra padrão.</p>
                    @if ($cliente->ativo)
                        <a href="{{ route('pontos.create', $cliente) }}" class="botao botao-primario">Novo ponto</a>
                    @endif
                </div>
                @if ($cliente->pontosColeta->isEmpty())
                    <p class="text-slate-500">Nenhum ponto de coleta cadastrado.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="tabela min-w-[760px]">
                            <thead><tr><th>Ponto de coleta</th><th>Tipo de amostra</th><th>Endereço e referência</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
                            <tbody>
                                @foreach ($cliente->pontosColeta as $ponto)
                                    <tr>
                                        <td class="font-medium">{{ $ponto->identificacao }}</td>
                                        <td>{{ $ponto->tipoAmostra->nome }}</td>
                                        <td>
                                            {{ $ponto->enderecoCompleto() }}
                                            @if ($ponto->referencia)<div class="text-xs text-slate-500">{{ $ponto->referencia }}</div>@endif
                                            @if ($ponto->linkMapa())
                                                <a href="{{ $ponto->linkMapa() }}" target="_blank" rel="noopener" class="text-xs text-teal-700 hover:underline">Ver no mapa</a>
                                            @endif
                                        </td>
                                        <td><x-selo-situacao :ativo="$ponto->ativo" /></td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('pontos.edit', $ponto) }}" class="botao botao-pequeno botao-secundario">Editar</a>
                                                <form method="POST" action="{{ route('pontos.situacao', $ponto) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="ativo" value="{{ $ponto->ativo ? 0 : 1 }}">
                                                    @if ($ponto->ativo)
                                                        <button type="submit" class="botao botao-pequeno botao-perigo">Inativar</button>
                                                    @elseif ($cliente->ativo)
                                                        <button type="submit" class="botao botao-pequeno bg-teal-50 text-teal-700 hover:bg-teal-100">Reativar</button>
                                                    @endif
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @break

            @case('orcamentos')
                <div class="alerta alerta-info">Os orçamentos do cliente aparecerão aqui no MOD05.</div>
                @break

            @case('historico')
                <x-tabela-historico :registros="$historico" :campos="$camposHistorico" />
                @break
        @endswitch
    </div>

    {{-- RF07 / RN04: confirmação antes de inativar --}}
    <dialog id="dialogo-inativar" class="dialogo" aria-labelledby="titulo-inativar">
        <form method="POST" action="{{ route('clientes.situacao', $cliente) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="ativo" value="0">
            <h2 id="titulo-inativar" class="text-lg font-semibold">Inativar cliente</h2>
            <p>
                <strong>{{ $cliente->nome }}</strong> não poderá ser usado em novos orçamentos e seus pontos de coleta
                também serão inativados. O cadastro e o histórico continuam disponíveis para consulta.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao botao-secundario" data-fechar-dialogo>Cancelar</button>
                <button type="submit" class="botao botao-perigo-cheio">Inativar</button>
            </div>
        </form>
    </dialog>
</x-layout>
