// RF14 – formulário de pacote: mostra a soma dos preços avulsos dos parâmetros marcados,
// como referência para definir o preço do pacote.

const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function atualizarSoma(form) {
    const marcados = [...form.querySelectorAll('input[name="parametros[]"]:checked')];
    const soma = marcados.reduce((total, caixa) => total + Number(caixa.dataset.preco), 0);
    form.querySelector('[data-soma-avulsa]').textContent = marcados.length
        ? `${marcados.length} parâmetro(s). Soma dos preços avulsos: ${moeda.format(soma)}.`
        : '';
}

document.addEventListener('change', (evento) => {
    const form = evento.target.closest('[data-form-pacote]');
    if (form && evento.target.name === 'parametros[]') atualizarSoma(form);
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-form-pacote]').forEach(atualizarSoma);
});
