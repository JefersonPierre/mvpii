{{-- UC04 – Cadastrar ponto de coleta (RF09, RF10, RN03) --}}
@php
    $novo = ! $ponto->exists;
    $titulo = $novo ? 'Novo ponto de coleta' : 'Editar ponto de coleta';
    // UC04 3a: endereço do cliente, copiado para o formulário pelo botão "Usar endereço do cliente".
    $enderecoCliente = collect($cliente->only(\App\Models\Cliente::CAMPOS_ENDERECO))
        ->put('cep', \App\Support\Documento::formatarCep($cliente->cep));
@endphp

<x-layout :titulo="$titulo">
    <div>
        <a href="{{ route('clientes.show', [$cliente, 'aba' => 'pontos']) }}" class="text-sm text-teal-700 hover:underline">← {{ $cliente->nome }}</a>
        <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
        <p class="text-sm text-slate-500">Cliente: {{ $cliente->nome }}</p>
    </div>

    <form method="POST" action="{{ $novo ? route('pontos.store', $cliente) : route('pontos.update', $ponto) }}"
          data-form-ponto data-endereco-cliente='@json($enderecoCliente)' class="cartao max-w-3xl space-y-6" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        @if ($errors->any())
            <div class="alerta alerta-erro" role="alert">Confira os campos destacados.</div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="identificacao" class="rotulo">Identificação *</label>
                <input id="identificacao" name="identificacao" maxlength="150" autofocus placeholder="Ex.: Reservatório superior – Bloco A"
                       value="{{ old('identificacao', $ponto->identificacao) }}" @class(['campo', 'campo-erro' => $errors->has('identificacao')])>
                @error('identificacao') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="tipo_amostra_id" class="rotulo">Tipo de amostra padrão *</label>
                <select id="tipo_amostra_id" name="tipo_amostra_id" @class(['campo', 'campo-erro' => $errors->has('tipo_amostra_id')])>
                    <option value="">Selecione…</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}" @selected((int) old('tipo_amostra_id', $ponto->tipo_amostra_id) === $tipo->id)>
                            {{ $tipo->nome }}{{ $tipo->ativo ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                </select>
                @error('tipo_amostra_id') <p class="erro">{{ $message }}</p> @enderror
                @if ($tipos->isEmpty())
                    <p class="ajuda">
                        Nenhum tipo de amostra ativo.
                        @can('catalogo.editar')
                            <a href="{{ route('catalogo.tipos-amostra.create') }}" class="underline">Cadastrar no catálogo</a>.
                        @else
                            Peça ao administrador para cadastrar no catálogo.
                        @endcan
                    </p>
                @endif
            </div>
        </div>

        <fieldset class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <legend class="text-base font-semibold">Endereço do ponto</legend>
                @if ($cliente->temEndereco())
                    <button type="button" data-usar-endereco-cliente class="botao botao-pequeno botao-secundario">Usar endereço do cliente</button>
                @else
                    <span class="ajuda">O cliente não tem endereço cadastrado para copiar.</span>
                @endif
            </div>
            <div class="grid gap-4 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label for="cep" class="rotulo">CEP</label>
                    <input id="cep" name="cep" value="{{ old('cep', $ponto->cep) }}" data-mascara="cep" inputmode="numeric"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('cep')])>
                    @error('cep') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-4">
                    <label for="logradouro" class="rotulo">Logradouro *</label>
                    <input id="logradouro" name="logradouro" data-endereco maxlength="150" value="{{ old('logradouro', $ponto->logradouro) }}" @class(['campo', 'campo-erro' => $errors->has('logradouro')])>
                    @error('logradouro') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-1">
                    <label for="numero" class="rotulo">Número</label>
                    <input id="numero" name="numero" data-endereco maxlength="20" value="{{ old('numero', $ponto->numero) }}" class="campo">
                </div>
                <div class="md:col-span-2">
                    <label for="complemento" class="rotulo">Complemento</label>
                    <input id="complemento" name="complemento" maxlength="80" value="{{ old('complemento', $ponto->complemento) }}" class="campo">
                </div>
                <div class="md:col-span-3">
                    <label for="bairro" class="rotulo">Bairro</label>
                    <input id="bairro" name="bairro" data-endereco maxlength="80" value="{{ old('bairro', $ponto->bairro) }}" class="campo">
                </div>
                <div class="md:col-span-4">
                    <label for="cidade" class="rotulo">Cidade *</label>
                    <input id="cidade" name="cidade" data-endereco maxlength="80" value="{{ old('cidade', $ponto->cidade) }}" @class(['campo', 'campo-erro' => $errors->has('cidade')])>
                    @error('cidade') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="uf" class="rotulo">UF *</label>
                    <select id="uf" name="uf" data-endereco @class(['campo', 'campo-erro' => $errors->has('uf')])>
                        <option value=""></option>
                        @foreach (\App\Models\Cliente::UFS as $uf)
                            <option value="{{ $uf }}" @selected(old('uf', $ponto->uf) === $uf)>{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('uf') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-6">
                    <label for="referencia" class="rotulo">Referência</label>
                    <input id="referencia" name="referencia" maxlength="255" placeholder="Ex.: Cobertura, acesso pela escada B"
                           value="{{ old('referencia', $ponto->referencia) }}" class="campo">
                </div>
            </div>
        </fieldset>

        {{-- RF47 / RN49: coordenadas pelo endereço, com ajuste no mapa --}}
        <fieldset class="space-y-2">
            <legend class="text-base font-semibold">Localização no mapa</legend>
            <p class="ajuda">Usada no roteiro das coletas (RN47). Busque pelo endereço e confira; se preciso, arraste o marcador ou clique no mapa.
                Sem localização, o ponto fica no fim do roteiro.</p>
            @if ($sugestao)
                <div class="alerta alerta-info flex flex-wrap items-center justify-between gap-2">
                    <span>A coleta de {{ $sugestao->data_hora_coleta->format('d/m/Y') }} registrou a localização {{ $sugestao->latitude_coleta }}, {{ $sugestao->longitude_coleta }}.</span>
                    <button type="button" data-usar-sugestao data-lat="{{ $sugestao->latitude_coleta }}" data-lng="{{ $sugestao->longitude_coleta }}" class="botao botao-pequeno botao-secundario">Usar esta localização</button>
                </div>
            @endif
            <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div>
                    <label for="latitude" class="rotulo">Latitude</label>
                    <input id="latitude" name="latitude" inputmode="decimal" value="{{ old('latitude', $ponto->latitude) }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('latitude')])>
                    @error('latitude') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="longitude" class="rotulo">Longitude</label>
                    <input id="longitude" name="longitude" inputmode="decimal" value="{{ old('longitude', $ponto->longitude) }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('longitude')])>
                    @error('longitude') <p class="erro">{{ $message }}</p> @enderror
                </div>
                @if (config('laboratorio.geocodificacao'))
                    <button type="button" data-buscar-posicao class="botao botao-secundario">Buscar pelo endereço</button>
                @endif
            </div>
            <div data-mapa-posicao data-geocodificacao="{{ config('laboratorio.geocodificacao') ? '1' : '0' }}" data-rebuscar="{{ $ponto->exists ? '0' : '1' }}"
                 class="h-72 w-full rounded-md bg-slate-100"></div>
            <p class="ajuda" data-aviso-mapa aria-live="polite">{{ config('laboratorio.geocodificacao') ? 'Ao preencher o endereço, o sistema busca a posição no mapa.' : 'Clique no mapa para marcar a posição.' }}</p>
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ route('clientes.show', [$cliente, 'aba' => 'pontos']) }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar ponto</button>
        </div>
    </form>
</x-layout>
