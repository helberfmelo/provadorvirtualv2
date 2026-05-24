# BigShop Model3 Pro - Widget Nativo

Status: Sprint 58. Este documento descreve a instalação do widget universal do Provador Virtual no front compartilhado `model3/stores/pro_store`.

## Arquivos estudados

- Front model3 pro: `D:\Projetos\bigshop\172.16.151.2\bigshop\model3\stores\pro_store\produto.vue`
- Backend/API BigShop: `D:\Projetos\bigshop\172.16.151.5\bigshop\api-v2\funcoes.php`
- Painel BigShop: `D:\Projetos\bigbangshop2.0\src\pages\configurations\additionalAppsEdit.vue`

Esses diretórios locais são cópias dos originais. Aplicar as mesmas alterações no repositório/ambiente oficial da BigShop quando for publicar.

## Regra de exibição

O front pro deve instalar o container do widget na página de produto, logo após a seleção de cor/tamanho e antes dos blocos de compra/tabela. O widget só renderiza os botões quando o endpoint `config-check` do Provador Virtual responde `configured=true`.

Comportamento obrigatório:

- produto com tabela no Provador Virtual: mostrar `Descubra seu tamanho` e `Tabela de Medidas` do Provador Virtual; esconder a tabela de medidas nativa da BigShop;
- produto sem tabela no Provador Virtual, mas com tabela BigShop: não mostrar botões do Provador Virtual; mostrar tabela BigShop;
- produto sem tabela no Provador Virtual e sem tabela BigShop: não mostrar botão de tabela;
- se o app estiver ausente, desativado ou o widget falhar, manter comportamento nativo BigShop.

## Configuração por loja

O front compartilhado não deve conter IDs internos fixos do Provador Virtual. A configuração vem de `bbs.template_model3_apps` via `$root.generalData.store.apps`.

Não colar o snippet estático diretamente no template compartilhado do modelo pro com IDs fixos. No modelo pro, o `produto.vue` cria o container e injeta o script dinamicamente para que cada loja use os dados do próprio backend.

Criar o app no catálogo global de apps da BigShop:

```sql
INSERT INTO bbs.apps (
  name,
  app_type,
  cod_1_name,
  cod_2_name,
  cod_3_name,
  cod_4_name,
  app_code,
  description
)
SELECT
  'Provador Virtual',
  'Addons',
  'URL do script',
  'Chave pública (opcional)',
  'Tema JSON (opcional)',
  'Ativo (S/N)',
  'provador_virtual',
  'Instala o widget universal do Provador Virtual na página de produto do modelo pro.'
WHERE NOT EXISTS (
  SELECT 1 FROM bbs.apps WHERE app_code = 'provador_virtual'
);
```

Observação operacional: o select `Configurações > Apps adicionais > Tipo` é alimentado pelo endpoint BigShop `/get_apps`, que consulta a tabela global `apps`. Se o registro `app_code='provador_virtual'` não existir em `apps`, o app não aparece no select mesmo que o front já tenha tratamento para ele.

Na cópia local estudada, o arquivo `D:\Projetos\bigshop\172.16.151.5\bigshop\sistema\context\get_apps.php` deve executar o `INSERT ... WHERE NOT EXISTS` acima antes do `select *, name as label, id as value from apps`. Se o ambiente local estiver apontando para outra cópia do backend, aplicar o SQL diretamente no banco usado pelo `localhost`.

Em `Configurações > Apps adicionais`, cada loja deve cadastrar:

- `Nome`: `Provador Virtual`;
- `Tipo`: `Provador Virtual`;
- `Cod 1`: `https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js`;
- `Cod 2`: vazio por enquanto, ou chave pública do widget se o SaaS passar a exigir;
- `Cod 3`: JSON de tema opcional;
- `Cod 4`: `S` para ativo, `N` para desativado.

Para cada loja que usa o front pro, informar ou conferir:

