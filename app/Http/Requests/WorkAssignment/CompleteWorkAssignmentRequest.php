<?php

namespace App\Http\Requests\WorkAssignment;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWorkAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['produced_quantity' => ['required', 'numeric', 'min:0'], 'good_quantity' => ['required', 'numeric', 'min:0'], 'defective_quantity' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
