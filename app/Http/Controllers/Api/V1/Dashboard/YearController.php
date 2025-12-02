<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Year\YearStoreRequest;
use App\Services\Dashboard\YearService;
class YearController extends Controller
{
    protected YearService $yearService;
    public function __construct(YearService $yearService)
    {
        $this->yearService=$yearService;
    }
    public function index()
    {
        $result=$this->yearService->index();
        return response()->json($result,200);
    }
    public function store(YearStoreRequest $request)
    {
        $validated=$request->validated();
        $year=$this->yearService->store($validated);
        return response()->json($year,201);
    }
}
