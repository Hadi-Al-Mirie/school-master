<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Supervisor\SupervisorStoreRequest;
use App\Services\Dashboard\SupervisorService;
class SupervisorController extends Controller
{
    protected SupervisorService $supervisorService;
    public function __construct(SupervisorService $supervisorService)
    {
        $this->supervisorService = $supervisorService;
    }
    public function index()
    {
        $supervisor = $this->supervisorService->index();
        return response()->json($supervisor, 200);
    }
    public function store(SupervisorStoreRequest $request)
    {
        $validated = $request->validated();
        $result = $this->supervisorService->store($validated);
        return response()->json($result, 201);
    }
    public function show($id)
    {
        $supervisor = $this->supervisorService->show($id);
        return response()->json($supervisor, 200);
    }
}
