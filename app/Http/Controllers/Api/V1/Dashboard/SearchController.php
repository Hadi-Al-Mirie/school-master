<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\search\SearchRequest;
use App\Services\Dashboard\SearchService;
class SearchController extends Controller
{
    protected SearchService $searchService;
    public function __construct(SearchService $searchService)
    {
        $this->searchService=$searchService;
    }
    public function index(SearchRequest $request)
    {
        $validated=$request->validated();
        $result=$this->searchService->search($validated);
        return response()->json($result);
    }
}
