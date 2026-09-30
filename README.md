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

## Testes e verificação

```bash
php artisan test     # testes automatizados
vendor/bin/pint      # padroniza a formatação do código
npm run build        # build de produção do CSS/JS
```

## Estrutura

```
app/
  Http/Controllers/    Telas: login, recuperação de senha, usuários, clientes
  Http/Middleware/     UsuarioAtivo (RN10)
  Http/Requests/       Validação dos formulários
  Models/              Usuario, TokenSenha, RegistroAuditoria, Cliente, Contato
  Services/            Auditoria (RN08), LinksSenha (RF02), CadastroClientes (UC03)
  Support/             RegrasAcesso (RN09), Documento (CPF/CNPJ – RN01)
database/migrations/   Esquema do banco
resources/views/       Telas (Blade)
routes/web.php         Rotas do sistema
tests/                 Testes (Feature e Unit)
docs/                  Documentação técnica
```

## Produção (final do projeto)

Ajustar o `.env`: `APP_ENV=production`, `APP_DEBUG=false`, banco MySQL/PostgreSQL em `DB_*`, `MAIL_MAILER=smtp`
com os dados do servidor de e-mail real, HTTPS e cópia de segurança do banco. Depois: `composer install --no-dev`,
`npm run build` e `php artisan migrate --force`.

## Andamento do Entregável 1

- [x] Estrutura do projeto
- [x] MOD01 – Login e recuperação de senha (RF01, RF02)
- [x] MOD01 – Cadastro de usuários (RF03)
- [x] MOD02 – Clientes (RF04–RF08)
- [ ] MOD03 – Pontos de coleta (RF09–RF11)
- [ ] MOD04 – Catálogo técnico (RF12–RF16)
- [ ] MOD05 – Orçamentos (RF17–RF22)
- [ ] MOD06 – Consulta e histórico (RF23–RF24)
