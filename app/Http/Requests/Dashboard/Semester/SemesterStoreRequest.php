<?php
namespace App\Http\Requests\Dashboard\Semester;
use Illuminate\Foundation\Http\FormRequest;
class SemesterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'year_id'=>'required|integer|exists:years,id',
            'name'=>'required|min:1|max:10',
            'start_date'=>'required|date',
            'end_date'=>'required|date|after:start_date'
        ];
    }
}
