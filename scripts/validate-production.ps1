param(
    [string] $BaseUrl = "https://provadorvirtual.online",
    [string] $ApiBase = "",
    [string] $Email = "demo@provadorvirtual.online",
    [string] $Password = "provador123"
)

$ErrorActionPreference = "Stop"

$BaseUrl = $BaseUrl.TrimEnd("/")
$FrontendBase = $BaseUrl
if ($FrontendBase.EndsWith("/provadorvirtual_v2")) {
    $FrontendBase = $FrontendBase.Substring(0, $FrontendBase.Length - "/provadorvirtual_v2".Length)
}
if ([string]::IsNullOrWhiteSpace($ApiBase)) {
    if ($BaseUrl.EndsWith("/provadorvirtual_v2")) {
        $ApiBase = "$BaseUrl/public/api/v1"
    } else {
        $ApiBase = "$BaseUrl/provadorvirtual_v2/public/api/v1"
    }
} else {
    $ApiBase = $ApiBase.TrimEnd("/")
}

function Assert-True {
    param(
        [bool] $Condition,
        [string] $Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

function Get-HeaderValue {
    param(
        $Headers,
        [string] $Name
    )

    $value = $Headers[$Name]

    if ($value -is [System.Array]) {
        return [string] ($value -join ", ")
    }

    return [string] $value
}

function Assert-Page {
    param([string] $Path)

    $response = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl$Path"
    Assert-True ($response.StatusCode -eq 200) "$Path nao retornou 200"
    Assert-True ($response.Content.Contains('<div id="app"')) "$Path nao retornou o app Vue"
    "PAGE $Path OK"
}

function Assert-LegacyFrontendRedirect {
    param(
        [string] $LegacyPath,
        [string] $CleanPath
    )

    $request = [System.Net.WebRequest]::Create("$BaseUrl$LegacyPath")
    $request.AllowAutoRedirect = $false
    $response = $request.GetResponse()

    try {
        $statusCode = [int] $response.StatusCode
        $location = $response.Headers["Location"]
        $expectedUrl = "$BaseUrl$CleanPath"

        if (-not [string]::IsNullOrWhiteSpace($location) -and $location.StartsWith("/")) {
            $location = "$BaseUrl$location"
        }

        Assert-True (@(301, 302, 307, 308) -contains $statusCode) "$LegacyPath nao redirecionou"
        Assert-True ($location -eq $expectedUrl) "$LegacyPath redirecionou para $location, esperado $expectedUrl"
        "REDIRECT $LegacyPath -> $CleanPath OK"
    } finally {
        $response.Close()
    }
}

Assert-Page "/"
Assert-Page "/login"
Assert-Page "/saas/login"
Assert-Page "/checkout"
Assert-Page "/produto-teste"
Assert-Page "/privacidade"
Assert-Page "/termos"
Assert-Page "/saas"
Assert-Page "/saas/auditoria"
Assert-Page "/saas/empresas"
Assert-Page "/saas/empresas/nova"
Assert-Page "/saas/usuarios"
Assert-Page "/saas/usuarios/novo"
Assert-Page "/saas/usuarios-empresas"
Assert-Page "/saas/usuarios-empresas/novo"
Assert-Page "/saas/checkout"
Assert-Page "/saas/pedidos"
Assert-Page "/saas/trocas-bigshop"
Assert-Page "/saas/emails"
Assert-Page "/saas/emails/configuracoes"
Assert-Page "/app"
Assert-Page "/app/analytics"
Assert-Page "/app/pedidos"
Assert-Page "/app/devolucoes"
Assert-Page "/app/assistente"
Assert-Page "/app/widget"
Assert-Page "/app/produtos"
Assert-Page "/app/produtos/novo"
Assert-Page "/app/tabelas-de-medidas"
Assert-Page "/app/tabelas-de-medidas/nova"
Assert-Page "/app/modelagens"
Assert-Page "/app/categorias"
Assert-Page "/app/marcas"
Assert-Page "/app/taxonomia"
Assert-Page "/app/regras-de-importacao"
Assert-Page "/app/integracoes"
Assert-Page "/app/sincronizacao"
Assert-Page "/app/usuarios"
Assert-Page "/app/usuarios/novo"

if (-not $BaseUrl.EndsWith("/provadorvirtual_v2")) {
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/" "/"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/login" "/login"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/app/produtos/novo" "/app/produtos/novo"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/app/categorias" "/app/categorias"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/app/marcas" "/app/marcas"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/app/taxonomia" "/app/taxonomia"
}

$WidgetBase = if ($BaseUrl.EndsWith("/provadorvirtual_v2")) {
    "$BaseUrl/widget/v1"
} else {
    "$BaseUrl/provadorvirtual_v2/widget/v1"
}

$widgetJs = Invoke-WebRequest -UseBasicParsing -Uri "$WidgetBase/provador-virtual.js"
Assert-True ($widgetJs.StatusCode -eq 200) "widget JS nao retornou 200"
Assert-True ($widgetJs.Content.Contains("pv_shopper_profile_v2")) "widget JS sem perfil v2"
Assert-True ($widgetJs.Content.Contains("role=`"dialog`"")) "widget JS sem dialog acessivel"
Assert-True ($widgetJs.Content.Contains("placementConfig")) "widget JS sem posicionamento por seletor"
Assert-True ($widgetJs.Content.Contains("data-pv-root")) "widget JS sem controle de duplicidade"
Assert-True ($widgetJs.RawContentLength -lt 95000) "widget JS acima do limite de performance"
"WIDGET js OK"

$widgetCss = Invoke-WebRequest -UseBasicParsing -Uri "$WidgetBase/provador-virtual.css"
Assert-True ($widgetCss.StatusCode -eq 200) "widget CSS nao retornou 200"
Assert-True ($widgetCss.Content.Contains("@media (max-width: 560px)")) "widget CSS sem regra mobile"
Assert-True ($widgetCss.RawContentLength -lt 45000) "widget CSS acima do limite de performance"
"WIDGET css OK"

$health = Invoke-RestMethod -Uri "$ApiBase/health" -Headers @{ Accept = "application/json" }
Assert-True ($health.status -eq "ok") "health fora do esperado"
"API health OK"

$ops = Invoke-RestMethod -Uri "$ApiBase/ops/status" -Headers @{ Accept = "application/json" }
Assert-True ($ops.status -eq "ok") "ops/status fora do esperado"
Assert-True ($ops.checks.database -eq "ok") "database check fora do esperado"
"API ops/status OK"

$demo = Invoke-RestMethod -Uri "$ApiBase/demo/product-test" -Headers @{ Accept = "application/json" }
Assert-True ($demo.product.name -eq "Vestido Midi Aurora") "produto demo fora do esperado"
"API demo product OK"

$merchantId = [int] $demo.product.merchant_id
$storeId = [int] $demo.product.store_id
$productId = [int] $demo.product.id

$recommendationBody = @{
    merchant_id = $merchantId
    store_id = $storeId
    product_id = $productId
    platform = "custom"
    measurements = @{
        bust = 92
        waist = 74
        hip = 100
        height = 166
        weight = 62
    }
    shopper_profile = @{
        consent_measurements = $true
        fit_preference = "regular"
        gender = "female"
        body_shape = "hourglass"
    }
} | ConvertTo-Json -Depth 5

$recommendation = Invoke-RestMethod -Method Post -Uri "$ApiBase/public/recommendations" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $recommendationBody
Assert-True ($recommendation.recommended_size -eq "M") "recomendacao nao retornou tamanho M"
Assert-True ($recommendation.shopper_profile.consent -eq $true) "perfil consentido nao foi retornado"
Assert-True ([string]::IsNullOrWhiteSpace($recommendation.learning.status) -eq $false) "learning status ausente"
"API recommendation OK"

$signalBody = @{
    signal = "add_to_cart"
    selected_size = $recommendation.recommended_size
    source = "production-validation"
} | ConvertTo-Json -Depth 3

$signal = Invoke-RestMethod -Method Post -Uri "$ApiBase/public/recommendations/$($recommendation.recommendation_id)/signal" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $signalBody
Assert-True ([string]::IsNullOrWhiteSpace($signal.learning_status) -eq $false) "learning signal ausente"
"API learning signal OK"

if ($recommendation.shopper_profile.id -and $recommendation.shopper_profile.token) {
    $forgetBody = @{
        merchant_id = $merchantId
        store_id = $storeId
        profile_id = $recommendation.shopper_profile.id
        profile_token = $recommendation.shopper_profile.token
    } | ConvertTo-Json -Depth 3

    $forget = Invoke-RestMethod -Method Post -Uri "$ApiBase/public/shopper-profiles/forget" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $forgetBody
    Assert-True ($forget.forgotten -eq $true) "perfil de smoke nao foi esquecido"
    "API shopper profile forget OK"
}

$identityBody = @{
    merchant_id = $merchantId
    store_id = $storeId
    product_id = $productId
    platform = "custom"
} | ConvertTo-Json -Depth 3

try {
    Invoke-WebRequest -UseBasicParsing -Method Post -Uri "$ApiBase/public/recommendations/config-check" -Headers @{ Accept = "application/json"; Origin = "https://evil.example" } -ContentType "application/json" -Body $identityBody | Out-Null
    throw "origem nao autorizada foi aceita"
} catch {
    if ($_.Exception.Response -and ([int] $_.Exception.Response.StatusCode) -eq 403) {
        "CORS bad origin OK"
    } else {
        throw
    }
}

$allowed = Invoke-WebRequest -UseBasicParsing -Method Post -Uri "$ApiBase/public/recommendations/config-check" -Headers @{ Accept = "application/json"; Origin = "https://provadorvirtual.online" } -ContentType "application/json" -Body $identityBody
$allowedOrigin = @($allowed.Headers["Access-Control-Allow-Origin"])[0]
Assert-True ($allowed.StatusCode -eq 200) "origem permitida nao retornou 200"
Assert-True ($allowedOrigin -eq "https://provadorvirtual.online") "header CORS permitido incorreto"
"CORS allowed origin OK"

$loginBody = @{ email = $Email; password = $Password } | ConvertTo-Json
try {
    $login = Invoke-RestMethod -Method Post -Uri "$ApiBase/auth/login" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $loginBody
} catch {
    if ($_.Exception.Response -and ([int] $_.Exception.Response.StatusCode) -eq 429) {
        Start-Sleep -Seconds 65
        $login = Invoke-RestMethod -Method Post -Uri "$ApiBase/auth/login" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $loginBody
    } else {
        throw
    }
}
Assert-True ([string]::IsNullOrWhiteSpace($login.token) -eq $false) "login demo nao retornou token"
"Auth login OK"

$headers = @{
    Accept = "application/json"
    Authorization = "Bearer $($login.token)"
}

$me = Invoke-RestMethod -Uri "$ApiBase/me" -Headers $headers

$widgetInstall = Invoke-RestMethod -Uri "$ApiBase/widget-install" -Headers $headers
Assert-True ($widgetInstall.data.theme.placement.selector -eq "#provador-virtual-container") "widget sem placement padrao"
Assert-True (@($widgetInstall.data.platform_guide.guide.placement_suggestions).Count -gt 0) "widget sem sugestoes de seletor"
"API widget install OK"

$widgetUsageAnalytics = Invoke-RestMethod -Uri "$ApiBase/analytics/widget-usage?period=30d" -Headers $headers
Assert-True ($null -ne $widgetUsageAnalytics.data) "widget usage analytics sem data"
Assert-True ($null -ne $widgetUsageAnalytics.data.summary) "widget usage analytics sem resumo"
Assert-True ($null -ne $widgetUsageAnalytics.data.filter_options) "widget usage analytics sem filtros"
"API widget usage analytics OK"

$recommendationAnalytics = Invoke-RestMethod -Uri "$ApiBase/analytics/recommendations?period=30d&per_page=5" -Headers $headers
Assert-True ($null -ne $recommendationAnalytics.data) "recommendation analytics sem data"
Assert-True ($null -ne $recommendationAnalytics.data.product_ranking) "recommendation analytics sem ranking"
Assert-True ($null -ne $recommendationAnalytics.data.recommendation_report.meta) "recommendation analytics sem paginacao"
Assert-True ($null -ne $recommendationAnalytics.data.learning_pipeline.summary) "recommendation analytics sem pipeline de aprendizado"
Assert-True ($recommendationAnalytics.data.learning_pipeline.guardrails.review_required -eq $true) "recommendation analytics sem guardrail de revisao"
"API recommendation analytics OK"

$recommendationExport = Invoke-WebRequest -UseBasicParsing -Uri "$ApiBase/analytics/recommendations/export?report=recommendations&period=30d" -Headers $headers
$recommendationExportContentType = Get-HeaderValue -Headers $recommendationExport.Headers -Name "Content-Type"
Assert-True ($recommendationExport.StatusCode -eq 200) "export de recomendacoes nao retornou 200"
Assert-True ($recommendationExportContentType -like "text/csv*") "export de recomendacoes sem CSV"
"API recommendation export OK"

$ordersOverview = Invoke-RestMethod -Uri "$ApiBase/orders/overview?period=30d" -Headers $headers
Assert-True ($null -ne $ordersOverview.data) "orders overview sem data"
Assert-True ($null -ne $ordersOverview.data.summary) "orders overview sem resumo"
Assert-True ($null -ne $ordersOverview.data.filter_options) "orders overview sem filtros"
"API orders overview OK"

$ordersList = Invoke-RestMethod -Uri "$ApiBase/orders?period=30d&per_page=5" -Headers $headers
Assert-True ($null -ne $ordersList.data) "orders list sem data"
Assert-True ($null -ne $ordersList.meta) "orders list sem paginacao"
"API orders list OK"

$returnsOverview = Invoke-RestMethod -Uri "$ApiBase/returns/overview?period=30d" -Headers $headers
Assert-True ($null -ne $returnsOverview.data) "returns overview sem data"
Assert-True ($null -ne $returnsOverview.data.summary) "returns overview sem resumo"
Assert-True ($null -ne $returnsOverview.data.filter_options) "returns overview sem filtros"
"API returns overview OK"

$returnsList = Invoke-RestMethod -Uri "$ApiBase/returns?period=30d&per_page=5" -Headers $headers
Assert-True ($null -ne $returnsList.data) "returns list sem data"
Assert-True ($null -ne $returnsList.meta) "returns list sem paginacao"
"API returns list OK"

$returnsTemplate = Invoke-WebRequest -UseBasicParsing -Uri "$ApiBase/returns/template?format=csv" -Headers $headers
$returnsTemplateContentType = Get-HeaderValue -Headers $returnsTemplate.Headers -Name "Content-Type"
Assert-True ($returnsTemplate.StatusCode -eq 200) "returns template nao retornou 200"
Assert-True ($returnsTemplateContentType -like "text/csv*") "returns template sem CSV"
Assert-True ($returnsTemplate.Content.Contains("return_reference")) "returns template sem cabecalho esperado"
"API returns template OK"

$returnsPreviewBody = @{
    format = "csv"
    commit = $false
    content = "return_reference;order_reference;ordered_at;processed_at;status;return_reason;sku;product_name;ordered_size;ideal_size;returned_size;quantity;refund_amount;source_platform`nRET-VAL-001;PV-ORDER-2026-001;2026-05-28 10:00:00;2026-05-29 11:00:00;returned;ficou pequeno;PV-AURORA-MIDI-M;Vestido Midi Aurora;M;G;M;1;189.90;custom"
} | ConvertTo-Json -Depth 4

$returnsPreview = Invoke-RestMethod -Method Post -Uri "$ApiBase/returns/import" -Headers $headers -ContentType "application/json" -Body $returnsPreviewBody
Assert-True ($returnsPreview.summary.valid -ge 1) "returns preview sem linhas validas"
Assert-True ($returnsPreview.columns.mapping.order_reference -eq "order_reference") "returns preview sem mapeamento sugerido"
"API returns preview OK"

$placementBody = @{
    platform = $widgetInstall.data.platform
    url = "$FrontendBase/produto-teste"
    mode = "inside"
    selector = "#app"
    container_id = "provador-virtual-container"
} | ConvertTo-Json -Depth 3

$placementPreview = Invoke-RestMethod -Method Post -Uri "$ApiBase/widget-install/placement-preview" -Headers $headers -ContentType "application/json" -Body $placementBody
Assert-True ($placementPreview.data.status -ne "failed") "preview de posicionamento falhou"
Assert-True ($placementPreview.data.diagnostics.anchor.matches -gt 0) "preview nao encontrou seletor #app"
"API widget placement preview $($placementPreview.data.status)"

$readiness = Invoke-RestMethod -Uri "$ApiBase/go-live/readiness" -Headers $headers
Assert-True ($readiness.summary.status -ne "blocked") "readiness bloqueado"
Assert-True ([string]::IsNullOrWhiteSpace($readiness.pilot_package.status) -eq $false) "pacote de piloto ausente"
"Go-live readiness $($readiness.summary.status)"

$brands = Invoke-RestMethod -Uri "$ApiBase/brands" -Headers $headers
Assert-True ($null -ne $brands.summary) "brands summary ausente"
Assert-True ($null -ne $brands.data) "brands data ausente"
"API brands OK"

$categories = Invoke-RestMethod -Uri "$ApiBase/categories" -Headers $headers
Assert-True ($null -ne $categories.summary) "categories summary ausente"
Assert-True ($null -ne $categories.data) "categories data ausente"
Assert-True ($null -ne $categories.taxonomy_categories) "categories taxonomy ausente"
"API categories OK"

$integrations = Invoke-RestMethod -Uri "$ApiBase/integrations" -Headers $headers
$integrationKeys = @($integrations.data | ForEach-Object { $_.key })
Assert-True ($integrationKeys -contains "xml_feed") "integrations sem plataforma xml_feed"
Assert-True ($integrationKeys -contains "api") "integrations sem plataforma api"
$xmlFeedIntegration = @($integrations.data | Where-Object { $_.key -eq "xml_feed" })[0]
$apiIntegration = @($integrations.data | Where-Object { $_.key -eq "api" })[0]
Assert-True ($xmlFeedIntegration.setup.fields.feed_url.required -eq $true) "xml_feed sem feed_url obrigatorio"
Assert-True ($null -eq $xmlFeedIntegration.setup.fields.access_token) "xml_feed expôs token indevido"
Assert-True ($apiIntegration.setup.fields.api_base_url.required -eq $true) "api sem api_base_url obrigatoria"
Assert-True ($apiIntegration.setup.fields.access_token.secret -eq $true) "api sem token secreto"
Assert-True ($apiIntegration.guide.webhook.signature_header -eq "X-Provador-Signature") "api sem webhook assinado"
Assert-True ($apiIntegration.guide.gtm.default -eq $false) "GTM nao deve ser padrao"
Assert-True (@($apiIntegration.guide.api_examples).Count -gt 0) "api sem exemplos de API"
$checkKeys = @($apiIntegration.guide.checklist | ForEach-Object { $_.key })
Assert-True ($checkKeys -contains "product_id_found") "checklist sem produto"
Assert-True ($checkKeys -contains "variant_id_found") "checklist sem variacao"
Assert-True ($checkKeys -contains "sku_found") "checklist sem SKU"
Assert-True ($checkKeys -contains "buttons_rendered") "checklist sem botoes renderizados"
"API integrations OK"

if ($me.saas_permissions.saas_audit.view -eq $true) {
    $saasAudit = Invoke-RestMethod -Uri "$ApiBase/saas/audit-logs?limit=5" -Headers $headers
    Assert-True ($null -ne $saasAudit.data.summary) "saas audit sem resumo"
    Assert-True ($null -ne $saasAudit.data.logs) "saas audit sem logs"
    Assert-True ($null -ne $saasAudit.data.acceptances) "saas audit sem aceites"
    "API saas audit OK"
}

$ruleSimulationBody = @{
    import_rules = @{}
} | ConvertTo-Json -Depth 5

$ruleSimulation = Invoke-RestMethod -Method Post -Uri "$ApiBase/integrations/custom/import-rules/simulate" -Headers $headers -ContentType "application/json" -Body $ruleSimulationBody
Assert-True ($null -ne $ruleSimulation.data) "simulacao de regras sem data"
Assert-True ($ruleSimulation.data.sample_total -gt 0) "simulacao de regras sem amostra"
Assert-True (@($ruleSimulation.data.impact_by_rule).Count -gt 0) "simulacao de regras sem impacto"
Assert-True (@($ruleSimulation.data.rows).Count -gt 0) "simulacao de regras sem linhas"
"API import rule simulation OK"

$syncHistory = Invoke-RestMethod -Uri "$ApiBase/integrations/sync-history" -Headers $headers
Assert-True ($null -ne $syncHistory.data) "sync history sem data"
Assert-True ($null -ne $syncHistory.meta) "sync history sem meta"
$syncMetaKeys = @($syncHistory.meta.PSObject.Properties.Name)
Assert-True ($syncMetaKeys -contains "totals") "sync history sem totais"
Assert-True ($syncMetaKeys -contains "timeline") "sync history sem timeline"
Assert-True ($syncMetaKeys -contains "by_origin") "sync history sem origem"
Assert-True ($syncMetaKeys -contains "by_status") "sync history sem status"
if (@($syncHistory.data).Count -gt 0) {
    $firstSync = @($syncHistory.data)[0]
    Assert-True ($null -ne $firstSync.execution_key) "sync history sem chave de execucao"
    Assert-True ($null -ne $firstSync.origin) "sync history sem origem por execucao"
    Assert-True ($null -ne $firstSync.counters.total) "sync history sem contador total"
}
"API sync history OK"

$syncIssueExport = Invoke-WebRequest -UseBasicParsing -Uri "$ApiBase/integrations/sync-issues/export" -Headers $headers
Assert-True ($syncIssueExport.StatusCode -eq 200) "sync issues export nao retornou 200"
Assert-True ($syncIssueExport.Content.Contains("execution_key")) "sync issues export sem execution_key"
Assert-True ($syncIssueExport.Content.Contains("root_cause")) "sync issues export sem causa raiz"
"API sync issues export OK"

$integrationChangeCurrent = Invoke-RestMethod -Uri "$ApiBase/merchant/integration-change-requests/current" -Headers $headers
Assert-True ($integrationChangeCurrent.PSObject.Properties.Name -contains "data") "integration change current sem data"
"API integration change current OK"

$taxonomy = Invoke-RestMethod -Uri "$ApiBase/taxonomy/intelligence" -Headers $headers
Assert-True ($null -ne $taxonomy.summary) "taxonomy summary ausente"
Assert-True ($null -ne $taxonomy.active_version) "taxonomy active version ausente"
Assert-True ($null -ne $taxonomy.signals) "taxonomy signals ausentes"
"API taxonomy intelligence OK"

"PRODUCTION VALIDATION OK"
