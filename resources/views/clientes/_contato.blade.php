{{-- Uma linha de contato do formulário (RF06). $chave identifica a linha: c{id} (existente) ou n… (novo). --}}
@php
    $campo = fn (string $nome) => "contatos[{$chave}][{$nome}]";
    $erro = collect(['nome', 'telefone', 'email', 'cargo'])
        ->map(fn ($c) => $errors->first("contatos.{$chave}.{$c}"))
        ->filter()->first();
@endphp
<div data-contato class="grid grid-cols-2 items-start gap-2 rounded-md border border-slate-200 p-3 md:grid-cols-[auto_1.4fr_1fr_1fr_1.4fr_auto] md:border-0 md:p-0">
    @if (! empty($contato['id']))
        <input type="hidden" name="{{ $campo('id') }}" value="{{ $contato['id'] }}">
    @endif
    <label class="col-span-2 flex items-center gap-2 pt-2 text-sm md:col-span-1">
        <input type="radio" name="principal" value="{{ $chave }}" @checked($principal) class="accent-teal-600">
        <span class="md:sr-only">Principal</span>
    </label>
    <input type="text" name="{{ $campo('nome') }}" value="{{ $contato['nome'] ?? '' }}" placeholder="Nome *" aria-label="Nome do contato" maxlength="120"
           @class(['campo', 'campo-erro' => $errors->has("contatos.{$chave}.nome")])>
    <input name="{{ $campo('cargo') }}" value="{{ $contato['cargo'] ?? '' }}" placeholder="Cargo" aria-label="Cargo" maxlength="80" class="campo">
    <input name="{{ $campo('telefone') }}" value="{{ \App\Support\Documento::formatarTelefone($contato['telefone'] ?? '') }}" placeholder="(00) 00000-0000"
           aria-label="Telefone" data-mascara="telefone" inputmode="tel"
           @class(['campo font-mono', 'campo-erro' => $errors->has("contatos.{$chave}.telefone")])>
    <input type="email" name="{{ $campo('email') }}" value="{{ $contato['email'] ?? '' }}" placeholder="E-mail" aria-label="E-mail do contato" maxlength="150"
           @class(['campo', 'campo-erro' => $errors->has("contatos.{$chave}.email")])>
    <button type="button" data-remover-contato class="botao botao-pequeno botao-perigo self-center" aria-label="Remover contato">Remover</button>
    @if ($erro)
        <p class="erro col-span-full">{{ $erro }}</p>
    @endif
</div>
