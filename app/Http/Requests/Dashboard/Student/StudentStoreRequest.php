<?php
namespace App\Http\Requests\Dashboard\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StudentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:8',
            'first_name'=>'required|string|max:50|min:2',
            'last_name'=>'required|string|max:50|min:2',
            'father_name'=>'required|string|max:255',
            'mother_name'=>'required|string|max:255',
            'gender'=>['required',Rule::in(['Male','Female','Other'])],
            'birth_day'=>'required|date',
            'location'=>'required|string|min:4',
            'father_number'=>'required|string|max:255',
            'mother_number'=>'required|string|max:255',
            'section_id'=>'required|integer|exists:sections,id'
        ];
    }
}
