<?php

namespace App\Http\Requests\Diner;

use App\Models\Dish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDinerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'integer', 'exists:dishes,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.allergen_ids' => ['nullable', 'array'],
            'items.*.allergen_ids.*' => ['integer', 'exists:allergens,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);
            $dishIds = collect($items)->pluck('dish_id')->filter()->unique();

            if ($dishIds->isEmpty()) {
                return;
            }

            $unavailable = Dish::whereIn('id', $dishIds)
                ->where('is_available', false)
                ->pluck('id');

            foreach ($items as $index => $item) {
                if (isset($item['dish_id']) && $unavailable->contains($item['dish_id'])) {
                    $validator->errors()->add("items.$index.dish_id", 'This dish is not currently available.');
                }
            }
        });
    }
}
