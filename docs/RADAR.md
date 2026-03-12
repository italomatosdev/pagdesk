# Radar — Consulta cadastral interna

O **Radar** é a consulta cadastral interna do PagDesk: uma tela dedicada para consultar um cliente por CPF ou CNPJ e visualizar empréstimos ativos, pendências atrasadas e resumo por operação. Funciona como um “Serasa/SPC” interno do sistema, usando os mesmos dados do modal de verificação de documento (cadastro de cliente), porém em uma página inteira.

---

## Visão geral

- **Objetivo:** permitir que consultores, gestores e administradores consultem rapidamente a situação de um cliente (pendências e empréstimos) antes de liberar crédito ou realizar uma venda.
- **Dados exibidos:** os mesmos da verificação por CPF/CNPJ no cadastro de cliente: busca global (todas as empresas), empréstimos ativos, parcelas atrasadas e detalhamento por operação/empresa.
- **Quem acessa:** **Administrador**, **Gestor** e **Consultor** (menu **Radar** no sidebar; Super Admin não usa essa tela, pois tem acesso às empresas diretamente).

---

## Rota e tela

| Rota | Descrição |
|------|------------|
| `GET /radar` | Formulário de consulta (campo CPF/CNPJ). Com parâmetro `?documento=`, exibe o resultado na mesma página. |

- **Nome da rota:** `radar.index`
- **Controller:** `App\Modules\Core\Controllers\RadarController`
- **View:** `resources/views/radar/index.blade.php`

---

## Fluxo de uso

1. Usuário acessa **Radar** no menu (ícone de radar).
2. Informa **CPF ou CNPJ** (com ou sem formatação) e clica em **Consultar**.
3. O sistema executa a mesma lógica da rota `clientes.buscar.cpf`: busca o cliente por documento em **todas as empresas** (consulta global).
4. O resultado é exibido na **própria tela** (não em modal):
   - **Cliente não encontrado:** mensagem informativa.
   - **Cliente encontrado:** ficha com dados do cliente, cards de totais (empréstimos ativos e pendências atrasadas), alertas e tabelas “Empréstimos por operação” e “Pendências por operação”.
5. Se o cliente existir, são exibidos links **Ver ficha** e **Editar** para a tela do cliente.

---

## Conteúdo da ficha (resultado)

- **Dados do cliente:** nome, documento (CPF/CNPJ formatado), ID, operações vinculadas (quando for cliente da própria empresa).
- **Cards de totais:**
  - **Empréstimos ativos:** quantidade e valor total.
  - **Pendências atrasadas:** valor total em parcelas vencidas.
- **Alertas:** ativo em outra empresa, ativo em outra operação, pendências em aberto ou “sem pendências”.
- **Empréstimos por operação:** para cada operação (e empresa, quando diferente), quantidade de empréstimos ativos e valor total.
- **Pendências por operação:** para cada operação (e empresa), valor em aberto e quantidade/valor de parcelas atrasadas.

Quando o CPF/CNPJ está cadastrado em **outra empresa** (consulta cruzada), é exibido um aviso e a ficha segue o mesmo formato, com dados agregados por empresa.

---

## Integração com a verificação de documento

O Radar **reutiliza** a lógica do modal de verificação de CPF/CNPJ do cadastro de cliente:

- O `RadarController` chama internamente `ClienteController::buscarPorCpf()` com o documento informado e utiliza a resposta JSON (existe, cliente, ficha, consulta_cruzada) para montar a view.
- Não há duplicação de regras de negócio: a busca global, o cálculo de empréstimos ativos e de parcelas pendentes/atrasadas são os mesmos usados em **Clientes > Novo > Verificar documento**.

---

## Arquivos principais

| Caminho | Descrição |
|---------|-----------|
| `app/Modules/Core/Controllers/RadarController.php` | Controller do Radar (index: formulário + resultado). |
| `resources/views/radar/index.blade.php` | View: formulário de consulta e exibição da ficha. |
| `routes/web.php` | Rota `GET /radar` nomeada `radar.index`. |
| `resources/views/layouts/sidebar.blade.php` | Item de menu **Radar** (após Clientes). |

A lógica de dados (busca por documento, empréstimos ativos, pendências) está em:

- `App\Modules\Core\Controllers\ClienteController::buscarPorCpf()`
- `App\Modules\Core\Services\ClienteConsultaService` (consulta cruzada)

---

## Observações

- **Super Admin:** o menu Radar não é exibido no sidebar para Super Admin; o foco da funcionalidade é o uso por consultor, gestor e administrador na operação do dia a dia.
- **Permissão:** apenas usuários com papel **administrador**, **gestor** ou **consultor** podem acessar `/radar`; caso contrário, retorna 403.
- **Privacidade:** a consulta é global (todas as empresas) para que o usuário veja histórico do cliente em outras empresas/operações, útil para decisão de crédito e para evitar duplicidade de cadastro.
