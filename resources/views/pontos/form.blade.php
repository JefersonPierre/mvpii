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
                    <p class="ajuda">Nenhum tipo de amostra ativo. <a href="{{ route('catalogo.tipos-amostra.create') }}" class="underline">Cadastrar no catálogo</a>.</p>
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
                    <input id="logradouro" name="logradouro" maxlength="150" value="{{ old('logradouro', $ponto->logradouro) }}" @class(['campo', 'campo-erro' => $errors->has('logradouro')])>
                    @error('logradouro') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-1">
                    <label for="numero" class="rotulo">Número</label>
                    <input id="numero" name="numero" maxlength="20" value="{{ old('numero', $ponto->numero) }}" class="campo">
                </div>
                <div class="md:col-span-2">
                    <label for="complemento" class="rotulo">Complemento</label>
                    <input id="complemento" name="complemento" maxlength="80" value="{{ old('complemento', $ponto->complemento) }}" class="campo">
                </div>
                <div class="md:col-span-3">
                    <label for="bairro" class="rotulo">Bairro</label>
                    <input id="bairro" name="bairro" maxlength="80" value="{{ old('bairro', $ponto->bairro) }}" class="campo">
                </div>
                <div class="md:col-span-4">
                    <label for="cidade" class="rotulo">Cidade *</label>
                    <input id="cidade" name="cidade" maxlength="80" value="{{ old('cidade', $ponto->cidade) }}" @class(['campo', 'campo-erro' => $errors->has('cidade')])>
                    @error('cidade') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="uf" class="rotulo">UF *</label>
                    <select id="uf" name="uf" @class(['campo', 'campo-erro' => $errors->has('uf')])>
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

        <fieldset class="space-y-2">
            <legend class="text-base font-semibold">Coordenadas (opcional)</legend>
            <p class="ajuda">Em graus decimais, como aparecem no Google Maps (ex.: -22,9056 e -47,0608).</p>
            <div class="grid gap-4 md:grid-cols-2">
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
            </div>
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ route('clientes.show', [$cliente, 'aba' => 'pontos']) }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar ponto</button>
        </div>
    </form>
</x-layout>
