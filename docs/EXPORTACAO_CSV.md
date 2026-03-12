# Exportação CSV (Excel)

As listagens de **Clientes** e **Empréstimos** podem ser exportadas em CSV. O arquivo abre diretamente no Excel (separador `;` e UTF-8 com BOM).

## Onde exportar

| Tela           | Botão           | Rota / Filtros |
|----------------|-----------------|----------------|
| Lista de Clientes   | **Exportar CSV** | `/clientes/export` – respeita filtros: Documento (CPF/CNPJ), Nome |
| Lista de Empréstimos| **Exportar CSV** | `/emprestimos/export` – respeita filtros: Operação, Status, Tipo, ID do Cliente |

## Colunas exportadas

### Clientes
- Documento, Nome, Tipo (PF/PJ), Telefone, Email, Cidade, Estado  
- **Super Admin:** coluna adicional **Empresa**

### Empréstimos
- ID, Cliente, Operação, Valor, Status, Tipo, Data início, Consultor

## Formato

- **Encoding:** UTF-8 (BOM) para acentuação correta no Excel  
- **Separador:** `;` (ponto e vírgula)  
- **Nome do arquivo:** `clientes_YYYY-MM-DD_HHmmss.csv` ou `emprestimos_YYYY-MM-DD_HHmmss.csv`

## Uso

1. Aplique os filtros desejados na listagem (documento, nome, operação, status etc.).  
2. Clique em **Exportar CSV**.  
3. O download usa os mesmos filtros da tela.

## Limite

A exportação traz **todos** os registros que passam nos filtros (sem paginação). Para listas muito grandes, considere filtrar por período, operação ou cliente para reduzir o volume.
