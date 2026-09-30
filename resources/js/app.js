// Diálogos de confirmação: um botão com data-abrir-dialogo="id" abre o <dialog> com esse id,
// levando a URL do formulário (data-acao) e o nome do registro (data-nome).
document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-dialogo]');
    if (abrir) {
        const dialogo = document.getElementById(abrir.dataset.abrirDialogo);
        dialogo.querySelector('form').action = abrir.dataset.acao;
        dialogo.querySelectorAll('[data-nome]').forEach((el) => (el.textContent = abrir.dataset.nome));
        dialogo.showModal();
        return;
    }

    const fechar = evento.target.closest('[data-fechar-dialogo]');
    if (fechar) {
        fechar.closest('dialog').close();
    }
});
