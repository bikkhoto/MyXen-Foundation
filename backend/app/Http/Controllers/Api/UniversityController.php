<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UniversityId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="UniversityId",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="university_name", type="string", example="MIT"),
 *     @OA\Property(property="student_id", type="string", example="STU123456"),
 *     @OA\Property(property="faculty", type="string", example="Engineering"),
 *     @OA\Property(property="department", type="string", example="Computer Science"),
 *     @OA\Property(property="enrollment_year", type="integer", example=2020),
 *     @OA\Property(property="graduation_year", type="integer", example=2024),
 *     @OA\Property(property="status", type="string", enum={"pending", "verified", "rejected", "expired"})
 * )
 */
class UniversityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/university/id",
     *     tags={"University"},
     *     summary="Get university ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="University ID retrieved")
     * )
     */
    public function show(Request $request)
    {
        $universityId = UniversityId::where('user_id', $request->user()->id)->first();

        if (!$universityId) {
            return $this->error('University ID not found', 404);
        }

        return $this->success($universityId);
    }

    /**
     * @OA\Post(
     *     path="/university/id",
     *     tags={"University"},
     *     summary="Register university ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"university_name", "student_id"},
     *                 @OA\Property(property="university_name", type="string"),
     *                 @OA\Property(property="student_id", type="string"),
     *                 @OA\Property(property="faculty", type="string"),
     *                 @OA\Property(property="department", type="string"),
     *                 @OA\Property(property="enrollment_year", type="integer"),
     *                 @OA\Property(property="graduation_year", type="integer"),
     *                 @OA\Property(property="id_card", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="University ID registered")
     * )
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'university_name' => 'required|string|max:255',
            'student_id' => 'required|string|max:50',
            'faculty' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'enrollment_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'graduation_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'id_card' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = $request->user();

        // Check if user already has a university ID
        $existing = UniversityId::where('user_id', $user->id)->first();
        if ($existing && $existing->status !== 'rejected') {
            return $this->error('You already have a university ID on file', 400);
        }

        // Store ID card if uploaded
        $idCardPath = null;
        if ($request->hasFile('id_card')) {
            $idCardPath = $request->file('id_card')->store('university-ids', 'local');
        }

        $universityId = UniversityId::create([
            'user_id' => $user->id,
            'university_name' => $validated['university_name'],
            'student_id' => $validated['student_id'],
            'faculty' => $validated['faculty'] ?? null,
            'department' => $validated['department'] ?? null,
            'enrollment_year' => $validated['enrollment_year'] ?? null,
            'graduation_year' => $validated['graduation_year'] ?? null,
            'id_card_path' => $idCardPath,
            'status' => 'pending',
        ]);

        // Update user's university ID reference
        $user->update(['university_id' => $validated['student_id']]);

        return $this->success($universityId, 'University ID registered successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/university/id",
     *     tags={"University"},
     *     summary="Update university ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="faculty", type="string"),
     *             @OA\Property(property="department", type="string"),
     *             @OA\Property(property="graduation_year", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="University ID updated")
     * )
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'faculty' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'graduation_year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 10),
        ]);

        $universityId = UniversityId::where('user_id', $request->user()->id)->firstOrFail();

        $universityId->update($validated);

        return $this->success($universityId, 'University ID updated successfully');
    }

    /**
     * @OA\Get(
     *     path="/university/verify/{student_id}",
     *     tags={"University"},
     *     summary="Verify student ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="student_id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Verification result")
     * )
     */
    public function verify($student_id)
    {
        $universityId = UniversityId::where('student_id', $student_id)
            ->where('status', 'verified')
            ->first();

        if (!$universityId) {
            return $this->success([
                'verified' => false,
                'message' => 'Student ID not found or not verified',
            ]);
        }

        return $this->success([
            'verified' => true,
            'university_name' => $universityId->university_name,
            'is_current_student' => $universityId->isCurrentStudent(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/university/benefits",
     *     tags={"University"},
     *     summary="Get student benefits",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Benefits retrieved")
     * )
     */
    public function benefits(Request $request)
    {
        $universityId = UniversityId::where('user_id', $request->user()->id)
            ->where('status', 'verified')
            ->first();

        if (!$universityId) {
            return $this->success([
                'eligible' => false,
                'message' => 'University ID not verified',
                'benefits' => [],
            ]);
        }

        return $this->success([
            'eligible' => true,
            'is_current_student' => $universityId->isCurrentStudent(),
            'benefits' => [
                'reduced_fees' => [
                    'name' => 'Reduced Transaction Fees',
                    'description' => '50% off transaction fees for verified students',
                    'active' => $universityId->isCurrentStudent(),
                ],
                'cashback' => [
                    'name' => 'Student Cashback',
                    'description' => 'Earn 2% cashback on campus purchases',
                    'active' => $universityId->isCurrentStudent(),
                ],
                'priority_support' => [
                    'name' => 'Priority Support',
                    'description' => 'Access to priority customer support',
                    'active' => true,
                ],
            ],
        ]);
    }
}
