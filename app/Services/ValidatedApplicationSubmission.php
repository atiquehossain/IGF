<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

final class ValidatedApplicationSubmission
{
    /**
     * @param list<array{
     *     application_form_field_id:int,
     *     value_text:?string,
     *     value_number:float|int|string|null,
     *     value_date:?string,
     *     value_boolean:?bool,
     *     value_json:?array
     * }> $answers
     * @param array<string, mixed> $values
     * @param array<int, UploadedFile> $files
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly array $answers,
        public readonly array $values,
        public readonly ?UploadedFile $cv,
        public readonly array $files,
    ) {
    }
}
