// RF38 / RNF14 – service worker do aplicativo do coletor (gerado pelo servidor; versão {{ $versao }}).
// Guarda a tela de coletas do dia e os arquivos do build para o sistema abrir sem conexão. Os dados das coletas e os
// registros pendentes ficam no IndexedDB, gravados pela própria tela; aqui não se guarda nenhuma resposta da API.
const CACHE = 'coletas-{{ $versao }}';
const TELA = '/campo';
const ARQUIVOS = @json($arquivos);

self.addEventListener('install', (evento) => {
    evento.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await cache.addAll(ARQUIVOS);
        // A tela depende do login: guarda se a resposta vier certa (sem redirecionar para o login).
        const tela = await fetch(TELA, { credentials: 'same-origin' }).catch(() => null);
        if (tela && tela.ok && !tela.redirected) await cache.put(TELA, tela);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil((async () => {
        for (const nome of await caches.keys()) {
            if (nome.startsWith('coletas-') && nome !== CACHE) await caches.delete(nome);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (evento) => {
    const pedido = evento.request;
    const url = new URL(pedido.url);
    if (pedido.method !== 'GET' || url.origin !== self.location.origin) return;

    // Tela do coletor: rede primeiro (sempre a mais nova); sem conexão, a guardada. Qualquer outra tela do sistema,
    // sem conexão, abre a do coletor, que funciona offline.
    if (pedido.mode === 'navigate') {
        evento.respondWith((async () => {
            try {
                const resposta = await fetch(pedido);
                if (url.pathname === TELA && resposta.ok && !resposta.redirected) {
                    const cache = await caches.open(CACHE);
                    await cache.put(TELA, resposta.clone());
                }
                return resposta;
            } catch {
                const guardada = await caches.match(TELA);
                return guardada ?? new Response('<h1>Sem conexão</h1><p>Abra o sistema com internet ao menos uma vez neste aparelho.</p>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
            }
        })());
        return;
    }

    // CSS, JS e ícones: guardados na instalação (nomes versionados pelo build).
    if (ARQUIVOS.includes(url.pathname) || url.pathname.startsWith('/build/')) {
        evento.respondWith((async () => (await caches.match(pedido)) ?? fetch(pedido).then(async (resposta) => {
            if (resposta.ok) (await caches.open(CACHE)).put(pedido, resposta.clone());
            return resposta;
        }))());
    }
});
