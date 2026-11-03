<?php

namespace Database\Seeders;

use App\Models\Legislacao;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Models\TipoAmostra;
use Illuminate\Database\Seeder;

/**
 * Dados de exemplo do catálogo técnico (seção 3 da documentação): parâmetros, pacotes e limites da
 * Portaria GM/MS nº 888/2021 (água potável) e da Resolução CONAMA nº 430/2011 (lançamento de efluentes).
 *
 * ATENÇÃO: os preços são fictícios e os limites devem ser conferidos com o texto oficial e com a gerente
 * do laboratório antes do uso real. Pode ser executado mais de uma vez: não duplica nem sobrescreve nada.
 *
 * Uso: php artisan db:seed --class=CatalogoExemploSeeder (no ambiente local já roda com db:seed).
 */
class CatalogoExemploSeeder extends Seeder
{
    private const FQ = 'FISICO_QUIMICA';

    private const MICRO = 'MICROBIOLOGICA';

    public function run(): void
    {
        $agua = TipoAmostra::firstOrCreate(['nome' => 'Água potável'], ['descricao' => 'Água para consumo humano']);
        $efluente = TipoAmostra::firstOrCreate(['nome' => 'Efluente'], ['descricao' => 'Efluentes domésticos e industriais']);

        // nome => [unidade, método, categoria, preço de exemplo]
        $p = collect([
            'pH' => ['adimensional', 'SMWW 4500-H+ B – Potenciométrico', self::FQ, 25],
            'Turbidez' => ['uT', 'SMWW 2130 B – Nefelométrico', self::FQ, 25],
            'Cor aparente' => ['uH', 'SMWW 2120 C – Espectrofotométrico', self::FQ, 25],
            'Cloro residual livre' => ['mg/L', 'SMWW 4500-Cl G – DPD colorimétrico', self::FQ, 30],
            'Coliformes totais' => ['NMP/100 mL', 'SMWW 9223 B – Substrato cromogênico', self::MICRO, 45],
            'Escherichia coli' => ['NMP/100 mL', 'SMWW 9223 B – Substrato cromogênico', self::MICRO, 45],
            'Ferro total' => ['mg/L', 'SMWW 3500-Fe B – Fenantrolina', self::FQ, 40],
            'Manganês' => ['mg/L', 'SMWW 3500-Mn – Persulfato', self::FQ, 40],
            'Nitrato (como N)' => ['mg/L', 'SMWW 4500-NO3 – Espectrofotométrico', self::FQ, 40],
            'Fluoreto' => ['mg/L', 'SMWW 4500-F D – SPADNS', self::FQ, 40],
            'Dureza total' => ['mg/L CaCO3', 'SMWW 2340 C – Titulométrico EDTA', self::FQ, 30],
            'Sólidos dissolvidos totais' => ['mg/L', 'SMWW 2540 C – Gravimétrico', self::FQ, 35],
            'Temperatura' => ['°C', 'SMWW 2550 B – Termométrico', self::FQ, 15],
            'Materiais sedimentáveis' => ['mL/L', 'SMWW 2540 F – Cone Imhoff', self::FQ, 30],
            'DBO (5 dias, 20 °C)' => ['mg/L', 'SMWW 5210 B – Incubação 5 dias', self::FQ, 70],
            'Óleos minerais' => ['mg/L', 'SMWW 5520 – Extração e gravimetria', self::FQ, 80],
            'Óleos vegetais e gorduras animais' => ['mg/L', 'SMWW 5520 – Extração e gravimetria', self::FQ, 80],
            'Ferro dissolvido' => ['mg/L', 'SMWW 3500-Fe B – Fenantrolina', self::FQ, 40],
            'Nitrogênio amoniacal total' => ['mg/L', 'SMWW 4500-NH3 – Fenato', self::FQ, 45],
            'Sulfeto' => ['mg/L', 'SMWW 4500-S2 D – Azul de metileno', self::FQ, 45],
        ])->map(fn ($d, $nome) => Parametro::firstOrCreate(['nome' => $nome],
            ['unidade' => $d[0], 'metodo' => $d[1], 'categoria' => $d[2], 'preco' => $d[3]]));

        $this->pacote('Potabilidade básica', $agua, 150,
            [$p['pH'], $p['Turbidez'], $p['Cor aparente'], $p['Cloro residual livre'], $p['Coliformes totais'], $p['Escherichia coli']]);
        $this->pacote('Efluente – lançamento básico', $efluente, 280,
            [$p['pH'], $p['Temperatura'], $p['Materiais sedimentáveis'], $p['DBO (5 dias, 20 °C)'], $p['Óleos minerais'], $p['Óleos vegetais e gorduras animais']]);

        // Portaria GM/MS nº 888/2021 – padrão de potabilidade
        $portaria = $this->legislacao('Portaria GM/MS nº 888/2021', 'Ministério da Saúde', '2021-05-07', $agua);
        $this->limites($portaria, $agua, [
            ['pH', 'FAIXA', 6, 9, 'Faixa recomendada no sistema de distribuição'],
            ['Turbidez', 'MAXIMO', null, 5, 'Padrão organoléptico'],
            ['Cor aparente', 'MAXIMO', null, 15, 'Padrão organoléptico'],
            ['Cloro residual livre', 'FAIXA', 0.2, 5, 'Mínimo de 0,2 mg/L em toda a rede de distribuição'],
            ['Coliformes totais', 'AUSENCIA', null, null, 'Ausência em 100 mL'],
            ['Escherichia coli', 'AUSENCIA', null, null, 'Ausência em 100 mL'],
            ['Ferro total', 'MAXIMO', null, 0.3, null],
            ['Manganês', 'MAXIMO', null, 0.1, null],
            ['Nitrato (como N)', 'MAXIMO', null, 10, null],
            ['Fluoreto', 'MAXIMO', null, 1.5, null],
            ['Dureza total', 'MAXIMO', null, 300, 'Padrão organoléptico'],
            ['Sólidos dissolvidos totais', 'MAXIMO', null, 500, 'Padrão organoléptico'],
        ], $p);

        // Resolução CONAMA nº 430/2011 – condições e padrões de lançamento de efluentes
        $conama = $this->legislacao('Resolução CONAMA nº 430/2011', 'Conselho Nacional do Meio Ambiente', '2011-05-16', $efluente);
        $this->limites($conama, $efluente, [
            ['pH', 'FAIXA', 5, 9, null],
            ['Temperatura', 'MAXIMO', null, 40, 'Inferior a 40 °C'],
            ['Materiais sedimentáveis', 'MAXIMO', null, 1, 'Teste de 1 hora em cone Imhoff'],
            ['DBO (5 dias, 20 °C)', 'MAXIMO', null, 120, 'Esgoto sanitário tratado; ou remoção mínima de 60%'],
            ['Óleos minerais', 'MAXIMO', null, 20, null],
            ['Óleos vegetais e gorduras animais', 'MAXIMO', null, 50, null],
            ['Ferro dissolvido', 'MAXIMO', null, 15, null],
            ['Fluoreto', 'MAXIMO', null, 10, 'Fluoreto total'],
            ['Nitrogênio amoniacal total', 'MAXIMO', null, 20, null],
            ['Sulfeto', 'MAXIMO', null, 1, null],
        ], $p);
    }

