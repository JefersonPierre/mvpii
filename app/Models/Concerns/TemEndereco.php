<?php

namespace App\Models\Concerns;

use App\Support\Documento;

/** Endereço com CEP, logradouro, número, complemento, bairro, cidade e UF (clientes e pontos de coleta). */
trait TemEndereco
{
    public const CAMPOS_ENDERECO = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf'];

    /** Ex.: "Rua A, 10, apto 2 – Centro, Campinas/SP – CEP 13000-000". Vazio se não houver endereço. */
    public function enderecoCompleto(): string
    {
        $linha = collect([$this->logradouro, $this->numero, $this->complemento])->filter()->implode(', ');
        $local = collect([$this->bairro, collect([$this->cidade, $this->uf])->filter()->implode('/')])->filter()->implode(', ');
        $cep = $this->cep ? 'CEP '.Documento::formatarCep($this->cep) : '';

        return collect([$linha, $local, $cep])->filter()->implode(' – ');
    }

    public function temEndereco(): bool
    {
        return filled($this->logradouro) && filled($this->cidade) && filled($this->uf);
    }
}
