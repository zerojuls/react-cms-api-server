<?php

namespace App\Api\V1\Controllers;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Log;
use Dingo\Api\Routing\Helpers;

class UserController extends Controller
{
  use Helpers;

  public function index()
  {
      // NOTE: Original 
      // $currentUser = JWTAuth::parseToken()->authenticate();
      // return $currentUser
      //     ->books()
      //     ->orderBy('created_at', 'DESC')
      //     ->get()
      //     ->toArray();
      //     
      
      return response()->json(['auth'=>Auth::user(), 'items'=>User::all(['id', 'name as primary', 'email as secondary'])]);
  }

  public function activeUsers(){
    $currentUser = JWTAuth::parseToken()->authenticate();

    $users = User::where([['id', '!=', $currentUser->id], ['logged_in', '=', 1]])
                        ->orderBy('name', 'ASC')
                        ->get(['email', 'name', 'id', 'logged_in']);

    $activeUsers = [];

    if($users){
      foreach ( $users as $user )
      {
        if(Cache::has('user-is-online-' . $user->id)){
          $activeUsers[] = $user;
        }
      } 
    }

    return compact('activeUsers');
  }


  public function store(Request $request)
  {
      $currentUser = JWTAuth::parseToken()->authenticate();

      $book = new Book;

      $book->title = $request->get('title');
      $book->author_name = $request->get('author_name');
      $book->pages_count = $request->get('pages_count');

      if($currentUser->books()->save($book))
          return $this->response->created();
      else
          return $this->response->error('could_not_create_book', 500);
  }

  public function createRole(Request $request){
    $role = new Role();
    $role->name = $request->input('name');
    $role->save();

    return response()->json("created");    
  }

  public function createPermission(Request $request){
    $viewUsers = new Permission();
    $viewUsers->name = $request->input('name');
    $viewUsers->save();

    return response()->json("created");      
  }

  public function assignRole(Request $request){
    $user = User::where('email', '=', $request->input('email'))->first();

    $role = Role::where('name', '=', $request->input('role'))->first();
    // $user->attachRole($request->input('role'));
    $user->roles()->attach($role->id);

    return response()->json("created");
  }

  public function attachPermission(Request $request){
    $role = Role::where('name', '=', $request->input('role'))->first();
    $permission = Permission::where('name', '=', $request->input('name'))->first();
    $role->attachPermission($permission);

    return response()->json("created");
  }
}
