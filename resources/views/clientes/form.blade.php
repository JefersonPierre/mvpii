{{-- UC03 – Cadastrar cliente (RF04, RF05, RF06, RN01, RN02) --}}
@php
    $novo = ! $cliente->exists;
    $titulo = $novo ? ($cliente->interessado ? 'Cadastro rápido de interessado' : 'Novo cliente') : 'Editar '.($cliente->interessado ? 'interessado' : 'cliente');

    // Completar o cadastro para aprovar um orçamento (UC10 2a) já marca "Cliente completo".
    $completando = ! $novo && request('orcamento');
    $cadastro = old('cadastro', $cliente->interessado && ! $completando ? 'interessado' : 'cliente');
    $tipoPessoa = old('tipo_pessoa', $cliente->tipo_pessoa ?? 'J');

    // Contatos: os enviados (após erro de validação), os já gravados ou uma linha em branco.
    if (is_array(old('contatos'))) {
        $contatos = old('contatos');
        $principal = old('principal');
    } else {
        $contatos = $cliente->contatos->mapWithKeys(fn ($c) => ["c{$c->id}" => $c->only('id', 'nome', 'cargo', 'telefone', 'email')])->all();
        $principal = $cliente->contatos->firstWhere('principal', true) ? 'c'.$cliente->contatos->firstWhere('principal', true)->id : null;
    }
    if (empty($contatos)) {
        $contatos = ['n1' => []];
        $principal = 'n1';
    }
@endphp

