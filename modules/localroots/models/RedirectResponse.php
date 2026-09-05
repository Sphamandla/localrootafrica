<?php

namespace modules\localroots\models;

use craft\commerce\base\RequestResponseInterface;

class RedirectResponse implements RequestResponseInterface
{
    public function __construct(
        private bool $successful = false,
        private bool $redirect = false,
        private string $redirectMethod = 'POST',
        private string $redirectUrl = '',
        private array $redirectData = [],
        private string $reference = '',
        private string $code = '',
        private string $message = '',
        private mixed $data = null,
        private bool $processing = false,
    ) {
    }

    public static function redirectPost(string $url, array $data, string $reference = ''): self
    {
        return new self(
            successful: false,
            redirect: true,
            redirectMethod: 'POST',
            redirectUrl: $url,
            redirectData: $data,
            reference: $reference,
            processing: true,
        );
    }

    public static function redirectGet(string $url, string $reference = ''): self
    {
        return new self(
            successful: false,
            redirect: true,
            redirectMethod: 'GET',
            redirectUrl: $url,
            reference: $reference,
            processing: true,
        );
    }

    public static function success(string $reference = '', mixed $data = null): self
    {
        return new self(successful: true, reference: $reference, data: $data);
    }

    public static function failed(string $message, string $code = 'payment.failed'): self
    {
        return new self(successful: false, code: $code, message: $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function isProcessing(): bool
    {
        return $this->processing;
    }

    public function isRedirect(): bool
    {
        return $this->redirect;
    }

    public function getRedirectMethod(): string
    {
        return $this->redirectMethod;
    }

    public function getRedirectData(): array
    {
        return $this->redirectData;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getTransactionReference(): string
    {
        return $this->reference;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function redirect(): void
    {
    }
}
