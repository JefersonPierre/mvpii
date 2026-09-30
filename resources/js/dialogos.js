// Diálogos de confirmação: um botão com data-abrir-dialogo="id" abre o <dialog> com esse id,
// levando a URL do formulário (data-acao, opcional) e o nome do registro (data-nome, opcional).
document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-dialogo]');
    if (abrir) {
        const dialogo = document.getElementById(abrir.dataset.abrirDialogo);
        if (abrir.dataset.acao) {
            dialogo.querySelector('form').action = abrir.dataset.acao;
        }
        if (abrir.dataset.nome) {
            dialogo.querySelectorAll('[data-nome]').forEach((el) => (el.textContent = abrir.dataset.nome));
        }
        dialogo.showModal();
        return;
    }

    const fechar = evento.target.closest('[data-fechar-dialogo]');
    if (fechar) {
        fechar.closest('dialog').close();
    }
});

// Reabre o diálogo que voltou com erro de validação (ex.: resposta do orçamento sem motivo).
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog[data-abrir-ao-carregar]').forEach((dialogo) => dialogo.showModal());
});
