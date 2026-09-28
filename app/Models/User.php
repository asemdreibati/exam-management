<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Kyslik\ColumnSortable\Sortable;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles,Sortable;
    public $sortable = [
        'id',
        'email',
        'username',
        'role',
        'number_of_observation',
        'temporary_role',
        'faculty_id',
        'department_id',
        'city',
        'property',
        ];
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'username',
        'password',
        'role',
        'number_of_observation',
        'temporary_role',
        'is_active',
        'faculty_id',
        'department_id',
        'city',
        'property',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Always encrypt password when it is updated.
     *
     * @param $value
     * @return string
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    /**
     * Temporary roles that grant administrative access.
     */
    public const ADMIN_TEMPORARY_ROLES = [
        'رئيس شعبة الامتحانات',
        'عميد',
    ];

    public function isAdmin(): bool
    {
        return in_array($this->temporary_role, self::ADMIN_TEMPORARY_ROLES, true);
    }

    public function teaches(){
        return $this->belongsToMany('App\Models\Course','course_teacher')->withPivot('course_id','user_id','section_type');
    }


    //observation2
    public function rotationsObjection(){
        return $this->belongsToMany('App\Models\Rotation','course_rotation_user')->withPivot('course_id','user_id','rotation_id')->withTimestamps();
    }
    public function coursesObjection(){
        return $this->belongsToMany('App\Models\Course','course_rotation_user')->withPivot('course_id','user_id','rotation_id')->withTimestamps();
    }
    //observation2
    //assign_users_in_rooms3
    public function courses(){//many to many between course & user
        return $this->belongsToMany(Course::class,'course_room_rotation_user')->withPivot('rotation_id','course_id','room_id','user_id','roleIn');
    }
    public function rooms(){//many to many between course & user
        return $this->belongsToMany(Room::class,'course_room_rotation_user')->withPivot('rotation_id','course_id','room_id','user_id','roleIn');
    }
    public function rotations(){//many to many between User & Rotation
        return $this->belongsToMany('App\Models\Rotation','course_room_rotation_user')->withPivot('rotation_id','course_id','room_id','user_id','roleIn');
    }
    //assign_users_in_rooms3


    public function faculty(){
        return $this->belongsTo(Faculty::class);//->withDefault(['name1'=>'dfd','name2'=>'ks']);// can access to this properties like i name foreginKey dept_id instead of name department_id  if doesn't has any problem i can't access to this properties
        //when reletionship has a problem the benifit of this to acees to this properties when access to this relationship from any user as   <h1>{{$user->department->name1}}</h1><h1>{{$user->department->name2}}</h1>
    }
    public function department(){
        return $this->belongsTo(Department::class);//->withDefault(['name1'=>'dfd','name2'=>'ks']);// can access to this properties like i name foreginKey dept_id instead of name department_id  if doesn't has any problem i can't access to this properties
        //when reletionship has a problem the benifit of this to acees to this properties when access to this relationship from any user as   <h1>{{$user->department->name1}}</h1><h1>{{$user->department->name2}}</h1>
    }


    // public function posts(){//one to many between user & post
    //     return $this->hasMany(Post::class);
    // }
    // public function department(){
    //     return $this->belongsTo(Department::class);//->withDefault(['name1'=>'dfd','name2'=>'ks']);// can access to this properties like i name foreginKey dept_id instead of name department_id  if doesn't has any problem i can't access to this properties
    //     //when reletionship has a problem the benifit of this to acees to this properties when access to this relationship from any user as   <h1>{{$user->department->name1}}</h1><h1>{{$user->department->name2}}</h1>
    // }



    public static function search($searchTerm){
        if($searchTerm != "")
        dd($searchTerm);
        return empty($searchTerm) ? static::query() : static::query()->where('username','like','%' .$searchTerm. '%')
        ->orWhere('id','like','%' .$searchTerm. '%');
    }

    public function initial_members(){//many to many between Rotation & User
        return $this->belongsToMany('App\Models\Rotation','initial_members_for_each_rotation')->withPivot('rotation_id','user_id','options')->withTimestamps();
    }
}
