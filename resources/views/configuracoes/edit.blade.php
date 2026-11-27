{{-- UC12 – Configurar parâmetros comerciais (RF22), UC19 – critérios de recebimento (RF35) e UC29 – laboratório e laudo (RF56) --}}
@use('App\Support\Numero')

<x-layout titulo="Configurações">
    <div>
        <h1 class="text-2xl font-semibold">Configurações</h1>
        <p class="text-sm text-slate-500">Mudanças valem para os próximos orçamentos, recebimentos e laudos; os já registrados não mudam.</p>
    </div>

    <form method="POST" action="{{ route('configuracoes') }}" class="cartao max-w-2xl space-y-4" novalidate>
        @csrf
        @method('PUT')
        <h2 class="text-lg font-semibold">Orçamento</h2>
        <p class="-mt-3 text-sm text-slate-500">Valores padrão usados ao elaborar um orçamento.</p>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="validade_dias" class="rotulo">Validade padrão (dias) *</label>
                <input id="validade_dias" name="validade_dias" type="number" min="1" max="365"
                       value="{{ old('validade_dias', $configuracao->validade_dias) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('validade_dias')])>
                @error('validade_dias') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="taxa_coleta" class="rotulo">Taxa de coleta padrão (R$) *</label>
                <input id="taxa_coleta" name="taxa_coleta" inputmode="decimal"
                       value="{{ old('taxa_coleta', Numero::campo($configuracao->taxa_coleta, true)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('taxa_coleta')])>
                @error('taxa_coleta') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="desconto_maximo" class="rotulo">Desconto máximo (%) *</label>
                <input id="desconto_maximo" name="desconto_maximo" inputmode="decimal"
                       value="{{ old('desconto_maximo', Numero::decimal($configuracao->desconto_maximo)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('desconto_maximo')])>
                @error('desconto_maximo') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="condicoes_comerciais" class="rotulo">Condições comerciais (vão no PDF do orçamento)</label>
            <textarea id="condicoes_comerciais" name="condicoes_comerciais" rows="5" maxlength="5000"
                      @class(['campo', 'campo-erro' => $errors->has('condicoes_comerciais')])>{{ old('condicoes_comerciais', $configuracao->condicoes_comerciais) }}</textarea>
            @error('condicoes_comerciais') <p class="erro">{{ $message }}</p> @enderror
        </div>

        <h2 class="border-t border-slate-200 pt-4 text-lg font-semibold">Recebimento das amostras</h2>
        <p class="-mt-3 text-sm text-slate-500">
            Fora destes critérios, o sistema alerta no recebimento e o aceite exige observação (RN35).
        </p>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="prazo_recebimento_horas" class="rotulo">Prazo máximo da coleta ao recebimento (horas) *</label>
                <input id="prazo_recebimento_horas" name="prazo_recebimento_horas" type="number" min="1" max="720"
                       value="{{ old('prazo_recebimento_horas', $configuracao->prazo_recebimento_horas) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('prazo_recebimento_horas')])>
                @error('prazo_recebimento_horas') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="temperatura_maxima_recebimento" class="rotulo">Temperatura máxima de recebimento (°C) *</label>
                <input id="temperatura_maxima_recebimento" name="temperatura_maxima_recebimento" inputmode="decimal"
                       value="{{ old('temperatura_maxima_recebimento', Numero::decimal($configuracao->temperatura_maxima_recebimento)) }}"
                       @class(['campo font-mono', 'campo-erro' => $errors->has('temperatura_maxima_recebimento')])>
                @error('temperatura_maxima_recebimento') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>
        {{-- UC29 – RF56: dados do laboratório no laudo e ponto de partida das rotas (RN47) --}}
        <h2 id="laboratorio" class="border-t border-slate-200 pt-4 text-lg font-semibold">Laboratório e laudo</h2>
        <p class="-mt-3 text-sm text-slate-500">Usados nos próximos laudos e como ponto de partida das rotas dos coletores. O laudo só é emitido com o responsável técnico informado.</p>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="laboratorio_nome" class="rotulo">Nome do laboratório</label>
                <input id="laboratorio_nome" name="laboratorio_nome" maxlength="150" placeholder="{{ config('laboratorio.dados.nome') }}"
                       value="{{ old('laboratorio_nome', $configuracao->laboratorio_nome) }}" @class(['campo', 'campo-erro' => $errors->has('laboratorio_nome')])>
                @error('laboratorio_nome') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="laboratorio_endereco" class="rotulo">Endereço</label>
                <input id="laboratorio_endereco" name="laboratorio_endereco" maxlength="255" data-endereco placeholder="Rua, número, bairro, cidade/UF"
                       value="{{ old('laboratorio_endereco', $configuracao->laboratorio_endereco) }}" @class(['campo', 'campo-erro' => $errors->has('laboratorio_endereco')])>
                @error('laboratorio_endereco') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="laboratorio_telefone" class="rotulo">Telefone</label>
                <input id="laboratorio_telefone" name="laboratorio_telefone" maxlength="30"
                       value="{{ old('laboratorio_telefone', $configuracao->laboratorio_telefone) }}" class="campo">
            </div>
            <div></div>
            <div>
                <label for="responsavel_tecnico" class="rotulo">Responsável técnico</label>
                <input id="responsavel_tecnico" name="responsavel_tecnico" maxlength="150"
                       value="{{ old('responsavel_tecnico', $configuracao->responsavel_tecnico) }}" @class(['campo', 'campo-erro' => $errors->has('responsavel_tecnico')])>
                @error('responsavel_tecnico') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="registro_profissional" class="rotulo">Registro profissional</label>
                <input id="registro_profissional" name="registro_profissional" maxlength="60" placeholder="Ex.: CRQ IV 04123456"
                       value="{{ old('registro_profissional', $configuracao->registro_profissional) }}" @class(['campo', 'campo-erro' => $errors->has('registro_profissional')])>
                @error('registro_profissional') <p class="erro">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="observacoes_laudo" class="rotulo">Observações padrão do laudo</label>
                <textarea id="observacoes_laudo" name="observacoes_laudo" rows="3" maxlength="3000"
                          @class(['campo', 'campo-erro' => $errors->has('observacoes_laudo')])>{{ old('observacoes_laudo', $configuracao->observacoes_laudo) }}</textarea>
                @error('observacoes_laudo') <p class="erro">{{ $message }}</p> @enderror
            </div>
        </div>
        <fieldset class="space-y-2">
            <legend class="text-base font-semibold">Localização do laboratório (partida das rotas)</legend>
            <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <div>
                    <label for="laboratorio_latitude" class="rotulo">Latitude</label>
                    <input id="laboratorio_latitude" name="laboratorio_latitude" inputmode="decimal" value="{{ old('laboratorio_latitude', $configuracao->laboratorio_latitude) }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('laboratorio_latitude')])>
                    @error('laboratorio_latitude') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="laboratorio_longitude" class="rotulo">Longitude</label>
                    <input id="laboratorio_longitude" name="laboratorio_longitude" inputmode="decimal" value="{{ old('laboratorio_longitude', $configuracao->laboratorio_longitude) }}"
                           @class(['campo font-mono', 'campo-erro' => $errors->has('laboratorio_longitude')])>
                    @error('laboratorio_longitude') <p class="erro">{{ $message }}</p> @enderror
                </div>
                @if (config('laboratorio.geocodificacao'))
                    <button type="button" data-buscar-posicao class="botao botao-secundario">Buscar pelo endereço</button>
                @endif
            </div>
            <div data-mapa-posicao data-campo-lat="[name=laboratorio_latitude]" data-campo-lng="[name=laboratorio_longitude]"
                 data-geocodificacao="{{ config('laboratorio.geocodificacao') ? '1' : '0' }}" class="h-64 w-full rounded-md bg-slate-100"></div>
            <p class="ajuda" data-aviso-mapa aria-live="polite">Clique no mapa ou arraste o marcador para ajustar a posição.</p>
        </fieldset>

        <div class="flex justify-end">
            <button type="submit" class="botao botao-primario">Salvar configurações</button>
        </div>
    </form>

    <div class="cartao space-y-3">
        <h2 class="text-lg font-semibold">Histórico de alterações</h2>
        <x-tabela-historico :registros="$registros"
            :campos="['validade_dias' => 'Validade padrão (dias)', 'taxa_coleta' => 'Taxa de coleta', 'desconto_maximo' => 'Desconto máximo (%)', 'condicoes_comerciais' => 'Condições comerciais',
                'prazo_recebimento_horas' => 'Prazo máximo de recebimento (horas)', 'temperatura_maxima_recebimento' => 'Temperatura máxima de recebimento',
                'laboratorio_nome' => 'Nome do laboratório', 'laboratorio_endereco' => 'Endereço do laboratório', 'laboratorio_telefone' => 'Telefone do laboratório',
                'laboratorio_latitude' => 'Latitude do laboratório', 'laboratorio_longitude' => 'Longitude do laboratório', 'responsavel_tecnico' => 'Responsável técnico',
                'registro_profissional' => 'Registro profissional', 'observacoes_laudo' => 'Observações do laudo']" />
    </div>
</x-layout>
