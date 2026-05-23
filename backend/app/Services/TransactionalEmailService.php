<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\EmailSetting;
use App\Models\MerchantCompany;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailSend;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TransactionalEmailService
{
    public const CODE_SIGNUP = 'cadastro_realizado';

    public const CODE_PAYMENT_CONFIRMED = 'pagamento_confirmado';

    public const CODE_PAYMENT_PENDING = 'aguardando_pagamento';

    public const CODE_PAYMENT_ERROR = 'erro_pagamento';

    public function sendForCheckout(
        string $code,
        CheckoutSession $session,
        array $extraContext = [],
        ?int $repeatAfterHours = null,
    ): TransactionalEmailSend {
        $session->loadMissing(['merchant', 'company', 'user']);

        if ($existing = $this->existingSent($code, $session, $repeatAfterHours)) {
            return $existing;
        }

        return $this->send(
            code: $code,
            recipientEmail: $session->user?->email ?: $session->lead_email,
            recipientName: $session->user?->name ?: $session->lead_name,
            context: [
                ...$this->checkoutContext($session),
                ...$extraContext,
            ],
            session: $session,
            company: $session->company,
            user: $session->user,
        );
    }

    public function sendForCompany(
        string $code,
        MerchantCompany $company,
        ?User $user,
        array $extraContext = [],
    ): TransactionalEmailSend {
        $company->loadMissing('merchant');

        return $this->send(
            code: $code,
            recipientEmail: $user?->email,
            recipientName: $user?->name,
            context: [
                ...$this->companyContext($company, $user),
                ...$extraContext,
            ],
            company: $company,
            user: $user,
        );
    }

    public function dispatchFinancialEmails(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $summary = [
            'checked' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'already_sent' => 0,
            'limit' => $limit,
            'ran_at' => now()->toISOString(),
        ];

        $sessions = CheckoutSession::query()
            ->with(['merchant', 'company', 'user'])
            ->whereIn('status', [
                CheckoutSession::STATUS_PENDING,
                CheckoutSession::STATUS_CHECKOUT_CREATED,
                CheckoutSession::STATUS_PAID,
                CheckoutSession::STATUS_FAILED,
                CheckoutSession::STATUS_CANCELLED,
                CheckoutSession::STATUS_EXPIRED,
                CheckoutSession::STATUS_REFUNDED,
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        foreach ($sessions as $session) {
            $code = $this->codeForSessionStatus($session);

            if (! $code) {
                continue;
            }

            $summary['checked']++;
            $send = $this->sendForCheckout(
                $code,
                $session,
                repeatAfterHours: $code === self::CODE_PAYMENT_PENDING ? 6 : null,
            );

            if (! $send->wasRecentlyCreated) {
                $summary['already_sent']++;

                continue;
            }

            match ($send->status) {
                TransactionalEmailSend::STATUS_SENT => $summary['sent']++,
                TransactionalEmailSend::STATUS_FAILED => $summary['failed']++,
                default => $summary['skipped']++,
            };
        }

        return $summary;
    }

    public function codeForSessionStatus(CheckoutSession $session): ?string
    {
        if ($session->status === CheckoutSession::STATUS_PAID) {
            return self::CODE_PAYMENT_CONFIRMED;
        }

        if (in_array($session->status, [
            CheckoutSession::STATUS_FAILED,
            CheckoutSession::STATUS_CANCELLED,
            CheckoutSession::STATUS_EXPIRED,
            CheckoutSession::STATUS_REFUNDED,
        ], true)) {
            return self::CODE_PAYMENT_ERROR;
        }

        if (in_array($session->status, [
            CheckoutSession::STATUS_PENDING,
            CheckoutSession::STATUS_CHECKOUT_CREATED,
        ], true) && $session->payment_method === 'pix') {
            return self::CODE_PAYMENT_PENDING;
        }

        return null;
    }

    private function send(
        string $code,
        ?string $recipientEmail,
        ?string $recipientName,
        array $context,
        ?CheckoutSession $session = null,
        ?MerchantCompany $company = null,
        ?User $user = null,
    ): TransactionalEmailSend {
        $template = TransactionalEmail::query()
            ->where('code', TransactionalEmail::normalizeCode($code))
            ->first();
        $settings = EmailSetting::current();
        $subject = $template ? $this->render((string) $template->subject, $context) : null;
        $body = $template ? $this->render((string) $template->body, $context) : null;

        $send = TransactionalEmailSend::query()->create([
            'transactional_email_id' => $template?->id,
            'checkout_session_id' => $session?->id,
            'merchant_id' => $company?->merchant_id ?: $session?->merchant_id,
            'merchant_company_id' => $company?->id ?: $session?->merchant_company_id,
            'user_id' => $user?->id ?: $session?->user_id,
            'code' => TransactionalEmail::normalizeCode($code),
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body' => $body,
            'status' => TransactionalEmailSend::STATUS_PENDING,
            'context' => $this->safeContext($context),
        ]);

        if (! $template || ! $template->is_active) {
            return $this->markSkipped($send, 'Template transacional inativo ou não encontrado.');
        }

        if (! $settings->is_active) {
            return $this->markSkipped($send, 'Envio transacional inativo nas configurações SMTP.');
        }

        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->markSkipped($send, 'Destinatario invalido ou ausente.');
        }

        if (! $this->mailerReady($settings)) {
            return $this->markSkipped($send, 'Credenciais de envio incompletas.');
        }

        try {
            $this->configureMailer($settings);
            Mail::mailer($settings->mailer ?: 'smtp')->raw((string) $body, function ($message) use ($recipientEmail, $recipientName, $subject): void {
                $message->to($recipientEmail, $recipientName ?: null)
                    ->subject((string) $subject);
            });

            $send->forceFill([
                'status' => TransactionalEmailSend::STATUS_SENT,
                'sent_at' => now(),
                'error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Falha no envio de e-mail transacional.', [
                'code' => $code,
                'recipient_email' => $recipientEmail,
                'message' => $exception->getMessage(),
            ]);

            $send->forceFill([
                'status' => TransactionalEmailSend::STATUS_FAILED,
                'error' => Str::limit($exception->getMessage(), 1800),
            ])->save();
        }

        return $send;
    }

    private function existingSent(string $code, CheckoutSession $session, ?int $repeatAfterHours): ?TransactionalEmailSend
    {
        $query = TransactionalEmailSend::query()
            ->where('checkout_session_id', $session->id)
            ->where('code', TransactionalEmail::normalizeCode($code))
            ->where('status', TransactionalEmailSend::STATUS_SENT)
            ->latest('id');

        if ($repeatAfterHours !== null) {
            $query->where('created_at', '>=', now()->subHours(max(1, $repeatAfterHours)));
        }

        return $query->first();
    }

    private function markSkipped(TransactionalEmailSend $send, string $reason): TransactionalEmailSend
    {
        $send->forceFill([
            'status' => TransactionalEmailSend::STATUS_SKIPPED,
            'error' => $reason,
        ])->save();

        return $send;
    }

    private function configureMailer(EmailSetting $settings): void
    {
        $mailer = $settings->mailer ?: 'smtp';
        config()->set('mail.default', $mailer);

        if ($mailer === 'smtp') {
            config()->set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'scheme' => $settings->encryption ?: null,
                'host' => $settings->host,
                'port' => $settings->port ?: 587,
                'username' => $settings->username,
                'password' => $settings->smtp_password,
                'timeout' => null,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ]);
        }

        config()->set('mail.from.address', $settings->from_address ?: config('mail.from.address'));
        config()->set('mail.from.name', $settings->from_name ?: config('mail.from.name'));
        app('mail.manager')->purge($mailer);
    }

    private function mailerReady(EmailSetting $settings): bool
    {
        if (in_array($settings->mailer, ['array', 'log'], true)) {
            return true;
        }

        return $settings->mailer === 'smtp'
            && filled($settings->host)
            && filled($settings->from_address);
    }

    private function checkoutContext(CheckoutSession $session): array
    {
        return [
            ...$this->companyContext($session->company, $session->user),
            'nome' => $session->user?->name ?: $session->lead_name,
            'empresa' => $session->company?->name ?: $session->lead_company,
            'email_acesso' => $session->user?->email ?: $session->lead_email,
            'link_checkout' => $this->frontendUrl('/checkout'),
            'link_pix' => $this->checkoutStatusUrl($session),
            'link_renovacao' => $this->frontendUrl('/checkout'),
            'valor' => $this->money($session->amount_cents),
        ];
    }

    private function companyContext(?MerchantCompany $company, ?User $user): array
    {
        return [
            'nome' => $user?->name ?: 'lojista',
            'empresa' => $company?->name ?: 'sua empresa',
            'codigo_empresa' => $company?->access_code ?: '',
            'email_acesso' => $user?->email ?: '',
            'link_login' => $this->frontendUrl('/login'),
            'link_checkout' => $this->frontendUrl('/checkout'),
            'link_pix' => $this->frontendUrl('/checkout/sucesso'),
            'link_recuperacao' => $this->frontendUrl('/login'),
            'link_renovacao' => $this->frontendUrl('/checkout'),
        ];
    }

    private function checkoutStatusUrl(CheckoutSession $session): string
    {
        return $this->frontendUrl('/checkout/sucesso?'.http_build_query(['ref' => $session->public_reference]));
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function render(string $template, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{{'.$key.'}}'] = (string) $value;
            }
        }

        return strtr($template, $replace);
    }

    private function safeContext(array $context): array
    {
        return Arr::except($context, ['senha', 'password', 'smtp_password', 'token', 'card_token']);
    }

    private function money(int $amountCents): string
    {
        return 'R$ '.number_format($amountCents / 100, 2, ',', '.');
    }
}
