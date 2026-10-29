<?php

namespace Tests\Unit;

use App\Support\Conformidade;
use PHPUnit\Framework\TestCase;

// RN52 – comparação do resultado com o limite da legislação
class ConformidadeTest extends TestCase
{
    public function test_ct33_faixa_e_ausencia(): void
    {
        $this->assertSame(Conformidade::NAO_CONFORME, Conformidade::avaliar('FAIXA', 6, 9, '9.7'));
        $this->assertSame(Conformidade::CONFORME, Conformidade::avaliar('FAIXA', 6, 9, '7.2'));
        $this->assertSame(Conformidade::NAO_CONFORME, Conformidade::avaliar('AUSENCIA', null, null, null, presenca: 'PRESENTE'));
        $this->assertSame(Conformidade::CONFORME, Conformidade::avaliar('AUSENCIA', null, null, null, presenca: 'AUSENTE'));
    }

    public function test_ct34_menor_que_lq_e_sem_limite(): void
    {
        $this->assertSame(Conformidade::CONFORME, Conformidade::avaliar('MAXIMO', null, 5, null, menorQueLq: true, lq: 0.1));
        $this->assertSame(Conformidade::SEM_LIMITE, Conformidade::avaliar(null, null, null, '5'));
    }

    public function test_rn52_casos_de_limite(): void
    {
        $this->assertSame(Conformidade::CONFORME, Conformidade::avaliar('MAXIMO', null, 5, '5'), 'o valor igual ao máximo é conforme');
        $this->assertSame(Conformidade::INCONCLUSIVO, Conformidade::avaliar('MAXIMO', null, 5, null, menorQueLq: true, lq: 10));
        $this->assertSame(Conformidade::NAO_CONFORME, Conformidade::avaliar('MINIMO', 0.2, null, null, menorQueLq: true, lq: 0.1));
        $this->assertSame(Conformidade::INCONCLUSIVO, Conformidade::avaliar('MINIMO', 0.2, null, null, menorQueLq: true, lq: 0.5));
        $this->assertNull(Conformidade::avaliar('MAXIMO', null, 5, null), 'sem resultado, sem conformidade');
    }

    public function test_rn52_conclusao_da_amostra(): void
    {
        $this->assertSame('NAO_CONFORME', Conformidade::conclusao([Conformidade::CONFORME, Conformidade::NAO_CONFORME, Conformidade::INCONCLUSIVO], true));
        $this->assertSame('INCONCLUSIVA', Conformidade::conclusao([Conformidade::CONFORME, Conformidade::INCONCLUSIVO], true));
        $this->assertSame('CONFORME', Conformidade::conclusao([Conformidade::CONFORME, Conformidade::SEM_LIMITE], true));
        $this->assertSame('SEM_LEGISLACAO', Conformidade::conclusao([Conformidade::CONFORME], false));
    }
}
