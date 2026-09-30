<?php

// CI: transforma os testes que falharam (relatório JUnit) em anotações do GitHub Actions.
foreach (simplexml_load_file($argv[1])->xpath('//testcase[failure or error]') as $teste) {
    $mensagem = (string) ($teste->failure ?: $teste->error);
    echo '::error title=', $teste['class'], '::', rawurlencode(substr($mensagem, 0, 1500)), PHP_EOL;
}
