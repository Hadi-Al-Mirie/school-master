<?php
namespace App\Http\Requests\Dashboard\Auth;
use Illuminate\Foundation\Http\FormRequest;
class SendPasswordResetLinkRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'email'=>'required|email|exists:users,email'
        ];
    }
}
