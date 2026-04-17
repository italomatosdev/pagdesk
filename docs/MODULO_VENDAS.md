# Módulo de Vendas (PagDesk)

Este documento descreve o **módulo de vendas** do PagDesk: cadastro de produtos, registro de vendas, formas de pagamento (à vista e crediário), integração com caixa e com o módulo de empréstimos.

---

## Visão geral

O módulo permite:

- **Produtos:** cadastro por empresa e por operação, com estoque, preço de venda, fotos e anexos.
- **Vendas:** registro de vendas com cliente, operação, itens (produtos ou descrição livre), múltiplas formas de pagamento.
- **Formas de pagamento:** Dinheiro, PIX, Cartão (geram entrada no caixa da operação) e **Crediário** (gera empréstimo e parcelas automaticamente).
- **Estoque:** validação na venda e baixa automática após a conclusão.
- **Auditoria:** criação de venda e de empréstimo crediário registradas em logs de auditoria.

**Quem acessa:** apenas **Administrador** e **Gestor** (menu Vendas e Produtos no sidebar).

---

## Rotas e telas

| Rota | Descrição |
|------|------------|
| `GET /produtos` | Listagem de produtos (filtros: operação, busca, status, estoque). Totalizadores e aviso de produtos sem operação. |
| `GET /produtos/create` | Formulário de novo produto (operacao_id obrigatório). |
| `POST /produtos` | Cadastra produto. |
| `GET /produtos/{id}` | Detalhes do produto (dados + fotos/anexos). |
| `GET /produtos/{id}/edit` | Edição do produto (dados + gestão de anexos). |
| `GET /produtos/{id}/custos` | Histórico de preço de custo (apenas administrador/gestor). |
| `PUT/PATCH/POST /produtos/{id}` | Atualiza produto. |
| `DELETE /produtos/{id}/anexos/{anexoId}` | Remove anexo do produto. |
| `GET /vendas` | Listagem de vendas (filtros, totalizadores). |
| `GET /vendas/create` | Nova venda: cliente (Select2), operação, itens (produto ou descrição livre), formas de pagamento. |
| `POST /vendas` | Registra venda (VendaService). |
| `PATCH /vendas/{venda}/itens/{vendaItem}/custo` | Corrige custo unitário aplicado em um item (gestão; item deve ter produto). |
| `GET /vendas/{id}` | Detalhes da venda (itens, formas, totais, link para empréstimo crediário se houver). |
| `GET /vendas/{venda}/formas/{forma}/comprovante` | Download do comprovante de uma forma de pagamento. |

---

## Produtos

### Model e tabela

- **Model:** `App\Modules\Core\Models\Produto`
- **Tabela:** `produtos`
- **Campos principais:** `empresa_id`, `operacao_id`, `nome`, `codigo`, `preco_venda`, `custo_unitario_vigente` (espelho do último custo; nullable até o primeiro registro), `custo_vigente_atualizado_em`, `unidade`, `estoque`, `ativo`
- **Escopo:** `EmpresaScope` (filtro por empresa do usuário; Super Admin vê todos).
- **Regra:** todo produto deve ter **operacao_id** (obrigatório no cadastro/edição). Produtos sem operação não aparecem nas vendas; a listagem exibe aviso e filtro “Ver produtos sem operação”.

### Preço de custo e histórico

- **Quem vê e edita:** apenas **Administrador** e **Gestor** (`User::podeVerCustoProdutos()`). Consultores com acesso ao catálogo **não** veem valores de custo nem colunas de custo na listagem.
- **Cadastro:** no **novo produto**, o **preço de custo** é obrigatório; gera a primeira linha em `produto_custo_historicos` e preenche `custo_unitario_vigente` no produto (`ProdutoCustoService`).
- **Alteração:** na edição, informar “Novo preço de custo” cria nova linha no histórico (vigência fechada na anterior). Se o produto ainda não tinha custo, o campo é obrigatório até preencher.
- **Tabela** `produto_custo_historicos`: `produto_id`, `custo_unitario`, `valido_de`, `valido_ate`, `user_id`, `observacao`.
- **Valor zero:** custo **0,00** é permitido desde que exista registro explícito (diferente de “nunca informado”, em que `custo_unitario_vigente` permanece `null`).
- **Listagem (gestão):** totalizador e filtro “Sem custo cadastrado”; coluna “Preço custo”.

### Fotos e anexos

