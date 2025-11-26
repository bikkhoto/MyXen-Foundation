<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="KycDocument",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="document_type", type="string", example="id_card"),
 *     @OA\Property(property="document_number", type="string", example="AB123456"),
 *     @OA\Property(property="status", type="string", enum={"pending", "verified", "rejected", "expired"}),
 *     @OA\Property(property="verified_at", type="string", format="date-time"),
 *     @OA\Property(property="expires_at", type="string", format="date")
 * )
 */
class KycController extends Controller
{
    /**
     * @OA\Get(
     *     path="/kyc/status",
     *     tags={"KYC"},
     *     summary="Get KYC status",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="KYC status retrieved")
     * )
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $documents = $user->kycDocuments()->get();

        $kycConfig = config('kyc.levels');
        $currentLevel = $kycConfig[$user->kyc_level] ?? $kycConfig[0];

        return $this->success([
            'kyc_level' => $user->kyc_level,
            'level_name' => $currentLevel['name'],
            'daily_limit' => $currentLevel['daily_limit'],
            'monthly_limit' => $currentLevel['monthly_limit'],
            'documents' => $documents,
            'next_level_requirements' => $this->getNextLevelRequirements($user->kyc_level),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/kyc/documents",
     *     tags={"KYC"},
     *     summary="List KYC documents",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Documents retrieved")
     * )
     */
    public function documents(Request $request)
    {
        $documents = $request->user()->kycDocuments()->get();

        return $this->success($documents);
    }

    /**
     * @OA\Post(
     *     path="/kyc/documents",
     *     tags={"KYC"},
     *     summary="Submit KYC document",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"document_type", "document"},
     *                 @OA\Property(property="document_type", type="string"),
     *                 @OA\Property(property="document_number", type="string"),
     *                 @OA\Property(property="document", type="string", format="binary"),
     *                 @OA\Property(property="expires_at", type="string", format="date")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Document submitted")
     * )
     */
    public function submitDocument(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|string|in:' . implode(',', array_keys(config('kyc.document_types'))),
            'document_number' => 'nullable|string|max:50',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'expires_at' => 'nullable|date|after:today',
        ]);

        // Store file
        $path = $request->file('document')->store('kyc-documents', 'local');

        $document = KycDocument::create([
            'user_id' => $request->user()->id,
            'document_type' => $validated['document_type'],
            'document_number' => $validated['document_number'] ?? null,
            'file_path' => $path,
            'status' => 'pending',
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return $this->success($document, 'Document submitted successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/kyc/documents/{id}",
     *     tags={"KYC"},
     *     summary="Get document details",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document retrieved")
     * )
     */
    public function showDocument(Request $request, $id)
    {
        $document = KycDocument::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        return $this->success($document);
    }

    /**
     * @OA\Delete(
     *     path="/kyc/documents/{id}",
     *     tags={"KYC"},
     *     summary="Delete pending document",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document deleted")
     * )
     */
    public function deleteDocument(Request $request, $id)
    {
        $document = KycDocument::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Delete file
        Storage::disk('local')->delete($document->file_path);

        $document->delete();

        return $this->success(null, 'Document deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/kyc/requirements",
     *     tags={"KYC"},
     *     summary="Get KYC requirements",
     *     @OA\Response(response=200, description="Requirements retrieved")
     * )
     */
    public function requirements()
    {
        return $this->success([
            'levels' => config('kyc.levels'),
            'document_types' => config('kyc.document_types'),
        ]);
    }

    /**
     * Get requirements for next KYC level.
     */
    private function getNextLevelRequirements(int $currentLevel): ?array
    {
        $levels = config('kyc.levels');
        $nextLevel = $currentLevel + 1;

        if (!isset($levels[$nextLevel])) {
            return null;
        }

        return [
            'level' => $nextLevel,
            'name' => $levels[$nextLevel]['name'],
            'required_documents' => $levels[$nextLevel]['required_documents'],
            'daily_limit' => $levels[$nextLevel]['daily_limit'],
            'monthly_limit' => $levels[$nextLevel]['monthly_limit'],
        ];
    }
}
