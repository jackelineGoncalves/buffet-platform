<?php

namespace App\Http\Requests\Diner;

use Illuminate\Foundation\Http\FormRequest;

class StoreDinerSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests' => ['required', 'integer', 'min:1'],
        ];
    }
}
