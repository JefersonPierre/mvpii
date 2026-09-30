import { calcularDiferencas } from './diferencas';

describe('RN08 – diferenças entre valores', () => {
  it('lista só os campos alterados, com valor anterior e novo', () => {
    const r = calcularDiferencas(
      { nome: 'Ana', email: 'ana@lab.com', ativo: true },
      { nome: 'Ana Souza', email: 'ana@lab.com', ativo: true },
      ['nome', 'email', 'ativo'],
    );
    expect(r).toEqual([{ campo: 'nome', valorAnterior: 'Ana', valorNovo: 'Ana Souza' }]);
  });

  it('trata vazio e nulo como a mesma coisa', () => {
    expect(calcularDiferencas({ obs: '' }, { obs: null }, ['obs'])).toEqual([]);
  });

  it('converte booleanos em texto', () => {
    expect(calcularDiferencas({ ativo: true }, { ativo: false }, ['ativo'])).toEqual([
      { campo: 'ativo', valorAnterior: 'true', valorNovo: 'false' },
    ]);
  });
});
