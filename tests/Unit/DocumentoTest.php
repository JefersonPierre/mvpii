<?php

namespace Tests\Unit;

use App\Support\Documento;
use PHPUnit\Framework\TestCase;

// RN01: dígitos verificadores de CPF e CNPJ.
class DocumentoTest extends TestCase
{
    public function test_cpf(): void
    {
        $this->assertTrue(Documento::cpfValido('52998224725'));
        $this->assertTrue(Documento::valido('F', '529.982.247-25'));
        $this->assertFalse(Documento::cpfValido('52998224724'));
        $this->assertFalse(Documento::cpfValido('11111111111'));
        $this->assertFalse(Documento::cpfValido('5299822472'));
    }

    public function test_cnpj_numerico(): void
    {
        $this->assertTrue(Documento::cnpjValido('11222333000181'));
        $this->assertTrue(Documento::valido('J', '11.222.333/0001-81'));
        $this->assertFalse(Documento::cnpjValido('11222333000180'));
        $this->assertFalse(Documento::cnpjValido('00000000000000'));
    }

    public function test_cnpj_alfanumerico(): void
    {
        // Exemplo publicado pela Receita Federal para o novo formato.
        $this->assertTrue(Documento::valido('J', '12.ABC.345/01DE-35'));
        $this->assertFalse(Documento::valido('J', '12.ABC.345/01DE-36'));
    }

    public function test_tipo_de_pessoa_define_o_documento(): void
    {
        $this->assertFalse(Documento::valido('F', '11222333000181'));
        $this->assertFalse(Documento::valido('J', '52998224725'));
    }

    public function test_formatacao(): void
    {
        $this->assertSame('529.982.247-25', Documento::formatar('52998224725'));
        $this->assertSame('12.ABC.345/01DE-35', Documento::formatar('12abc34501de35'));
        $this->assertSame('(19) 99876-5432', Documento::formatarTelefone('19998765432'));
        $this->assertSame('(19) 3232-0000', Documento::formatarTelefone('1932320000'));
        $this->assertSame('13010-000', Documento::formatarCep('13010000'));
    }
}
