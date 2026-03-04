<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreSiteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255', 'url'],
            'background_color' => ['required', 'string', 'max:255', 'hex_color'],
            'icon' => ['required', File::types(['png', 'jpg', 'jpeg', 'svg'])],
            'no_padding' => ['sometimes', 'boolean'],
        ];
    }
}
