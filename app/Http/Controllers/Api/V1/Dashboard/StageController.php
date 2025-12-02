<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\StageService;
use Illuminate\Http\Request;
class StageController extends Controller
{
    protected StageService $stageService;
    public function __construct(StageService $stageService)
    {
        $this->stageService=$stageService;
    }
    public function index(Request $request)
    {
        $result=$this->stageService->index();
        return response()->json($result,200);
    }
    public function indexStagesOnly()
    {
        $result=$this->stageService->indexStagesOnly();
        return response()->json($result,200);
    }
}
