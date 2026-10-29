// MOD10 / MOD11 – aplicativo do coletor no celular (UC20, UC21, UC22; protótipos P14, P15 e P16).
// Funciona sem conexão: as coletas do dia e de amanhã ficam guardadas no aparelho (RF39), o registro da coleta é
// gravado no aparelho com um identificador único (RN40) e enviado quando há conexão (RF41).
import * as banco from './campo/banco';
import { reduzir } from './campo/fotos';

const raiz = document.getElementById('campo');
const tela = raiz.querySelector('[data-tela]');
const aviso = raiz.querySelector('[data-aviso]');
const pagina = {
    usuarioId: Number(raiz.dataset.usuarioId),
    usuarioNome: raiz.dataset.usuarioNome,
    urlConectar: raiz.dataset.urlConectar,
    urlSair: raiz.dataset.urlSair,
    urlLogin: raiz.dataset.urlLogin,
    csrf: document.querySelector('meta[name="csrf-token"]').content,
};
const estado = { dados: null, acesso: null, dia: 0, sincronizando: false, precisaEntrar: false };

const ROTULOS = {
    PENDENTE: ['Pendente de envio', 'bg-amber-100 text-amber-800'],
    ENVIADO: ['Enviado', 'bg-green-100 text-green-800'],
    CONFLITO: ['Enviado com conflito', 'bg-violet-100 text-violet-800'],
    PROBLEMA: ['Com problema', 'bg-red-100 text-red-800'],
};

// ---------- utilidades ----------
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const selo = (est) => `<span class="selo ${ROTULOS[est][1]}">${ROTULOS[est][0]}</span>`;
const online = () => navigator.onLine;
const numero = (t) => {
    const s = String(t ?? '').trim();
    if (!s) return null;
    const v = Number(s.replace(',', '.'));
    return Number.isFinite(v) ? v : NaN;
};
const distancia = (a, b) => {
    const r = 6371000;
    const rad = (g) => (g * Math.PI) / 180;
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return 2 * r * Math.asin(Math.min(1, Math.sqrt(h)));
};
const km = (m) => (m == null ? '' : m < 1000 ? `${Math.round(m)} m` : `${(m / 1000).toFixed(1).replace('.', ',')} km`);
const dataBr = (iso) => iso.split('-').reverse().join('/');
const uuid = () => (crypto.randomUUID ? crypto.randomUUID()
    : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));
const agoraLocal = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

function mostrarAviso(texto, tipo = 'info') {
    const classes = { info: 'alerta-info', sucesso: 'alerta-sucesso', erro: 'alerta-erro' };
    aviso.innerHTML = texto ? `<div class="alerta ${classes[tipo]}">${texto}</div>` : '';
}

// ---------- acesso do aparelho (RN39) ----------
async function garantirAcesso() {
    estado.acesso = await banco.ler('acesso');
    const vence = estado.acesso ? new Date(estado.acesso.expira_em).getTime() : 0;
    if (!online() || (estado.acesso && vence - Date.now() > 60 * 60 * 1000)) return;
    try {
        const resposta = await fetch(pagina.urlConectar, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': pagina.csrf },
            body: JSON.stringify({ nome: navigator.userAgent.slice(0, 250), token_anterior: estado.acesso?.token ?? null }),
        });
        if (resposta.status === 419) return location.reload(); // tela guardada com token antigo: recarrega com conexão
        if (resposta.status === 401 || resposta.redirected) {
            estado.precisaEntrar = true;
            return;
        }
        if (!resposta.ok) return;
        const json = await resposta.json();
        estado.acesso = { token: json.token, expira_em: json.expira_em, usuario: json.usuario };
        estado.precisaEntrar = false;
        await banco.gravar('acesso', estado.acesso);
    } catch {
        // sem conexão de verdade: segue com o que está guardado
    }
}

