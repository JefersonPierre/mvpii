// UC08 – formulário do orçamento: busca de cliente, contatos, itens (pacotes e parâmetros avulsos)
// e prévia do cálculo (RN13). O servidor sempre recalcula ao salvar.

const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const centavos = (valor) => Math.round(Number(valor || 0) * 100);
const lerNumero = (texto) => {
    let t = String(texto ?? '').replace(/R\$|\s/g, '');
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    const n = Number(t);
    return Number.isFinite(n) ? n : 0;
};
const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

function iniciar(form) {
    const dados = JSON.parse(form.querySelector('[data-dados-orcamento]').textContent);
    const catalogo = new Map([...dados.pacotes, ...dados.parametros].map((i) => [i.referencia, i]));
    const tipoAmostra = form.querySelector('[name="tipo_amostra_id"]');
    const lista = form.querySelector('[data-itens]');
    let proximaLinha = 0;

    // ---------- Cliente e contato ----------
    const blocoCliente = form.querySelector('[data-cliente]');
    const campoClienteId = blocoCliente.querySelector('[name="cliente_id"]');
    const escolhido = blocoCliente.querySelector('[data-cliente-escolhido]');
    const busca = blocoCliente.querySelector('[data-cliente-busca]');
    const entradaBusca = busca.querySelector('input');
    const resultados = busca.querySelector('[data-cliente-resultados]');
    const contato = form.querySelector('[name="contato_id"]');
    let clientesEncontrados = [];

    function escolherCliente(cliente, contatoId = null) {
        campoClienteId.value = cliente?.id ?? '';
        escolhido.hidden = !cliente;
        busca.hidden = !!cliente;
        resultados.hidden = true;
        contato.innerHTML = '';
        if (!cliente) {
            contato.innerHTML = '<option value="">Selecione o cliente</option>';
            entradaBusca.value = '';
            entradaBusca.focus();
            return;
        }
        escolhido.querySelector('[data-cliente-nome]').textContent = cliente.nome;
        escolhido.querySelector('[data-cliente-documento]').textContent = cliente.documento || 'sem CPF/CNPJ';
        escolhido.querySelector('[data-cliente-interessado]').hidden = !cliente.interessado;

        contato.append(new Option('Sem contato definido', ''));
        const padrao = contatoId ?? cliente.contatos.find((c) => c.principal)?.id;
        for (const c of cliente.contatos) {
            const rotulo = `${c.nome}${c.email ? ' · ' + c.email : ' · sem e-mail'}${c.principal ? ' (principal)' : ''}`;
            contato.append(new Option(rotulo, c.id, false, c.id === padrao));
        }
    }

    let espera;
    entradaBusca.addEventListener('input', () => {
        clearTimeout(espera);
        const termo = entradaBusca.value.trim();
        if (termo.length < 2) {
            resultados.hidden = true;
            return;
        }
        espera = setTimeout(async () => {
            const resposta = await fetch(`${dados.buscarClientes}?q=${encodeURIComponent(termo)}`, {
                headers: { Accept: 'application/json' },
            });
            clientesEncontrados = await resposta.json();
            resultados.innerHTML = clientesEncontrados.length
                ? clientesEncontrados.map((c, i) => `
                    <li><button type="button" data-indice="${i}" class="block w-full px-3 py-2 text-left text-sm hover:bg-teal-50">
                        <span class="font-medium">${escapar(c.nome)}</span>
                        <span class="ml-2 font-mono text-xs text-slate-500">${escapar(c.documento)}</span>
                        ${c.interessado ? '<span class="selo ml-2 bg-amber-100 text-amber-800">Interessado</span>' : ''}
                    </button></li>`).join('')
                : '<li class="px-3 py-2 text-sm text-slate-500">Nenhum cliente ativo encontrado.</li>';
            resultados.hidden = false;
        }, 250);
    });
    resultados.addEventListener('click', (evento) => {
        const botao = evento.target.closest('[data-indice]');
        if (botao) escolherCliente(clientesEncontrados[Number(botao.dataset.indice)]);
    });
    blocoCliente.querySelector('[data-trocar-cliente]').addEventListener('click', () => escolherCliente(null));
    escolherCliente(dados.cliente, dados.contatoId);

    // ---------- Itens ----------
    function opcoes(tipo, selecionada) {
        const itens = tipo === 'PACOTE'
            ? dados.pacotes.filter((p) => String(p.tipoAmostraId) === tipoAmostra.value || p.referencia === selecionada)
            : dados.parametros;
        const vazio = tipo === 'PACOTE' && !tipoAmostra.value ? 'Escolha antes o tipo de amostra' : 'Selecione…';
        return `<option value="">${vazio}</option>` + itens.map((i) => `
            <option value="${i.referencia}" ${i.referencia === selecionada ? 'selected' : ''}>
                ${escapar(i.nome)}${i.ativo ? '' : ' (inativo)'}
            </option>`).join('');
    }

    function adicionarLinha(item) {
        const tipo = item.referencia ? item.referencia.split(':')[0] : item.tipo;
        const n = proximaLinha++;
        const linha = document.createElement('div');
        linha.dataset.linha = '';
        linha.dataset.tipo = tipo;
        linha.dataset.referenciaGravada = item.referenciaGravada ?? '';
        linha.dataset.precoGravado = item.precoGravado ?? '';
        linha.className = 'grid grid-cols-2 items-start gap-2 rounded-md border border-slate-200 p-2 md:grid-cols-[minmax(12rem,1fr)_5rem_6.5rem_6.5rem_4.5rem] md:border-0 md:p-0';
        linha.innerHTML = `
            ${item.id ? `<input type="hidden" name="itens[${n}][id]" value="${item.id}">` : ''}
            <div class="col-span-2 md:col-span-1">
                <select name="itens[${n}][referencia]" aria-label="${tipo === 'PACOTE' ? 'Pacote' : 'Parâmetro avulso'}"
                        class="campo ${item.erro ? 'campo-erro' : ''}">${opcoes(tipo, item.referencia)}</select>
                <p class="ajuda" data-detalhe></p>
            </div>
            <input name="itens[${n}][quantidade]" type="number" min="1" max="999" value="${Number(item.quantidade) || 1}"
                   aria-label="Quantidade de amostras" class="campo font-mono">
            <span class="self-center text-right font-mono text-sm" data-preco></span>
            <span class="self-center text-right font-mono text-sm font-semibold" data-subtotal></span>
            <button type="button" class="botao botao-pequeno botao-perigo self-center" data-remover-item aria-label="Remover item">Remover</button>
            ${item.erro ? `<p class="erro col-span-full">${escapar(item.erro)}</p>` : ''}`;
        lista.append(linha);
        return linha;
    }

    /** Preço da linha: o congelado, se o item já estava gravado assim; senão o atual do catálogo (RN15). */
    function precoDaLinha(linha) {
        const referencia = linha.querySelector('select').value;
        if (!referencia) return null;
        if (referencia === linha.dataset.referenciaGravada && linha.dataset.precoGravado !== '') {
            return Number(linha.dataset.precoGravado);
        }
        return Number(catalogo.get(referencia)?.preco ?? 0);
    }

    function recalcular() {
        let somaItens = 0;
        const linhas = [...lista.querySelectorAll('[data-linha]')];
        for (const linha of linhas) {
            const preco = precoDaLinha(linha);
            const quantidade = Math.max(0, parseInt(linha.querySelector('input[type="number"]').value, 10) || 0);
            const subtotal = preco === null ? 0 : centavos(preco) * quantidade;
            somaItens += subtotal;
            linha.querySelector('[data-preco]').textContent = preco === null ? '—' : moeda.format(preco);
            linha.querySelector('[data-subtotal]').textContent = preco === null ? '—' : moeda.format(subtotal / 100);
            const item = catalogo.get(linha.querySelector('select').value);
            linha.querySelector('[data-detalhe]').textContent = item?.parametros ? `Inclui: ${item.parametros}` : '';
        }
        form.querySelector('[data-sem-itens]').hidden = linhas.length > 0;

        const taxa = centavos(lerNumero(form.querySelector('[name="taxa_coleta"]').value));
        const campoDesconto = form.querySelector('[name="desconto_percentual"]');
        const percentual = lerNumero(campoDesconto.value);
        const desconto = Math.round((somaItens + taxa) * percentual / 100);
        const resumo = (chave, texto) => (form.querySelector(`[data-resumo="${chave}"]`).textContent = texto);
        resumo('itens', moeda.format(somaItens / 100));
        resumo('taxa', moeda.format(taxa / 100));
        resumo('percentual', percentual.toLocaleString('pt-BR'));
        resumo('desconto', `− ${moeda.format(desconto / 100)}`);
        resumo('total', moeda.format((somaItens + taxa - desconto) / 100));

        // RN14: avisa já na tela; o servidor também recusa.
        const maximo = Number(campoDesconto.dataset.descontoMaximo);
        const aviso = form.querySelector('[data-aviso-desconto]');
        aviso.hidden = percentual <= maximo;
        aviso.textContent = `Máximo permitido: ${maximo.toLocaleString('pt-BR')}%.`;
        campoDesconto.classList.toggle('campo-erro', percentual > maximo);
    }

    form.querySelectorAll('[data-adicionar-item]').forEach((botao) => botao.addEventListener('click', () => {
        const linha = adicionarLinha({ tipo: botao.dataset.adicionarItem, quantidade: 1 });
        linha.querySelector('select').focus();
        recalcular();
    }));
    lista.addEventListener('click', (evento) => {
        const remover = evento.target.closest('[data-remover-item]');
        if (!remover) return;
        remover.closest('[data-linha]').remove();
        recalcular();
    });
    // Ao trocar o tipo de amostra, as linhas de pacote mostram só os pacotes do novo tipo.
    tipoAmostra.addEventListener('change', () => {
        lista.querySelectorAll('[data-linha][data-tipo="PACOTE"] select').forEach((select) => {
            const atual = catalogo.get(select.value);
            select.innerHTML = opcoes('PACOTE', atual && String(atual.tipoAmostraId) === tipoAmostra.value ? select.value : '');
        });
        recalcular();
    });
    form.addEventListener('input', recalcular);
    form.addEventListener('change', recalcular);

    dados.itens.forEach(adicionarLinha);
    recalcular();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-form-orcamento]').forEach(iniciar);
});
