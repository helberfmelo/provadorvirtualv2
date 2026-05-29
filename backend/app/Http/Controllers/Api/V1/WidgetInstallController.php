<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWidgetInstallRequest;
use App\Http\Resources\WidgetInstallResource;
use App\Models\MerchantCompany;
use App\Models\WidgetInstall;
use App\Services\Audit\AuditLogger;
use App\Support\ActiveTenant;
use App\Support\PlatformCatalog;
use App\Support\WidgetButtonStyleCatalog;
use App\Support\WidgetPlacementCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WidgetInstallController extends Controller
{
    use ResolvesMerchant;

    public function show(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);
        $install = $this->resolveInstall($merchant, $company);

        return new WidgetInstallResource($install->load('company'));
    }

    public function update(UpdateWidgetInstallRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = app(ActiveTenant::class)->company($request, $merchant);
        $install = $this->resolveInstall($merchant, $activeCompany);
        $data = $request->validated();
        $mode = $data['mode'] ?? 'publish';
        unset($data['mode']);

        abort_if(
            $activeCompany?->platform === 'bigshop'
            && array_key_exists('platform', $data)
            && $data['platform'] !== 'bigshop',
            403,
            'Sua empresa contratou o plano BigShop. O widget pode ser instalado apenas na BigShop.'
        );

        $data = $this->normalizePayload($merchant, $activeCompany, $data);
        $stateChanges = Arr::only($data, ['platform', 'allowed_domains', 'theme', 'is_active']);

        if ($mode === 'discard') {
            $install->forceFill($this->draftColumns())->save();
        } elseif ($mode === 'draft') {
            $draftState = array_replace($this->draftState($install), $stateChanges);
            $install->update([
                'draft_platform' => $draftState['platform'],
                'draft_allowed_domains' => $draftState['allowed_domains'],
                'draft_theme' => $draftState['theme'],
                'draft_is_active' => $draftState['is_active'],
            ]);
        } else {
            $baseState = $stateChanges === [] ? $this->draftState($install) : $this->liveState($install);
            $publishState = array_replace($baseState, $stateChanges);
            $this->ensurePlacementCanPublish($publishState['theme'] ?? []);
            $install->update([
                ...Arr::only($data, ['merchant_company_id']),
                'platform' => $publishState['platform'],
                'allowed_domains' => $publishState['allowed_domains'],
                'theme' => $publishState['theme'],
                'is_active' => $publishState['is_active'],
                ...$this->draftColumns(),
                'published_at' => now(),
            ]);
        }

        app(AuditLogger::class)->log($request, $merchant, 'widget_install.updated', 'widget', 'info', [
            'platform' => $install->platform,
            'merchant_company_id' => $install->merchant_company_id,
            'module' => 'widget',
            'action' => $mode,
            'is_active' => $install->is_active,
            'allowed_domains_count' => count($install->allowed_domains ?? []),
            'has_draft' => filled($install->draft_platform) || is_array($install->draft_theme),
        ], $install);

        return new WidgetInstallResource($install->refresh()->load('company'));
    }

    public function placementPreview(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = app(ActiveTenant::class)->company($request, $merchant);

        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', Rule::in(PlatformCatalog::keys())],
            'mode' => ['required', 'string', Rule::in(WidgetPlacementCatalog::modes())],
            'selector' => ['required', 'string', 'max:180'],
            'container_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
        ]);

        $selector = trim((string) $data['selector']);
        $this->ensureSelectorSyntax($selector, 'selector');

        $url = $this->publicProductUrl($data['url'] ?? $activeCompany?->domain);
        $platform = $data['platform'] ?? $activeCompany?->platform ?? 'custom';
        $containerId = trim((string) ($data['container_id'] ?? WidgetPlacementCatalog::DEFAULT_CONTAINER_ID));
        $httpStatus = null;
        $body = '';
        $error = null;

        try {
            $response = Http::timeout(8)->retry(1, 100)->get($url);
            $httpStatus = $response->status();
            $body = (string) $response->body();
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $reachable = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 400;
        $analysis = $this->placementDiagnostics($body, $selector, $containerId, $reachable);
        $checks = $this->placementChecks($analysis, $reachable);
        $status = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'failed')
            ? 'failed'
            : (collect($checks)->contains(fn (array $check): bool => $check['status'] === 'warning') ? 'warning' : 'passed');

        app(AuditLogger::class)->log($request, $merchant, 'widget_install.placement_previewed', 'widget', 'info', [
            'platform' => $platform,
            'merchant_company_id' => $activeCompany?->id,
            'module' => 'widget',
            'action' => 'placement_preview',
            'status' => $status,
            'url_host' => (string) parse_url($url, PHP_URL_HOST),
            'selector' => $selector,
            'mode' => $data['mode'],
        ]);

        return response()->json([
            'data' => [
                'status' => $status,
                'url' => $url,
                'http_status' => $httpStatus,
                'platform' => $platform,
                'placement' => [
                    'mode' => $data['mode'],
                    'selector' => $selector,
                    'container_id' => $containerId,
                    'label' => $this->placementModeLabel($data['mode']),
                ],
                'checks' => $checks,
                'diagnostics' => $analysis,
                'error' => $error,
                'checked_at' => now()->toISOString(),
            ],
        ]);
    }

    private function normalizePayload($merchant, ?MerchantCompany $activeCompany, array $data): array
    {
        if ($activeCompany?->platform === 'bigshop') {
            $data['platform'] = 'bigshop';
            $data['merchant_company_id'] = $activeCompany->id;
        }

        if (array_key_exists('merchant_company_id', $data)) {
            $data['merchant_company_id'] = $this->merchantCompany($merchant, $data['merchant_company_id'])?->id;
        }

        if (array_key_exists('theme', $data)) {
            $data['theme'] = array_filter(
                $data['theme'] ?? [],
                fn ($value): bool => ! ($value === null || $value === '')
            );
            $data['theme'] = $this->normalizeTheme($data['theme']);
        }

        if (array_key_exists('allowed_domains', $data)) {
            $data['allowed_domains'] = collect($data['allowed_domains'])
                ->map(fn (string $domain): string => Str::of($domain)->lower()->trim()->toString())
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $data;
    }

    private function liveState(WidgetInstall $install): array
    {
        return [
            'platform' => $install->platform ?: 'custom',
            'allowed_domains' => $install->allowed_domains ?? [],
            'theme' => $this->normalizeTheme($install->theme ?? []),
            'is_active' => (bool) $install->is_active,
        ];
    }

    private function draftState(WidgetInstall $install): array
    {
        $liveState = $this->liveState($install);

        return [
            'platform' => $install->draft_platform ?: $liveState['platform'],
            'allowed_domains' => $install->draft_allowed_domains ?? $liveState['allowed_domains'],
            'theme' => $install->draft_theme ?? $liveState['theme'],
            'is_active' => $install->draft_is_active ?? $liveState['is_active'],
        ];
    }

    private function draftColumns(): array
    {
        return [
            'draft_platform' => null,
            'draft_allowed_domains' => null,
            'draft_theme' => null,
            'draft_is_active' => null,
        ];
    }

    private function resolveInstall($merchant, ?MerchantCompany $company = null): WidgetInstall
    {
        $company ??= $merchant->companies()->orderBy('id')->first();

        $install = WidgetInstall::query()->firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'merchant_company_id' => $company?->id,
                'public_key' => 'pv_'.Str::lower(Str::random(24)),
                'platform' => $company?->platform ?? 'custom',
                'allowed_domains' => array_values(array_filter([
                    $company?->domain,
                    'localhost',
                    '127.0.0.1',
                ])),
                'theme' => [
                    'primary' => '#0f172a',
                    'secondary' => '#ff4d5e',
                    'accent' => '#ff7a1a',
                    'background' => '#ffffff',
                    'text' => '#111827',
                    'font_family' => 'Manrope, Inter, Arial, sans-serif',
                    'font_size' => '14',
                    'font_weight' => '800',
                    'button_radius' => '8',
                    'button_style' => WidgetButtonStyleCatalog::DEFAULT,
                    'button_background' => '#ff4d5e',
                    'button_text' => '#ffffff',
                    'button_primary_icon' => 'hanger',
                    'button_secondary_icon' => 'ruler',
                    'button_icon_animation' => true,
                    'confetti_enabled' => true,
                    'presentation_mode' => 'drawer',
                    'placement' => WidgetPlacementCatalog::default(),
                ],
                'is_active' => true,
            ]
        );

        if ($company?->platform === 'bigshop' && $install->platform !== 'bigshop') {
            $install->forceFill([
                'merchant_company_id' => $company->id,
                'platform' => 'bigshop',
            ])->save();
        }

        return $install;
    }

    private function normalizeTheme(array $theme): array
    {
        $theme['placement'] = WidgetPlacementCatalog::normalize(
            is_array($theme['placement'] ?? null) ? $theme['placement'] : null
        );

        return $theme;
    }

    private function ensurePlacementCanPublish(array $theme): void
    {
        $placement = WidgetPlacementCatalog::normalize(
            is_array($theme['placement'] ?? null) ? $theme['placement'] : null
        );

        $this->ensureSelectorSyntax($placement['selector'], 'theme.placement.selector');

        if (($placement['validation']['status'] ?? 'untested') === 'failed') {
            throw ValidationException::withMessages([
                'theme.placement.selector' => ['Teste o seletor novamente antes de publicar; a última validação falhou.'],
            ]);
        }
    }

    private function ensureSelectorSyntax(string $selector, string $field): void
    {
        if (! $this->selectorSyntaxIsSafe($selector) || $this->selectorToXpath($selector) === null) {
            throw ValidationException::withMessages([
                $field => ['Use um seletor CSS simples e válido, como #id, .classe, tag, [data-atributo] ou combinações por espaço.'],
            ]);
        }
    }

    private function selectorSyntaxIsSafe(string $selector): bool
    {
        $selector = trim($selector);

        return $selector !== ''
            && mb_strlen($selector) <= 180
            && ! preg_match('/[<{};`]/', $selector)
            && substr_count($selector, '[') === substr_count($selector, ']')
            && substr_count($selector, '(') === substr_count($selector, ')')
            && substr_count($selector, '"') % 2 === 0
            && substr_count($selector, "'") % 2 === 0;
    }

    private function publicProductUrl(?string $url): string
    {
        $value = trim((string) $url);

        if ($value === '') {
            throw ValidationException::withMessages([
                'url' => ['Informe a URL pública da página de produto para validar.'],
            ]);
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! filter_var($value, FILTER_VALIDATE_URL) || ! is_string($host) || $host === '') {
            throw ValidationException::withMessages([
                'url' => ['Informe uma URL pública válida.'],
            ]);
        }

        $host = mb_strtolower($host);
        $isPublicIp = ! filter_var($host, FILTER_VALIDATE_IP)
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local') || ! $isPublicIp) {
            throw ValidationException::withMessages([
                'url' => ['Use uma URL pública da loja.'],
            ]);
        }

        return $value;
    }

    private function placementDiagnostics(string $body, string $selector, string $containerId, bool $reachable): array
    {
        $containerSelector = '#'.$containerId;
        $containerCount = $this->selectorMatchCount($body, $containerSelector);
        $scriptPosition = $this->firstScriptPosition($body);
        $containerPosition = $this->firstContainerPosition($body, $containerId);

        return [
            'reachable' => $reachable,
            'anchor' => [
                'selector' => $selector,
                'matches' => $this->selectorMatchCount($body, $selector),
            ],
            'container' => [
                'selector' => $containerSelector,
                'matches' => $containerCount,
                'before_script' => $containerPosition !== null && $scriptPosition !== null
                    ? $containerPosition < $scriptPosition
                    : null,
            ],
            'script' => [
                'found' => $scriptPosition !== null,
            ],
            'duplicates' => [
                'container' => $containerCount > 1,
            ],
        ];
    }

    private function placementChecks(array $analysis, bool $reachable): array
    {
        $anchorFound = (int) data_get($analysis, 'anchor.matches', 0) > 0;
        $containerMatches = (int) data_get($analysis, 'container.matches', 0);
        $scriptFound = (bool) data_get($analysis, 'script.found');
        $containerBeforeScript = data_get($analysis, 'container.before_script');

        return [
            $this->placementCheck(
                'page_reachable',
                'Página acessível',
                $reachable,
                'A URL precisa retornar uma PDP pública com status 2xx ou 3xx.'
            ),
            $this->placementCheck(
                'anchor_found',
                'Seletor encontrado',
                $anchorFound,
                'Ajuste o seletor CSS para um elemento existente na página do produto.'
            ),
            $this->placementCheck(
                'script_found',
                'Script do widget presente',
                $scriptFound,
                'Inclua o script do Provador Virtual na página antes de publicar.',
                warning: true
            ),
            $this->placementCheck(
                'container_found',
                'Container do widget presente',
                $containerMatches > 0,
                'Inclua ou permita criar o container provador-virtual-container antes da inicialização do script.',
                warning: true
            ),
            $this->placementCheck(
                'container_before_script',
                'Container antes do script',
                $containerBeforeScript === true,
                'Posicione o container antes do script ou use um seletor/âncora que exista antes do carregamento.',
                warning: true
            ),
            $this->placementCheck(
                'no_duplicate_container',
                'Sem duplicidade de container',
                $containerMatches <= 1,
                'Remova containers duplicados para evitar múltiplos botões na PDP.'
            ),
        ];
    }

    private function placementCheck(string $key, string $label, bool $passed, string $action, bool $warning = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'passed' : ($warning ? 'warning' : 'failed'),
            'action' => $passed ? null : $action,
        ];
    }

    private function placementModeLabel(string $mode): string
    {
        return match ($mode) {
            'before' => 'Antes do seletor',
            'after' => 'Depois do seletor',
            default => 'Dentro do seletor',
        };
    }

    private function selectorMatchCount(string $body, string $selector): int
    {
        if (trim($body) === '') {
            return 0;
        }

        $xpathSelector = $this->selectorToXpath($selector);

        if ($xpathSelector === null) {
            return 0;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$body, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return 0;
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query($xpathSelector);

        return $nodes ? $nodes->length : 0;
    }

    private function selectorToXpath(string $selector): ?string
    {
        $groups = collect(explode(',', trim($selector)))
            ->map(fn (string $group): string => trim($group))
            ->filter()
            ->values();

        if ($groups->isEmpty()) {
            return null;
        }

        $paths = [];

        foreach ($groups as $group) {
            $group = preg_replace('/\s*>\s*/', ' > ', $group);
            $tokens = preg_split('/\s+/', (string) $group, -1, PREG_SPLIT_NO_EMPTY);
            $path = '';
            $axis = '//';

            foreach ($tokens as $token) {
                if ($token === '>') {
                    $axis = '/';

                    continue;
                }

                $compound = $this->compoundSelectorToXpath($token);

                if ($compound === null) {
                    return null;
                }

                $path .= $axis.$compound;
                $axis = '//';
            }

            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return $paths === [] ? null : implode(' | ', $paths);
    }

    private function compoundSelectorToXpath(string $selector): ?string
    {
        $remaining = $selector;
        $tag = '*';
        $conditions = [];

        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)/', $remaining, $match)) {
            $tag = $match[1];
            $remaining = substr($remaining, strlen($match[0]));
        }

        while ($remaining !== '') {
            if (preg_match('/^#([A-Za-z][A-Za-z0-9_-]*)/', $remaining, $match)) {
                $conditions[] = '@id = '.$this->xpathLiteral($match[1]);
                $remaining = substr($remaining, strlen($match[0]));

                continue;
            }

            if (preg_match('/^\.([A-Za-z][A-Za-z0-9_-]*)/', $remaining, $match)) {
                $conditions[] = 'contains(concat(" ", normalize-space(@class), " "), '.$this->xpathLiteral(' '.$match[1].' ').')';
                $remaining = substr($remaining, strlen($match[0]));

                continue;
            }

            if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_.:-]*)(?:\s*([*^$]?=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]+)))?\]/', $remaining, $match)) {
                $attribute = $match[1];
                $operator = $match[2] ?? null;
                $value = $match[3] ?? $match[4] ?? $match[5] ?? null;

                $conditions[] = match ($operator) {
                    '=' => '@'.$attribute.' = '.$this->xpathLiteral((string) $value),
                    '*=' => 'contains(@'.$attribute.', '.$this->xpathLiteral((string) $value).')',
                    '^=' => 'starts-with(@'.$attribute.', '.$this->xpathLiteral((string) $value).')',
                    '$=' => 'substring(@'.$attribute.', string-length(@'.$attribute.') - string-length('.$this->xpathLiteral((string) $value).') + 1) = '.$this->xpathLiteral((string) $value),
                    default => '@'.$attribute,
                };
                $remaining = substr($remaining, strlen($match[0]));

                continue;
            }

            return null;
        }

        return $tag.($conditions === [] ? '' : '['.implode(' and ', $conditions).']');
    }

    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        return 'concat(\''.str_replace("'", "', \"'\", '", $value).'\')';
    }

    private function firstScriptPosition(string $body): ?int
    {
        return $this->firstRegexPosition('/<script[^>]+src=["\'][^"\']*provador-virtual\.js[^"\']*["\']/i', $body);
    }

    private function firstContainerPosition(string $body, string $containerId): ?int
    {
        return $this->firstRegexPosition('/<[^>]+\bid=["\']'.preg_quote($containerId, '/').'["\'][^>]*>/i', $body);
    }

    private function firstRegexPosition(string $pattern, string $body): ?int
    {
        if (! preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return $match[0][1];
    }
}
