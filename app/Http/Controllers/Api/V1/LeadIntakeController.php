<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\LeadIntakeRequest;
use App\Services\Lead\LeadIntakeService;
use Illuminate\Http\JsonResponse;

class LeadIntakeController extends Controller
{
    public function __construct(private readonly LeadIntakeService $service) {}

    public function store(LeadIntakeRequest $request): JsonResponse
    {
        $result = $this->service->process($request->validated());

        return response()->json([
            'data' => [
                'lead_id' => $result->lead->id,
                'status' => $result->lead->status->value,
                'duplicate' => $result->wasDuplicate,
                'whatsapp_scheduled' => $result->whatsappScheduled,
            ],
        ], $result->wasDuplicate ? 200 : 201);
    }
}
