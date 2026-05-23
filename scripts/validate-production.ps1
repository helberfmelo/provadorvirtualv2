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

Assert-Page "/"
Assert-Page "/login"
Assert-Page "/produto-teste"
Assert-Page "/privacidade"
Assert-Page "/termos"

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
} | ConvertTo-Json -Depth 5

$recommendation = Invoke-RestMethod -Method Post -Uri "$ApiBase/public/recommendations" -Headers @{ Accept = "application/json" } -ContentType "application/json" -Body $recommendationBody
Assert-True ($recommendation.recommended_size -eq "M") "recomendacao nao retornou tamanho M"
"API recommendation OK"

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
"Go-live readiness $($readiness.summary.status)"

"PRODUCTION VALIDATION OK"
