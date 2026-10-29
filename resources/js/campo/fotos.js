// RN45 – até três fotos por amostra, reduzidas no próprio aparelho para no máximo 1 MB cada antes do envio.

const LIMITE = 1024 * 1024;

function carregar(arquivo) {
    return new Promise((resolver, rejeitar) => {
        const url = URL.createObjectURL(arquivo);
        const imagem = new Image();
        imagem.onload = () => { URL.revokeObjectURL(url); resolver(imagem); };
        imagem.onerror = () => { URL.revokeObjectURL(url); rejeitar(new Error('Imagem inválida')); };
        imagem.src = url;
    });
}

/** Devolve a foto em data URL JPEG, com o lado maior de até 1600 px e no máximo 1 MB. */
export async function reduzir(arquivo) {
    const imagem = await carregar(arquivo);
    let lado = 1600;
    let qualidade = 0.8;
    for (let tentativa = 0; tentativa < 8; tentativa++) {
        const escala = Math.min(1, lado / Math.max(imagem.width, imagem.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(imagem.width * escala);
        canvas.height = Math.round(imagem.height * escala);
        canvas.getContext('2d').drawImage(imagem, 0, 0, canvas.width, canvas.height);
        const dados = canvas.toDataURL('image/jpeg', qualidade);
        if ((dados.length - dados.indexOf(',') - 1) * 0.75 <= LIMITE) return dados;
        lado = Math.round(lado * 0.8);
        qualidade = Math.max(0.5, qualidade - 0.1);
    }
    throw new Error('Não foi possível reduzir a foto para 1 MB.');
}
