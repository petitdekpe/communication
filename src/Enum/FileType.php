<?php

declare(strict_types=1);

namespace App\Enum;

enum FileType: string
{
    case Pdf = 'pdf';
    case Word = 'word';
    case Excel = 'excel';
    case Zip = 'zip';
    case Other = 'other';

    public static function fromMimeType(?string $mimeType): self
    {
        return match (true) {
            'application/pdf' === $mimeType => self::Pdf,
            \in_array($mimeType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true) => self::Word,
            \in_array($mimeType, ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true) => self::Excel,
            \in_array($mimeType, ['application/zip', 'application/x-zip-compressed'], true) => self::Zip,
            default => self::Other,
        };
    }

    public function labelKey(): string
    {
        return 'file_type.'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pdf => 'ri:file-pdf-2-line',
            self::Word => 'ri:file-word-2-line',
            self::Excel => 'ri:file-excel-2-line',
            self::Zip => 'ri:file-zip-line',
            self::Other => 'ri:file-text-line',
        };
    }
}
