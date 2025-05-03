<?php

namespace App\Traits;

use App\Permission;
use Illuminate\Support\Facades\Validator;

trait PermissibleTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootPermissibleTrait()
    {
        // Selete object's shares on object's delete
        static::deleting(function ($model) {
            $model->permissions()->delete();
        });
    }

    /**
     * Permissions for this object.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Permission, $this>
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class, 'permissible_id', 'id')
            ->where('permissible_type', self::class);
    }

    /**
     * Validate ACL input
     *
     * @param mixed $input Common ACL input
     *
     * @return array List of validation errors
     */
    protected function validateACL(&$input): array
    {
        if (!is_array($input)) {
            $input = (array) $input;
        }

        $users = [];
        $errors = [];
        $supported = $this->supportedACL();

        foreach ($input as $i => $v) {
            if (!is_string($v) || empty($v) || !substr_count($v, ',')) {
                $errors[$i] = \trans('validation.acl-entry-invalid');
            } else {
                list($user, $acl) = explode(',', $v, 2);
                $user = trim($user);
                $acl = trim($acl);
                $error = null;

                if (!isset($supported[$acl])) {
                    $errors[$i] = \trans('validation.acl-permission-invalid');
                } elseif (in_array($user, $users) || ($error = $this->validateACLIdentifier($user))) {
                    $errors[$i] = $error ?: \trans('validation.acl-entry-invalid');
                }

                $input[$i] = "$user, $acl";
                $users[] = $user;
            }
        }

        return $errors;
    }

    /**
     * Validate an ACL identifier.
     *
     * @param string $identifier Email address
     *
     * @return ?string Error message on validation error
     */
    protected function validateACLIdentifier(string $identifier): ?string
    {
        $v = Validator::make(['email' => $identifier], ['email' => 'required|email']);

        if ($v->fails()) {
            return \trans('validation.emailinvalid');
        }

        $user = \App\User::where('email', \strtolower($identifier))->first();

        if ($user) {
            return null;
        }

        return \trans('validation.notalocaluser');
    }

    /**
     * Build an ACL list from the object's permissions
     *
     * @return array ACL list in a "common" format
     */
    protected function getACL(): array
    {
        $supported = $this->supportedACL();

        return $this->permissions()->get()
            ->map(function ($permission) use ($supported) {
                $acl = array_search($permission->rights, $supported) ?: 'none';
                return "{$permission->user}, {$acl}";
            })
            ->all();
    }

    /**
     * Update the permissions based on the ACL input.
     *
     * @param array $acl ACL list in a "common" format
     */
    protected function setACL(array $acl): void
    {
        $users = [];
        $supported = $this->supportedACL();

        foreach ($acl as $item) {
            list($user, $right) = explode(',', $item, 2);
            $users[\strtolower($user)] = $supported[trim($right)] ?? 0;
        }

        // Compare the input with existing shares
        $this->permissions()->get()->each(function ($permission) use (&$users) {
            if (isset($users[$permission->user])) {
                if ($permission->rights != $users[$permission->user]) {
                    $permission->rights = $users[$permission->user];
                    $permission->save();
                }
                unset($users[$permission->user]);
            } else {
                $permission->delete();
            }
        });

        foreach ($users as $user => $rights) {
            $this->permissions()->create([
                    'user' => $user,
                    'rights' => $rights,
                    'permissible_type' => self::class,
            ]);
        }
    }

    /**
     * Returns a map of supported ACL labels.
     *
     * @return array Map of supported share rights/ACL labels
     */
    protected function supportedACL(): array
    {
        return [
            'read-only' => Permission::RIGHT_READ,
            'read-write' => Permission::RIGHT_READ | Permission::RIGHT_WRITE,
            'full' => Permission::RIGHT_ADMIN,
        ];
    }
}
