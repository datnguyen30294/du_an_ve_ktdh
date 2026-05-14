<?php

namespace App\Modules\PMC\Account\Models;

use App\Common\Contracts\StorageServiceInterface;
use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Enums\Gender;
use App\Modules\PMC\Account\Traits\HasPermissions;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPermissions, Notifiable, SoftDeletes;

    protected $table = 'accounts';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_code',
        'gender',
        'avatar_path',
        'job_title_id',
        'role_id',
        'is_active',
        'bank_bin',
        'bank_label',
        'bank_account_number',
        'bank_account_name',
        'capability_rating',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'gender' => Gender::class,
            'is_active' => 'boolean',
            'capability_rating' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'account_department', 'account_id', 'department_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<JobTitle, $this>
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'account_project', 'account_id', 'project_id');
    }

    /**
     * Tickets đang phân công cho account này (chưa ở trạng thái kết thúc).
     * Dùng để xác định account đang bận xử lý ticket hay không.
     *
     * @return BelongsToMany<OgTicket, $this>
     */
    public function activeAssignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(OgTicket::class, 'og_ticket_assignees', 'account_id', 'og_ticket_id')
            ->whereNotIn('status', [
                OgTicketStatus::Completed->value,
                OgTicketStatus::Cancelled->value,
                OgTicketStatus::Rejected->value,
            ]);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path
                ? app(StorageServiceInterface::class)->getUrl($this->avatar_path)
                : null,
        );
    }

    /**
     * True when the account has complete bank information for QR generation.
     */
    public function hasBankInfo(): bool
    {
        return ! empty($this->bank_bin)
            && ! empty($this->bank_account_number)
            && ! empty($this->bank_account_name);
    }

    /**
     * Bank info as structured payload for API responses and QR generation.
     *
     * @return array{bin: string, label: string, account_number: string, account_name: string}|null
     */
    public function bankInfo(): ?array
    {
        if (! $this->hasBankInfo()) {
            return null;
        }

        return [
            'bin' => (string) $this->bank_bin,
            'label' => (string) ($this->bank_label ?? ''),
            'account_number' => (string) $this->bank_account_number,
            'account_name' => (string) $this->bank_account_name,
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('name', BaseModel::likeOperator(), "%{$keyword}%")
                ->orWhere('email', BaseModel::likeOperator(), "%{$keyword}%")
                ->orWhere('employee_code', BaseModel::likeOperator(), "%{$keyword}%");
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->whereHas('departments', function (Builder $q) use ($departmentId): void {
            $q->where('departments.id', $departmentId);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByJobTitle(Builder $query, int $jobTitleId): Builder
    {
        return $query->where('job_title_id', $jobTitleId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByRole(Builder $query, int $roleId): Builder
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByProject(Builder $query, int $projectId): Builder
    {
        return $query->whereHas('projects', function (Builder $q) use ($projectId): void {
            $q->where('projects.id', $projectId);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): \Database\Factories\Tenant\AccountFactory
    {
        return \Database\Factories\Tenant\AccountFactory::new();
    }
}
