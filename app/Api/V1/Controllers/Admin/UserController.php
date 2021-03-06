<?php

namespace App\Api\V1\Controllers\Admin;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Dingo\Api\Routing\Helpers;
use Cache;
use Validator;
use Dingo\Api\Exception\ValidationHttpException;

use App\Jobs\LogoutInactiveUser;
use App\Transformers\UserTransformer;
use App\Transformers\UserWidgetTransformer;

/**
 * User resource representation.
 * 
 * @Resource("User", uri="/users")
 */
class UserController extends Controller
{
  use Helpers;

  /** ROLES AND PERMISSIONS **/

  /**
   * Assign a Role to a User
   * @param  Request $request - should container user_id and role_id
   * @return Dingo\Api\Http\Response
   */
  public function assignRole(Request $request){
    $user = User::find($request->input('user_id'));
    $role = Role::find($request->input('role_id'));
    if($user) {
      if($role) {
        $user->roles()->sync([$role->id]);
      } else {
        return $this->response->errorNotFound('Could Not Find details for Role with id=' . $request->input('role_id'));
      }
    } else {
      return $this->response->errorNotFound('Could Not Find details for Role with id=' . $request->input('user_id'));
    }
    
    return $this->response->item($user, new UserTransformer)->setStatusCode(200);
  }

  /** UNIQUE ACCESSOR METHODS  */

  public function activeUsers(){
    $currentUser = JWTAuth::parseToken()->authenticate();

    $users = User::where([['id', '!=', $currentUser->id], ['logged_in', '=', 1]])
                        ->orderBy('name', 'ASC')
                        ->get(['email', 'name', 'id', 'logged_in']);

    $activeUsers = [];

    // Loop through Users that are supposedly logged in and see if there is a none-expired
    // Cache entry for them. If there is then they are actually active, if not, they should be set as being
    // logged out.
    // TODO: logged_in should perhaps be refactored to `is_active`
    if($users->count()){
      foreach ( $users as $user )
      {
        if(Cache::has('user-is-online-' . $user->id)){
          $activeUsers[] = $user;
        } else {
          // if they were not in the cache but had logged_in set to true, then set it to false.
          $this->dispatch(new LogoutInactiveUser($user));
        }
      } 
    }

    return $this->response->collection(collect($activeUsers), new UserWidgetTransformer);
  }

  /** CRUD METHODS **/

  /**
   * Get a JSON representation of all all Users.
   *
   * @Get("/")
   * @Versions({"v1"})
   * 
   * @return Dingo\Api\Http\Response
   */
  public function index()
  { 
    // TODO: PAGINATE THIS
    $users = User::orderBy('position')->get();
    return $this->response->collection($users, new UserTransformer);
  }

  public function updateIndex(Request $request) {
    $minimalArray = $request->get('minimalArray');
    if($minimalArray) {
      $i = 0;
      foreach($minimalArray as $node) {
        User::where('id', $node['id'])->update(['position' => $i++]);
      }
      return $this->response->noContent()->setStatusCode(200);
    } else {
      return $this->response->error('Update Failed, no data received.', 401);
    }
  }

  /**
   * Method for handling a request to create and save a new User.
   * @param  Request $request - contains data for creating a new user
   * @return Dingo\Api\Http\Response - an api response.
   */
  public function store(Request $request)
  {
    $validator = Validator::make($request->only(['name', 'email', 'password']), [
        'name' => 'required|max:255|alpha_spaces',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:7'
    ]);

    if ($validator->fails()) {
      throw new \Dingo\Api\Exception\StoreResourceFailedException('Could not create new user.', $validator->errors());
    }

    $user = new User($request->only(['name', 'email', 'password']));

    if($user->save())
      return $this->response->item($user, new UserTransformer)->setStatusCode(200);
    else
      return $this->response->errorBadRequest('Could Not Create User');
  }

  /**
   * Get a JSON representation of all a User.
   *
   * @Get("/{id}")
   * @Versions({"v1"})
   * @Parameters({
   *      @Parameter("id", description="The id of the user to view", default=null),
   * })
   * 
   * @param  Request $request - HTTP Request from the client
   * @param  int     $id      - The id of the User to get details for
   * @return Dingo\Api\Http\Response 
   */
  public function show(Request $request, $id){
    if($user = User::with('roles')->find($id)){
      return $this->response->item($user, new UserTransformer)->setStatusCode(200);
    } 
    return  $this->response->errorNotFound('Could Not Find details for User with id=' . $id);
  }

  /**
   * Method for handling a request to update an existing user.
   * @param  Request $request - contains data for creating a new user
   * @return Dingo\Api\Http\Response - an api response.
   */
  public function update(Request $request, $id){

    $user = User::find($id);
    if(!$user) {
      return $this->response->errorNotFound('Could Not Find User with id=' . $id);
    }

    $acceptedInput = $request->only(['name', 'email', 'password']);
   
    $validator = Validator::make($acceptedInput, [
        'name' => 'max:255|alpha_spaces',
        'email' => 'email|unique:users,email,' . $id, // exclude the User being updated from Users list being used to get list of existing emails addresses.
        'password' => 'min:7'
    ]);

    if ($validator->fails()) {
      throw new \Dingo\Api\Exception\UpdateResourceFailedException('Could not update the user.', $validator->errors());
    }

    if($request->has('password')){
      $user->password = $request->get('password');
    }
    if($request->has('name')){
      $user->name = $request->get('name');
    }
    if($request->has('email')){
      $user->email = $request->get('email');
    }

    if($user->save())
      return $this->response->item($user, new UserTransformer)->setStatusCode(200);
    else
      return $this->response->errorBadRequest('Could not Update User with id=' . $id);
  }

  /**
   * Handle a request to remove a User from the DB
   * @param  Request $request - The request to remove the User
   * @param  int     $id      - The id of the User to remove
   * @return Dingo\Api\Http\Response - A response for the client
   */
  public function destroy(Request $request, $id) {
    if($user = User::find($id)) {
      if($user->delete())
        return $this->response->noContent()->setStatusCode(200);
      else
        return $this->response->errorBadRequest('Could Note Remove the User with id=' . $id);
    }
    return $this->response->errorNotFound('Could not Find User to remove with an id=' . $id);
  }
}
