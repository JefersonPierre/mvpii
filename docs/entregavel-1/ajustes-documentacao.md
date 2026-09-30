# Ajustes na documentação técnica do Entregável 1

A versão 0.6 da documentação foi escrita para a stack inicial (NestJS + React + Docker). O projeto passou a usar
**Laravel**, e os ajustes abaixo **já foram aplicados na versão 0.7**
(`Documentacao_Tecnica_E1_Cadastros_Orcamentos_v0.7.docx`), incluindo as figuras 1 (diagrama de contexto) e 9.1 a
9.3 (diagramas de sequência), redesenhadas com os novos participantes. Os requisitos funcionais, as regras de negócio,
os casos de uso e os protótipos não mudaram. Este arquivo fica como registro do que mudou e por quê.

## 1. Seção 3 – Ambiente de desenvolvimento e execução

| Trecho atual | Passa a ser |
| --- | --- |
| Containers Docker orquestrados pelo Docker Compose | Aplicação Laravel executada com PHP 8.4 (no Windows, pelo Laravel Herd) |
| Pré-requisitos: Git e Docker | Pré-requisitos: Git, PHP 8.3+ com Composer e Node.js 22 |
| Execução: `docker compose up` | Execução: `composer install`, `npm install`, `php artisan migrate --seed`, `php artisan serve` e `npm run dev` |
| E-mail simulado pelo Mailpit | No ambiente local os e-mails vão para o arquivo de log (`MAIL_MAILER=log`) e a tela mostra o link que iria no e-mail; em produção, SMTP real configurado no `.env` |
| Banco PostgreSQL | SQLite no desenvolvimento (arquivo, sem instalação); MySQL ou PostgreSQL em produção, só trocando o `.env` |
| Dados iniciais: tipos de amostra, parâmetros e limites da Portaria 888/2021 e da CONAMA 430/2011 | Mantido: `CatalogoExemploSeeder` (roda com `php artisan db:seed` no ambiente local; preços fictícios) |

## 2. Tabela de tecnologias

| Camada | Antes | Agora |
| --- | --- | --- |
| Linguagem | TypeScript | PHP 8.4 (servidor) e JavaScript (interações na tela) |
| Interface | React + Vite + Mantine | Laravel Blade + Tailwind CSS (Vite) |
| Servidor | Node.js + NestJS (API REST) | Laravel 13 (aplicação web MVC, sem API separada) |
| Banco e acesso a dados | PostgreSQL + Prisma | SQLite/MySQL/PostgreSQL + Eloquent (migrações versionadas) |
| Autenticação | JWT + bcrypt | Sessão do Laravel + bcrypt (sessão expira em 30 minutos) |
| E-mail | Nodemailer + Mailpit | Laravel Mail (log local, SMTP em produção) |
| Tarefas agendadas | @nestjs/schedule | Agendador do Laravel (`schedule:run` no cron) – expiração de orçamentos e cópia do banco |
| PDF | – | dompdf (barryvdh/laravel-dompdf) |
| Documentação da API | Swagger (OpenAPI) | Não se aplica (não há API separada) |
| Testes | Jest e Vitest | PHPUnit (testes unitários e funcionais) |
| Execução | Docker + Docker Compose | Laravel Herd / `php artisan serve` no desenvolvimento; servidor PHP em produção |

## 3. Requisitos não funcionais

- **RNF10 (Manutenibilidade):** trocar "API documentada com Swagger" por "rotas e regras documentadas no código
  (comentários com RF/RN) e no README".
- **RNF11 (Portabilidade):** trocar "rodar em qualquer computador com Docker por meio de um único comando" por
  "rodar em qualquer computador com PHP, Composer e Node.js; a troca do ambiente local para produção é feita apenas
  por configuração (.env)".
- **RNF06 (Backup):** o script local agora existe: `php artisan banco:copiar` e `php artisan banco:restaurar`
  (retenção de 30 dias, cópia diária pelo agendador).

## 4. Diagramas de sequência (seção 9)

O participante **"API"** passa a ser **"Controlador (Laravel)"**, e as chamadas `POST /clientes`,
`POST /orcamentos` e `POST /orcamentos/{id}/envio` são envios de formulário para as mesmas rotas. O restante
(serviços, validações e gravação com auditoria) continua igual.

## 5. Plano de testes (seção 11) – onde cada caso está automatizado

