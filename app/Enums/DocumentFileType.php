<?php

namespace App\Enums;

enum DocumentFileType: string
{
    case Pdf = 'pdf';
    case Image = 'image';
    case Word = 'word';
    case Excel = 'excel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Image => __('Image'),
            self::Word => 'Word',
            self::Excel => 'Excel',
            self::Other => __('Other'),
        };
    }

    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_contains($mime, 'pdf') => self::Pdf,
            str_contains($mime, 'image/') => self::Image,
            str_contains($mime, 'word')
                || str_contains($mime, 'document') => self::Word,
            str_contains($mime, 'excel')
                || str_contains($mime, 'spreadsheet')
                || str_contains($mime, 'csv') => self::Excel,
            default => self::Other,
        };
    }
}
