// UC24 3 – conformidade de cada resultado enquanto o analista digita (RN52). O servidor calcula de novo ao salvar.

const SELOS = {
    CONFORME: ['Conforme', 'bg-green-100 text-green-800'],
    NAO_CONFORME: ['Não conforme', 'bg-red-100 text-red-800'],
    INCONCLUSIVO: ['Inconclusivo', 'bg-amber-100 text-amber-800'],
    SEM_LIMITE: ['Sem limite', 'bg-slate-100 text-slate-600'],
};

const numero = (texto) => {
    const t = String(texto ?? '').trim();
    if (t === '') return null;
    const v = Number(t.includes(',') ? t.replaceAll('.', '').replace(',', '.') : t);
    return Number.isFinite(v) ? v : null;
};

export function avaliar(tipo, min, max, valor, menorQueLq, presenca, lq) {
    if (!menorQueLq && !presenca && valor === null) return null;
    if (!tipo) return 'SEM_LIMITE';
    if (tipo === 'AUSENCIA') return presenca === 'AUSENTE' ? 'CONFORME' : presenca === 'PRESENTE' ? 'NAO_CONFORME' : 'INCONCLUSIVO';
    if (menorQueLq) {
        if (tipo === 'MAXIMO') return lq !== null && lq > max ? 'INCONCLUSIVO' : 'CONFORME';
        return lq !== null && lq <= min ? 'NAO_CONFORME' : 'INCONCLUSIVO';
    }
    const conforme = { MAXIMO: valor <= max, MINIMO: valor >= min, FAIXA: valor >= min && valor <= max }[tipo];
    return conforme === undefined ? 'INCONCLUSIVO' : conforme ? 'CONFORME' : 'NAO_CONFORME';
}

function atualizar(linha) {
    const celula = linha.querySelector('[data-conformidade]');
    if (linha.dataset.tipo === 'SEM') return; // sem legislação de referência: sem comparação
    const valorCampo = linha.querySelector('[data-valor]');
    const lqMarcado = linha.querySelector('[data-lq-marcado]')?.checked ?? false;
    if (valorCampo) valorCampo.disabled = lqMarcado;
    const resultado = avaliar(linha.dataset.tipo || null, numero(linha.dataset.min), numero(linha.dataset.max),
        numero(valorCampo?.value), lqMarcado, linha.querySelector('[data-presenca]')?.value || null, numero(linha.dataset.lq));
    celula.innerHTML = resultado ? `<span class="selo whitespace-nowrap ${SELOS[resultado][1]}">${SELOS[resultado][0]}</span>` : '';
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-form-resultados]');
    if (!form) return;
    const linhas = form.querySelectorAll('[data-linha-resultado]');
    linhas.forEach(atualizar);
    form.addEventListener('input', (e) => {
        const linha = e.target.closest('[data-linha-resultado]');
        if (linha) atualizar(linha);
    });
    form.addEventListener('change', (e) => {
        const linha = e.target.closest('[data-linha-resultado]');
        if (linha) atualizar(linha);
        // RN51: ao escolher a legislação, recarrega a tela com os limites dela (os valores digitados vão junto).
        if (e.target.matches('[data-recarregar-legislacao]') && e.target.value) form.querySelector('[value="salvar"]').click();
    });
});