<x-layout :titulo="$titulo">
    <div>
        <a href="{{ $novo ? route('clientes.index') : route('clientes.show', $cliente) }}" class="text-sm text-teal-700 hover:underline">
            ← {{ $novo ? 'Clientes' : $cliente->nome }}
        </a>
        <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
        <p class="text-sm text-slate-500">Campos com * são obrigatórios para clientes. Interessados precisam só de nome e um contato.</p>
    </div>

    <form method="POST" action="{{ $novo ? route('clientes.store') : route('clientes.update', $cliente) }}"
          data-form-cliente data-consulta-cep="{{ config('laboratorio.consulta_cep') ? '1' : '0' }}"
          class="cartao max-w-5xl space-y-6" novalidate>
        @csrf
        {{-- Volta para o orçamento depois de salvar: cadastro rápido (UC08 1a) ou completar cadastro (UC10 2a) --}}
        @if (request('orcamento') || old('voltar_orcamento'))
            <input type="hidden" name="voltar_orcamento" value="{{ old('voltar_orcamento', request('orcamento')) }}">
        @endif
        @unless ($novo) @method('PUT') @endunless

        @if ($errors->any())
            <div class="alerta alerta-erro" role="alert">Confira os campos destacados.</div>
        @endif

        <div class="flex flex-wrap gap-x-10 gap-y-4">
            <fieldset>
                <legend class="rotulo">Cadastro</legend>
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="cadastro" value="cliente" @checked($cadastro === 'cliente') class="accent-teal-600"> Cliente completo</label>
                    <label class="flex items-center gap-2"><input type="radio" name="cadastro" value="interessado" @checked($cadastro === 'interessado') class="accent-teal-600"> Interessado (cadastro rápido)</label>
                </div>
            </fieldset>
            <fieldset>
                <legend class="rotulo">Pessoa</legend>
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="tipo_pessoa" value="F" @checked($tipoPessoa === 'F') class="accent-teal-600"> Física</label>
                    <label class="flex items-center gap-2"><input type="radio" name="tipo_pessoa" value="J" @checked($tipoPessoa === 'J') class="accent-teal-600"> Jurídica</label>
                </div>
            </fieldset>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="nome" class="rotulo"><span data-rotulo-nome>Razão social</span> *</label>
                <input id="nome" name="nome" maxlength="150" value="{{ old('nome', $cliente->nome) }}" @class(['campo', 'campo-erro' => $errors->has('nome')])>
                @error('nome') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div data-campo-fantasia>
                <label for="nome_fantasia" class="rotulo">Nome fantasia</label>
                <input id="nome_fantasia" name="nome_fantasia" maxlength="150" value="{{ old('nome_fantasia', $cliente->nome_fantasia) }}" class="campo">
            </div>
            <div>
                <label for="documento" class="rotulo"><span data-rotulo-documento>CNPJ</span> <span data-obrigatorio-cliente>*</span></label>
                <input id="documento" name="documento" value="{{ old('documento', $cliente->documentoFormatado()) }}"
                       data-mascara="{{ $tipoPessoa === 'F' ? 'cpf' : 'cnpj' }}" autocomplete="off"
                       @class(['campo font-mono uppercase', 'campo-erro' => $errors->has('documento')])>
                @error('documento') <p class="erro">{{ $message }}</p> @enderror
                @if (session('cliente_existente'))
                    {{-- UC03 6b: documento já cadastrado --}}
                    <p class="mt-1 text-sm">
                        <a href="{{ route('clientes.show', session('cliente_existente')['id']) }}" class="font-medium text-teal-700 underline">
                            Abrir o cadastro de {{ session('cliente_existente')['nome'] }}
                        </a>
                    </p>
                @endif
            </div>
        </div>

        <fieldset class="space-y-4">
            <legend class="mb-2 text-base font-semibold">Endereço</legend>
            <div class="grid gap-4 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label for="cep" class="rotulo">CEP <span data-obrigatorio-cliente>*</span></label>
                    <input id="cep" name="cep" value="{{ old('cep', $cliente->cep) }}" data-mascara="cep" inputmode="numeric"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('cep')])>
                    <p class="ajuda" data-aviso-cep aria-live="polite">{{ config('laboratorio.consulta_cep') ? 'O endereço é preenchido pelo CEP.' : '' }}</p>
                    @error('cep') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-4">
                    <label for="logradouro" class="rotulo">Logradouro <span data-obrigatorio-cliente>*</span></label>
                    <input id="logradouro" name="logradouro" maxlength="150" value="{{ old('logradouro', $cliente->logradouro) }}" @class(['campo', 'campo-erro' => $errors->has('logradouro')])>
                    @error('logradouro') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-1">
                    <label for="numero" class="rotulo">Número <span data-obrigatorio-cliente>*</span></label>
                    <input id="numero" name="numero" maxlength="20" value="{{ old('numero', $cliente->numero) }}" @class(['campo', 'campo-erro' => $errors->has('numero')])>
                    @error('numero') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="complemento" class="rotulo">Complemento</label>
                    <input id="complemento" name="complemento" maxlength="80" value="{{ old('complemento', $cliente->complemento) }}" class="campo">
                </div>
                <div class="md:col-span-3">
                    <label for="bairro" class="rotulo">Bairro <span data-obrigatorio-cliente>*</span></label>
                    <input id="bairro" name="bairro" maxlength="80" value="{{ old('bairro', $cliente->bairro) }}" @class(['campo', 'campo-erro' => $errors->has('bairro')])>
                    @error('bairro') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-4">
                    <label for="cidade" class="rotulo">Cidade <span data-obrigatorio-cliente>*</span></label>
                    <input id="cidade" name="cidade" maxlength="80" value="{{ old('cidade', $cliente->cidade) }}" @class(['campo', 'campo-erro' => $errors->has('cidade')])>
                    @error('cidade') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="uf" class="rotulo">UF <span data-obrigatorio-cliente>*</span></label>
                    <select id="uf" name="uf" @class(['campo', 'campo-erro' => $errors->has('uf')])>
                        <option value=""></option>
                        @foreach (\App\Models\Cliente::UFS as $uf)
                            <option value="{{ $uf }}" @selected(old('uf', $cliente->uf) === $uf)>{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('uf') <p class="erro">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <legend class="text-base font-semibold">Contatos *</legend>
                <button type="button" data-adicionar-contato class="botao botao-pequeno botao-secundario">Adicionar contato</button>
            </div>
            <p class="ajuda">Informe telefone ou e-mail em cada contato e marque o principal.</p>
            <div class="hidden grid-cols-[auto_1.4fr_1fr_1fr_1.4fr_auto] gap-2 text-xs font-semibold text-slate-600 md:grid">
                <span>Principal</span><span>Nome</span><span>Cargo</span><span>Telefone</span><span>E-mail</span><span></span>
            </div>
            <div data-contatos class="space-y-2">
                @foreach ($contatos as $chave => $contato)
                    @include('clientes._contato', ['chave' => $chave, 'contato' => $contato, 'principal' => (string) $principal === (string) $chave])
                @endforeach
            </div>
            @error('contatos') <p class="erro">{{ $message }}</p> @enderror
            @error('principal') <p class="erro">{{ $message }}</p> @enderror
            <template id="modelo-contato">
                @include('clientes._contato', ['chave' => '__CHAVE__', 'contato' => [], 'principal' => false])
            </template>
        </fieldset>

        <div>
            <label for="observacoes" class="rotulo">Observações</label>
            <textarea id="observacoes" name="observacoes" rows="3" maxlength="2000" class="campo">{{ old('observacoes', $cliente->observacoes) }}</textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $novo ? route('clientes.index') : route('clientes.show', $cliente) }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</x-layout>
