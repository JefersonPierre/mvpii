{{-- Mensagens de retorno das operações (sessão flash). --}}
@if (session('sucesso'))
    <div class="alerta alerta-sucesso" role="status">{{ session('sucesso') }}</div>
@endif
@if (session('erro'))
    <div class="alerta alerta-erro" role="alert">{{ session('erro') }}</div>
@endif
@if (session('link_local'))
    <div class="alerta alerta-info">
        <strong>Ambiente de desenvolvimento:</strong> os e-mails não são enviados de verdade.
        <a href="{{ session('link_local') }}" class="font-medium underline">Abrir o link que iria no e-mail</a>.
    </div>
@endif
