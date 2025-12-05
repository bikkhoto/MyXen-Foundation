<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialProgram;
use App\Models\ProgramParticipation;
use App\Services\MYXN\FinancialProgramService;
use App\Services\MYXN\TracingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FinancialProgramController extends Controller
{
    public function __construct(
        protected FinancialProgramService $programService,
        protected TracingService $tracingService
    ) {}

    /**
     * List all available financial programs
     */
    public function index(Request $request): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.index');

        try {
            $query = FinancialProgram::query();

            // Filter by type
            if ($request->has('type')) {
                $query->ofType($request->type);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                $query->active();
            }

            // Filter featured
            if ($request->boolean('featured')) {
                $query->featured();
            }

            $programs = $query->orderBy('is_featured', 'desc')
                ->orderBy('apy_rate', 'desc')
                ->get();

            $this->tracingService->recordEvent($span, 'programs_fetched', [
                'count' => $programs->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $programs,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch programs',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Get a specific program details
     */
    public function show(int $id): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.show', [
            'program_id' => $id,
        ]);

        try {
            $program = FinancialProgram::with(['activeParticipations' => function ($query) {
                $query->select('financial_program_id')
                    ->selectRaw('COUNT(*) as total_participants')
                    ->selectRaw('SUM(amount) as total_staked')
                    ->groupBy('financial_program_id');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $program,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Program not found',
            ], 404);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Enroll in a financial program
     */
    public function enroll(Request $request, int $programId): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.enroll', [
            'program_id' => $programId,
            'user_id' => $request->user()->id,
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'wallet_address' => 'required|string|size:44',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $program = FinancialProgram::findOrFail($programId);
            $user = $request->user();

            // Check if program is active
            if (!$program->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This program is not currently active',
                ], 400);
            }

            // Check capacity
            if (!$program->hasCapacity()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This program has reached maximum capacity',
                ], 400);
            }

            // Check amount limits
            if (!$program->isAmountValid($request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => "Amount must be between {$program->min_amount} and {$program->max_amount} MYXN",
                ], 400);
            }

            // Check for existing active participation
            $existingParticipation = ProgramParticipation::where('user_id', $user->id)
                ->where('financial_program_id', $programId)
                ->whereIn('status', ['pending', 'active'])
                ->first();

            if ($existingParticipation) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active participation in this program',
                ], 400);
            }

            // Create participation
            $participation = $this->programService->enrollInProgram(
                $user->id,
                $programId,
                $request->amount,
                $request->wallet_address
            );

            $this->tracingService->recordFinancialOperation($span, 'enroll', [
                'program_id' => $programId,
                'amount' => $request->amount,
                'participation_id' => $participation->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully enrolled in program',
                'data' => $participation,
            ], 201);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll in program',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Withdraw from a program
     */
    public function withdraw(Request $request, int $participationId): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.withdraw', [
            'participation_id' => $participationId,
            'user_id' => $request->user()->id,
        ]);

        try {
            $participation = ProgramParticipation::where('id', $participationId)
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['active', 'matured'])
                ->firstOrFail();

            // Calculate withdrawal amount
            $withdrawalAmount = $participation->getTotalValue();
            $penalty = 0;

            // Apply early withdrawal penalty if applicable
            if ($participation->isEarlyWithdrawal()) {
                $penaltyRate = $participation->program->early_withdrawal_penalty / 100;
                $penalty = $withdrawalAmount * $penaltyRate;
                $withdrawalAmount -= $penalty;

                $this->tracingService->recordEvent($span, 'early_withdrawal_penalty', [
                    'penalty_rate' => $penaltyRate,
                    'penalty_amount' => $penalty,
                ]);
            }

            // Process withdrawal
            $result = $this->programService->processWithdrawal($participation, $withdrawalAmount);

            $this->tracingService->recordFinancialOperation($span, 'withdraw', [
                'participation_id' => $participationId,
                'withdrawal_amount' => $withdrawalAmount,
                'penalty' => $penalty,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal processed successfully',
                'data' => [
                    'withdrawal_amount' => $withdrawalAmount,
                    'penalty_applied' => $penalty,
                    'tx_hash' => $result['tx_hash'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process withdrawal',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Claim rewards from participation
     */
    public function claimRewards(Request $request, int $participationId): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.claim_rewards', [
            'participation_id' => $participationId,
            'user_id' => $request->user()->id,
        ]);

        try {
            $participation = ProgramParticipation::where('id', $participationId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->withPendingRewards()
                ->firstOrFail();

            $claimableAmount = $participation->getClaimableRewards();

            if ($claimableAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rewards available to claim',
                ], 400);
            }

            // Process reward claim
            $result = $this->programService->claimRewards($participation);

            $this->tracingService->recordFinancialOperation($span, 'claim_rewards', [
                'participation_id' => $participationId,
                'claimed_amount' => $claimableAmount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rewards claimed successfully',
                'data' => [
                    'claimed_amount' => $claimableAmount,
                    'tx_hash' => $result['tx_hash'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to claim rewards',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Get user's participations
     */
    public function myParticipations(Request $request): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.my_participations', [
            'user_id' => $request->user()->id,
        ]);

        try {
            $participations = ProgramParticipation::with('program')
                ->forUser($request->user()->id)
                ->orderByRaw("FIELD(status, 'active', 'matured', 'pending', 'withdrawn', 'cancelled')")
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate current rewards for active participations
            $participations->each(function ($participation) {
                if ($participation->isActive()) {
                    $participation->current_rewards = $participation->calculateCurrentRewards();
                }
            });

            return response()->json([
                'success' => true,
                'data' => $participations,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch participations',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Get participation details
     */
    public function participationDetails(Request $request, int $participationId): JsonResponse
    {
        $span = $this->tracingService->startSpan('financial_programs.participation_details', [
            'participation_id' => $participationId,
        ]);

        try {
            $participation = ProgramParticipation::with(['program', 'transactions'])
                ->where('id', $participationId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            // Add computed fields
            $participation->current_rewards = $participation->calculateCurrentRewards();
            $participation->days_until_maturity = $participation->getDaysUntilMaturity();
            $participation->total_value = $participation->getTotalValue();

            return response()->json([
                'success' => true,
                'data' => $participation,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Participation not found',
            ], 404);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }
}
