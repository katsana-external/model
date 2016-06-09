<?php namespace Orchestra\Model;

use Orchestra\Notifier\Notifiable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Orchestra\Model\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Auth\Passwords\CanResetPassword;
use Orchestra\Contracts\Notification\Recipient;
use Orchestra\Contracts\Authorization\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Eloquent implements Authorizable, CanResetPasswordContract, Recipient, UserContract
{
    use Authenticatable, CanResetPassword, Notifiable, Searchable, SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Available user status as constant.
     */
    const UNVERIFIED = 0;
    const SUSPENDED  = 63;
    const VERIFIED   = 1;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * List of searchable attributes.
     *
     * @var array
     */
    protected $searchable = ['email', 'fullname'];

    /**
     * Has many and belongs to relationship with Role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role')->withTimestamps();
    }

    /**
     * Search user based on keyword as roles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $keyword
     * @param  array  $roles
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch(Builder $query, $keyword = '', $roles = [])
    {
        $query->with('roles')->whereNotNull('users.id');

        if (! empty($roles)) {
            $query->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('roles.id', $roles);
            });
        }

        return $this->setupWildcardQueryFilter($query, $keyword, $this->getSearchableColumns());
    }

    /**
     * Set `password` mutator.
     *
     * @param  string  $value
     *
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        if (Hash::needsRehash($value)) {
            $value = Hash::make($value);
        }

        $this->attributes['password'] = $value;
    }

    /**
     * Get the e-mail address where notification are sent.
     *
     * @return string
     */
    public function getRecipientEmail()
    {
        return $this->getEmailForPasswordReset();
    }

    /**
     * Get the fullname where notification are sent.
     *
     * @return string
     */
    public function getRecipientName()
    {
        return $this->getAttribute('fullname');
    }

    /**
     * Get roles name as an array.
     *
     * @return \Illuminate\Support\Collection|array
     */
    public function getRoles()
    {
        // If the relationship is already loaded, avoid re-querying the
        // database and instead fetch the collection.
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->getRelation('roles')->pluck('name');
    }

    /**
     * Activate current user.
     *
     * @return \Orchestra\Model\User
     */
    public function activate()
    {
        $this->setAttribute('status', self::VERIFIED);

        return $this;
    }

    /**
     * Assign role to user.
     *
     * @param  int|array  $roles
     *
     * @return void
     */
    public function attachRole($roles)
    {
        $this->roles()->sync((array) $roles, false);
    }

    /**
     * Deactivate current user.
     *
     * @return \Orchestra\Model\User
     */
    public function deactivate()
    {
        $this->setAttribute('status', self::UNVERIFIED);

        return $this;
    }

    /**
     * Un-assign role from user.
     *
     * @param  int|array  $roles
     *
     * @return void
     */
    public function detachRole($roles)
    {
        $this->roles()->detach((array) $roles);
    }

    /**
     * Determine if current user has the given role.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array|string  $roles
     *
     * @return bool
     */
    public function is($roles)
    {
        $userRoles = $this->getRoles();

        if ($userRoles instanceof Arrayable) {
            $userRoles = $userRoles->toArray();
        }

        // For a pre-caution, we should return false in events where user
        // roles not an array.
        if (! is_array($userRoles)) {
            return false;
        }

        // We should ensure that all given roles match the current user,
        // consider it as a AND condition instead of OR.
        foreach ((array) $roles as $role) {
            if (! in_array($role, $userRoles)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the current user account activated or not.
     *
     * @return bool
     */
    public function isActivated()
    {
        return ($this->getAttribute('status') == self::VERIFIED);
    }

    /**
     * Determine if current user has any of the given role.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array|string  $roles
     *
     * @return bool
     */
    public function isAny($roles)
    {
        $userRoles = $this->getRoles();

        if ($userRoles instanceof Arrayable) {
            $userRoles = $userRoles->toArray();
        }

        // For a pre-caution, we should return false in events where user
        // roles not an array.
        if (! is_array($userRoles)) {
            return false;
        }

        // We should ensure that any given roles match the current user,
        // consider it as OR condition.
        foreach ((array) $roles as $role) {
            if (in_array($role, $userRoles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if current user does not has any of the given role.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array|string  $roles
     *
     * @return bool
     */
    public function isNot($roles)
    {
        return ! $this->is($roles);
    }

    /**
     * Determine if current user does not has any of the given role.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array|string  $roles
     *
     * @return bool
     */
    public function isNotAny($roles)
    {
        return ! $this->isAny($roles);
    }

    /**
     * Determine if the current user account suspended or not.
     *
     * @return bool
     */
    public function isSuspended()
    {
        return ($this->getAttribute('status') == self::SUSPENDED);
    }

    /**
     * Send notification for a user.
     *
     * @param  \Orchestra\Contracts\Notification\Message|string  $subject
     * @param  string|array|null  $view
     * @param  array  $data
     *
     * @return \Orchestra\Contracts\Notification\Receipt
     */
    public function notify($subject, $view = null, array $data = [])
    {
        return $this->sendNotification($this, $subject, $view, $data);
    }

    /**
     * Suspend current user.
     *
     * @return \Orchestra\Model\User
     */
    public function suspend()
    {
        $this->setAttribute('status', self::SUSPENDED);

        return $this;
    }
}
