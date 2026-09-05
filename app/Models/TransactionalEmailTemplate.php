<?php

namespace App\Models;

use App\Support\TransactionalEmailTemplateCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TransactionalEmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'locale',
        'subject',
        'html_body',
        'text_body',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if (!TransactionalEmailTemplateCatalog::supports(
                (string) $template->template_key,
                (string) $template->locale
            )) {
                throw new InvalidArgumentException('Unsupported transactional email template identity.');
            }
        });
    }
}
