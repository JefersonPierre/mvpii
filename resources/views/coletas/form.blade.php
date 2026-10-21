{{-- UC14 – Registrar agendamento de coleta (RF27, RF28, RN27–RN30) e reagendamento (UC14 1a, RF29, RN31) --}}
@use('App\Models\Coleta')
@php
    $novo = ! $coleta->exists;
    $titulo = ($novo ? 'Agendar coleta' : 'Reagendar coleta').' · '.$orcamento->numeroComRevisao();

    // Amostras: as enviadas (após erro de validação) ou uma linha inicial, com o único ponto já escolhido.
    $comSaldo = $saldo->filter(fn ($s) => $s['saldo'] > 0)->keys()->all();
    $linhas = collect(old('amostras', [['ponto_coleta_id' => $pontos->count() === 1 ? $pontos->first()->id : '', 'itens' => $comSaldo]]))->values();
    $contato = $orcamento->cliente->contatoPrincipal;
@endphp

<x-layout :titulo="$titulo">
    <div>
        <a href="{{ $novo ? route('orcamentos.show', $orcamento) : route('coletas.show', $coleta) }}" class="text-sm text-teal-700 hover:underline">
            ← {{ $novo ? $orcamento->numeroComRevisao() : 'Coleta de '.$coleta->quando() }}
        </a>
        <h1 class="text-2xl font-semibold">{{ $novo ? 'Agendar coleta' : 'Reagendar coleta' }} · <span class="font-mono">{{ $orcamento->numeroComRevisao() }}</span></h1>
        <p class="text-sm text-slate-500">
            {{ $orcamento->cliente->nome }} · {{ $orcamento->tipoAmostra->nome }}
            @if ($orcamento->data_resposta) · aprovado em {{ $orcamento->data_resposta->format('d/m/Y') }} @endif
        </p>
    </div>

    <form method="POST" action="{{ $novo ? route('orcamentos.coletas.store', $orcamento) : route('coletas.update', $coleta) }}"
          data-form-coleta class="grid items-start gap-4 xl:grid-cols-[1fr_24rem]" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless
        <script type="application/json" data-ocupacao>@json($ocupacao)</script>

        <div class="space-y-4">
            {{-- RF28: saldo de amostras de cada item --}}
            <div class="cartao space-y-3">
                <h2 class="text-lg font-semibold">Saldo de amostras do orçamento</h2>
                <div class="overflow-x-auto">
                    <table class="tabela min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Item</th><th class="text-right">Aprovadas</th><th class="text-right">Agendadas</th>
                                <th class="text-right">Coletadas</th><th class="text-right">Recebidas</th>
                                @if ($novo)<th class="text-right">Neste agendamento</th>@endif
                                <th class="text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($saldo as $id => $s)
                                <tr data-saldo-item="{{ $id }}" data-saldo="{{ $s['saldo'] }}">
                                    <td>{{ $s['item']->descricao }}</td>
                                    <td class="text-right font-mono">{{ $s['aprovadas'] }}</td>
                                    <td class="text-right font-mono">{{ $s['agendadas'] }}</td>
                                    <td class="text-right font-mono">{{ $s['coletadas'] }}</td>
                                    <td class="text-right font-mono">{{ $s['recebidas'] }}</td>
                                    @if ($novo)<td class="text-right font-mono" data-neste>0</td>@endif
                                    <td class="text-right font-mono font-semibold" data-restante>{{ $s['saldo'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="erro" data-aviso-saldo hidden></p>
                @error('orcamento') <p class="erro">{{ $message }}</p> @enderror
                @foreach ($errors->get('amostras') as $mensagem)
                    <p class="erro">{{ $mensagem }}</p>
                @endforeach
            </div>

            @if ($novo)
                {{-- RF27 / RN27 / RN30: cada amostra tem ponto e itens a analisar; o código é gerado ao salvar --}}
                <div class="cartao space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">Amostras</h2>
                        <button type="button" class="botao botao-secundario" data-adicionar-amostra @disabled($pontos->isEmpty())>Adicionar amostra</button>
                    </div>
                    @if ($pontos->isEmpty())
                        <div class="alerta border-amber-200 bg-amber-50 text-amber-900">
                            O cliente não tem ponto de coleta ativo de {{ $orcamento->tipoAmostra->nome }}.
                            @can('clientes.editar')
                                <a href="{{ route('pontos.create', $orcamento->cliente) }}" class="font-medium underline">Cadastrar ponto de coleta</a>
                                e volte para agendar.
                            @endcan
                        </div>
                    @endif
                    <div data-amostras class="divide-y divide-slate-200">
                        @foreach ($linhas as $n => $linha)
                            @include('coletas._amostra', ['n' => $n, 'linha' => $linha])
                        @endforeach
                    </div>
                    <template data-modelo-amostra>
                        @include('coletas._amostra', ['n' => '__N__', 'linha' => ['ponto_coleta_id' => $pontos->count() === 1 ? $pontos->first()->id : '', 'itens' => $comSaldo]])
                    </template>
                    <p class="ajuda">
                        Só aparecem os pontos ativos do cliente com tipo de amostra {{ $orcamento->tipoAmostra->nome }}.
                        @can('clientes.editar')
                            Ponto não cadastrado? <a href="{{ route('pontos.create', $orcamento->cliente) }}" class="underline">Cadastrar ponto</a>.
                        @endcan
                    </p>
                </div>
            @else
                <div class="cartao space-y-3">
                    <h2 class="text-lg font-semibold">Amostras</h2>
                    <p class="text-sm text-slate-500">As amostras e os códigos são mantidos no reagendamento.</p>
                    <ul class="space-y-1 text-sm">
                        @foreach ($coleta->amostras as $amostra)
                            <li><span class="font-mono">{{ $amostra->codigo }}</span> · {{ $amostra->pontoColeta->identificacao }} · <x-selo-amostra :situacao="$amostra->situacao" /></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- RN29: data, período, horário opcional e coletor --}}
        <div class="cartao space-y-4 xl:sticky xl:top-4">
            <h2 class="text-lg font-semibold">Data e responsável</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="data" class="rotulo">Data *</label>
                    <input id="data" name="data" type="date" min="{{ today()->toDateString() }}" required
                           value="{{ old('data', $coleta->data?->toDateString()) }}" data-campo-ocupacao
                           @class(['campo', 'campo-erro' => $errors->has('data')])>
                    @error('data') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="periodo" class="rotulo">Período *</label>
                    <select id="periodo" name="periodo" required data-campo-ocupacao @class(['campo', 'campo-erro' => $errors->has('periodo')])>
                        @foreach (Coleta::PERIODOS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('periodo', $coleta->periodo ?? 'MANHA') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    @error('periodo') <p class="erro">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="horario" class="rotulo">Horário (opcional)</label>
                <input id="horario" name="horario" type="time" value="{{ old('horario', $coleta->horarioFormatado()) }}"
                       @class(['campo', 'campo-erro' => $errors->has('horario')])>
                @error('horario') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="coletor_id" class="rotulo">Coletor responsável *</label>
                <select id="coletor_id" name="coletor_id" required data-campo-ocupacao @class(['campo', 'campo-erro' => $errors->has('coletor_id')])>
                    <option value="">Selecione…</option>
                    @foreach ($coletores as $c)
                        <option value="{{ $c->id }}" @selected((int) old('coletor_id', $coleta->coletor_id) === $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
                @if ($coletores->isEmpty())
                    <p class="ajuda">Nenhum usuário ativo com o perfil Coletor. O administrador atribui o perfil em Usuários.</p>
                @endif
                @error('coletor_id') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div class="alerta border-amber-200 bg-amber-50 text-amber-900" data-aviso-ocupacao hidden></div>
            @if (session('conflito'))
                <div class="alerta border-amber-200 bg-amber-50 text-amber-900 space-y-2">
                    <p>{{ session('conflito') }}</p>
                    <label class="flex items-center gap-2 font-medium">
                        <input type="checkbox" name="confirmar_conflito" value="1"> Agendar mesmo assim
                    </label>
                </div>
            @endif
            <div>
                <label for="instrucoes" class="rotulo">Instruções para o coletor</label>
                <textarea id="instrucoes" name="instrucoes" rows="3" maxlength="2000"
                          @class(['campo', 'campo-erro' => $errors->has('instrucoes')])>{{ old('instrucoes', $coleta->instrucoes) }}</textarea>
                @error('instrucoes') <p class="erro">{{ $message }}</p> @enderror
            </div>
            @unless ($novo)
                <div>
                    <label for="motivo" class="rotulo">Motivo do reagendamento *</label>
                    <input id="motivo" name="motivo" maxlength="255" required value="{{ old('motivo') }}"
                           @class(['campo', 'campo-erro' => $errors->has('motivo')])>
                    @error('motivo') <p class="erro">{{ $message }}</p> @enderror
                </div>
            @endunless
            <p class="text-sm text-slate-500">
                @if ($contato?->email)
                    Ao salvar, o contato principal ({{ $contato->email }}) recebe {{ $novo ? 'a confirmação' : 'o aviso' }} por e-mail.
                @else
                    O contato principal do cliente não tem e-mail: comunique o cliente por outro meio.
                @endif
            </p>
            <div class="flex justify-end gap-2">
                <a href="{{ $novo ? route('orcamentos.show', $orcamento) : route('coletas.show', $coleta) }}" class="botao botao-secundario">Cancelar</a>
                <button type="submit" class="botao botao-primario" @disabled($novo && $pontos->isEmpty())>{{ $novo ? 'Agendar' : 'Reagendar' }}</button>
            </div>
        </div>
    </form>
</x-layout>
