param(
    [string] $BaseUrl = "https://provadorvirtual.online",
    [string] $ApiBase = "",
    [string] $Email = "demo@provadorvirtual.online",
    [string] $Password = "provador123"
)

$ErrorActionPreference = "Stop"

$BaseUrl = $BaseUrl.TrimEnd("/")
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
Assert-Page "/saas/empresas"
Assert-Page "/saas/empresas/nova"
Assert-Page "/saas/usuarios"
Assert-Page "/saas/usuarios/novo"
Assert-Page "/saas/usuarios-empresas"
Assert-Page "/saas/usuarios-empresas/novo"
Assert-Page "/saas/checkout"
Assert-Page "/saas/pedidos"
Assert-Page "/saas/emails"
Assert-Page "/saas/emails/configuracoes"
Assert-Page "/app"
Assert-Page "/app/widget"
Assert-Page "/app/produtos"
Assert-Page "/app/produtos/novo"
Assert-Page "/app/tabelas-de-medidas"
Assert-Page "/app/tabelas-de-medidas/nova"
Assert-Page "/app/integracoes"
Assert-Page "/app/sincronizacao"
Assert-Page "/app/usuarios"
Assert-Page "/app/usuarios/novo"

if (-not $BaseUrl.EndsWith("/provadorvirtual_v2")) {
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/" "/"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/login" "/login"
    Assert-LegacyFrontendRedirect "/provadorvirtual_v2/app/produtos/novo" "/app/produtos/novo"
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
$login = Invoke-RestMethod -Method Post -Uri "$ApiBase/auth/login" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $loginBody
Assert-True ([string]::IsNullOrWhiteSpace($login.token) -eq $false) "login demo nao retornou token"
"Auth login OK"

$headers = @{
    Accept = "application/json"
    Authorization = "Bearer $($login.token)"
}

$readiness = Invoke-RestMethod -Uri "$ApiBase/go-live/readiness" -Headers $headers
Assert-True ($readiness.summary.status -ne "blocked") "readiness bloqueado"
Assert-True ([string]::IsNullOrWhiteSpace($readiness.pilot_package.status) -eq $false) "pacote de piloto ausente"
"Go-live readiness $($readiness.summary.status)"

"PRODUCTION VALIDATION OK"