- **Tabela:** `produto_anexos` (`produto_id`, `nome_arquivo`, `caminho`, `tipo` [imagem/documento], `ordem`, `tamanho`).
- **Model:** `App\Modules\Core\Models\ProdutoAnexo`
- Arquivos em `storage/app/public/produtos/{id}/`. Fotos e documentos gerenciados na tela de edição do produto.

### Listagem e filtros

- Filtros: operação, busca (nome/código), status (ativo/inativo), estoque (com/sem), custo com/sem (só gestão).
- Totalizadores: total de produtos, ativos, inativos, sem estoque, unidades em estoque, valor do estoque, sem custo cadastrado (só gestão).
- Coluna “Status estoque”: Sem estoque / Baixo (&lt; 5) / Em estoque.

---

## Vendas

### Model e tabelas relacionadas

- **Venda:** `App\Modules\Core\Models\Venda` (tabela `vendas`).  
  Campos: `cliente_id`, `operacao_id`, `user_id`, `empresa_id`, `data_venda`, `status`, `valor_total_bruto`, `valor_desconto`, `valor_total_final`, `observacoes`.
- **VendaItem:** `App\Modules\Core\Models\VendaItem` (tabela `venda_itens`).  
  Cada item: `venda_id`, `produto_id` (nullable), `descricao` (nullable), `quantidade`, `preco_unitario_vista`, `preco_unitario_crediario`, `subtotal_vista`, `subtotal_crediario`, `custo_unitario_aplicado`, `custo_total_aplicado` (snapshot do custo vigente do produto no momento da venda; itens só com descrição livre ficam sem custo).
- **FormaPagamentoVenda:** `App\Modules\Core\Models\FormaPagamentoVenda` (tabela `forma_pagamento_venda`).  
  Campos: `venda_id`, `forma` (vista, pix, cartao, crediario), `valor`, `descricao`, `comprovante_path`, `numero_parcelas` (para crediário), `emprestimo_id` (quando forma = crediário).

### Fluxo de registro (VendaService)

1. **Validações:** operação e cliente existentes; pelo menos um item e uma forma de pagamento.
2. **Estoque:** soma da quantidade por produto nos itens; verifica se cada produto tem estoque suficiente; caso contrário, lança `ValidationException`.
3. **Custo:** para cada item com `produto_id`, o produto deve ter **`custo_unitario_vigente` definido** (não nulo); caso contrário, lança `ValidationException` na chave `itens` (venda não é registrada). Itens apenas com descrição livre não exigem custo.
4. **Totais:** `valor_total_final` = soma dos valores das formas de pagamento; `valor_total_bruto` = soma dos subtotais à vista dos itens.
5. **Criação da venda** e auditoria `criar_venda`.
6. **Itens:** criação dos `VendaItem` com **snapshot** de custo (`custo_unitario_aplicado` = custo vigente do produto, `custo_total_aplicado` = quantidade × custo) e **baixa de estoque** (decremento em `produtos.estoque`) por produto.
7. **Formas de pagamento:**
   - **Dinheiro, PIX, Cartão:** criação de entrada no caixa da operação (`CashService::registrarMovimentacao`), com descrição “Venda #X - [forma]” e opcional comprovante.
   - **Crediário:** vínculo cliente-operação (`EmprestimoService::garantirVinculoClienteOperacao`); criação de `Emprestimo` (tipo `crediario`, status ativo, `venda_id` preenchido); geração de parcelas via `LoanStrategyFactory` (estratégia dinheiro); atualização de `FormaPagamentoVenda.emprestimo_id`; auditoria `criar_emprestimo_crediario`.

Tudo ocorre dentro de uma **transação** de banco; em caso de falha, nada é persistido.

### Formas de pagamento

| Forma   | Constante              | Rótulo   | Caixa        | Comprovante |
|--------|------------------------|----------|-------------|-------------|
| À vista| `FORMA_VISTA` (vista)  | Dinheiro | Entrada     | Sim         |
| PIX    | `FORMA_PIX`            | PIX      | Entrada     | Sim         |
| Cartão | `FORMA_CARTAO`         | Cartão   | Entrada     | Sim         |
| Parcelado | `FORMA_CREDIARIO`   | Crediário| Não (parcelas) | Não    |

Para crediário é obrigatório informar **número de parcelas**. O valor informado na forma é o valor total do crediário (empréstimo).

### Tela “Nova venda”

