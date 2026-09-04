<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\EvaluateTrainingParticipantRequest;
use App\Http\Requests\Training\StoreTrainingParticipantRequest;
use App\Http\Requests\Training\UpdateTrainingParticipantRequest;
use App\Http\Resources\V1\Training\TrainingParticipantResource;
use App\Models\TrainingParticipant;
use App\Services\Training\TrainingParticipantService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TrainingParticipantController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly TrainingParticipantService $participantService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:training.participant.view',   only: ['index', 'show', 'history']),
            new Middleware('permission:training.participant.register',   only: ['store']),
            new Middleware('permission:training.participant.update',   only: ['update']),
            new Middleware('permission:training.participant.delete',    only: ['destroy']),
            new Middleware('permission:training.evaluation.create',    only: ['evaluate']),
        ];
    }

    /**
     * Display a listing of participants.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $trainingId = $request->filled('training_id')
            ? $request->integer('training_id')
            : null;

        $employeeId = $request->filled('employee_id')
            ? $request->integer('employee_id')
            : null;

        $participants = $this->participantService->paginate(
            perPage: $perPage,
            trainingId: $trainingId,
            employeeId: $employeeId,
        );

        return ApiResponse::success(
            data: TrainingParticipantResource::collection(
                $participants
            ),
            message: 'Daftar peserta training berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $participants->currentPage(),
                    'last_page' => $participants->lastPage(),
                    'per_page' => $participants->perPage(),
                    'total' => $participants->total(),
                    'from' => $participants->firstItem(),
                    'to' => $participants->lastItem(),
                ],
            ],
        );
    }

    /**
     * Register participant.
     */
    public function store(
        StoreTrainingParticipantRequest $request
    ): JsonResponse {
        $participant = $this->participantService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: TrainingParticipantResource::make($participant),
            message: 'Peserta berhasil didaftarkan ke training.',
        );
    }

    /**
     * Display participant.
     */
    public function show(
        TrainingParticipant $trainingParticipant
    ): JsonResponse {
        return ApiResponse::success(
            data: TrainingParticipantResource::make(
                $trainingParticipant
            ),
            message: 'Detail peserta training berhasil diambil.',
        );
    }

    /**
     * Update participant.
     */
    public function update(
        UpdateTrainingParticipantRequest $request,
        TrainingParticipant $trainingParticipant
    ): JsonResponse {
        $participant = $this->participantService->update(
            $trainingParticipant,
            $request->validated(),
        );

        return ApiResponse::success(
            data: TrainingParticipantResource::make($participant),
            message: 'Data peserta training berhasil diperbarui.',
        );
    }

    /**
     * Evaluate participant.
     */
    public function evaluate(
        EvaluateTrainingParticipantRequest $request,
        TrainingParticipant $trainingParticipant
    ): JsonResponse {
        $participant = $this->participantService->evaluate(
            $trainingParticipant,
            (float) $request->validated('score'),
        );

        return ApiResponse::success(
            data: TrainingParticipantResource::make($participant),
            message: 'Evaluasi peserta training berhasil disimpan.',
        );
    }

    public function history(
        Request $request,
        int $employeeId
    ): JsonResponse {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $participants = $this->participantService->history(
            employeeId: $employeeId,
            perPage: $perPage,
        );

        return ApiResponse::success(
            data: TrainingParticipantResource::collection(
                $participants
            ),
            message: 'Riwayat training employee berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $participants->currentPage(),
                    'last_page' => $participants->lastPage(),
                    'per_page' => $participants->perPage(),
                    'total' => $participants->total(),
                    'from' => $participants->firstItem(),
                    'to' => $participants->lastItem(),
                ],
            ],
        );
    }

    /**
     * Remove participant.
     */
    public function destroy(
        TrainingParticipant $trainingParticipant
    ): JsonResponse {
        $this->participantService->delete($trainingParticipant);

        return ApiResponse::success(
            data: null,
            message: 'Peserta berhasil dihapus dari training.',
        );
    }
}
