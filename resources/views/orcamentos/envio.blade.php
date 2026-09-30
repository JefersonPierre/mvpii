{{-- UC09 – Enviar orçamento (RF18): pré-visualização, destinatário e mensagem --}}
@php($emailPadrao = $orcamento->contato?->email ?? $orcamento->cliente->contatos->firstWhere('email', '!=', null)?->email)

<x-layout titulo="Enviar {{ $orcamento->numeroComRevisao() }}">
    <div>
        <a href="{{ route('orcamentos.show', $orcamento) }}" class="text-sm text-teal-700 hover:underline">← {{ $orcamento->numeroComRevisao() }}</a>
        <h1 class="text-2xl font-semibold">Enviar orçamento {{ $orcamento->numero() }}</h1>
        <p class="text-sm text-slate-500">Confira o PDF, o destinatário e a mensagem. Depois de enviado, o orçamento só muda por nova revisão.</p>
    </div>

    <div class="grid items-start gap-4 xl:grid-cols-[1fr_24rem]">
        <div class="cartao p-0">
            <iframe src="{{ route('orcamentos.pdf', $orcamento) }}" title="Pré-visualização do PDF" class="h-[70vh] w-full rounded-lg"></iframe>
        </div>

        <form method="POST" action="{{ route('orcamentos.envio', $orcamento) }}" class="cartao space-y-4" novalidate>
            @csrf
            <div>
                <label for="email" class="rotulo">E-mail do destinatário</label>
                <input id="email" name="email" type="email" maxlength="150" value="{{ old('email', $emailPadrao) }}"
                       @class(['campo', 'campo-erro' => $errors->has('email')])>
                @error('email') <p class="erro">{{ $message }}</p> @enderror
                @unless ($emailPadrao)
                    <p class="ajuda">O contato não tem e-mail. Informe um, ou baixe o PDF e registre o envio manual.</p>
                @endunless
            </div>
            <div>
                <label for="mensagem" class="rotulo">Mensagem</label>
                <textarea id="mensagem" name="mensagem" rows="7" maxlength="5000" class="campo">{{ old('mensagem', $mensagem) }}</textarea>
            </div>
            <button type="submit" name="forma" value="email" class="botao botao-primario w-full">Enviar por e-mail</button>

            {{-- UC09 2a: sem e-mail, entrega por outro meio --}}
            <div class="space-y-2 border-t border-slate-200 pt-4">
                <p class="text-sm text-slate-600">Vai entregar por outro meio (WhatsApp, impresso)?</p>
                <a href="{{ route('orcamentos.pdf', [$orcamento, 'baixar' => 1]) }}" class="botao botao-secundario w-full">Baixar PDF</a>
                <button type="submit" name="forma" value="manual" class="botao botao-secundario w-full">Registrar envio manual</button>
            </div>
        </form>
    </div>
</x-layout>
