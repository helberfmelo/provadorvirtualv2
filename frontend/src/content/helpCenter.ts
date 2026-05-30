export type HelpArticleGroup = 'operacao' | 'catalogo' | 'provador' | 'resultados' | 'conta' | 'plataformas'

export type HelpArticle = {
  key: string
  title: string
  group: HelpArticleGroup
  summary: string
  whenToUse: string
  steps: string[]
  primaryTo: string
  primaryLabel: string
  nextTo?: string
  nextLabel?: string
  relatedKeys?: string[]
  tags?: string[]
  routeMatchers?: Array<{
    path: string
    exact?: boolean
  }>
}

type SupportContext = {
  article?: HelpArticle | null
  routePath?: string
  companyName?: string | null
  companyCode?: string | null
  merchantName?: string | null
  platform?: string | null
  userName?: string | null
  userEmail?: string | null
}

export const supportBaseUrl = 'https://wa.me/5531993157573'

export const helpGroupLabels: Record<HelpArticleGroup, string> = {
  operacao: 'Operação',
  catalogo: 'Catálogo',
  provador: 'Provador',
  resultados: 'Resultados',
  conta: 'Conta',
  plataformas: 'Plataformas',
}

const platformLabels: Record<string, string> = {
  bigshop: 'BigShop',
  shopify: 'Shopify',
  woocommerce: 'WooCommerce',
  nuvemshop: 'Nuvemshop',
  vtex: 'VTEX',
  tray: 'Tray',
  loja_integrada: 'Loja Integrada',
  magento: 'Magento',
  opencart: 'OpenCart',
  xml_feed: 'XML/feed',
  api: 'API',
  custom: 'Personalizada',
}