    private function pacote(string $nome, TipoAmostra $tipo, float $preco, array $parametros): void
    {
        $pacote = Pacote::firstOrCreate(['nome' => $nome], ['tipo_amostra_id' => $tipo->id, 'preco' => $preco]);
        if ($pacote->wasRecentlyCreated) {
            $pacote->parametros()->sync(collect($parametros)->pluck('id'));
        }
    }

    private function legislacao(string $nome, string $orgao, string $inicio, TipoAmostra $tipo): Legislacao
    {
        $legislacao = Legislacao::firstOrCreate(['nome' => $nome], ['orgao_emissor' => $orgao, 'inicio_vigencia' => $inicio]);
        $legislacao->tiposAmostra()->syncWithoutDetaching([$tipo->id]);

        return $legislacao;
    }

    /** @param  list<array{0: string, 1: string, 2: float|int|null, 3: float|int|null, 4: string|null}>  $limites */
    private function limites(Legislacao $legislacao, TipoAmostra $tipo, array $limites, $parametros): void
    {
        foreach ($limites as [$parametro, $tipoLimite, $minimo, $maximo, $observacao]) {
            $legislacao->limites()->firstOrCreate(
                ['parametro_id' => $parametros[$parametro]->id, 'tipo_amostra_id' => $tipo->id],
                ['tipo' => $tipoLimite, 'valor_minimo' => $minimo, 'valor_maximo' => $maximo, 'observacao' => $observacao],
            );
        }
    }
}
