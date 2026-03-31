<?php

namespace App\Http\Controllers;

use App\Services\Quotes\QuoteDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(QuoteDashboardService $quoteDashboardService): View
    {
        return view('dashboard', $quoteDashboardService->getData());
    }
}
