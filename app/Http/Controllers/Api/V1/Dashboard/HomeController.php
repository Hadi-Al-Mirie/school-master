<?php
namespace App\Http\Controllers\Api\V1\Dashboard;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\HomeService;
class HomeController extends Controller
{
    protected HomeService $homeService;
    public function __construct(HomeService $homeService)
    {
        $this->homeService=$homeService;
    }
    public function index()
    {
        return $this->homeService->index();
    }
}
