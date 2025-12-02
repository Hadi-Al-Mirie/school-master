<?php
namespace App\Services\Dashboard;
use App\Models\Supervisor;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class HomeService
{
    public function index()
    {
        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalSupervisors = Supervisor::count();
        $genderCount = Student::selectRaw('gender, COUNT(*) as count')->groupBy('gender')->pluck('count', 'gender');
        $classDistribution = DB::table('students')->join('classrooms', 'students.classroom_id', '=', 'classrooms.id')->select('classrooms.name as classroom_name', DB::raw('count(*) as count'))->groupBy('classroom_name')->pluck('count', 'classroom_name');
        return response()->json([
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'total_supervisors' => $totalSupervisors,
            'gender_distribution' => [
                'male' => $genderCount['male'],
                'female' => $genderCount['female'],
            ],
            'class_distribution' => $classDistribution,
            'timestamp' => Carbon::now()->format('H:i:s'),
            'date' => Carbon::now()->toDateString(),
        ]);
    }
}