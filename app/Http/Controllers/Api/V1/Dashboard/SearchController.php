<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Employee;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:students,teachers,employees,all',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $q = $data['q'] ?? null;
        $type = $data['type'] ?? 'all';
        $perPage = $data['per_page'] ?? 15;

        if (!$q) {
            return response()->json(['success' => true, 'query' => $q, 'type' => $type, 'results' => []], 200);
        }

        $results = [];

        $searchModel = function(string $modelClass, array $columns, bool $searchUser = true) use ($q, $perPage) {

            $modelInstance = new $modelClass;
            $table = $modelInstance->getTable();

            $existing = Schema::getColumnListing($table);
            $columns = array_values(array_intersect($columns, $existing));

            $query = $modelClass::query();

            $query->where(function($qb) use ($columns, $q, $searchUser, $table) {

                if ($searchUser) {
                    $qb->whereHas('user', function($u) use ($q) {
                        $u->where('first_name', 'like', "%{$q}%")
                          ->orWhere('last_name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                    });
                }

                if (!empty($columns)) {
                    $qb->orWhere(function($sub) use ($columns, $q, $table) {
                        foreach ($columns as $col) {

                            $sub->orWhere("{$table}.{$col}", 'like', "%{$q}%");
                        }
                    });
                }
            });

            return $query->with('user')
                         ->paginate($perPage)
                         ->appends(['q' => request('q'), 'type' => request('type')]);
        };


        if ($type === 'students' || $type === 'all') {
            $results['students'] = $searchModel(Student::class, [
                'first_name', 'last_name',
                'father_name','mother_name','gender','location','birth_day'
            ], true);
        }

        if ($type === 'teachers' || $type === 'all') {
            $results['teachers'] = $searchModel(Teacher::class, [
                'teacher_mobile_number','landline_phone_number','detailed_address','subject'
            ], true);
        }

        if ($type === 'employees' || $type === 'all') {
            $results['employees'] = $searchModel(Employee::class, [
                'employee_mobile_number','landline_phone_number','detailed_address'
            ], true);
        }

        return response()->json([
            'success' => true,
            'query' => $q,
            'type' => $type,
            'results' => $results
        ], 200);
    }
}