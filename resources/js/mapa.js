// MOD11 – mapas com Leaflet + OpenStreetMap (RF44, RF47, RNF18). O Leaflet só é carregado nas telas com mapa.
// [data-mapa]: mapa das coletas numeradas na ordem de visita. [data-mapa-posicao]: marcador arrastável que
// preenche os campos de latitude e longitude do formulário (pontos de coleta e laboratório).

const CORES = { AGENDADA: '#0284c7', REALIZADA: '#16a34a', NAO_REALIZADA: '#f59e0b' };

async function leaflet() {
    const [modulo] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
    return modulo.default ?? modulo;
}

function camadaOsm(L) {
    return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; colaboradores do <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    });
}

function marcadorNumerado(L, texto, cor) {
    return L.divIcon({
        className: '',
        html: `<span style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9999px;background:${cor};color:#fff;font:600 13px sans-serif;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4)">${texto}</span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });
}

export async function mapaDeColetas(elemento, pontos, origem) {
    const L = await leaflet();
    const mapa = L.map(elemento, { scrollWheelZoom: false });
    camadaOsm(L).addTo(mapa);
    const limites = [];
    if (origem) {
        L.marker([origem.lat, origem.lng], { icon: marcadorNumerado(L, 'L', '#334155'), title: origem.titulo }).addTo(mapa).bindPopup(origem.titulo);
        limites.push([origem.lat, origem.lng]);
    }
    for (const p of pontos) {
        L.marker([p.lat, p.lng], { icon: marcadorNumerado(L, p.n, CORES[p.situacao] ?? '#0284c7'), title: p.titulo })
            .addTo(mapa).bindPopup(`<strong>${p.n}. ${p.titulo}</strong><br>${p.detalhe ?? ''}`);
        limites.push([p.lat, p.lng]);
    }
    if (limites.length > 1) L.polyline(limites, { color: '#0f766e', weight: 2, dashArray: '4 6' }).addTo(mapa);
    if (limites.length) mapa.fitBounds(limites, { padding: [30, 30], maxZoom: 16 });
    else mapa.setView([-15.78, -47.93], 4);
    return mapa;
}

// RF47 / RN49: busca as coordenadas pelo endereço (Nominatim) e deixa ajustar arrastando o marcador.
// RNF18: no máximo uma consulta por segundo; a busca só acontece quando o usuário pede ou ao sair do endereço.
let ultimaConsulta = 0;
export async function buscarEndereco(texto) {
    const espera = 1000 - (Date.now() - ultimaConsulta);
    if (espera > 0) await new Promise((r) => setTimeout(r, espera));
    ultimaConsulta = Date.now();
    const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=br&accept-language=pt-BR&q=${encodeURIComponent(texto)}`;
    const resposta = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!resposta.ok) throw new Error('Falha na busca');
    const [primeiro] = await resposta.json();
    return primeiro ? { lat: Number(primeiro.lat), lng: Number(primeiro.lon) } : null;
}

async function mapaDePosicao(elemento) {
    const form = elemento.closest('form');
    const campoLat = form.querySelector(elemento.dataset.campoLat ?? '[name="latitude"]');
    const campoLng = form.querySelector(elemento.dataset.campoLng ?? '[name="longitude"]');
    const aviso = form.querySelector('[data-aviso-mapa]');
    const ler = (campo) => {
        const v = Number(String(campo.value).replace(',', '.'));
        return campo.value.trim() !== '' && Number.isFinite(v) ? v : null;
    };
    const L = await leaflet();
    const mapa = L.map(elemento);
    camadaOsm(L).addTo(mapa);
    let marcador = null;

    function posicionar(lat, lng, preencher = true) {
        if (!marcador) {
            marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
            marcador.on('dragend', () => {
                const { lat: la, lng: ln } = marcador.getLatLng();
                campoLat.value = la.toFixed(7);
                campoLng.value = ln.toFixed(7);
                if (aviso) aviso.textContent = 'Posição ajustada no mapa. Salve para gravar.';
            });
        } else {
            marcador.setLatLng([lat, lng]);
        }
        mapa.setView([lat, lng], 17);
        if (preencher) {
            campoLat.value = lat.toFixed(7);
            campoLng.value = lng.toFixed(7);
        }
    }

    // Os ícones padrão do Leaflet não passam pelo build: usa um círculo.
    L.Marker.prototype.options.icon = marcadorNumerado(L, '', '#0f766e');
    const lat = ler(campoLat);
    const lng = ler(campoLng);
    if (lat !== null && lng !== null) posicionar(lat, lng, false);
    else mapa.setView([-15.78, -47.93], 4);

    mapa.on('click', (e) => {
        posicionar(e.latlng.lat, e.latlng.lng);
        if (aviso) aviso.textContent = 'Posição marcada no mapa. Salve para gravar.';
    });
    for (const campo of [campoLat, campoLng]) {
        campo.addEventListener('change', () => {
            const a = ler(campoLat);
            const b = ler(campoLng);
            if (a !== null && b !== null) posicionar(a, b, false);
        });
    }

    async function buscar() {
        const endereco = [...form.querySelectorAll('[data-endereco]')].map((c) => c.value.trim()).filter(Boolean).join(', ');
        if (!endereco) return;
        if (aviso) aviso.textContent = 'Buscando a posição pelo endereço…';
        try {
            const achado = await buscarEndereco(endereco);
            if (!achado) {
                if (aviso) aviso.textContent = 'Endereço não encontrado no mapa. Marque a posição clicando no mapa ou salve sem coordenadas.';
                return;
            }
            posicionar(achado.lat, achado.lng);
            if (aviso) aviso.textContent = 'Posição encontrada pelo endereço. Confira e, se preciso, arraste o marcador.';
        } catch {
            if (aviso) aviso.textContent = 'Não foi possível buscar a posição agora. Marque no mapa ou salve sem coordenadas.';
        }
    }

    form.querySelector('[data-buscar-posicao]')?.addEventListener('click', buscar);
    form.querySelector('[data-usar-sugestao]')?.addEventListener('click', (e) => {
        posicionar(Number(e.currentTarget.dataset.lat), Number(e.currentTarget.dataset.lng));
        if (aviso) aviso.textContent = 'Localização da coleta usada como posição do ponto. Salve para gravar.';
    });
    // RN49: ao alterar o endereço, busca de novo (quando o ponto ainda não tem coordenadas confirmadas).
    if (elemento.dataset.geocodificacao === '1') {
        for (const campo of form.querySelectorAll('[data-endereco]')) {
            campo.addEventListener('change', () => {
                if (ler(campoLat) === null || elemento.dataset.rebuscar === '1') buscar();
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    for (const el of document.querySelectorAll('[data-mapa]')) {
        mapaDeColetas(el, JSON.parse(el.dataset.pontos || '[]'), JSON.parse(el.dataset.origem || 'null'));
    }
    for (const el of document.querySelectorAll('[data-mapa-posicao]')) mapaDePosicao(el);
});
