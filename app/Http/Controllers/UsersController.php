<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Room;
use App\Models\Course;
use App\Models\Department;
use App\Models\Rotation;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\MaxMinRoomsCapacity\Stock;
use App\Http\Controllers\MaxFlow\Graph;
use Illuminate\Support\LazyCollection;
use PhpOffice\PhpSpreadsheet\Helper\Size;

//use Illuminate\Support\Facades\Request;
class UsersController extends Controller
{
    /**
     * Fields any user may change on their own account.
     */
    private const SELF_EDITABLE_FIELDS = ['email', 'username'];

    /**
     * Fields only admins may set. `property` is mapped separately and
     * `password` is handled explicitly, so neither is listed here.
     */
    private const ADMIN_EDITABLE_FIELDS = [
        'email',
        'username',
        'role',
        'temporary_role',
        'faculty_id',
        'department_id',
        'number_of_observation',
        'city',
    ];

    public function search(Request $request)
    {
        $se = $request->se;
        $users = User::where('id','LIKE','%'.$request->se.'%')
                    ->orWhere('username','LIKE','%'.$request->se.'%')
                    ->orWhere('email','LIKE','%'.$request->se.'%')
                    ->orWhere('role','LIKE','%'.$request->se.'%')
                    ->orWhere('temporary_role','LIKE','%'.$request->se.'%')
                    ->orWhere('property','LIKE','%'.$request->se.'%')
                    ->orWhere('city','LIKE','%'.$request->se.'%')
                    ->sortable()
                    ->paginate(5);

        return view('users.index', [ 'users' => $users->appends(request()->except('page')), 'se'=>$se ]);
    }
    /**
     * Display all users
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $users = User::first()->paginate(20);
        // $users = User::sortable()->paginate(5);
        // $users = User::all();
        $users = User::first()->paginate(10);
        //var_dump($users);
        //to delete
        // $num_observations = 0;
        // $count_users_observations=0;
        // if(session()->exists('num_observations')){
        //     $num_observations = session()->get('num_observations');
        // }
        // if(session()->exists('count_users_observations')){
        //     $count_users_observations = session()->get('count_users_observations');
        // }
        //to delete

        //$users = User::sortable()->get();

    return view('users.index', compact('users'/*,'num_observations','count_users_observations'*/));
    }
    public function isActive(Request $request, User $user){
        $latest_rotation=Rotation::latest()->first();
        if($user->is_active == true){
            $latest_rotation->initial_members()->wherePivot('user_id',$user->id)->detach();//clear the user for the members list
            $user->is_active = 0;
            $user->save();
            return redirect()->route('users.index')
            ->withSuccess($user->username.' not active now.');
        }else {
            if($user->role==="دكتور")
                $latest_rotation->initial_members()->attach($user->id,['options'=> '{"1":"on"}']);
            $user->is_active = 1;
            $user->save();
            return redirect()->route('users.index')
            ->withSuccess($user->username.' active now.');
        }
    }
    /**
     * Show form for creating user
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('users.create',
        // ['roles' => Role::latest()->get()]
    );
    }

    /**
     * Store a newly created user
     *
     * @param User $user
     * @param StoreUserRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUserRequest $request)
    {
        User::create(array_merge(
            $request->only(array_merge(self::ADMIN_EDITABLE_FIELDS, ['password'])),
            ['property' => $this->propertyLabel($request->property)]
        ));

        return redirect()->route('users.index')
            ->withSuccess(__('User created successfully.'));
    }

    /**
     * Show user data
     *
     * @param User $user
     *
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        return view('users.show', [
            'user' => $user
        ]);
    }
    public function profile(User $user)
    {
        list($all_rotations_table, $observations_number_in_latest_rotation)=Stock::calcInfoForEachRotationForSpecificuser($user);
        //dd($all_rotations_table, $observations_number_in_latest_rotation);
        if(array_key_exists(Rotation::latest()->toBase()->get()[0]->id,$all_rotations_table))
            return view('users.profile', [
                'user' => $user,
                'rotations_in_lastet_rotation_table' => $all_rotations_table[Rotation::latest()->toBase()->get()[0]->id],
                'observations_number_in_latest_rotation' => $observations_number_in_latest_rotation
            ]);
        else
            return view('users.profile', [
                'user' => $user,
                'rotations_in_lastet_rotation_table' => [],
                'observations_number_in_latest_rotation' => $observations_number_in_latest_rotation
            ]);
    }
    public function observations(User $user)
    {
        $user_name=User::where('id',$user->id)->first()->username;

        list($all_rotations_table, $observations_number_in_latest_rotation)=Stock::calcInfoForEachRotationForSpecificuser($user);
        //dd( $observations_number_in_latest_rotation);
        return view('users.observations', [
            'user_name' => $user_name,
            'all_rotations_table' => $all_rotations_table,
            'rotations_numbers' => array_unique($user->rotations->toBase()->pluck('id')->toArray()),
            'observations_number_in_latest_rotation' => $observations_number_in_latest_rotation
        ]);
    }
    /**
     * Edit user data
     *
     * @param User $user
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        //dd($user,Role::latest()->first());
        return view('users.edit', [
            'user' => $user,
            //'userRole' => $user->roles->pluck('name')->toArray(),
            //'roles' => Role::latest()->get()
        ]);
    }

    /**
     * Update user data
     *
     * @param User $user
     * @param UpdateUserRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function update(User $user, UpdateUserRequest $request)
    {
        if ($request->filled(['old_password', 'new_password', 'new_password_verification'])
            && $request->user()->is($user)) {
            if (!Hash::check($request->old_password, $user->password)) {
                return redirect()->back()
                    ->withDanger(__('incorrect old password.'));
            }
            if ($request->new_password !== $request->new_password_verification) {
                return redirect()->back()
                    ->withDanger(__('incorrect verification password.'));
            }
            $user->password = $request->new_password;
        }

        $user->fill($request->only(self::SELF_EDITABLE_FIELDS));

        if ($request->user()->isAdmin()) {
            $user->fill($request->only(self::ADMIN_EDITABLE_FIELDS));
            $user->property = $this->propertyLabel($request->property);
        }

        $user->save();

        return redirect()->route('users.index')
        ->withSuccess(__('User updated successfully.'));
    }

    /**
     * Delete user data
     *
     * @param User $user
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')
            ->withSuccess(__('User deleted successfully.'));
    }

    public function create_user_courses(User $user)
    {
        $user_courses_teaches_ids=$user->teaches()->pluck('course_id');
        return view('users.create_user_courses', [
            'user' => $user,
            'user_courses_teaches_ids' => $user_courses_teaches_ids
        ]);
    }

    public function store_user_courses(Request $request,User $user)
    {   //sections_types
        //course_user_teach
        $course_user_teach=Course::where('course_name',$request->course_user_teach)->first();
        $this->validate($request,[
            'course_user_teach'=>'required',
            'sections_types' => 'required'
        ],[
            'sections_types.unique' => 'the name of course should be unique'
        ]);
        if(count($request->sections_types)==2){
            $user->teaches()->attach($course_user_teach,['section_type'=> 'نظري - عملي']);
        }else{
            if(array_keys($request->sections_types)[0]==1)
                $user->teaches()->attach($course_user_teach,['section_type'=> 'نظري']);
            elseif(array_keys($request->sections_types)[0]==2)
                $user->teaches()->attach($course_user_teach,['section_type'=> 'عملي']);
        }
        //return Response::json($user);
        return redirect()->route('users.edit_user_courses',$user->id)
            ->withSuccess(__('course created successfully for user.'));
    }

    public function edit_user_courses(User $user)
    {
        $user_courses_teaches_ids=$user->teaches()->pluck('course_id');
        $courses_common_with_users=[];
        foreach ($user->teaches()->get() as $course)
        if(count($course_common_with_users = $course->teachesBy()->where('id','!=',$user->id)->pluck('username')))
            $courses_common_with_users[$course->id]=$course_common_with_users->toarray();

        return view('users.edit_user_courses', [
            'user' => $user,
            'user_courses_teaches_ids' => $user_courses_teaches_ids,
            'courses_common_with_users'=>$courses_common_with_users
        ]);
    }

    public function update_user_courses(Request $request, User $user)
    {
        foreach ($request->sections_types as $course_id => $sections_types) {
        $user->teaches()->detach($course_id);
            if(count($sections_types)==2){
                $user->teaches()->attach($course_id,['section_type'=> 'نظري - عملي']);
            }else{
                if(array_keys($sections_types)[0]==1)
                    $user->teaches()->attach($course_id,['section_type'=> 'نظري']);
                elseif(array_keys($sections_types)[0]==2)
                    $user->teaches()->attach($course_id,['section_type'=> 'عملي']);
            }
        }
        return redirect()->route('users.edit_user_courses',$user->id)
        ->withSuccess(__('Course For User updated successfully.'));
    }



    public function destroy_user_courses(User $user,Course $course)
    {
        $user->teaches()->detach($course->id);
        return redirect()->route('users.edit_user_courses',$user->id)
            ->withSuccess(__('Course deleted successfully.'));
    }



    /**
     * Map the property option submitted by the user forms to its label.
     */
    private function propertyLabel(?string $option): string
    {
        switch ($option) {
            case '1':
                return 'عضو هيئة فنية';
            case '2':
                return 'عضو هيئة تدريسية';
            default:
                return '';
        }
    }

    public function setObservations(Request $request){
        $all_roles = array_unique(User::pluck('role')->all());
        $all_property = array_unique(User::pluck('property')->all());
        if($request['role_user']=="all_users")
            $query=User::all();
        elseif(in_array($request['role_user'],$all_roles))
            $query=User::where('role',$request['role_user'])->get();
        elseif(in_array($request['role_user'],$all_property))
            $query=User::where('property',$request['role_user'])->get();
        else
            return redirect()->back()->withDanger(__('غير موجود دور '.$request['role_user']));
        foreach ($query as $user) {
            $user->number_of_observation = (int)$request['reset_vlaue'];
            $user->save();
        }

        return redirect()->route('users.index')
        ->withSuccess(__('User Observations updated successfully.'));
    }

}

/*

git clone https://github.com/codeanddeploy/laravel8-authentication-example.git

if your using my previous tutorial navigate your project folder and run composer update



install packages

composer require spatie/laravel-permission
composer require laravelcollective/html

then run php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

php artisan migrate

php artisan make:migration create_posts_table

php artisan migrate

models
php artisan make:model Post

middleware
- create custom middleware
php artisan make:middleware PermissionMiddleware

register middleware
-

routes

controllers

- php artisan make:controller UsersController
- php artisan make:controller PostsController
- php artisan make:controller RolesController
- php artisan make:controller PermissionsController

requests
- php artisan make:request StoreUserRequest
- php artisan make:request UpdateUserRequest

blade files

create command to lookup all routes
- php artisan make:command CreateRoutePermissionsCommand
- php artisan permission:create-permission-routes

seeder for default roles and create admin user
php artisan make:seeder CreateAdminUserSeeder
php artisan db:seed --class=CreateAdminUserSeeder



*/
