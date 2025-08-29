<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\teacher\StoreTeacherRequest;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with(['user:id,first_name,last_name'])
            ->get(['id', 'user_id']) // keep user_id only to resolve the relation
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'first_name' => $t->user?->first_name,
                    'last_name' => $t->user?->last_name,
                    'min_av' => $t->minRequiredAvailabilities()
                ];
            });

        return response()->json($teachers, 200);
    }

    public function store(StoreTeacherRequest $request)
    {
        $validated = $request;
        $email = $request->email;
        $password = $request->password;
        DB::beginTransaction();
        try {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'password' => $password,
                'role_id' => 2,
            ]);
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
            ]);
            $assigned = collect();
            $payloadPairs = $request->input('section_subjects', []);
            if (is_array($payloadPairs) && !empty($payloadPairs)) {
                foreach ($payloadPairs as $pair) {
                    $sectionId = (int) $pair['section_id'];
                    $subjectId = (int) $pair['subject_id'];
                    $existing = \App\Models\SectionSubject::where('section_id', $sectionId)
                        ->where('subject_id', $subjectId)
                        ->first();
                    if ($existing) {
                        $existing->teacher_id = $teacher->id;
                        $existing->save();
                        $assigned->push($existing->load(['section', 'subject']));
                    } else {
                        $created = \App\Models\SectionSubject::create([
                            'section_id' => $sectionId,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacher->id,
                        ])->load(['section', 'subject']);
                        $assigned->push($created);
                    }
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => __('dashboard/teacher/store/messages.created_successfully'),
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                    ],
                    'teacher' => $teacher->load('user'),
                    'section_subjects' => $assigned->values(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Create teacher failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('dashboard/teacher/store/messages.unexpected_error'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function show($id)
    {
        $teacher = Teacher::findOrFail($id);
        return response()->json($teacher, 200);
    }

    public function update(Request $request, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->update($request->all());
        return response()->json($teacher, 200);
    }

    public function destroy(Teacher $teacher)
    {
        $teacher->delete();
        return response()->json(['message' => 'Teacher deleted'], 200);
    }

}
