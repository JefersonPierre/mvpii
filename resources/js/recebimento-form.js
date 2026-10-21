// UC17 – alerta de prazo e temperatura enquanto o analista preenche o recebimento (RN35). O servidor confere ao gravar.

const decimal = (texto) => Number(String(texto ?? '').trim().replace(/\./g, '').replace(',', '.'));
const duracao = (minutos) => {
    const horas = Math.floor(minutos / 60);
    const resto = String(minutos % 60).padStart(2, '0');
    return horas > 0 ? `${horas} h ${resto} min` : `${minutos % 60} min`;
};

function iniciar(form) {
    const aviso = form.querySelector('[data-alertas-recebimento]');
    const prazo = Number(form.dataset.prazoHoras);
    const maxima = Number(form.dataset.temperaturaMaxima);
    const coletadaEm = new Date(form.dataset.coletadaEm);

    function atualizar() {
        const alertas = [];
        const recebidaEm = new Date(form.elements.recebida_em.value);
        const minutos = Math.max(0, Math.round((recebidaEm - coletadaEm) / 60000));
        if (Number.isFinite(minutos) && minutos > prazo * 60) {
            alertas.push(`Prazo excedido: ${duracao(minutos)} desde a coleta (máximo de ${prazo} h).`);
        }
        const temperatura = decimal(form.elements.temperatura_recebimento.value);
        if (form.elements.temperatura_recebimento.value.trim() !== '' && temperatura > maxima) {
            alertas.push(`Temperatura acima do máximo configurado (${String(maxima).replace('.', ',')} °C).`);
        }
        aviso.textContent = alertas.length ? `${alertas.join(' ')} Para aceitar, registre uma observação.` : '';
        aviso.hidden = alertas.length === 0;
    }

    form.elements.recebida_em.addEventListener('input', atualizar);
    form.elements.temperatura_recebimento.addEventListener('input', atualizar);
    atualizar();
}

document.querySelectorAll('[data-form-recebimento]').forEach(iniciar);
