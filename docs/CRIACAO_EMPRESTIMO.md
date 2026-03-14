# Criação de empréstimo (normal e retroativo)

Este documento descreve o fluxo de **criação de empréstimo** no sistema: quem pode criar, como funciona a escolha do **consultor responsável** e a opção **"Nome (Você)"** ao final da lista.

---

## 1. Quem pode criar

| Papel            | Empréstimo normal | Empréstimo retroativo |
|------------------|-------------------|------------------------|
| **Gestor**       | Sim (vinculado a um consultor) | Sim (vinculado a um consultor) |
| **Administrador**| Sim (vinculado a um consultor) | Sim (vinculado a um consultor) |
| **Consultor**    | Sim (vinculado a si mesmo)     | Sim (vinculado a si mesmo; aguarda aceite) |

Super Admin **não** pode criar empréstimos.

---

## 2. Consultor responsável

Todo empréstimo tem um **consultor responsável** (`consultor_id`): é o usuário ao qual o empréstimo fica vinculado (liberação de dinheiro, caixa, parcelas, listagens).

### 2.1 Quem escolhe

- **Gestor e administrador:** sempre escolhem o consultor responsável em um **select** na tela de novo empréstimo (tanto para empréstimo **normal** quanto **retroativo**).
- **Consultor:** não vê o select; o responsável é sempre o próprio usuário.

### 2.2 Conteúdo do select

O select **"Consultor responsável"** (visível apenas para gestor e administrador) contém:

1. **Consultores da operação** – usuários com papel *consultor* vinculados à operação selecionada, em ordem alfabética.
2. **"Nome (Você)"** – sempre **ao final da lista**: opção que representa o próprio usuário logado (gestor ou administrador). Permite criar o empréstimo vinculado a si mesmo.

Assim, gestor e administrador podem criar empréstimo para outro consultor ou para si mesmos, de forma explícita.

### 2.3 Validação

- O consultor escolhido deve ter **acesso à operação** selecionada (incluindo quando é "Você").
- Empréstimos já criados pelo admin no passado (com `consultor_id` = admin) continuam válidos; nada quebra.

---

## 3. Empréstimo normal

- **Gestor:** obrigatório escolher consultor no select (pode ser "Nome (Você)").
- **Administrador:** obrigatório escolher consultor no select (pode ser "Nome (Você)").
- **Consultor:** empréstimo fica vinculado a ele; segue fluxo de aprovação e liberação.

Após aprovação, é criada liberação de dinheiro (quando a operação exige). O gestor/administrador libera; o valor sai do caixa de quem libera e entra no caixa do **consultor responsável** do empréstimo.

---

## 4. Empréstimo retroativo

- **Gestor / administrador:** escolhem o consultor no select (podem ser "Nome (Você)"). Empréstimo criado já **ativo**; não há liberação de dinheiro na criação (retroativo = dinheiro já foi dado no passado). Parcelas pagas podem ser registradas na tela do empréstimo, com opção de gerar ou não caixa.
- **Consultor:** empréstimo vinculado a ele; criado em **aguardando aceite**. Após aprovação em **Liberações** → Empréstimos retroativos, passa a ativo.

Detalhes em **[EMPRESTIMO_RETROATIVO.md](EMPRESTIMO_RETROATIVO.md)**.

---

## 5. Resumo do fluxo por papel

```
GESTOR
  → Sempre vê o select "Consultor responsável" (normal e retroativo).
  → Lista: consultores da operação + "Nome (Você)" ao final.
  → Obrigatório escolher; pode ser ele mesmo.

ADMINISTRADOR
  → Sempre vê o select "Consultor responsável" (normal e retroativo).
  → Lista: consultores da operação + "Nome (Você)" ao final.
  → Obrigatório escolher; pode ser ele mesmo.

CONSULTOR
  → Não vê o select; o responsável é sempre ele.
  → Normal: fluxo de aprovação e liberação.
  → Retroativo: empréstimo fica aguardando aceite de gestor/admin.
```

---

## 6. Referência técnica (resumida)

- **Controller:** `App\Modules\Loans\Controllers\EmprestimoController` – `create()` monta a lista por operação e adiciona "Nome (Você)" ao final para gestor/admin; `store()` exige e valida `consultor_id` para gestor e administrador (normal e retroativo).
- **View:** `resources/views/emprestimos/create.blade.php` – bloco do consultor visível quando `ehGestorOuAdmin`; select preenchido via `consultoresPorOperacao` (JSON), que já inclui a entrada "(Você)" ao final.
- **Operações:** gestor vê apenas operações às quais tem acesso; administrador vê todas. A lista de consultores (e "Você") é por operação selecionada.

---

*Documento referente à criação de empréstimo e à escolha do consultor responsável (incluindo opção "Nome (Você)" ao final da lista).*
