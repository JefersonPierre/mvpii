// RF16 – formulário de limite: mostra só os valores que o tipo de limite usa
// (máximo → valor máximo; mínimo → valor mínimo; faixa → os dois; ausência → nenhum) e a unidade do parâmetro.

const USA = { MAXIMO: ['maximo'], MINIMO: ['minimo'], FAIXA: ['minimo', 'maximo'], AUSENCIA: [] };

function atualizar(form) {
    const tipo = form.querySelector('[name="tipo"]:checked')?.value;
    form.querySelectorAll('[data-valor]').forEach((bloco) => {
        bloco.hidden = !(USA[tipo] ?? []).includes(bloco.dataset.valor);
    });
    const unidade = form.querySelector('[name="parametro_id"]').selectedOptions[0]?.dataset.unidade;
    form.querySelectorAll('[data-rotulo-unidade]').forEach((el) => (el.textContent = unidade ? `(${unidade})` : ''));
}

document.addEventListener('change', (evento) => {
    const form = evento.target.closest('[data-form-limite]');
    if (form) atualizar(form);
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-form-limite]').forEach(atualizar);
});