async function api(metodo, url, corpo) {
    const resposta = await fetch(url, {
        method: metodo,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${estado.acesso?.token ?? ''}` },
        body: corpo ? JSON.stringify(corpo) : undefined,
    });
    let json = null;
    try { json = await resposta.json(); } catch { /* sem corpo */ }
    if (resposta.status === 401) estado.precisaEntrar = true;
    return { status: resposta.status, json };
}

// ---------- coletas guardadas (RF39) ----------
async function atualizarColetas() {
    if (!online() || !estado.acesso?.token) return;
    try {
        const { status, json } = await api('GET', '/api/campo/coletas');
        if (status === 200) {
            estado.dados = json;
            await banco.gravar('coletas', json);
        }
    } catch { /* conexão caiu */ }
}

function coletasDoDia() {
    return estado.dados?.dias?.[estado.dia]?.coletas ?? [];
}

function coletaPorId(id) {
    for (const dia of estado.dados?.dias ?? []) {
        const c = dia.coletas.find((x) => x.id === id);
        if (c) return c;
    }
    return null;
}

// ---------- sincronização (UC22) ----------
async function sincronizar(manual = false) {
    if (estado.sincronizando) return;
    if (!online()) {
        if (manual) mostrarAviso('Sem conexão. Os registros continuam guardados no aparelho e serão enviados quando a conexão voltar.');
        return;
    }
    estado.sincronizando = true;
    try {
        await garantirAcesso();
        if (estado.precisaEntrar) {
            mostrarAviso(`O acesso deste aparelho venceu. <a class="underline" href="${pagina.urlLogin}">Entre de novo</a>, com conexão, para enviar os registros.`, 'erro');
            return;
        }
        const pendentes = (await banco.registros()).filter((r) => r.estado === 'PENDENTE').sort((a, b) => a.criado_em.localeCompare(b.criado_em));
        let enviados = 0;
        for (const r of pendentes) {
            let resposta;
            try {
                resposta = await api('POST', '/api/sincronizacao/coletas', r.payload);
            } catch {
                break; // conexão caiu: os não confirmados continuam pendentes (UC22 1a)
            }
            if (resposta.status === 200) {
                // UC22 4: marca como enviado e apaga do aparelho os dados já enviados.
                await banco.gravarRegistro({ uuid: r.uuid, coleta_id: r.coleta_id, cliente: r.cliente, criado_em: r.criado_em,
                    estado: resposta.json.situacao === 'CONFLITO' ? 'CONFLITO' : 'ENVIADO', mensagem: resposta.json.mensagem, enviado_em: new Date().toISOString() });
                enviados++;
            } else if (resposta.status === 401) {
                mostrarAviso(`O acesso deste aparelho venceu. <a class="underline" href="${pagina.urlLogin}">Entre de novo</a> para enviar os registros.`, 'erro');
                break;
            } else if (resposta.status === 422 || resposta.status === 403) {
                const erros = resposta.json?.errors ? Object.values(resposta.json.errors).flat() : [];
                await banco.gravarRegistro({ ...r, estado: 'PROBLEMA', mensagem: erros[0] ?? resposta.json?.mensagem ?? 'Registro recusado.' });
            } else {
                break; // erro no servidor: tenta de novo depois
            }
        }
        const ordem = await banco.ler('ordem_pendente');
        if (ordem) {
            const { status } = await api('POST', '/api/campo/ordem', ordem).catch(() => ({ status: 0 }));
            if (status === 200) await banco.gravar('ordem_pendente', null);
        }
        await atualizarColetas();
        await banco.gravar('ultima_sincronizacao', new Date().toISOString());
        await limparAntigos();
        if (enviados) mostrarAviso(enviados === 1 ? '1 registro enviado.' : `${enviados} registros enviados.`, 'sucesso');
        else if (manual) mostrarAviso('Aparelho sincronizado.', 'sucesso');
    } finally {
        estado.sincronizando = false;
        await desenhar();
    }
}

/** Os registros já enviados ficam no aparelho só por dois dias, para o coletor conferir. */
async function limparAntigos() {
    const limite = Date.now() - 2 * 24 * 60 * 60 * 1000;
    for (const r of await banco.registros()) {
        if (['ENVIADO', 'CONFLITO'].includes(r.estado) && new Date(r.enviado_em).getTime() < limite) await banco.apagarRegistro(r.uuid);
    }
}

// ---------- telas ----------
async function rodape() {
    const conexao = raiz.querySelector('[data-conexao]');
    conexao.textContent = online() ? 'Com conexão' : 'Sem conexão';
    conexao.className = `selo ${online() ? 'bg-white/20 text-white' : 'bg-amber-300 text-amber-950'}`;
    const ultima = await banco.ler('ultima_sincronizacao');
    raiz.querySelector('[data-ultima-sincronizacao]').textContent = ultima
        ? `Última sincronização: ${new Date(ultima).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}`
        : 'Ainda não sincronizado neste aparelho';
    const pendentes = (await banco.registros()).filter((r) => ['PENDENTE', 'PROBLEMA'].includes(r.estado)).length;
    raiz.querySelector('[data-contador-pendentes]').textContent = pendentes ? `(${pendentes} pendente${pendentes > 1 ? 's' : ''})` : '';
}

async function telaLista() {
    raiz.querySelector('[data-titulo]').textContent = 'Coletas do dia';
    if (!estado.dados) {
        tela.innerHTML = online()
            ? '<p class="text-slate-500">Buscando as suas coletas…</p>'
            : '<div class="alerta alerta-info">Sem conexão e sem coletas guardadas neste aparelho. Abra o sistema com internet ao menos uma vez antes de ir a campo (RN39).</div>';
        return;
    }
    const regs = await banco.registros();
    const porColeta = Object.fromEntries(regs.map((r) => [r.coleta_id, r]));
    const dia = estado.dados.dias[estado.dia];
    const coletas = coletasDoDia();
    const abas = estado.dados.dias.map((d, i) => `<button type="button" data-acao="dia" data-dia="${i}" class="botao ${i === estado.dia ? 'botao-primario' : 'botao-secundario'} flex-1">${i === 0 ? 'Hoje' : 'Amanhã'} · ${dataBr(d.data).slice(0, 5)}</button>`).join('');

    const itens = coletas.map((c, i) => {
        const r = porColeta[c.id];
        const pontos = [...new Set(c.amostras.map((a) => a.ponto.identificacao))].join(' · ');
        return `<li class="cartao space-y-2 p-4">
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${r ? 'bg-green-600' : 'bg-sky-600'} font-semibold text-white">${i + 1}</span>
                <div class="min-w-0 flex-1">
                    <a href="#/coleta/${c.id}" class="block font-semibold text-teal-800">${esc(c.cliente)}</a>
                    <p class="text-sm text-slate-600">${esc(pontos)}</p>
                    <p class="text-xs text-slate-500">${esc(c.amostras[0]?.ponto.endereco ?? '')}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        <span>${esc(c.periodo)}${c.horario ? ' · ' + esc(c.horario) : ''}</span>
                        <span>${c.amostras.length} amostra${c.amostras.length > 1 ? 's' : ''}</span>
                        ${c.lat == null ? '<span class="selo bg-amber-100 text-amber-800">Sem localização</span>' : c.distancia_metros != null ? `<span class="text-slate-500">${km(c.distancia_metros)} ${i === 0 ? 'do laboratório' : 'do anterior'}</span>` : ''}
                        ${r ? selo(r.estado) : ''}
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <button type="button" data-acao="mover" data-id="${c.id}" data-direcao="-1" class="botao botao-pequeno botao-secundario" aria-label="Subir" ${i === 0 ? 'disabled' : ''}>↑</button>
                    <button type="button" data-acao="mover" data-id="${c.id}" data-direcao="1" class="botao botao-pequeno botao-secundario" aria-label="Descer" ${i === coletas.length - 1 ? 'disabled' : ''}>↓</button>
                </div>
            </div>
            ${r ? '' : `<a href="#/registrar/${c.id}" class="botao botao-primario w-full py-3 text-base">Registrar coleta</a>`}
        </li>`;
    }).join('');

    const rotas = dia.links.google.map((link, i) => `<a href="${esc(link)}" target="_blank" rel="noopener" class="botao botao-primario flex-1">Google Maps${dia.links.google.length > 1 ? ' ' + (i + 1) : ''}</a>`).join('');
    tela.innerHTML = `
        <div class="flex gap-2">${abas}</div>
        ${coletas.length ? `
            <div class="flex flex-wrap gap-2">
                ${rotas}
                ${dia.links.waze ? `<a href="${esc(dia.links.waze)}" target="_blank" rel="noopener" class="botao botao-secundario flex-1">Waze (próximo)</a>` : ''}
                <a href="#/mapa" class="botao botao-secundario flex-1">Ver no mapa</a>
            </div>
            ${dia.links.google.length > 1 ? '<p class="ajuda">Mais de nove paradas: a rota do Google Maps foi dividida em trechos.</p>' : ''}
            <ol class="space-y-3">${itens}</ol>`
        : `<div class="cartao text-slate-500">Nenhuma coleta agendada para ${estado.dia === 0 ? 'hoje' : 'amanhã'}.</div>`}`;
}

async function telaMapa() {
    raiz.querySelector('[data-titulo]').textContent = 'Mapa do dia';
    if (!online()) {
        tela.innerHTML = '<div class="alerta alerta-info">O mapa precisa de internet. Sem conexão, use a lista na ordem guardada no aparelho.</div><a href="#/" class="botao botao-secundario w-full">Voltar para a lista</a>';
        return;
    }
    tela.innerHTML = '<a href="#/" class="text-sm text-teal-700">← Lista</a><div data-mapa-campo class="h-[70vh] w-full rounded-lg bg-slate-200"></div>';
    const pontos = coletasDoDia().map((c, i) => (c.lat == null ? null : { n: i + 1, lat: c.lat, lng: c.lng, situacao: 'AGENDADA', titulo: c.cliente, detalhe: c.amostras[0]?.ponto.identificacao })).filter(Boolean);
    const lab = estado.dados?.laboratorio;
    const { mapaDeColetas } = await import('./mapa');
    mapaDeColetas(tela.querySelector('[data-mapa-campo]'), pontos, lab?.lat != null ? { lat: lab.lat, lng: lab.lng, titulo: lab.nome } : null);
}

function telaColeta(id) {
    const c = coletaPorId(id);
    if (!c) return location.replace('#/');
    raiz.querySelector('[data-titulo]').textContent = c.cliente;
    tela.innerHTML = `
        <a href="#/" class="text-sm text-teal-700">← Coletas do dia</a>
        <div class="cartao space-y-2 p-4">
            <p class="text-sm"><strong>${esc(c.periodo)}${c.horario ? ' · ' + esc(c.horario) : ''}</strong> · ${dataBr(c.agendada_para)}</p>
            ${c.instrucoes ? `<p class="alerta alerta-info">${esc(c.instrucoes)}</p>` : ''}
        </div>
        ${c.amostras.map((a) => `
            <div class="cartao space-y-1 p-4">
                <p class="font-mono text-sm">${esc(a.codigo)}</p>
                <p class="font-semibold">${esc(a.ponto.identificacao)}</p>
                <p class="text-sm text-slate-600">${esc(a.ponto.endereco)}</p>
                ${a.ponto.referencia ? `<p class="text-sm text-slate-600">Referência: ${esc(a.ponto.referencia)}</p>` : ''}
                <p class="text-xs text-slate-500">${esc(a.itens)}</p>
                ${a.parametros_campo.length ? `<p class="text-xs text-teal-800">Medir em campo: ${a.parametros_campo.map((p) => esc(p.nome)).join(', ')}</p>` : ''}
            </div>`).join('')}
        <a href="#/registrar/${c.id}" class="botao botao-primario w-full py-3 text-base">Registrar coleta</a>`;
}

function campoAmostra(a) {
    const motivos = Object.entries(estado.dados.motivos).map(([v, r]) => `<option value="${v}">${esc(r)}</option>`).join('');
    const medicoes = a.parametros_campo.map((p) => `
        <div>
            <label class="rotulo" for="m-${a.id}-${p.id}">${esc(p.nome)} (${esc(p.unidade)})</label>
            <input id="m-${a.id}-${p.id}" name="medicao" data-parametro="${p.id}" inputmode="decimal" class="campo font-mono text-base"
                   data-limite='${esc(JSON.stringify(p.limite))}'>
            <p class="ajuda" data-ajuda-limite>${p.limite ? 'Limite: ' + esc(p.limite.descricao) : ''}</p>
        </div>`).join('');
    return `<fieldset class="cartao space-y-3 p-4" data-amostra="${a.id}">
        <legend class="sr-only">Amostra ${esc(a.codigo)}</legend>
        <div><p class="font-mono text-sm">${esc(a.codigo)}</p><p class="font-semibold">${esc(a.ponto.identificacao)}</p></div>
        <div class="grid grid-cols-2 gap-2" role="radiogroup">
            <label class="botao botao-secundario has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50"><input type="radio" name="situacao-${a.id}" value="COLETADA" checked class="sr-only"> Coletada</label>
            <label class="botao botao-secundario has-[:checked]:border-amber-600 has-[:checked]:bg-amber-50"><input type="radio" name="situacao-${a.id}" value="NAO_COLETADA" class="sr-only"> Não coletada</label>
        </div>
        <div data-se-coletada class="space-y-3">
            <div><label class="rotulo" for="t-${a.id}">Temperatura da amostra (°C)</label><input id="t-${a.id}" name="temperatura" inputmode="decimal" class="campo font-mono text-base"></div>
            ${medicoes}
            <div>
                <label class="rotulo" for="f-${a.id}">Fotos (opcional, até 3)</label>
                <input id="f-${a.id}" name="fotos" type="file" accept="image/*" capture="environment" multiple class="block w-full text-sm">
                <div data-miniaturas class="mt-2 flex gap-2"></div>
            </div>
        </div>
        <div data-se-nao-coletada hidden><label class="rotulo" for="mo-${a.id}">Motivo *</label><select id="mo-${a.id}" name="motivo" class="campo text-base"><option value="">Selecione…</option>${motivos}</select></div>
        <div><label class="rotulo" for="o-${a.id}">Observações</label><input id="o-${a.id}" name="observacoes" maxlength="255" class="campo text-base"></div>
    </fieldset>`;
}

function telaRegistro(id) {
    const c = coletaPorId(id);
    if (!c) return location.replace('#/');
    raiz.querySelector('[data-titulo]').textContent = 'Registrar coleta';
    const motivos = Object.entries(estado.dados.motivos).map(([v, r]) => `<option value="${v}">${esc(r)}</option>`).join('');
    tela.innerHTML = `
        <a href="#/coleta/${c.id}" class="text-sm text-teal-700">← ${esc(c.cliente)}</a>
        <form data-form-registro class="space-y-3" novalidate>
            <div class="cartao space-y-3 p-4">
                <div><label class="rotulo" for="data_hora">Data e hora da coleta *</label><input id="data_hora" name="data_hora" type="datetime-local" value="${agoraLocal()}" max="${agoraLocal()}" class="campo text-base"></div>
                <div><label class="rotulo" for="tempo">Condições do tempo</label><input id="tempo" name="condicoes_tempo" maxlength="150" placeholder="Ex.: ensolarado" class="campo text-base"></div>
            </div>
            ${c.amostras.map(campoAmostra).join('')}
            <p data-erro-registro class="erro" role="alert"></p>
            <button type="submit" class="botao botao-primario w-full py-3 text-base">Registrar coleta</button>
            <details class="cartao p-4">
                <summary class="cursor-pointer font-medium">Nenhuma amostra coletada? Registrar como não realizada</summary>
                <div class="mt-3 space-y-3">
                    <div><label class="rotulo" for="motivo_nr">Motivo *</label><select id="motivo_nr" name="motivo_nao_realizada" class="campo text-base"><option value="">Selecione…</option>${motivos}</select></div>
                    <div><label class="rotulo" for="detalhe_nr">Detalhe</label><input id="detalhe_nr" name="motivo_nao_realizada_detalhe" maxlength="255" class="campo text-base"></div>
                    <button type="button" data-acao="nao-realizada" class="botao botao-secundario w-full py-3">Registrar como não realizada</button>
                </div>
            </details>
        </form>`;

    const form = tela.querySelector('[data-form-registro]');
    const fotos = {};
    form.addEventListener('change', async (e) => {
        const bloco = e.target.closest('[data-amostra]');
        if (e.target.type === 'radio' && bloco) {
            const coletada = e.target.value === 'COLETADA';
            bloco.querySelector('[data-se-coletada]').hidden = !coletada;
            bloco.querySelector('[data-se-nao-coletada]').hidden = coletada;
        }
        if (e.target.name === 'fotos' && bloco) {
            const id = bloco.dataset.amostra;
            fotos[id] = [];
            const miniaturas = bloco.querySelector('[data-miniaturas]');
            miniaturas.innerHTML = '<span class="ajuda">Reduzindo as fotos…</span>';
            for (const arquivo of [...e.target.files].slice(0, 3)) {
                try { fotos[id].push(await reduzir(arquivo)); } catch { /* foto ignorada */ }
            }
            miniaturas.innerHTML = fotos[id].map((f) => `<img src="${f}" alt="" class="h-16 w-16 rounded object-cover">`).join('')
                + (e.target.files.length > 3 ? '<span class="ajuda">Só as 3 primeiras fotos foram guardadas.</span>' : '');
        }
    });
    // UC21 3c: medição fora do limite é destacada, sem impedir o registro.
    form.addEventListener('input', (e) => {
        if (e.target.name !== 'medicao') return;
        const limite = JSON.parse(e.target.dataset.limite || 'null');
        const v = numero(e.target.value);
        const fora = limite && v !== null && !Number.isNaN(v)
            && ((limite.min != null && v < limite.min) || (limite.max != null && v > limite.max));
        e.target.classList.toggle('campo-erro', !!fora);
        e.target.parentElement.querySelector('[data-ajuda-limite]').textContent = limite
            ? (fora ? `Fora do limite (${limite.descricao}). Confira a medição.` : `Limite: ${limite.descricao}`) : '';
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        salvarRegistro(c, form, fotos, 'REALIZADA');
    });
    form.querySelector('[data-acao="nao-realizada"]').addEventListener('click', () => salvarRegistro(c, form, fotos, 'NAO_REALIZADA'));
}

function localizacao() {
    return new Promise((resolver) => {
        if (!navigator.geolocation) return resolver(null);
        navigator.geolocation.getCurrentPosition((p) => resolver({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => resolver(null), { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
    });
}

async function salvarRegistro(c, form, fotos, acao) {
    const erro = form.querySelector('[data-erro-registro]');
    const falhar = (texto) => { erro.textContent = texto; erro.scrollIntoView({ block: 'center' }); };
    const dataHora = form.elements.data_hora.value;
    if (!dataHora) return falhar('Informe a data e a hora da coleta.');
    if (new Date(dataHora).getTime() > Date.now() + 60000) return falhar('A data e a hora da coleta não podem ser futuras.');

    const amostras = [];
    for (const bloco of form.querySelectorAll('[data-amostra]')) {
        const id = Number(bloco.dataset.amostra);
        const situacao = bloco.querySelector(`input[name="situacao-${id}"]:checked`).value;
        const medicoes = {};
        for (const m of bloco.querySelectorAll('[name="medicao"]')) {
            const v = numero(m.value);
            if (Number.isNaN(v)) return falhar('Informe as medições de campo em números, ex.: 7,2.');
            if (v !== null) medicoes[m.dataset.parametro] = String(v);
        }
        const temperatura = numero(bloco.querySelector('[name="temperatura"]').value);
        if (Number.isNaN(temperatura)) return falhar('Informe a temperatura em °C, ex.: 6,5.');
        const motivo = bloco.querySelector('[name="motivo"]').value;
        if (acao === 'REALIZADA' && situacao === 'NAO_COLETADA' && !motivo) return falhar('Informe o motivo da amostra não coletada.');
        amostras.push({ id, situacao, temperatura, observacoes: bloco.querySelector('[name="observacoes"]').value || null,
            motivo: situacao === 'NAO_COLETADA' ? motivo : null, medicoes: situacao === 'COLETADA' ? medicoes : {},
            fotos: situacao === 'COLETADA' ? (fotos[id] ?? []) : [] });
    }
    if (acao === 'REALIZADA' && !amostras.some((a) => a.situacao === 'COLETADA')) {
        return falhar('Marque ao menos uma amostra coletada ou registre a coleta como não realizada.');
    }
    if (acao === 'NAO_REALIZADA' && !form.elements.motivo_nao_realizada.value) return falhar('Informe o motivo da coleta não realizada.');

    // RN44: localização no momento da coleta; a falta dela não impede o registro.
    erro.textContent = '';
    const posicao = await localizacao();
    const pontos = c.amostras.map((a) => a.ponto).filter((p) => p.lat != null);
    if (posicao && pontos.length) {
        const menor = Math.min(...pontos.map((p) => distancia(posicao, p)));
        if (menor > estado.dados.distancia_maxima
            && !confirm(`Você está a ${km(menor)} do ponto de coleta. Registrar mesmo assim? A coleta será marcada para conferência.`)) return;
    }

    const registro = {
        uuid: uuid(),
        coleta_id: c.id,
        cliente: c.cliente,
        criado_em: new Date().toISOString(),
        estado: 'PENDENTE',
        payload: {
            coleta_id: c.id,
            agendada_para: c.agendada_para,
            acao,
            origem: online() ? 'CELULAR' : 'CELULAR_SEM_CONEXAO',
            data_hora_coleta: new Date(dataHora).toISOString(),
            condicoes_tempo: form.elements.condicoes_tempo.value || null,
            latitude: posicao?.lat ?? null,
            longitude: posicao?.lng ?? null,
            motivo_nao_realizada: acao === 'NAO_REALIZADA' ? form.elements.motivo_nao_realizada.value : null,
            motivo_nao_realizada_detalhe: acao === 'NAO_REALIZADA' ? form.elements.motivo_nao_realizada_detalhe.value || null : null,
            amostras: acao === 'REALIZADA' ? amostras : [],
        },
    };
    registro.payload.uuid = registro.uuid; // RN40: o mesmo identificador em todos os reenvios
    await banco.gravarRegistro(registro);
    location.hash = '#/';
    mostrarAviso(online() ? 'Coleta registrada. Enviando…' : 'Coleta guardada no aparelho: pendente de envio. Ela será enviada quando a conexão voltar.', online() ? 'info' : 'sucesso');
    sincronizar();
}

async function telaEnvios() {
    raiz.querySelector('[data-titulo]').textContent = 'Envios';
    const regs = (await banco.registros()).sort((a, b) => b.criado_em.localeCompare(a.criado_em));
    tela.innerHTML = `
        <p class="text-sm text-slate-600">Registros feitos neste aparelho. Os pendentes são enviados automaticamente quando há conexão.</p>
        ${regs.length ? `<ul class="space-y-2">${regs.map((r) => `
            <li class="cartao space-y-1 p-4">
                <div class="flex items-center justify-between gap-2"><span class="font-medium">${esc(r.cliente)}</span>${selo(r.estado)}</div>
                <p class="text-xs text-slate-500">Registrado em ${new Date(r.criado_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</p>
                ${r.mensagem ? `<p class="text-sm">${esc(r.mensagem)}</p>` : ''}
                ${r.estado === 'PROBLEMA' ? `<div class="flex gap-2"><button type="button" data-acao="corrigir" data-uuid="${r.uuid}" class="botao botao-pequeno botao-primario">Corrigir e registrar de novo</button></div>` : ''}
            </li>`).join('')}</ul>` : '<div class="cartao text-slate-500">Nenhum registro neste aparelho.</div>'}`;
}

async function desenhar() {
    await rodape();
    const [, rota, id] = (location.hash || '#/').match(/^#\/?([a-z-]*)\/?(\d*)/) ?? [];
    if (rota === 'coleta') return telaColeta(Number(id));
    if (rota === 'registrar') return telaRegistro(Number(id));
    if (rota === 'envios') return telaEnvios();
    if (rota === 'mapa') return telaMapa();
    return telaLista();
}

// ---------- ações ----------
async function mover(id, direcao) {
    const coletas = coletasDoDia();
    const i = coletas.findIndex((c) => c.id === id);
    const j = i + direcao;
    if (i < 0 || j < 0 || j >= coletas.length) return;
    [coletas[i], coletas[j]] = [coletas[j], coletas[i]];
    await banco.gravar('coletas', estado.dados);
    // UC20 1a: a nova ordem vale para aquele dia; sem conexão, é enviada na próxima sincronização.
    const ordem = { data: estado.dados.dias[estado.dia].data, ids: coletas.map((c) => c.id) };
    await banco.gravar('ordem_pendente', ordem);
    if (online()) sincronizar();
    desenhar();
}

async function sair() {
    // RN39: não sai com registros pendentes de envio.
    const pendentes = (await banco.registros()).filter((r) => ['PENDENTE', 'PROBLEMA'].includes(r.estado));
    if (pendentes.length) {
        mostrarAviso(`Há ${pendentes.length} registro(s) não enviado(s). Sincronize antes de sair, para não perder as coletas.`, 'erro');
        return;
    }
    if (!online()) {
        mostrarAviso('Sem conexão: conecte-se à internet para sair do sistema.', 'erro');
        return;
    }
    await banco.limparTudo(); // RNF16
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = pagina.urlSair;
    form.innerHTML = `<input type="hidden" name="_token" value="${esc(pagina.csrf)}">`;
    document.body.append(form);
    form.submit();
}

raiz.addEventListener('click', async (e) => {
    const alvo = e.target.closest('[data-acao]');
    if (!alvo) return;
    const acao = alvo.dataset.acao;
    if (acao === 'menu') raiz.querySelector('[data-menu]').hidden = !raiz.querySelector('[data-menu]').hidden;
    if (acao === 'sincronizar') sincronizar(true);
    if (acao === 'sair') sair();
    if (acao === 'dia') { estado.dia = Number(alvo.dataset.dia); desenhar(); }
    if (acao === 'mover') mover(Number(alvo.dataset.id), Number(alvo.dataset.direcao));
    if (acao === 'corrigir') {
        const r = await banco.registro(alvo.dataset.uuid);
        await banco.apagarRegistro(r.uuid);
        location.hash = `#/registrar/${r.coleta_id}`;
    }
});
raiz.querySelector('[data-menu]').addEventListener('click', (e) => {
    if (e.target.closest('a')) raiz.querySelector('[data-menu]').hidden = true;
});

window.addEventListener('hashchange', () => { mostrarAviso(''); desenhar(); window.scrollTo(0, 0); });
window.addEventListener('online', () => { rodape(); sincronizar(); });
window.addEventListener('offline', () => rodape());
setInterval(() => { if (online()) sincronizar(); }, 2 * 60 * 1000);

// ---------- início ----------
(async function iniciar() {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});

    // Outro usuário entrou neste aparelho: apaga os dados do anterior, se não houver nada pendente (RNF16).
    const acesso = await banco.ler('acesso');
    if (acesso?.usuario && acesso.usuario.id !== pagina.usuarioId) {
        const pendentes = (await banco.registros()).filter((r) => ['PENDENTE', 'PROBLEMA'].includes(r.estado));
        if (pendentes.length) {
            mostrarAviso(`Este aparelho tem registros não enviados de ${esc(acesso.usuario.nome)}. Peça para ${esc(acesso.usuario.nome)} entrar e sincronizar.`, 'erro');
            tela.innerHTML = '';
            return;
        }
        await banco.limparTudo();
    }
    estado.dados = (await banco.ler('coletas')) ?? null;
    await desenhar();
    await garantirAcesso();
    await atualizarColetas();
    await desenhar();
    await sincronizar();
})();