- **Cliente:** Select2 (rota `clientes.api.buscar`). É **obrigatório** selecionar a **operação** antes: sem `operacao_id` válido a busca não roda e o usuário é alertado. Com operação, só entram clientes **já vinculados a essa operação** (`operation_clients`); o rótulo prioriza a **ficha** e a busca inclui nome/telefone/e-mail da ficha. Pode-se pré-selecionar cliente via query string `?cliente_id=X`.
- **Operação:** select; se o usuário tiver apenas uma operação, pode ser pré-selecionada.
- **Itens:** linhas com produto (select filtrado por operação e estoque &gt; 0) ou “Descrição livre”; quantidade; preço à vista e a crediário; subtotais. Produtos listados são filtrados pela operação escolhida (e por operação do produto). Opções com `data-sem-custo="1"` disparam aviso ao selecionar (produto sem custo cadastrado; venda será bloqueada até o gestor informar o custo).
- **Formas de pagamento:** tipo, valor, número de parcelas (crediário), descrição opcional, comprovante (arquivo) para Dinheiro/PIX/Cartão.
- **Total da venda:** soma das formas de pagamento (exibido na tela de detalhes como “Total da venda”).

### Detalhes da venda

- Dados da venda, cliente, operação, data.
- Itens com produto/descrição, quantidade, preços e subtotais.
- **Gestão:** coluna “Custo na venda” (unitário e total aplicados) e formulário para **corrigir** o custo unitário de itens com produto (ex.: vendas antigas sem snapshot ou retificação).
- Formas de pagamento com valor, descrição e link “Ver comprovante” quando houver `comprovante_path`.
- Card de totais: **Total da venda** (soma das formas), total bruto e desconto como referência.
- Se existir crediário: link para o empréstimo gerado.

---

## Integração com empréstimos

- Empréstimos gerados pela venda têm **tipo** `crediario` e **venda_id** preenchido.
- Na listagem e na tela de detalhes de empréstimos, o tipo “Crediário” é exibido (badge e ícone distintos).
- O filtro por tipo em empréstimos inclui a opção “Crediário”.
- Cobranças e pagamentos do crediário seguem o mesmo fluxo dos demais empréstimos.

---

## Integração com caixa

- Cada forma de pagamento **Dinheiro, PIX ou Cartão** gera uma **entrada** no caixa da operação da venda.
- Descrição: `Venda #<id> - <rótulo da forma>` e, se houver, texto da descrição informada.
- Opcional: `comprovante_path` repassado para a movimentação de caixa quando o usuário envia comprovante na forma de pagamento.

---

## Auditoria

- **criar_venda:** ao registrar a venda (modelo Venda, new_values).
- **criar_emprestimo_crediario:** ao criar o empréstimo do tipo crediário (modelo Emprestimo, new_values).
- **criar_produto** / **atualizar_produto:** no `ProdutoController` (store/update).

---

## Arquivos principais do módulo

| Caminho | Descrição |
|---------|-----------|
| `app/Modules/Core/Models/Produto.php` | Model produto (operacao_id, estoque, anexos). |
| `app/Modules/Core/Models/ProdutoAnexo.php` | Anexos/fotos do produto. |
| `app/Modules/Core/Models/Venda.php` | Model venda (itens, formas, emprestimoCrediario). |
| `app/Modules/Core/Models/VendaItem.php` | Item da venda (produto ou descrição, preços, subtotais). |
| `app/Modules/Core/Models/FormaPagamentoVenda.php` | Forma de pagamento (vista, pix, cartao, crediario). |
| `app/Modules/Core/Services/VendaService.php` | Registro de venda: estoque, itens, formas, caixa, crediário. |
| `app/Modules/Core/Controllers/ProdutoController.php` | CRUD produtos, anexos, filtro por operação. |
| `app/Modules/Core/Controllers/VendaController.php` | Listagem, create, store, show, download comprovante. |
| `resources/views/produtos/*` | Views de produtos (index, create, edit, show). |
| `resources/views/vendas/*` | Views de vendas (index, create, show). |

---

## Migrations relevantes

- `create_produtos_table`, `add_operacao_id_to_produtos_table`, `add_estoque_to_produtos_table`
- `create_produto_anexos_table`
- `create_vendas_table`, `create_venda_itens_table`, `create_forma_pagamento_venda_table`
- `add_venda_id_and_crediario_to_emprestimos`
- `add_descricao_comprovante_to_forma_pagamento_venda`

---

## Observações

- **Cancelamento de venda:** não implementado; cancelar manualmente exigiria repor estoque e tratar o empréstimo crediário à parte.
- **Estoque baixo:** o limite “baixo” na listagem de produtos está fixo em 5 unidades; pode ser tornado configurável no futuro.
- **Comprovantes:** armazenados em `storage/app/public/comprovantes_venda/`; o link público usa a rota de download com autorização.
