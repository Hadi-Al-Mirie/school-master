<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Semester\SemesterStoreRequest;
use App\Models\Semester;
class SemesterController extends Controller
{
    public function index()
    {
        return Semester::all();
    }
    public function store(SemesterStoreRequest $request)
    {
        $data=$request->validated();
        $semester=Semester::create($data);
        return response()->json($semester,201);
    }
}