export const helpArticles: HelpArticle[] = [
  {
    key: 'painel',
    title: 'Painel da loja',
    group: 'operacao',
    summary: 'Use o painel para entender o que falta antes de publicar o provador.',
    whenToUse: 'Quando você quer priorizar o próximo ajuste sem abrir tela por tela.',
    steps: [
      'Confira a cobertura do catálogo e as próximas ações.',
      'Abra o bloco com maior pendência primeiro.',
      'Revalide a publicação depois de ajustar produtos, tabelas ou integração.',
    ],
    primaryTo: '/app',
    primaryLabel: 'Abrir painel',
    nextTo: '/app/produtos',
    nextLabel: 'Ir para produtos',
    relatedKeys: ['produtos', 'publicacao', 'relatorios'],
    tags: ['visão geral', 'prioridades', 'cobertura', 'próximas ações'],
    routeMatchers: [{ path: '/app', exact: true }],
  },
  {
    key: 'produtos',
    title: 'Produtos',
    group: 'catalogo',
    summary: 'Revise tabela, categoria, modelagem, status e pendências sem depender de ajuste manual produto por produto.',
    whenToUse: 'Quando o catálogo ainda tem itens sem tabela, sem categoria, com erro de sincronização ou prontos para vínculo em lote.',
    steps: [
      'Filtre o grupo com maior pendência.',
      'Aplique vínculo em lote quando vários produtos compartilham a mesma tabela.',
      'Abra o detalhe apenas para diagnóstico fino, mídia ou histórico.',
    ],
    primaryTo: '/app/produtos',
    primaryLabel: 'Abrir produtos',
    nextTo: '/app/tabelas-de-medidas',
    nextLabel: 'Revisar tabelas',
    relatedKeys: ['tabelas', 'categorias', 'marcas', 'taxonomia'],
    tags: ['catálogo', 'status', 'tabela', 'categoria', 'modelagem'],
    routeMatchers: [{ path: '/app/produtos' }],
  },
  {
    key: 'tabelas',
    title: 'Tabelas de medidas',
    group: 'catalogo',
    summary: 'Tabelas boas deixam claro se a base é do corpo, da peça ou mista, com sistema de tamanho e ranges consistentes.',
    whenToUse: 'Quando você precisa criar, revisar, importar ou publicar uma tabela antes de usá-la nos produtos.',
    steps: [
      'Defina base, sistema de tamanho e medidas principais.',
      'Revise ranges, observações e variações personalizadas.',
      'Vincule a tabela aos produtos corretos antes de publicar o provador.',
    ],
    primaryTo: '/app/tabelas-de-medidas',
    primaryLabel: 'Abrir tabelas',
    nextTo: '/app/produtos',
    nextLabel: 'Vincular produtos',
    relatedKeys: ['assistente', 'modelagens', 'produtos'],
    tags: ['medidas', 'ranges', 'sistema de tamanho', 'importação'],
    routeMatchers: [{ path: '/app/tabelas-de-medidas' }],
  },
  {
    key: 'modelagens',
    title: 'Modelagens',
    group: 'catalogo',
    summary: 'Modelagens organizam caimento e ajudam a corrigir importações com diagnóstico mais claro.',
    whenToUse: 'Quando vários produtos compartilham o mesmo caimento e você precisa manter esse padrão em tabelas, filtros e regras.',
    steps: [
      'Padronize nome, código e intensidade do caimento.',
      'Use a mesma modelagem em produtos e tabelas compatíveis.',
      'Revise duplicidades antes de novas importações.',
    ],
    primaryTo: '/app/modelagens',
    primaryLabel: 'Abrir modelagens',
    nextTo: '/app/regras-de-importacao',
    nextLabel: 'Ajustar regras',
    relatedKeys: ['produtos', 'tabelas', 'regras'],
    tags: ['caimento', 'fit', 'diagnóstico', 'catálogo'],
    routeMatchers: [{ path: '/app/modelagens' }],
  },
  {
    key: 'categorias',
    title: 'Categorias',
    group: 'catalogo',
    summary: 'Normalize categorias locais para manter filtros, regras e relatórios falando a mesma língua.',
    whenToUse: 'Quando a loja traz categorias duplicadas, vagas ou fora do padrão operacional do portal.',
    steps: [
      'Revise duplicidades e grafias diferentes.',
      'Aplique uma categoria normalizada com impacto seguro.',
      'Use a categoria revisada em regras, produtos e relatórios.',
    ],
    primaryTo: '/app/categorias',
    primaryLabel: 'Abrir categorias',
    nextTo: '/app/taxonomia',
    nextLabel: 'Revisar taxonomia',
    relatedKeys: ['marcas', 'taxonomia', 'produtos'],
    tags: ['normalização', 'catálogo', 'filtros', 'regras'],
    routeMatchers: [{ path: '/app/categorias' }],
  },
  {
    key: 'marcas',
    title: 'Marcas',
    group: 'catalogo',
    summary: 'Revise duplicidades e consolide marcas para melhorar leitura do catálogo, regras e relatórios.',
    whenToUse: 'Quando a plataforma envia variações do mesmo nome de marca ou quando você quer padronizar filtros comerciais.',
    steps: [
      'Busque marcas parecidas ou duplicadas.',
      'Aprove a versão normalizada que deve prevalecer.',
      'Confirme o impacto antes de aplicar em massa.',
    ],
    primaryTo: '/app/marcas',
    primaryLabel: 'Abrir marcas',
    nextTo: '/app/produtos',
    nextLabel: 'Ver produtos',
    relatedKeys: ['categorias', 'taxonomia', 'relatorios'],
    tags: ['marca', 'normalização', 'catálogo', 'relatórios'],
    routeMatchers: [{ path: '/app/marcas' }],
  },
  {
    key: 'taxonomia',
    title: 'Taxonomia IA',
    group: 'catalogo',
    summary: 'Aprove sugestões com contexto de categoria, marca, gênero, faixa etária, modelagem e sistema de tamanho.',
    whenToUse: 'Quando o catálogo está heterogêneo e você quer revisar sugestões antes de padronizar dados em massa.',
    steps: [
      'Filtre as sugestões com maior confiança e maior impacto.',
      'Aprove primeiro o que reduz conflito nas próximas importações.',
      'Volte ao catálogo para conferir o resultado aplicado.',
    ],
    primaryTo: '/app/taxonomia',
    primaryLabel: 'Abrir taxonomia',
    nextTo: '/app/regras-de-importacao',
    nextLabel: 'Ver regras',
    relatedKeys: ['categorias', 'marcas', 'regras'],
    tags: ['IA', 'sugestões', 'padronização', 'aprendizado'],
    routeMatchers: [{ path: '/app/taxonomia' }],
  },
  {
    key: 'importacoes',
    title: 'Importações',
    group: 'operacao',
    summary: 'A importação deve começar por prévia e validação, nunca por gravação às cegas.',
    whenToUse: 'Quando você vai subir CSV, planilha, feed ou pacote de dados para atualizar o catálogo.',
    steps: [
      'Faça a prévia antes de qualquer commit.',
      'Corrija campos ausentes, linhas inválidas e conflitos de mapeamento.',
      'Só grave depois de revisar o impacto no catálogo.',
    ],
    primaryTo: '/app/importacoes',
    primaryLabel: 'Abrir importações',
    nextTo: '/app/sincronizacao',
    nextLabel: 'Ver sincronizações',
    relatedKeys: ['regras', 'sincronizacao', 'produtos'],
    tags: ['prévia', 'csv', 'planilha', 'feed', 'validação'],
    routeMatchers: [{ path: '/app/importacoes' }],
  },
  {
    key: 'regras',
    title: 'Regras de importação',
    group: 'operacao',
    summary: 'Regras reduzem retrabalho ao normalizar categoria, marca, gênero, status e modelagem ainda na entrada dos dados.',
    whenToUse: 'Quando a plataforma envia dados inconsistentes e você quer corrigir isso antes de cada importação ou sincronização.',
    steps: [
      'Revise a regra atual e simule o impacto antes de salvar.',
      'Atenção a conflitos críticos e regras amplas demais.',
      'Rode nova prévia de importação depois do ajuste.',
    ],
    primaryTo: '/app/regras-de-importacao',
    primaryLabel: 'Abrir regras',
    nextTo: '/app/importacoes',
    nextLabel: 'Testar importação',
    relatedKeys: ['importacoes', 'sincronizacao', 'taxonomia'],
    tags: ['fallback', 'simulação', 'normalização', 'impacto'],
    routeMatchers: [{ path: '/app/regras-de-importacao' }],
  },
  {
    key: 'provador',
    title: 'Instalação do provador',
    group: 'provador',
    summary: 'Publique somente depois de conferir domínios, posição na página, botões e prévia desktop/mobile.',
    whenToUse: 'Quando você está configurando o provador visível ao cliente final na página de produto.',
    steps: [
      'Salve rascunho antes de publicar.',
      'Confira domínio, seletor, botões e modo de apresentação.',
      'Valide a instalação na página de produto antes do go-live.',
    ],
    primaryTo: '/app/widget',
    primaryLabel: 'Abrir instalação',
    nextTo: '/app/go-live',
    nextLabel: 'Revisar publicação',
    relatedKeys: ['integracoes', 'publicacao', 'bigshop'],
    tags: ['widget', 'botões', 'domínios', 'posição', 'prévia'],
    routeMatchers: [{ path: '/app/widget' }],
  },
  {
    key: 'integracoes',
    title: 'Integrações',
    group: 'provador',
    summary: 'Separe plataforma da loja, origem do catálogo, instalação na página de produto e rastreamento.',
    whenToUse: 'Quando você precisa configurar feed, API, webhook, validação de instalação ou trocar a plataforma operacional da empresa.',
    steps: [
      'Confirme a plataforma da loja e o fluxo técnico usado.',
      'Preencha só os campos exigidos para essa integração.',
      'Use a validação da página de produto antes de publicar.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/sincronizacao',
    nextLabel: 'Ver histórico',
    relatedKeys: ['provador', 'sincronizacao', 'bigshop', 'shopify', 'api'],
    tags: ['plataforma', 'feed', 'api', 'webhook', 'validação'],
    routeMatchers: [{ path: '/app/integracoes' }],
  },
  {
    key: 'sincronizacao',
    title: 'Sincronização',
    group: 'operacao',
    summary: 'Use contadores e erros por execução para corrigir a origem antes de importar de novo.',
    whenToUse: 'Quando uma sync falhou, importou parcialmente ou trouxe produtos com conflito recorrente.',
    steps: [
      'Abra a execução com maior impacto.',
      'Identifique a causa raiz por produto, grid, SKU ou regra.',
      'Só reprocese depois de corrigir o problema na origem ou nas regras.',
    ],
    primaryTo: '/app/sincronizacao',
    primaryLabel: 'Abrir sincronização',
    nextTo: '/app/regras-de-importacao',
    nextLabel: 'Corrigir regras',
    relatedKeys: ['integracoes', 'importacoes', 'regras'],
    tags: ['histórico', 'erros', 'reprocessar', 'catálogo'],
    routeMatchers: [{ path: '/app/sincronizacao' }],
  },
  {
    key: 'relatorios',
    title: 'Relatórios',
    group: 'resultados',
    summary: 'Acompanhe uso do provador, recomendações e sinais comerciais para priorizar revisão do catálogo.',
    whenToUse: 'Quando você quer entender onde o provador ajuda, onde há erro e quais produtos pedem revisão primeiro.',
    steps: [
      'Comece pelos cards principais e pelo funil.',
      'Abra ranking e recomendações emitidas para achar produtos críticos.',
      'Cruze com pedidos e devoluções antes de mexer nas tabelas.',
    ],
    primaryTo: '/app/analytics',
    primaryLabel: 'Abrir relatórios',
    nextTo: '/app/pedidos',
    nextLabel: 'Abrir pedidos',
    relatedKeys: ['pedidos', 'devolucoes', 'assistente'],
    tags: ['analytics', 'uso do provador', 'ranking', 'recomendações'],
    routeMatchers: [{ path: '/app/analytics' }],
  },
  {
    key: 'pedidos',
    title: 'Pedidos',
    group: 'resultados',
    summary: 'Cruze pedidos com uso do provador para medir conversão assistida e tamanhos comprados.',
    whenToUse: 'Quando você quer entender o impacto comercial do provador nas compras reais da loja.',
    steps: [
      'Filtre período, origem e uso do provador.',
      'Revise produtos com mais pedidos assistidos.',
      'Compare com devoluções antes de ajustar tabela ou modelagem.',
    ],
    primaryTo: '/app/pedidos',
    primaryLabel: 'Abrir pedidos',
    nextTo: '/app/devolucoes',
    nextLabel: 'Abrir devoluções',
    relatedKeys: ['relatorios', 'devolucoes', 'tabelas'],
    tags: ['conversão', 'receita', 'pedido assistido', 'resultado'],
    routeMatchers: [{ path: '/app/pedidos' }],
  },
  {
    key: 'devolucoes',
    title: 'Devoluções',
    group: 'resultados',
    summary: 'Normalize motivos, compare com uso do provador e transforme sinais em revisão prática do catálogo.',
    whenToUse: 'Quando você quer saber se o problema está em tabela, modelagem, cadastro ou comportamento de compra.',
    steps: [
      'Revise motivos normalizados e tamanhos envolvidos.',
      'Cruze com pedidos assistidos e confiança da recomendação.',
      'Ajuste tabela ou modelagem só depois de ver o padrão recorrente.',
    ],
    primaryTo: '/app/devolucoes',
    primaryLabel: 'Abrir devoluções',
    nextTo: '/app/analytics',
    nextLabel: 'Voltar aos relatórios',
    relatedKeys: ['pedidos', 'relatorios', 'assistente'],
    tags: ['trocas', 'motivos', 'aprendizado', 'resultado'],
    routeMatchers: [{ path: '/app/devolucoes' }],
  },
  {
    key: 'assistente',
    title: 'Assistente IA',
    group: 'provador',
    summary: 'Use a IA para acelerar rascunhos e revisão, sem pular a conferência humana antes de salvar.',
    whenToUse: 'Quando você quer criar uma tabela inicial, revisar uma existente ou explicar risco e confiança para a loja.',
    steps: [
      'Informe categoria, marca, base da tabela e contexto do produto.',
      'Leia a confiança, os riscos e a explicação simples.',
      'Salve como rascunho e revise antes de publicar ou vincular.',
    ],
    primaryTo: '/app/assistente',
    primaryLabel: 'Abrir assistente',
    nextTo: '/app/tabelas-de-medidas/nova',
    nextLabel: 'Criar tabela',
    relatedKeys: ['tabelas', 'relatorios', 'devolucoes'],
    tags: ['IA', 'sugestão', 'rascunho', 'explicação'],
    routeMatchers: [{ path: '/app/assistente' }],
  },
  {
    key: 'publicacao',
    title: 'Publicação',
    group: 'provador',
    summary: 'A publicação conecta catálogo, instalação, dados e prontidão operacional da loja.',
    whenToUse: 'Quando você está prestes a liberar o provador para clientes e precisa saber se falta algo crítico.',
    steps: [
      'Resolva bloqueios antes de qualquer publicação.',
      'Use avisos para atacar riscos de experiência ou dados.',
      'Revalide depois de mexer em widget, integração ou catálogo.',
    ],
    primaryTo: '/app/go-live',
    primaryLabel: 'Abrir publicação',
    nextTo: '/app/widget',
    nextLabel: 'Revisar provador',
    relatedKeys: ['provador', 'integracoes', 'painel'],
    tags: ['go-live', 'checklist', 'bloqueios', 'avisos'],
    routeMatchers: [{ path: '/app/go-live' }],
  },
  {
    key: 'cobranca',
    title: 'Plano e cobrança',
    group: 'conta',
    summary: 'Veja plano, benefício BigShop, links financeiros e solicitações comerciais sem entrar no Admin.',
    whenToUse: 'Quando a loja precisa entender seu plano, o próximo vencimento ou abrir um link financeiro com rastreabilidade.',
    steps: [
      'Confira o status comercial e o ciclo atual.',
      'Abra links financeiros apenas pelos atalhos auditados do portal.',
      'Use o histórico para acompanhar trocas comerciais e ajustes pendentes.',
    ],
    primaryTo: '/app/plano-e-cobranca',
    primaryLabel: 'Abrir plano e cobrança',
    nextTo: '/app/integracoes',
    nextLabel: 'Ver integrações',
    relatedKeys: ['integracoes', 'bigshop', 'painel'],
    tags: ['plano', 'fatura', 'benefício', 'solicitação comercial'],
    routeMatchers: [{ path: '/app/plano-e-cobranca' }],
  },
  {
    key: 'usuarios',
    title: 'Usuários',
    group: 'conta',
    summary: 'Permissões devem acompanhar a função real de cada pessoa na operação da loja.',
    whenToUse: 'Quando você precisa convidar alguém, revisar acessos ou restringir edição a poucos usuários.',
    steps: [
      'Crie acessos com a menor permissão necessária.',
      'Acompanhe convite pendente e primeiro acesso.',
      'Revise usuários inativos periodicamente.',
    ],
    primaryTo: '/app/usuarios',
    primaryLabel: 'Abrir usuários',
    nextTo: '/app/ajuda',
    nextLabel: 'Voltar para a ajuda',
    relatedKeys: ['painel', 'cobranca'],
    tags: ['acesso', 'convite', 'permissão', 'empresa'],
    routeMatchers: [{ path: '/app/usuarios' }],
  },
  {
    key: 'bigshop',
    title: 'Guia de BigShop',
    group: 'plataformas',
    summary: 'Use BigShop quando a loja trabalha com integração nativa, feed, API e validação assistida da página de produto.',
    whenToUse: 'Quando a empresa opera na BigShop ou está avaliando ativação, sync e troca comercial ligada a ela.',
    steps: [
      'Confirme Store ID, origem do catálogo e estado técnico da conexão.',
      'Rode dry-run antes de qualquer sync real.',
      'Valide a página pública do produto antes da publicação final.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/sincronizacao',
    nextLabel: 'Ver histórico BigShop',
    relatedKeys: ['integracoes', 'sincronizacao', 'provador'],
    tags: ['bigshop', 'store id', 'dry-run', 'sync'],
  },
  {
    key: 'shopify',
    title: 'Guia de Shopify',
    group: 'plataformas',
    summary: 'Use Shopify quando a instalação do provador depende do snippet correto e da validação da página de produto.',
    whenToUse: 'Quando a loja usa Shopify e você precisa orientar instalação, dados e rastreamento sem improviso.',
    steps: [
      'Defina domínio permitido e snippet do provador.',
      'Valide o container na página de produto.',
      'Só publique depois da prévia desktop e mobile.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/widget',
    nextLabel: 'Abrir provador',
    relatedKeys: ['integracoes', 'provador', 'publicacao'],
    tags: ['shopify', 'snippet', 'produto', 'domínio'],
  },
  {
    key: 'woocommerce',
    title: 'Guia de WooCommerce',
    group: 'plataformas',
    summary: 'WooCommerce costuma exigir alinhamento entre página de produto, feed e validação do container.',
    whenToUse: 'Quando a loja usa WooCommerce e precisa posicionar o provador sem depender de ajuste manual repetido.',
    steps: [
      'Defina a origem do catálogo e o modo de instalação.',
      'Valide o seletor da página de produto.',
      'Revise feed ou API antes da publicação.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/widget',
    nextLabel: 'Ver posição do provador',
    relatedKeys: ['integracoes', 'provador', 'sincronizacao'],
    tags: ['woocommerce', 'container', 'feed', 'produto'],
  },
  {
    key: 'nuvemshop',
    title: 'Guia de Nuvemshop',
    group: 'plataformas',
    summary: 'Nuvemshop pede atenção especial a instalação, domínio e comportamento da página de produto.',
    whenToUse: 'Quando a loja opera na Nuvemshop e você quer um checklist simples de ativação.',
    steps: [
      'Confirme o domínio liberado.',
      'Configure e valide a posição do provador.',
      'Use o checklist de publicação antes do go-live.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/go-live',
    nextLabel: 'Abrir publicação',
    relatedKeys: ['provador', 'publicacao'],
    tags: ['nuvemshop', 'domínio', 'widget', 'publicação'],
  },
  {
    key: 'vtex',
    title: 'Guia de VTEX',
    group: 'plataformas',
    summary: 'VTEX costuma concentrar atenção em posicionamento do provador, produto correto e rastreamento.',
    whenToUse: 'Quando a empresa usa VTEX e você quer garantir ativação sem conflito com a página de produto.',
    steps: [
      'Defina o ponto de instalação no produto.',
      'Valide identificadores do produto e da variação.',
      'Teste a experiência final em desktop e mobile.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/widget',
    nextLabel: 'Conferir botões',
    relatedKeys: ['integracoes', 'provador', 'publicacao'],
    tags: ['vtex', 'pdp', 'identificador', 'validação'],
  },
  {
    key: 'tray',
    title: 'Guia de Tray',
    group: 'plataformas',
    summary: 'Tray pede organização entre dados do catálogo, posição do provador e validação da página.',
    whenToUse: 'Quando a loja opera na Tray e você quer reduzir ajustes manuais repetitivos.',
    steps: [
      'Configure a conexão mínima necessária.',
      'Valide o provador na página pública do produto.',
      'Revise o go-live antes de liberar a loja.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/go-live',
    nextLabel: 'Revisar go-live',
    relatedKeys: ['integracoes', 'provador', 'publicacao'],
    tags: ['tray', 'catálogo', 'produto', 'go-live'],
  },
  {
    key: 'loja_integrada',
    title: 'Guia de Loja Integrada',
    group: 'plataformas',
    summary: 'Loja Integrada pede foco em domínio, instalação do provador e revisão visual da página do produto.',
    whenToUse: 'Quando a empresa está nessa plataforma e você precisa orientar a ativação com clareza.',
    steps: [
      'Libere o domínio correto.',
      'Revise snippet, seletor e posição do provador.',
      'Só publique depois da validação da página pública.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/widget',
    nextLabel: 'Abrir instalação',
    relatedKeys: ['integracoes', 'provador', 'publicacao'],
    tags: ['loja integrada', 'domínio', 'validação', 'snippet'],
  },
  {
    key: 'magento',
    title: 'Guia de Magento',
    group: 'plataformas',
    summary: 'Magento pede atenção a instalação técnica, seletor da página de produto e rastreamento.',
    whenToUse: 'Quando a loja usa Magento e você quer um passo a passo curto para evitar erro de posicionamento.',
    steps: [
      'Confirme a origem do catálogo.',
      'Valide o container e os identificadores do produto.',
      'Revise publicação e checklist final.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/go-live',
    nextLabel: 'Ver checklist final',
    relatedKeys: ['integracoes', 'publicacao'],
    tags: ['magento', 'container', 'identificador', 'publicação'],
  },
  {
    key: 'opencart',
    title: 'Guia de OpenCart',
    group: 'plataformas',
    summary: 'OpenCart pede validação simples e direta na página do produto antes de liberar o provador.',
    whenToUse: 'Quando a loja usa OpenCart e você quer um roteiro curto de instalação e conferência.',
    steps: [
      'Revise domínio e snippet.',
      'Valide a página pública do produto.',
      'Reabra o go-live depois da instalação.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/widget',
    nextLabel: 'Revisar provador',
    relatedKeys: ['integracoes', 'provador', 'publicacao'],
    tags: ['opencart', 'domínio', 'produto', 'validação'],
  },
  {
    key: 'xml_feed',
    title: 'Guia de XML/feed',
    group: 'plataformas',
    summary: 'Use XML/feed quando o catálogo vem por arquivo ou URL e precisa de validação antes de gravar.',
    whenToUse: 'Quando a loja não usa API direta ou quando o feed é a fonte principal do catálogo.',
    steps: [
      'Valide a URL e o formato do feed.',
      'Faça prévia e revise campos ausentes.',
      'Volte para regras antes de nova importação se o mapeamento vier inconsistente.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/importacoes',
    nextLabel: 'Abrir importações',
    relatedKeys: ['integracoes', 'importacoes', 'regras'],
    tags: ['xml', 'feed', 'prévia', 'mapeamento'],
  },
  {
    key: 'api',
    title: 'Guia de API',
    group: 'plataformas',
    summary: 'Use API quando a loja entrega dados por base, token e webhook, sempre sem expor segredo em claro.',
    whenToUse: 'Quando a empresa depende de conexão técnica mais estruturada do que um feed simples.',
    steps: [
      'Preencha só os campos exigidos para a integração.',
      'Confirme que token e segredo ficaram mascarados após salvar.',
      'Teste webhook e validação da página antes do go-live.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/sincronizacao',
    nextLabel: 'Ver eventos técnicos',
    relatedKeys: ['integracoes', 'sincronizacao', 'publicacao'],
    tags: ['api', 'token', 'webhook', 'segurança'],
  },
  {
    key: 'custom',
    title: 'Guia de plataforma personalizada',
    group: 'plataformas',
    summary: 'Use a opção personalizada quando a loja não se encaixa em uma plataforma pronta e precisa de configuração manual guiada.',
    whenToUse: 'Quando você precisa instalar o provador com mais liberdade de seletor, domínio e snippet.',
    steps: [
      'Defina a página de produto de referência.',
      'Valide seletor, domínio e posição do provador.',
      'Registre o próximo passo no suporte se a loja depender de ajuste externo.',
    ],
    primaryTo: '/app/integracoes',
    primaryLabel: 'Abrir integrações',
    nextTo: '/app/widget',
    nextLabel: 'Ajustar posição do provador',
    relatedKeys: ['integracoes', 'provador', 'publicacao'],
    tags: ['personalizada', 'seletor', 'domínio', 'snippet'],
  },
]

const helpArticlesByKey = new Map(helpArticles.map((article) => [article.key, article]))

export function findHelpArticle(key: string) {
  return helpArticlesByKey.get(key) || null
}

export function findHelpArticleByRoute(path: string) {
  return helpArticles.find((article) => (
    article.routeMatchers || []
  ).some((matcher) => (
    matcher.exact ? path === matcher.path : path === matcher.path || path.startsWith(`${matcher.path}/`)
  ))) || null
}

export function relatedHelpArticles(article: HelpArticle) {
  return (article.relatedKeys || [])
    .map((key) => findHelpArticle(key))
    .filter((related): related is HelpArticle => Boolean(related))
}

export function articleGroupLabel(article: HelpArticle) {
  return helpGroupLabels[article.group]
}

export function normalizeHelpSearch(value: string) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

export function filterHelpArticles(searchTerm: string) {
  const normalizedSearch = normalizeHelpSearch(searchTerm)

  if (!normalizedSearch) {
    return helpArticles
  }

  return helpArticles.filter((article) => {
    const haystack = normalizeHelpSearch([
      article.title,
      article.summary,
      article.whenToUse,
      ...article.steps,
      ...(article.tags || []),
      articleGroupLabel(article),
    ].join(' '))

    return haystack.includes(normalizedSearch)
  })
}

export function buildSupportUrl(context: SupportContext = {}) {
  const article = context.article
  const topicLabel = article ? article.title : 'Ajuda geral'
  const routeLabel = context.routePath || article?.primaryTo || '/app/ajuda'
  const companyLabel = context.companyName || 'Sem empresa ativa'
  const companyCode = context.companyCode || 'Sem código informado'
  const merchantLabel = context.merchantName || 'Sem lojista informado'
  const userLabel = context.userName || 'Sem usuário informado'
  const userEmailLabel = context.userEmail || 'sem e-mail'
  const platformLabel = platformLabels[context.platform || ''] || context.platform || 'Não informado'

  const lines = [
    'Oi, preciso de ajuda no Provador Virtual.',
    `Assunto: ${topicLabel}`,
    `Tela: ${routeLabel}`,
    `Empresa: ${companyLabel}`,
    `Código da empresa: ${companyCode}`,
    `Lojista: ${merchantLabel}`,
    `Plataforma: ${platformLabel}`,
    `Usuário: ${userLabel} (${userEmailLabel})`,
  ]

  return `${supportBaseUrl}?text=${encodeURIComponent(lines.join('\n'))}`
}
