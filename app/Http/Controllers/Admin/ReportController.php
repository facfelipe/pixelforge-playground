<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildUsageReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, BuildUsageReport $buildUsageReport): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $from = $request->date('from')?->startOfDay() ?? now()->subDays(29)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        return Inertia::render('Admin/Report', $buildUsageReport->handle($from, $to));
    }
}
