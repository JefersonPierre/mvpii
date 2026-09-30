// Máscaras de CPF, CNPJ, telefone e CEP (RNF de usabilidade). O servidor sempre grava sem máscara.
// Uso: <input data-mascara="cpf|cnpj|telefone|cep">. O CNPJ aceita letras nas 12 primeiras posições
// (formato alfanumérico); os 2 dígitos verificadores são sempre números.

const MODELOS = {
    cpf: '000.000.000-00',
    cnpj: 'AA.AAA.AAA/AAAA-00',
    cep: '00000-000',
    telefone8: '(00) 0000-0000',
    telefone9: '(00) 00000-0000',
};

export function aplicarModelo(valor, modelo) {
    const chars = valor.toUpperCase().replace(/[^0-9A-Z]/g, '');
    let resultado = '';
    let i = 0;
    for (const m of modelo) {
        if (i >= chars.length) break;
        if (m === '0' || m === 'A') {
            const c = chars[i];
            const aceita = m === '0' ? /\d/.test(c) : /[0-9A-Z]/.test(c);
            i++;
            if (!aceita) continue;
            resultado += c;
        } else {
            resultado += m;
        }
    }
    return resultado;
}

export function mascarar(valor, tipo) {
    if (tipo === 'telefone') {
        const digitos = valor.replace(/\D/g, '');
        return aplicarModelo(digitos, digitos.length > 10 ? MODELOS.telefone9 : MODELOS.telefone8);
    }
    return aplicarModelo(valor, MODELOS[tipo]);
}

function formatarCampo(campo) {
    campo.value = mascarar(campo.value, campo.dataset.mascara);
}

document.addEventListener('input', (evento) => {
    if (evento.target.matches('[data-mascara]')) formatarCampo(evento.target);
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mascara]').forEach(formatarCampo);
});
