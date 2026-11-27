# mvpii – Laboratório de Análise de Água

MVP do TCC: sistema para um laboratório de análise de qualidade da água (água potável e efluentes).
A documentação técnica do Entregável 1 (Cadastros e Orçamentos) está em [`docs/entregavel-1`](docs/entregavel-1).
O Entregável 2 (Coletas e Perfis de Usuário), documentado em [`docs/entregavel-2`](docs/entregavel-2), acrescenta os perfis de acesso, o agendamento e o registro das coletas e o
recebimento das amostras no laboratório.
O Entregável 3 (Conformidade e Coleta pelo Celular), documentado em [`docs/entregavel-3`](docs/entregavel-3), acrescenta o
aplicativo do coletor para celular (funciona sem internet), o roteiro das coletas em mapa, os resultados com a comparação
com a legislação, a revisão e a liberação, e o laudo em PDF com QR Code de verificação.

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

No ambiente local, o `db:seed` também cria um usuário de exemplo para cada perfil, com a mesma senha do administrador,
para ver o sistema como cada função do laboratório:

| Usuário | Perfil | O que vê |
| --- | --- | --- |
| `admin@laboratorio.local` | Administrador | Tudo, inclusive usuários e configurações |
| `recepcao@laboratorio.local` | Recepção | Clientes, orçamentos, agendamento de coletas e amostras |
| `coletor@laboratorio.local` | Coletor | Somente as próprias coletas: agenda, roteiro, ficha e registro da coleta (também pelo celular, em `/campo`) |
| `analista@laboratorio.local` | Analista | Recebimento das amostras, resultados, revisão e liberação, consulta de laudos |

Os perfis e a matriz de permissões estão em `app/Support/Perfis.php` (seção 7.4 da documentação do Entregável 2).
Ao atualizar um banco do Entregável 1 com `php artisan migrate`, os usuários já cadastrados recebem o perfil
Administrador (RN25); depois, o administrador ajusta o perfil de cada um em Usuários.

No ambiente local os e-mails **não saem de verdade** (`MAIL_MAILER=log`): o conteúdo vai para
`storage/logs/laravel.log` e a própria tela mostra o link que iria no e-mail (convite de novo usuário e recuperação de senha).

Para recomeçar o banco do zero: `php artisan migrate:fresh --seed`.

No ambiente local, o `db:seed` também cadastra um **catálogo de exemplo** (parâmetros, pacotes e limites da Portaria
GM/MS nº 888/2021 e da Resolução CONAMA nº 430/2011), um cliente com um orçamento em rascunho e outro aprovado, com uma
coleta realizada (amostras aguardando o recebimento), outra agendada e uma de dois dias atrás com resultados liberados e
laudo emitido, além dos dados do laboratório e do responsável técnico usados no laudo. Os preços são fictícios e os limites devem ser
conferidos com o texto oficial antes do uso real.

Cópia de segurança do banco local (RNF06): `php artisan banco:copiar` cria uma cópia em `storage/app/private/copias`
(mantidas por 30 dias) e `php artisan banco:restaurar` lista as cópias e restaura a escolhida.

### Aplicativo do coletor (celular)

Entre como coletor no celular e abra **Coletas do dia (celular)** no menu, ou o endereço `/campo`. O navegador oferece
"Instalar" / "Adicionar à tela inicial". Depois da primeira abertura com internet, a tela abre sem conexão: as coletas de
hoje e amanhã ficam no aparelho, e os registros feitos sem internet ficam como **Pendente de envio** e são enviados
sozinhos quando a conexão volta (RF38–RF41). O acesso do aparelho vale 12 horas (RN39) e pode ser revogado pelo
administrador em Usuários. Para instalar e usar a câmera e o GPS fora do `localhost`, o endereço precisa ser HTTPS
(a publicação no Render já é).

