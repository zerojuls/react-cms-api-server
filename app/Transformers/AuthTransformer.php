<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;
use App\User;
use App\Helpers\UserHelper;

class AuthTransformer extends TransformerAbstract
{
  /**
   * Turn this item object into a generic array
   *
   * @return array
   */
  public function transform(User $user)
  {
    $roles = UserHelper::getRoleNames($user);

    return [
      'id'          => (int) $user->id,
      'name'        => $user->name,
      'email'       => $user->email,
      'logged_in'   => $user->logged_in,
      'deletable'   => true,
      'roles'       => $roles,
      'permissions' => UserHelper::getPermissionNames($user),
      'menuList'    => UserHelper::getMenuList($user),
      'isAdmin'     => in_array('admin', $roles->toArray())
    ];
  }
}