<?php
namespace App\Http\Requests\Dashboard\Year;
use Illuminate\Foundation\Http\FormRequest;
class YearStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date'
        ];
    }
}