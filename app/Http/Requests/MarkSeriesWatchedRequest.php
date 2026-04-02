<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkSeriesWatchedRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer'],
            'poster_path' => ['nullable', 'string', 'max:255', 'regex:/^\/[a-zA-Z0-9._-]+\.(jpg|png)$/'],
            'imdb_id' => ['nullable', 'string', 'max:20', 'regex:/^tt\d+$/'],
            'tvdb_id' => ['nullable', 'integer'],
            'episodes' => ['required', 'array', 'min:1', 'max:5000'],
            'episodes.*.tmdb_id' => ['required', 'integer', 'min:1'],
            'episodes.*.title' => ['required', 'string', 'max:255'],
            'episodes.*.season_number' => ['required', 'integer', 'min:1'],
            'episodes.*.episode_number' => ['required', 'integer', 'min:1'],
        ];
    }
}
