<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversityId extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'university_name',
        'student_id',
        'faculty',
        'department',
        'enrollment_year',
        'graduation_year',
        'status',
        'verified_at',
        'id_card_path',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'verified_at' => 'datetime',
        'enrollment_year' => 'integer',
        'graduation_year' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the university ID.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if university ID is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Check if student is currently enrolled.
     */
    public function isCurrentStudent(): bool
    {
        $currentYear = (int) date('Y');
        return $this->graduation_year >= $currentYear && $this->status === 'verified';
    }

    /**
     * Verify the university ID.
     */
    public function verify(): bool
    {
        $this->status = 'verified';
        $this->verified_at = now();
        return $this->save();
    }
}
