<?php

namespace App\Api\V1\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Http\Controllers\Controller;

use Dingo\Api\Routing\Helpers;

use App\Permission;

use App\Transformers\PermissionTransformer;

/**
 * Permission resource representation.
 * 
 * @Resource("Permission", uri="/permissions")
 */
class PermissionController extends Controller
{
    use Helpers;
    /**
     * Display a listing of the resource.
     *
     * @return Dingo\Api\Http\Response 
     */
    public function index()
    {
        $permissions = Permission::all();
        return $this->response->collection($permissions, new PermissionTransformer);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $permission = new Permission();
        $permission->name = $request->input('name');
        $permission->save();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if($permission = Permission::find($id)){
          return $this->response->item($permission, new PermissionTransformer)->setStatusCode(200);
        } 
        return  $this->response->errorNotFound('Could Not Find details for Permission with id=' . $id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
