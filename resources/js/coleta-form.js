// UC14 – formulário de agendamento: linhas de amostra, prévia do saldo (RN28) e alerta de coletor ocupado (RN29).
// O servidor confere tudo de novo ao salvar.

function iniciar(form) {
    const lista = form.querySelector('[data-amostras]');
    const modelo = form.querySelector('[data-modelo-amostra]');
    let proxima = lista ? lista.querySelectorAll('[data-amostra]').length : 0;

    function atualizarSaldo() {
        const avisos = [];
        for (const linha of form.querySelectorAll('[data-saldo-item]')) {
            const id = linha.dataset.saldoItem;
            const neste = lista ? lista.querySelectorAll(`[data-item-amostra][value="${id}"]:checked`).length : 0;
            const restante = Number(linha.dataset.saldo) - neste;
            const celulaNeste = linha.querySelector('[data-neste]');
            if (celulaNeste) celulaNeste.textContent = neste;
            const celula = linha.querySelector('[data-restante]');
            celula.textContent = restante;
            celula.classList.toggle('text-red-600', restante < 0);
            if (restante < 0) avisos.push(`${linha.cells[0].textContent.trim()}: só restam ${Number(linha.dataset.saldo)} amostras no orçamento.`);
        }
        const aviso = form.querySelector('[data-aviso-saldo]');
        aviso.textContent = avisos.join(' ');
        aviso.hidden = avisos.length === 0;
    }

    function atualizarOcupacao() {
        const ocupacao = JSON.parse(form.querySelector('[data-ocupacao]').textContent);
        const chave = ['coletor_id', 'data', 'periodo'].map((c) => form.elements[c].value).join('|');
        const quantidade = ocupacao[chave] ?? 0;
        const aviso = form.querySelector('[data-aviso-ocupacao]');
        aviso.hidden = quantidade === 0;
        aviso.textContent = quantidade === 1
            ? 'Este coletor já tem 1 coleta agendada neste dia e período.'
            : `Este coletor já tem ${quantidade} coletas agendadas neste dia e período.`;
    }

    form.querySelector('[data-adicionar-amostra]')?.addEventListener('click', () => {
        lista.insertAdjacentHTML('beforeend', modelo.innerHTML.replaceAll('__N__', String(proxima++)));
        atualizarSaldo();
    });
    lista?.addEventListener('click', (evento) => {
        const botao = evento.target.closest('[data-remover-amostra]');
        if (botao && lista.querySelectorAll('[data-amostra]').length > 1) {
            botao.closest('[data-amostra]').remove();
            atualizarSaldo();
        }
    });
    lista?.addEventListener('change', atualizarSaldo);
    form.querySelectorAll('[data-campo-ocupacao]').forEach((campo) => campo.addEventListener('change', atualizarOcupacao));

    if (lista) atualizarSaldo();
    atualizarOcupacao();
}

document.querySelectorAll('[data-form-coleta]').forEach(iniciar);
