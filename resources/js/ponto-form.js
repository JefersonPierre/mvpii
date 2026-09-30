// UC04 3a – "Usar endereço do cliente": copia o endereço do cadastro do cliente para o ponto de coleta.

document.addEventListener('click', (evento) => {
    const botao = evento.target.closest('[data-usar-endereco-cliente]');
    if (!botao) return;

    const form = botao.closest('[data-form-ponto]');
    const endereco = JSON.parse(form.dataset.enderecoCliente);
    for (const [campo, valor] of Object.entries(endereco)) {
        const entrada = form.querySelector(`[name="${campo}"]`);
        if (entrada) entrada.value = valor ?? '';
    }
    form.querySelector('[name="referencia"]').focus();
});