O mapa usa o OpenStreetMap (Leaflet) e a busca de coordenadas pelo endereço usa o Nominatim; para desligar a busca,
`GEOCODIFICACAO=false`. Os PDFs dos laudos ficam em `storage/app/private/laudos`: em produção, esse diretório precisa
estar em disco permanente e entrar na cópia de segurança.

## Testes e verificação

```bash
php artisan test     # testes automatizados
vendor/bin/pint      # padroniza a formatação do código
npm run build        # build de produção do CSS/JS
```

## Estrutura

```
app/
  Http/Controllers/    Telas: login, recuperação de senha, usuários, clientes, pontos de coleta, catálogo, orçamentos,
                       coletas (agenda, agendamento, registro, roteiro, conflitos), amostras (recebimento, consulta),
                       resultados, laudos (emissão, envio, retificação, verificação pública), aplicativo do coletor
  Http/Controllers/Api API do aplicativo do coletor (routes/api.php): coletas do aparelho e sincronização (RF39, RF41)
  Http/Middleware/     UsuarioAtivo (RN10), AutenticarDispositivo (token do aparelho – RN39)
  Policies/            ColetaPolicy e AmostraPolicy: o coletor vê e registra só as próprias coletas (RN36)
  Http/Requests/       Validação dos formulários
  Models/              Usuario, UsuarioPerfil, Cliente, Contato, PontoColeta, TipoAmostra, Parametro, Pacote, Legislacao,
                       Limite, Orcamento, OrcamentoItem, Coleta, Amostra…
  Services/            Auditoria (RN08), CadastroComHistorico, CadastroClientes (UC03), CadastroPontos (UC04),
                       CadastroLegislacoes (UC06), LinksSenha (RF02), AgendamentoColetas (UC14), AvisosColeta (RF30),
                       RegistroColetas (UC16), RecebimentoAmostras (UC17), Rastreabilidade (RNF12 e linha do tempo),
                       AcessoDispositivos (RN39), SincronizacaoColetas (UC22, UC23), Roteirizacao (UC20),
                       ResultadosAmostras (UC24, UC25), EmissaoLaudos (UC26, UC27)
  Rules/               NomeUnico (RN05), RegistroAtivo (RN04), ColetorAtivo (RN29)
  Support/             RegrasAcesso (RN09), Documento (CPF/CNPJ – RN01), Numero (formato brasileiro),
                       Perfis (matriz de permissões – RF26), CriteriosRecebimento (RN35), Conformidade (RN52),
                       Roteiro (ordem de visita e links – RN47, RN48), QrCode
resources/js/          campo.js (aplicativo do coletor, IndexedDB), mapa.js (Leaflet), resultados-form.js
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
e os links de senha aparecem na tela.

O banco é SQLite dentro do próprio serviço. O disco do plano gratuito não é permanente: a cada início do serviço o
banco é recriado com o usuário de acesso, o catálogo de exemplo, os usuários de exemplo de cada perfil (com a mesma
senha do usuário de acesso) e o cliente com os orçamentos e coletas de exemplo, e o que foi cadastrado antes se perde. Como o
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

## Andamento do Entregável 2

- [x] MOD07 – Perfis e permissões (RF25, RF26)
- [x] MOD08 – Agendamento, agenda e ficha de coleta (RF27–RF32)
- [x] MOD09 – Registro da coleta e recebimento das amostras (RF33–RF35)
- [x] MOD06 – Consulta de amostras e coletas na ficha e no orçamento (RF36, RF37)

## Andamento do Entregável 3

- [x] MOD10 – Coleta em campo pelo celular: PWA, sincronização, conflitos e medições de campo (RF38–RF43)
- [x] MOD11 – Roteirização: mapa, ordem de visita, Google Maps/Waze e coordenadas dos pontos (RF44–RF47)
- [x] MOD12 – Resultados, legislação de referência, conformidade, revisão e liberação (RF48–RF51)
- [x] MOD13 – Laudo em PDF, envio, retificação, verificação e dados do laboratório (RF52–RF56)
- [x] MOD06 – Consulta de laudos e não conformidades (RF57)
