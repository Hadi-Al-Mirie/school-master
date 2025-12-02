<?php
namespace App\Services\Dashboard;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class SupervisorService
{
    public function index()
    {
        return Supervisor::all();
    }
    public function store(array $validated)
    {
        return DB::transaction(function()use($validated){
            $email=$validated['email'];
            $password=$validated['password'];
            $user=User::create([
                'first_name'=>$validated['first_name'],
                'last_name'=>$validated['last_name'],
                'email'=>$email,
                'password'=>$password,
                'role_id'=>4
            ]);
            $supervisor=Supervisor::create([
                'user_id'=>$user->id,
                'phone'=>$validated['phone'],
                'stage_id'=>$validated['stage_id']
            ]);
            return [
                'success'=>true,
                'message'=>'Supervisor created successfully',
                'data'=>[
                    'supervisor'=>$supervisor,
                    'user_account'=>[
                        'email'=>$email
                    ]
                ]
            ];
        });
    }
    public function show($id)
    {
        return Supervisor::findOrFail($id);
    }
}
