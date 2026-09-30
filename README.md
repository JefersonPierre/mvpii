# mvpii – Laboratório de Análise de Água

MVP do TCC: sistema para um laboratório de análise de qualidade da água (água potável e efluentes).
A documentação técnica do Entregável 1 (Cadastros e Orçamentos) está em [`docs/entregavel-1`](docs/entregavel-1).

## Stack

PHP 8.4 · Laravel 13 · Blade + Tailwind CSS (Vite) · SQLite no desenvolvimento (MySQL ou PostgreSQL em produção) ·
PHPUnit.

## Como rodar

Pré-requisitos: **PHP 8.3+**, **Composer** e **Node 22** (no Windows, o [Laravel Herd](https://herd.laravel.com) já traz PHP e Composer).

```bash
git clone https://github.com/JefersonPierre/mvpii.git
cd mvpii
composer install
npm install
cp .env.example .env          # no Windows (PowerShell): copy .env.example .env
php artisan key:generate
php artisan migrate --seed    # cria o banco SQLite (database/database.sqlite) e o usuário inicial
```

Para trabalhar, deixe dois terminais abertos:

```bash
php artisan serve   # sistema em http://localhost:8000
npm run dev         # CSS/JS com recarga automática
```

Usuário inicial: `admin@laboratorio.local` / senha `Admin12345` (definidos no `.env`).

No ambiente local os e-mails **não saem de verdade** (`MAIL_MAILER=log`): o conteúdo vai para
`storage/logs/laravel.log` e a própria tela mostra o link que iria no e-mail (convite de novo usuário e recuperação de senha).

Para recomeçar o banco do zero: `php artisan migrate:fresh --seed`.

No ambiente local, o `db:seed` também cadastra um **catálogo de exemplo** (parâmetros, pacotes e limites da Portaria
GM/MS nº 888/2021 e da Resolução CONAMA nº 430/2011). Os preços são fictícios e os limites devem ser conferidos com o
texto oficial antes do uso real.

Cópia de segurança do banco local (RNF06): `php artisan banco:copiar` cria uma cópia em `storage/app/private/copias`
(mantidas por 30 dias) e `php artisan banco:restaurar` lista as cópias e restaura a escolhida.

## Testes e verificação

```bash
php artisan test     # testes automatizados
vendor/bin/pint      # padroniza a formatação do código
npm run build        # build de produção do CSS/JS
```

## Estrutura

```
app/
  Http/Controllers/    Telas: login, recuperação de senha, usuários, clientes, pontos de coleta, catálogo
  Http/Middleware/     UsuarioAtivo (RN10)
  Http/Requests/       Validação dos formulários
  Models/              Usuario, Cliente, Contato, PontoColeta, TipoAmostra, Parametro, Pacote, Legislacao, Limite…
  Services/            Auditoria (RN08), CadastroComHistorico, CadastroClientes (UC03), CadastroPontos (UC04),
                       CadastroLegislacoes (UC06), LinksSenha (RF02)
  Rules/               NomeUnico (RN05), RegistroAtivo (RN04)
  Support/             RegrasAcesso (RN09), Documento (CPF/CNPJ – RN01), Numero (formato brasileiro)
database/migrations/   Esquema do banco
resources/views/       Telas (Blade)
routes/web.php         Rotas do sistema
tests/                 Testes (Feature e Unit)
docs/                  Documentação técnica (e ajustes-documentacao.md: o que mudou com o Laravel)
```

## Produção (final do projeto)

Ajustar o `.env`: `APP_ENV=production`, `APP_DEBUG=false`, banco MySQL/PostgreSQL em `DB_*`, `MAIL_MAILER=smtp`
com os dados do servidor de e-mail real, HTTPS e cópia de segurança do banco. Depois: `composer install --no-dev`,
`npm run build` e `php artisan migrate --force`.

No servidor, configure também:

- o agendador do Laravel no cron (`* * * * * php artisan schedule:run`), que marca como Expirado, todo dia, os
  orçamentos enviados com a validade vencida (RN17). O comando manual é `php artisan orcamentos:expirar`;
- os dados do laboratório que saem no PDF do orçamento (`LABORATORIO_NOME`, `LABORATORIO_ENDERECO`, `LABORATORIO_TELEFONE`,
  `LABORATORIO_EMAIL` e `LABORATORIO_CNPJ`).

## Publicação para demonstração (Render, gratuito)

Para disponibilizar um link de validação sem custo, o sistema roda no [Render](https://render.com) (plano gratuito),
a partir da imagem Docker deste repositório. Com `LAB_DEMONSTRACAO=true` os e-mails são simulados: vão para o log,
os links de senha aparecem na tela e um aviso no topo das páginas informa isso.

O banco é SQLite dentro do próprio serviço. O disco do plano gratuito não é permanente: a cada início do serviço o
banco é recriado com o usuário de acesso e o catálogo de exemplo, e o que foi cadastrado antes se perde. Como o
serviço "dorme" após 15 minutos sem acesso (e o primeiro acesso seguinte leva de 30 a 60 segundos), isso acontece
a cada pausa no uso.

1. No Render, entre com a conta do GitHub e escolha **New → Blueprint** e o repositório `mvpii`. O arquivo
   `render.yaml` já descreve o serviço; o Render pede dois valores: `ADMIN_EMAIL` e `ADMIN_SENHA`, o usuário que
   será entregue ao professor (senha com no mínimo 8 caracteres, letras e números).
2. Aguarde o primeiro deploy (alguns minutos) e use o endereço `https://<nome>.onrender.com` mostrado no painel.
3. A cada merge no `main` o Render publica a nova versão.

Para manter os dados entre reinícios, basta um PostgreSQL externo (por exemplo, o plano gratuito do Neon): no painel
do Render, troque `DB_CONNECTION` para `pgsql` e informe o endereço de conexão em `DB_URL`. A suíte de testes também
roda no PostgreSQL no CI.

Arquivos envolvidos: `Dockerfile`, `docker/iniciar.sh` (prepara o banco com `php artisan sistema:preparar` e sobe o
servidor) e `render.yaml`.

## Andamento do Entregável 1

- [x] Estrutura do projeto
- [x] MOD01 – Login e recuperação de senha (RF01, RF02)
- [x] MOD01 – Cadastro de usuários (RF03)
- [x] MOD02 – Clientes (RF04–RF08)
- [x] MOD03 – Pontos de coleta (RF09–RF11)
- [x] MOD04 – Catálogo técnico (RF12–RF16)
- [x] MOD05 – Orçamentos (RF17–RF22)
- [x] MOD06 – Consulta e histórico (RF23–RF24)
