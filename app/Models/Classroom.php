<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'teacher_id',
    ];

    /**
     * Get the teacher of this classroom.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the members of this classroom.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_members')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Get the class memberships for this classroom.
     */
    public function classMembers(): HasMany
    {
        return $this->hasMany(ClassMember::class);
    }

    /**
     * Get the assignments in this classroom.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get the messages in this classroom.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}