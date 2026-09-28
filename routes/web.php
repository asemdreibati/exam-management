<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::group(['namespace' => 'App\Http\Controllers'], function()
{
    /**
     * Home Routes
     */
    Route::get('/', 'HomeController@index')->name('home.index');

    Route::group(['middleware' => ['guest']], function() {
        /**
         * Login Routes
         */
        Route::get('/login', 'LoginController@show')->name('login.show');
        Route::post('/login', 'LoginController@login')->name('login.perform');

    });

    Route::group(['middleware' => ['auth']], function() {
            /**
             * Logout Routes
             */
            Route::get('/logout', 'LogoutController@perform')->name('logout.perform');

            /**
             * User Routes
             */
            Route::group(['prefix' => 'users'], function() {
                Route::get('/', 'UsersController@index')->name('users.index');
                Route::get('/{user}/show', 'UsersController@show')->name('users.show');
                Route::get('/{user}/observations', 'UsersController@observations')->name('users.observations');
                Route::get('/{user}/profile', 'UsersController@profile')->name('users.profile');

                // A user may edit their own account; admins may edit any account.
                Route::group(['middleware' => 'adminAccess'], function() {
                    Route::get('/{user}/edit', 'UsersController@edit')->name('users.edit');
                    Route::patch('/{user}/update', 'UsersController@update')->name('users.update');
                });

                Route::group(['middleware' => 'adminOnly'], function() {
                    Route::get('/create', 'UsersController@create')->name('users.create');
                    Route::post('/create', 'UsersController@store')->name('users.store');
                    Route::delete('/{user}/delete', 'UsersController@destroy')->name('users.destroy');
                    Route::patch('/{user}/isActive', 'UsersController@isActive')->name('users.isActive');
                    Route::get('/{user}/create_user_courses', 'UsersController@create_user_courses')->name('users.create_user_courses');
                    Route::post('/{user}/store_user_courses', 'UsersController@store_user_courses')->name('users.store_user_courses');
                    Route::get('/{user}/edit_user_courses', 'UsersController@edit_user_courses')->name('users.edit_user_courses');
                    Route::patch('/{user}/update_user_courses', 'UsersController@update_user_courses')->name('users.update_user_courses');
                    Route::delete('/{user}/courses_teach/{course}/destroy_user_courses', 'UsersController@destroy_user_courses')->name('users.destroy_user_courses');
                    Route::patch('/setObservations', 'UsersController@setObservations')->name('users.setObservations');
                });

                Route::get('/search', 'UsersController@search')->name('users.search');

                //Excel
                Route::get('/observations-export/{rotation}', 'RotationsController@exportObservations')->name('observations.export');
                //Route::post('/users-import', 'RotationsController@import')->name('users.import');
            });

            /**
             * Course Routes
             */
            Route::group(['middleware'=>'adminAccess','prefix' => 'courses'], function() {
                Route::get('index-filtering', 'CoursesController@indexFiltering');//added
                Route::get('/', 'CoursesController@index')->name('courses.index');
                Route::get('/create', 'CoursesController@create')->name('courses.create');
                Route::post('/create', 'CoursesController@store')->name('courses.store');
                Route::get('/{course}/edit', 'CoursesController@edit')->name('courses.edit');
                Route::patch('/{course}/update', 'CoursesController@update')->name('courses.update');
                Route::delete('/{course}/delete', 'CoursesController@destroy')->name('courses.destroy');
                // Route::get('/{course}/add_users_to_course', 'UsersController@add_users_to_course')->name('users.add_users_to_course');
                // Route::post('/{course}/store_users_to_course', 'CoursesController@store_users_to_course')->name('courses.store_users_to_course');

            });

            /**
             * Room Routes
             */
            Route::group(['middleware'=>'adminAccess','prefix' => 'rooms'], function() {
                Route::get('/index', 'RoomsController@index')->name('rooms.index');
                Route::get('/create', 'RoomsController@create')->name('rooms.create');
                Route::post('/create', 'RoomsController@store')->name('rooms.store');
                Route::get('/{room}/edit', 'RoomsController@edit')->name('rooms.edit');
                Route::patch('/{room}/update', 'RoomsController@update')->name('rooms.update');
                Route::get('/{room}/show', 'RoomsController@show')->name('rooms.show');
                Route::delete('/{room}/delete', 'RoomsController@destroy')->name('rooms.destroy');
                Route::patch('/{room}/isActive', 'RoomsController@isActive')->name('rooms.isActive');
            });
            
            /**
             * Rotation Routes
             */
            Route::group(['prefix' => 'rotations'], function() {
                //Rotation CURD
                Route::get('/index', 'RotationsController@index')->name('rotations.index')->middleware('adminAccess');
                Route::get('/create', 'RotationsController@create')->name('rotations.create')->middleware('adminAccess');
                Route::post('/create', 'RotationsController@store')->name('rotations.store')->middleware('adminAccess');
                Route::get('/{rotation}/edit', 'RotationsController@edit')->name('rotations.edit')->middleware('adminAccess');
                Route::patch('/{rotation}/update', 'RotationsController@update')->name('rotations.update')->middleware('adminAccess');
                Route::delete('/{rotation}/delete', 'RotationsController@destroy')->name('rotations.destroy')->middleware('adminAccess');
                //Rotation CURD
                //Rotation ExamProgram
                Route::get('/{rotation}/show', 'CourseRotation_ExamProgramController@show')->name('rotations.program.show');
                Route::get('/{rotation}/create', 'CourseRotation_ExamProgramController@add_course_to_the_program')->name('rotations.program.add_course_to_the_program')->middleware('adminAccess');
                Route::post('/{rotation}/store', 'CourseRotation_ExamProgramController@store_course_to_the_program')->name('rotations.program.store_course_to_the_program')->middleware('adminAccess');
                Route::delete('/{rotation}/course/{course}/delete_course_from_program', 'CourseRotation_ExamProgramController@delete_course_from_program')->name('rotations.course.delete_course_from_program')->middleware('adminAccess');
                //initialization --> 1. initExamProgram, 2. initRoomsInAllCourses , 3. initUsersObservationsInAllCourses
                Route::get('/{rotation}/initExamProgram', 'CourseRotation_ExamProgramController@initExamProgram')->name('rotations.initExamProgram')->middleware('adminAccess');
                Route::get('/{rotation}/initRoomsInAllCourses', 'CourseRotation_ExamProgramController@initRoomsInAllCourses')->name('rotations.initRoomsInAllCourses')->middleware('adminAccess');
                Route::get('/{rotation}/initUsersObservationsInAllCourses', 'CourseRotation_ExamProgramController@initUsersObservationsInAllCourses')->name('rotations.initUsersObservationsInAllCourses')->middleware('adminAccess');

                //Rotation ExamProgram
                //Rotation Objections
                Route::get('/{rotation}/user/{user}/objections/create', 'CourseRotationUser_ObjectionController@create')->name('rotations.objections.create')->middleware('adminAccess');
                Route::post('/{rotation}/user/{user}/objections/create', 'CourseRotationUser_ObjectionController@store')->name('rotations.objections.store')->middleware('adminAccess');
                Route::get('/{rotation}/user/{user}/objections/edit', 'CourseRotationUser_ObjectionController@edit')->name('rotations.objections.edit')->middleware('adminAccess');
                Route::patch('/{rotation}/user/{user}/objections/update', 'CourseRotationUser_ObjectionController@update')->name('rotations.objections.update')->middleware('adminAccess');
                //Rotation Objections
                //Distribute Students && Members of Faculty
                Route::post('/{rotation}/distributeStudents', 'RotationsController@distributeStudents')->name('rotations.distributeStudents')->middleware('adminAccess');
                Route::post('/{rotation}/distributeMembersOfFaculty', 'RotationsController@distributeMembersOfFaculty')->name('rotations.distributeMembersOfFaculty')->middleware('adminAccess');

                //initial_members
                Route::get('/{rotation}/initial_members', 'RotationsController@create_initial_members')->name('rotations.create_initial_members')->middleware('adminAccess');
                Route::post('/{rotation}/initial_members', 'RotationsController@store_initial_members')->name('rotations.store_initial_members')->middleware('adminAccess');
                Route::get('/{rotation}/edit_initial_members', 'RotationsController@edit_initial_members')->name('rotations.edit_initial_members')->middleware('adminAccess');
                Route::patch('/{rotation}/update_initial_members', 'RotationsController@update_initial_members')->name('rotations.update_initial_members')->middleware('adminAccess');

            
            });
                        
            /**
             * Course with Rotation Routes
             */
            Route::group(['middleware'=>'adminAccess','prefix' => 'rotations'], function() {
                Route::get('/{rotation}/course/{course}/room/{specific_room}', 'CourseRotationController@get_room_for_course')->name('rotations.get_room_for_course');
                Route::patch('/{rotation}/course/{course}/room/{specific_room}', 'CourseRotationController@customize_room_for_course')->name('rotations.customize_room_for_course');
                Route::get('/{rotation}/course/{course}/show', 'CourseRotationController@show')->name('rotations.course.show');
                Route::get('/{rotation}/course/{course}/edit', 'CourseRotationController@edit')->name('rotations.course.edit');
                Route::patch('/{rotation}/course/{course}/update', 'CourseRotationController@update')->name('rotations.course.update');
            });
            /**
             * Course with Objection Routes
             */
            Route::group(['prefix' => 'objections'], function() {
                Route::get('/user/{user}/index', 'CourseRotationUser_ObjectionController@index')->name('objections.user.index')->middleware('adminAccess');
                Route::get('/rotation/{rotation}/user/{user}/show', 'CourseRotationUser_ObjectionController@show')->name('objections.user.show')->middleware('adminAccess');
            });
            Route::group(['prefix' => 'observations'], function() {
                //to show observation for user in spesific rotation
                Route::get('rotations/{rotation}/user/{user}/show', 'RotationsController@showObservationsInSpecificRotationForSpecificUser')->name('rotations.observations.user.show')->middleware('adminAccess');
            });
        });
    });



//Livewires
     //Route::get('/search', \App\Http\Livewire\Search::class)->name('search');