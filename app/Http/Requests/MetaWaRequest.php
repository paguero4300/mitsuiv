<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetaWaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient' => 'required|string|min:10',
            'message' => 'required|string|max:4096'
        ];
    }
}