| Caso | Requisito | Teste automatizado |
| --- | --- | --- |
| CT01 | RF01 | `LoginTest::test_ct01_entra_com_email_e_senha_corretos` |
| CT02 | RF01 | `LoginTest::test_ct02_bloqueia_apos_cinco_tentativas_mesmo_com_a_senha_certa` |
| CT03 | RF04 | `ClientesTest::test_ct03_cadastra_pessoa_juridica_e_exibe_a_ficha` |
| CT04 | RF04 | `ClientesTest::test_ct04_cpf_invalido_nao_grava` e `test_ct04_documento_duplicado_oferece_abrir_o_existente` |
| CT05 | RF05 | `ClientesTest::test_ct05_cadastro_rapido_de_interessado_sem_documento` |
| CT06 | RF09 | `PontosColetaTest::test_ct06_cadastra_ponto_e_lista_na_ficha` |
| CT07 | RF16 | `LegislacoesLimitesTest::test_ct07_cadastra_faixa_de_ph_para_agua_potavel` |
| CT08 | RF17 | `OrcamentosTest::test_ct08_calcula_o_total_e_grava_como_rascunho` e `CalculoOrcamentoTest` |
| CT09 | RF17 | `OrcamentosTest::test_ct09_rn14_desconto_acima_do_maximo_nao_grava` |
| CT10 | RF18 | `EnvioRespostaOrcamentoTest::test_ct10_envia_por_email_com_pdf_e_muda_para_enviado` |
| CT11 | RF19 | `EnvioRespostaOrcamentoTest::test_ct11_rn18_interessado_precisa_completar_o_cadastro_para_aprovar` |
| CT12 | RF19 | `EnvioRespostaOrcamentoTest::test_ct12_rn19_recusa_exige_motivo` |

Para rodar todos: `php artisan test` (99 testes).

## 6. Pontos para validar com a gerente

- Os limites de exemplo (Portaria 888/2021 e CONAMA 430/2011) devem ser conferidos com o texto oficial antes do uso real.
- Os preços do catálogo de exemplo são fictícios.
- Decisões tomadas onde a documentação não detalha:
  - ao reativar um cliente, os pontos de coleta continuam inativos e são reativados um a um;
  - "Duplicar" usa os preços atuais do catálogo; "Nova revisão" mantém os preços do orçamento original;
  - a validade do orçamento conta a partir do envio;
  - o desconto é um percentual sobre itens + taxa de coleta (conforme o CT08).

## 7. Versão 0.8

A versão 0.8 (`Documentacao_Tecnica_E1_Cadastros_Orcamentos_v0.8.docx`) substitui a 0.7 e acrescenta:

- as decisões da seção 6 como regras de negócio: RN13 (desconto percentual sobre itens + taxa), RN20 (reativação
  de cliente), RN21 (duplicar × nova revisão) e RN22 (validade a partir do envio);
- no roteiro de aceite (11.3), a conferência dos limites oficiais e a troca dos preços fictícios;
- as seções 12 a 15, feitas a partir do código implementado: diagrama de componentes, diagrama de classes,
  diagrama entidade-relacionamento e dicionário de dados.

## 8. Versão 0.9

A versão 0.9 (`Documentacao_Tecnica_E1_Cadastros_Orcamentos_v0.9.docx`) parte da última versão editada pelo autor
(0.6, com os ajustes de texto dele) e substitui a 0.8. Mantém as remoções feitas pelo autor (RNF02, RNF05, RNF11 e as
figuras dos casos de uso) e acrescenta:

- Figura 1 atualizada e o serviço de consulta de CEP (ViaCEP, opcional) de volta às entidades externas e aos fluxos;
- seção 3: ambiente de desenvolvimento e execução (local e produção) e a tabela de tecnologias preenchida;
- RNF06 com os comandos de cópia e restauração do banco, RNF07 sem resolução mínima e RNF10 com o README;
- RN01 completa (dígitos verificadores e documento único), RN13 com o desconto percentual e RN20 a RN22, também
  citadas na tabela de processos e nos casos de uso UC03, UC08 e UC10;
- seção 9: os três diagramas de sequência (UC03, UC08 e UC09) com participantes, passos e fluxo alternativo;
- seção 11: estratégia, casos de teste CT01 a CT12 e roteiro do teste de aceitação;
- seções 12 a 15 (componentes, classes, entidade-relacionamento e dicionário de dados) e o sumário atualizado;
- remoção de uma página em branco antes da seção 8.
