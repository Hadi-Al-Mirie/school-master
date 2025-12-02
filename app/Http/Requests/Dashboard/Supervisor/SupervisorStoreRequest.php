<?php
namespace App\Http\Requests\Dashboard\Supervisor;
use Illuminate\Foundation\Http\FormRequest;
class SupervisorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'first_name'=>'required|string|max:150',
            'last_name'=>'required|string|max:150',
            'email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:8',
            'phone'=>'required|string|min:10|max:20',
            'stage_id'=>'required|integer|exists:stages,id'
        ];
    }
}
