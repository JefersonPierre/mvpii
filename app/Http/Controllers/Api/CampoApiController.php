<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SincronizacaoRequest;
use App\Models\Coleta;
use App\Services\Roteirizacao;
use App\Services\SincronizacaoColetas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// MOD10 – API usada pelo aplicativo do coletor (UC21, UC22): coletas guardadas no aparelho, ordem e envio dos registros.
class CampoApiController extends Controller
{
    /** RF39 / RN38: coletas Agendadas do próprio coletor para hoje e amanhã. */
    public function coletas(Request $request, SincronizacaoColetas $sincronizacao): JsonResponse
    {
        return response()->json($sincronizacao->dadosDoAparelho($request->user()));
    }

    /** UC20 1a: o coletor muda a ordem de visita do dia. */
    public function ordem(Request $request, Roteirizacao $roteiro): JsonResponse
    {
        $dados = $request->validate(['data' => ['required', 'date_format:Y-m-d'], 'ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $roteiro->definirOrdem($request->user()->id, $dados['data'], array_map('intval', $dados['ids']));

        return response()->json(['mensagem' => 'Ordem de visita gravada.']);
    }

    /** UC22: um registro por requisição; o reenvio do mesmo identificador devolve o primeiro resultado (RN40). */
    public function sincronizar(SincronizacaoRequest $request, SincronizacaoColetas $sincronizacao): JsonResponse
    {
        // RN36: só a coleta que é ou já foi do coletor (a que passou a outro coletor vira conflito, RN42).
        $coleta = Coleta::find($request->integer('coleta_id'));
        if ($coleta && ! $sincronizacao->coletaJaFoiDoColetor($coleta, $request->user())) {
            return response()->json(['mensagem' => 'Esta coleta não é sua.', 'codigo' => 'SEM_PERMISSAO'], 403);
        }

        return response()->json($sincronizacao->sincronizar($request->user(), $request->attributes->get('dispositivo'), $request->validated()));
    }
}
