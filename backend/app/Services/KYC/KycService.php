<?php

namespace App\Services\KYC;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class KycService
{
    /**
     * Submit a KYC document.
     */
    public function submitDocument(
        User $user,
        string $documentType,
        string $filePath,
        ?string $documentNumber = null,
        ?string $expiresAt = null
    ): KycDocument {
        return KycDocument::create([
            'user_id' => $user->id,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'file_path' => $filePath,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Get user's KYC status.
     */
    public function getKycStatus(User $user): array
    {
        $documents = $user->kycDocuments()->get();
        $kycLevels = config('kyc.levels');
        $currentLevel = $kycLevels[$user->kyc_level] ?? $kycLevels[0];

        return [
            'level' => $user->kyc_level,
            'level_name' => $currentLevel['name'],
            'daily_limit' => $currentLevel['daily_limit'],
            'monthly_limit' => $currentLevel['monthly_limit'],
            'documents' => $documents,
            'can_upgrade' => $this->canUpgrade($user),
        ];
    }

    /**
     * Check if user can upgrade KYC level.
     */
    public function canUpgrade(User $user): bool
    {
        $kycLevels = config('kyc.levels');
        $nextLevel = $user->kyc_level + 1;

        if (!isset($kycLevels[$nextLevel])) {
            return false;
        }

        $requiredDocs = $kycLevels[$nextLevel]['required_documents'];
        $verifiedDocs = $user->kycDocuments()
            ->where('status', 'verified')
            ->pluck('document_type')
            ->toArray();

        return empty(array_diff($requiredDocs, $verifiedDocs));
    }

    /**
     * Upgrade user's KYC level.
     */
    public function upgradeLevel(User $user): bool
    {
        if (!$this->canUpgrade($user)) {
            return false;
        }

        $user->kyc_level = $user->kyc_level + 1;
        return $user->save();
    }

    /**
     * Approve a KYC document.
     */
    public function approveDocument(KycDocument $document): bool
    {
        $document->approve();

        // Check if user can be upgraded
        $user = $document->user;
        if ($this->canUpgrade($user)) {
            $this->upgradeLevel($user);
        }

        return true;
    }

    /**
     * Reject a KYC document.
     */
    public function rejectDocument(KycDocument $document, string $reason): bool
    {
        return $document->reject($reason);
    }

    /**
     * Delete a pending document.
     */
    public function deleteDocument(KycDocument $document): bool
    {
        if ($document->status !== 'pending') {
            return false;
        }

        Storage::disk('local')->delete($document->file_path);
        return $document->delete();
    }

    /**
     * Get required documents for a level.
     */
    public function getRequiredDocuments(int $level): array
    {
        $kycLevels = config('kyc.levels');
        return $kycLevels[$level]['required_documents'] ?? [];
    }
}
