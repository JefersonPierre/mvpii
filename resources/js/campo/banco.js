// RF39 / RF40 / RNF14 – armazenamento no aparelho (IndexedDB). Sobrevive a fechar o aplicativo e reiniciar o celular.
// "dados": token do aparelho, coletas guardadas, última sincronização. "registros": coletas registradas, pelo uuid (RN40).

const NOME = 'laboratorio-coletas';
const VERSAO = 1;
let conexao = null;

function abrir() {
    if (conexao) return conexao;
    conexao = new Promise((resolver, rejeitar) => {
        const pedido = indexedDB.open(NOME, VERSAO);
        pedido.onupgradeneeded = () => {
            const banco = pedido.result;
            if (!banco.objectStoreNames.contains('dados')) banco.createObjectStore('dados');
            if (!banco.objectStoreNames.contains('registros')) banco.createObjectStore('registros', { keyPath: 'uuid' });
        };
        pedido.onsuccess = () => resolver(pedido.result);
        pedido.onerror = () => rejeitar(pedido.error);
    });
    return conexao;
}

async function transacao(loja, modo, operacao) {
    const banco = await abrir();
    return new Promise((resolver, rejeitar) => {
        const t = banco.transaction(loja, modo);
        const resultado = operacao(t.objectStore(loja));
        t.oncomplete = () => resolver(resultado?.result);
        t.onerror = () => rejeitar(t.error);
        t.onabort = () => rejeitar(t.error);
    });
}

export const ler = (chave) => transacao('dados', 'readonly', (l) => l.get(chave));
export const gravar = (chave, valor) => transacao('dados', 'readwrite', (l) => l.put(valor, chave));
export const registros = () => transacao('registros', 'readonly', (l) => l.getAll());
export const registro = (uuid) => transacao('registros', 'readonly', (l) => l.get(uuid));
export const gravarRegistro = (r) => transacao('registros', 'readwrite', (l) => l.put(r));
export const apagarRegistro = (uuid) => transacao('registros', 'readwrite', (l) => l.delete(uuid));

/** RNF16: ao sair, apaga tudo do aparelho (só é permitido sem registros pendentes, RN39). */
export async function limparTudo() {
    await transacao('dados', 'readwrite', (l) => l.clear());
    await transacao('registros', 'readwrite', (l) => l.clear());
}
