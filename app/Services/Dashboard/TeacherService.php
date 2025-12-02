<?php
namespace App\Services\Dashboard;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class TeacherService
{
    public function index()
    {
        return Teacher::with(['user:id,first_name,last_name'])->get(['id', 'user_id'])->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->user?->first_name . ' ' . $t->user?->last_name,
                'min_av' => $t->minRequiredAvailabilities()
            ];
        });
    }
    public function store(array $validated, array $sectionSubjects = [])
    {
        return DB::transaction(function () use ($validated, $sectionSubjects) {
            $email = $validated['email'];
            $password = $validated['password'];
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'password' => $password,
                'role_id' => 2
            ]);
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'phone' => $validated['phone']
            ]);
            $assigned = collect();
            $payloadPairs = $sectionSubjects;
            if (is_array($payloadPairs) && !empty($payloadPairs)) {
                foreach ($payloadPairs as $pair) {
                    $sectionId = (int) $pair['section_id'];
                    $subjectId = (int) $pair['subject_id'];
                    $existing = \App\Models\SectionSubject::where('section_id', $sectionId)->where('subject_id', $subjectId)->first();
                    if ($existing) {
                        $existing->teacher_id = $teacher->id;
                        $existing->save();
                        $assigned->push($existing->load(['section', 'subject']));
                    } else {
                        $created = \App\Models\SectionSubject::create([
                            'section_id' => $sectionId,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacher->id
                        ])->load(['section', 'subject']);
                        $assigned->push($created);
                    }
                }
            }
            return [
                'success' => true,
                'message' => __('dashboard/teacher/store/messages.created_successfully'),
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email
                    ],
                    'teacher' => $teacher->load('user'),
                    'section_subjects' => $assigned->values()
                ]
            ];
        });
    }
    public function show($id)
    {
        return Teacher::findOrFail($id);
    }
    public function update(array $data, $id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->update($data);
        return $teacher;
    }
    public function destroy(Teacher $teacher)
    {
        $teacher->delete();
    }
}