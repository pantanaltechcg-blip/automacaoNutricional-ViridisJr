<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LabelExecutionListingService;
use Illuminate\Contracts\View\View;

class LabelIndexScreenController extends Controller
{
    public function index(LabelExecutionListingService $listingService): View
    {
        return view('rotulonutri.labels', [
            'calculations' => $listingService->paginate(),
        ]);
    }
}
