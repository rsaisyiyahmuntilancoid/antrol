<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskIdByNoRawatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'no_rawat' => 'required|string',
            'taskid' => 'required', // Bisa berupa integer (misal: 3) atau array (misal: [3,4,5])
            'waktu' => 'nullable|integer',
        ];
    }
}