- `loja.id` BigShop, usado como `data-store-id`;
- domínio público da loja, com e sem `www` quando os dois responderem;
- se a integração no Provador Virtual será por XML/feed ou API;
- URL do feed XML, quando for XML;
- produtos com tabela de medidas vinculada no portal da empresa do Provador Virtual.

## Dados enviados pelo front

O `produto.vue` deve carregar o script dinamicamente com:

- `data-platform="bigshop"`;
- `data-store-id`: `loja.id` da BigShop, exemplo Luna Moda Festa `53`;
- `data-product-id`: ID do produto pai BigShop, equivalente ao `g:item_group_id` do feed;
- `data-variant-id`: ID da grade/variação BigShop, equivalente ao `g:id` do feed;
- `data-sku`: SKU/ref da grade quando existir; fallback para ID da grade.

O backend do Provador Virtual resolve a empresa pelo par `platform=bigshop` + `external_store_id=53`, então o front não precisa conhecer `merchant_id` nem `merchant_company_id` internos do SaaS.

## Requisitos no Provador Virtual

Para uma loja BigShop funcionar:

- a empresa no SaaS deve estar com `platform=bigshop`;
- `external_store_id` da empresa deve ser o `loja.id` da BigShop;
- domínios permitidos do widget devem incluir o domínio da loja com e sem `www`, quando aplicável;
- o feed XML deve estar salvo na integração, por exemplo `https://www.lunamodafesta.com.br/feed.xml`;
- os produtos devem estar sincronizados;
- cada produto que deve exibir o Provador Virtual precisa ter tabela de medidas vinculada no portal da empresa.

Para integração apenas via XML/feed, `URL da API` e `Token` BigShop podem ficar vazios. O `store_id`/loja BigShop ainda deve estar correto para o widget resolver a empresa.

## Depuração em produção

Para validar uma página BigShop sem expor mensagem para clientes comuns, acessar o produto com `?pvdebug=1` ou executar no console `localStorage.setItem('provadorVirtualDebug', '1')` e recarregar. O front pro exibe uma faixa de debug no ponto do widget e escreve logs com o prefixo `[Provador Virtual]`.

Checklist rápido no console da página:

```js
vue.generalData.store.apps
product.provadorVirtualApp
product.provadorVirtualEnabled
product.provadorVirtualPayload()
document.getElementById('provador-virtual-container')
document.getElementById('provadorVirtualScript')
```

Se `vue.generalData.store.apps` vier vazio, conferir em `bbs.template_model3_apps` se o app da loja está com `deleted_at is null`, `cod_4='S'`, `type` apontando para `bbs.apps.id` do `app_code='provador_virtual'` e se o cache da loja foi limpo.

Se o script carregar, mas o widget não renderizar, testar o endpoint público `POST /api/v1/public/recommendations/config-check`. O retorno `measurement_table_missing` indica que o produto resolvido pelo par `platform=bigshop`, `store_id` e `product_id` ainda está sem `measurement_table_id` válido no SaaS.

## Luna Moda Festa

Para a loja piloto Luna Moda Festa:

- `data-store-id` deve ser o `loja.id` BigShop `53`;
- o feed configurado no SaaS deve ser `https://www.lunamodafesta.com.br/feed.xml`;
- `URL da API` e `Token` não são obrigatórios para sincronização via XML;
- a empresa no SaaS deve ter `platform=bigshop` e `external_store_id=53`;
- os domínios permitidos do widget devem incluir `lunamodafesta.com.br` e `www.lunamodafesta.com.br`;
- após sincronizar o XML, conferir a página de Produtos no portal da empresa e vincular tabela de medidas nos produtos que devem exibir o Provador Virtual.

## Alterações locais feitas na Sprint 58

- `produto.vue`: container e loader dinâmico do widget no modelo pro; escuta do evento `provadorvirtual:config`; recarregamento por troca de grade; ocultação condicional da tabela BigShop.
- `api-v2/funcoes.php`: retorno de `store.apps` inclui `ref`, `type` e `cod_4`, além de ignorar registros excluídos.
- `additionalAppsEdit.vue`: ajuda e valores padrão quando o app selecionado é `provador_virtual`.
