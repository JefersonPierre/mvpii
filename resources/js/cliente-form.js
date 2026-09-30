// UC03 – formulário de cliente: tipo de cadastro e de pessoa, busca de CEP e lista de contatos.

function iniciar(form) {
    const tipoPessoa = () => form.querySelector('[name="tipo_pessoa"]:checked')?.value ?? 'J';
    const interessado = () => form.querySelector('[name="cadastro"]:checked')?.value === 'interessado';

    // Pessoa física x jurídica: rótulos, máscara do documento e nome fantasia.
    function atualizarPessoa() {
        const fisica = tipoPessoa() === 'F';
        form.querySelector('[data-rotulo-nome]').textContent = fisica ? 'Nome' : 'Razão social';
        form.querySelector('[data-rotulo-documento]').textContent = fisica ? 'CPF' : 'CNPJ';
        form.querySelector('[data-campo-fantasia]').hidden = fisica;
        const documento = form.querySelector('[name="documento"]');
        documento.dataset.mascara = fisica ? 'cpf' : 'cnpj';
        documento.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // RF05: no interessado, documento e endereço são opcionais (o asterisco some).
    function atualizarCadastro() {
        form.querySelectorAll('[data-obrigatorio-cliente]').forEach((el) => (el.hidden = interessado()));
    }

    form.addEventListener('change', (evento) => {
        if (evento.target.name === 'tipo_pessoa') {
            form.querySelector('[name="documento"]').value = '';
            atualizarPessoa();
        }
        if (evento.target.name === 'cadastro') atualizarCadastro();
    });
    atualizarPessoa();
    atualizarCadastro();

    // UC03 3a: CEP informado preenche o endereço (ViaCEP). Se o serviço falhar, o usuário digita.
    const cep = form.querySelector('[name="cep"]');
    const avisoCep = form.querySelector('[data-aviso-cep]');
    if (form.dataset.consultaCep === '1') {
        cep.addEventListener('input', async () => {
            const digitos = cep.value.replace(/\D/g, '');
            if (digitos.length !== 8) return;
            avisoCep.textContent = 'Buscando endereço…';
            try {
                const resposta = await fetch(`https://viacep.com.br/ws/${digitos}/json/`);
                const dados = await resposta.json();
                if (dados.erro) {
                    avisoCep.textContent = 'CEP não encontrado. Preencha o endereço.';
                    return;
                }
                const preencher = (nome, valor) => {
                    const campo = form.querySelector(`[name="${nome}"]`);
                    if (valor) campo.value = valor;
                };
                preencher('logradouro', dados.logradouro);
                preencher('bairro', dados.bairro);
                preencher('cidade', dados.localidade);
                preencher('uf', dados.uf);
                avisoCep.textContent = 'Endereço preenchido pelo CEP. Confira e informe o número.';
                form.querySelector('[name="numero"]').focus();
            } catch {
                avisoCep.textContent = 'Não foi possível consultar o CEP agora. Preencha o endereço.';
            }
        });
    }

    // RF06: adicionar e remover contatos. Cada linha nova recebe uma chave própria (n1, n2…).
    const lista = form.querySelector('[data-contatos]');
    const modelo = form.querySelector('#modelo-contato');
    let sequencia = Date.now();

    form.querySelector('[data-adicionar-contato]').addEventListener('click', () => {
        const chave = `n${sequencia++}`;
        const linha = modelo.content.firstElementChild.cloneNode(true);
        linha.innerHTML = linha.innerHTML.replaceAll('__CHAVE__', chave);
        lista.append(linha);
        if (!lista.querySelector('[name="principal"]:checked')) {
            linha.querySelector('[name="principal"]').checked = true;
        }
        linha.querySelector('input[type="text"]').focus();
    });

    lista.addEventListener('click', (evento) => {
        const remover = evento.target.closest('[data-remover-contato]');
        if (!remover) return;
        const linha = remover.closest('[data-contato]');
        const eraPrincipal = linha.querySelector('[name="principal"]').checked;
        linha.remove();
        if (eraPrincipal) {
            const primeiro = lista.querySelector('[name="principal"]');
            if (primeiro) primeiro.checked = true;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-form-cliente]').forEach(iniciar);
});
