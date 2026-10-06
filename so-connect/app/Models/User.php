<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $primaryKey = 'user_id';

    protected $table = 'users';

    /**
     * `user_type` values. GUEST is a sign-up applicant: the account exists (so
     * they can confirm their email and follow their request) but carries no
     * organization membership, which is what every officer gate keys on.
     */
    public const TYPE_SUPERADMIN = 1;

    public const TYPE_ADMIN = 2;

    public const TYPE_OFFICER = 3;

    public const TYPE_GUEST = 4;

    protected $attributes = [
        'user_type' => 3,
        'profile_pending' => false,
        // Mirrors the notify_on_login column default so freshly created User
        // instances report the opt-in state without a DB reload.
        'notify_on_login' => true,
    ];

    protected $fillable = [
        'user_email',
        'google_id',
        'user_password',
        'user_type',
        'profile',
        'profile_pending',
        'force_password_change',
        'notify_on_login',
        'email_verified_at',
        'user_created_at',
        'documents_last_seen_at',
    ];

    public $timestamps = false;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'user_password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'user_password'        => 'hashed',
            'profile_pending'      => 'boolean',
            'force_password_change'=> 'boolean',
            'notify_on_login'      => 'boolean',
        ];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** A sign-up applicant whose account is still awaiting admin approval. */
    public function isGuest(): bool
    {
        return (int) $this->user_type === self::TYPE_GUEST;
    }

    public function getAuthPassword()
    {
        return $this->user_password;
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile', 'profile_id');
    }

    public function officers()
    {
        return $this->hasMany(Officer::class, 'user', 'user_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'author', 'user_id');
    }

    public function requests()
    {
        return $this->hasOne(Request::class, 'user', 'user_id');
    }

    public function approvals()
    {
        return $this->belongsTo(Approval::class, 'admin', 'user_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'generated_for', 'user_id');
    }

    public function managedForms()
    {
        return $this->hasMany(Form::class, 'created_by', 'user_id');
    }

    public function uploadedTemplates()
    {
        return $this->hasMany(Template::class, 'uploaded_by', 'user_id');
    }

    public function formSubmissions()
    {
        return $this->hasMany(FormSubmission::class, 'submitted_by', 'user_id');
    }

    public function generatedDocuments()
    {
        return $this->hasMany(GeneratedDocument::class, 'generated_by', 'user_id');
    }
}
