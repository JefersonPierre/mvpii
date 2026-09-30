{{-- UC06 – legislação de referência (RF15, RN07) --}}
@php
    $novo = ! $legislacao->exists;
    $titulo = $novo ? ($substituida ? 'Substituir legislação' : 'Nova legislação') : 'Editar legislação';
    $marcados = array_map('intval', old('tipos_amostra', $atuais));
    $voltar = $novo ? ($substituida ? route('catalogo.legislacoes.show', $substituida) : route('catalogo.legislacoes.index'))
        : route('catalogo.legislacoes.show', $legislacao);
@endphp

<x-layout :titulo="$titulo">
    <div>
        <a href="{{ $voltar }}" class="text-sm text-teal-700 hover:underline">← Voltar</a>
        <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
    </div>

    <form method="POST" action="{{ $novo ? route('catalogo.legislacoes.store') : route('catalogo.legislacoes.update', $legislacao) }}"
          class="cartao max-w-2xl space-y-5" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        @if ($substituida)
            {{-- UC06 1a: a anterior recebe a data de fim; os limites dela continuam para consulta --}}
            <input type="hidden" name="substitui_id" value="{{ $substituida->id }}">
            <div class="alerta alerta-info space-y-3">
                <p>Esta legislação substituirá <strong>{{ $substituida->nome }}</strong> (vigente desde {{ $substituida->inicio_vigencia->format('d/m/Y') }}).</p>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="fim_anterior" class="rotulo">Fim da vigência da anterior *</label>
                        <input id="fim_anterior" name="fim_anterior" type="date" value="{{ old('fim_anterior') }}"
                               @class(['campo', 'campo-erro' => $errors->has('fim_anterior')])>
                        @error('fim_anterior') <p class="erro">{{ $message }}</p> @enderror
                        @error('substitui_id') <p class="erro">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm">
                        <input type="hidden" name="copiar_limites" value="0">
                        <input type="checkbox" name="copiar_limites" value="1" @checked(old('copiar_limites', true)) class="accent-teal-600">
                        Copiar os limites da anterior como ponto de partida
                    </label>
                </div>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="nome" class="rotulo">Nome *</label>
                <input id="nome" name="nome" maxlength="150" autofocus value="{{ old('nome', $legislacao->nome) }}" placeholder="Ex.: Portaria GM/MS nº 888/2021"
                       @class(['campo', 'campo-erro' => $errors->has('nome')])>
                @error('nome') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="orgao_emissor" class="rotulo">Órgão emissor *</label>
                <input id="orgao_emissor" name="orgao_emissor" maxlength="120" value="{{ old('orgao_emissor', $legislacao->orgao_emissor) }}" placeholder="Ex.: Ministério da Saúde"
                       @class(['campo', 'campo-erro' => $errors->has('orgao_emissor')])>
                @error('orgao_emissor') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="inicio_vigencia" class="rotulo">Início da vigência *</label>
                <input id="inicio_vigencia" name="inicio_vigencia" type="date" value="{{ old('inicio_vigencia', $legislacao->inicio_vigencia?->toDateString()) }}"
                       @class(['campo', 'campo-erro' => $errors->has('inicio_vigencia')])>
                @error('inicio_vigencia') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="fim_vigencia" class="rotulo">Fim da vigência</label>
                <input id="fim_vigencia" name="fim_vigencia" type="date" value="{{ old('fim_vigencia', $legislacao->fim_vigencia?->toDateString()) }}"
                       @class(['campo', 'campo-erro' => $errors->has('fim_vigencia')])>
                <p class="ajuda">Deixe em branco enquanto estiver em vigor.</p>
                @error('fim_vigencia') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset>
            <legend class="rotulo">Tipos de amostra abrangidos *</legend>
            <div class="flex flex-wrap gap-4">
                @foreach ($tipos as $tipo)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="tipos_amostra[]" value="{{ $tipo->id }}" @checked(in_array($tipo->id, $marcados, true)) class="accent-teal-600">
                        {{ $tipo->nome }}
                    </label>
                @endforeach
            </div>
            @error('tipos_amostra') <p class="erro">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ $voltar }}" class="botao botao-secundario">Cancelar</a>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</x-layout>
