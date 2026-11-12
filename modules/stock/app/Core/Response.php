<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        /** @var array<string, string> */
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($payload === false ? '{}' : $payload, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function csv(string $filename, string $content): self
    {
        $filename = self::filename($filename, 'export.csv');

        return new self("\xEF\xBB\xBF" . $content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, max-age=0',
        ]);
    }

    public static function xls(string $filename, string $content): self
    {
        $filename = self::filename($filename, 'export.xls');

        return new self($content, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, max-age=0',
        ]);
    }

    public static function pdf(string $filename, string $content): self
    {
        $filename = self::filename($filename, 'export.pdf');

        return new self($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, max-age=0',
        ]);
    }

    private static function filename(string $filename, string $fallback): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', basename($filename)) ?: $fallback;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }
}
