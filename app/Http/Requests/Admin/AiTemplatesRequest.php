<?php

namespace App\Http\Requests\Admin;

use App\Services\Ai\PromptBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Template prompt (menu Perilaku). Penanda yang tidak dikenal atau penanda wajib yang hilang ditolak. */
class AiTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return collect(config('ai.templates'))
            ->map(fn ($_, $name) => ['required', 'string', match ($name) { 'system' => 'max:20000', 'asker_guest', 'asker_user' => 'max:4000', default => 'max:1000' }])
            ->all();
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (array_keys(config('ai.templates')) as $name) {
                $template = (string) $this->input($name);

                // Digabung jadi satu pesan: admin hanya melihat satu pesan per kolom.
                $problems = [];
                if ($unknown = PromptBuilder::unknownPlaceholders($name, $template)) {
                    $allowed = config("ai.placeholders.{$name}");
                    $problems[] = 'Penanda tidak dikenal: {'.implode('}, {', $unknown).'}. '
                        .($allowed ? 'Yang bisa dipakai: {'.implode('}, {', $allowed).'}.' : 'Template ini tidak memakai penanda.');
                }
                if ($missing = PromptBuilder::missingPlaceholders($name, $template)) {
                    $problems[] = 'Penanda wajib tidak ada: {'.implode('}, {', $missing).'}.';
                }
                if ($problems) {
                    $validator->errors()->add($name, implode(' ', $problems));
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'system' => 'Template system',
            'test_question' => 'Pertanyaan Tes chat',
            'vision_test' => 'Perintah Tes vision',
            'asker_guest' => 'Data penanya: tamu',
            'asker_user' => 'Data penanya: user login',
        ];
    }
}
