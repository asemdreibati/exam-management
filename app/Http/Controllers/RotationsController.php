<?php

namespace App\Http\Controllers;
use App\Models\Rotation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\MaxMinRoomsCapacity\Stock;
use App\Http\Controllers\MaxFlow\Graph;
use App\Http\Controllers\MaxFlow\EnumPersonType;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ObservationsExport;
use App\Exports\exportObservationsInSpecificDay;
use App\Imports\UsersImport;
class rotationsController extends Controller
{

//distribute students into rooms
    public function isAvailableRoom($rotation, $course, $room){
        $curr_date=$course->rotationsProgram()->where('rotation_id',$rotation->id)->first()->pivot->date;
        $curr_time=$course->rotationsProgram()->where('rotation_id',$rotation->id)->first()->pivot->time;
        $curr_duration=$course->rotationsProgram()->where('rotation_id',$rotation->id)->first()->pivot->duration;


        // $all_courses_distributed=Course::with('distributionRoom')->whereHas('distributionRoom', function($query) use($room,$rotation){
        //     $query->where('room_id',$room->id)->where('rotation_id',$rotation->id);})->get();
        $all_courses_distributed_to_this_room_in_this_rotation_ids=$rotation->distributionCourse()->wherePivot('room_id',$room->id)->pluck('id');//dump($all_courses_distributed_to_this_room_in_this_rotation_ids);
        // foreach ($all_courses_distributed_to_this_room_in_this_rotation_ids as $courseM)
                if((count($rotation->coursesProgram()->whereIn('id',$all_courses_distributed_to_this_room_in_this_rotation_ids)->wherePivot('date',$curr_date)->wherePivot('time','>=',$curr_time)->wherePivot('time','<=',gmdate('H:i:s',strtotime($curr_time)+strtotime($curr_duration)))->get())
                ||  count($rotation->coursesProgram()->whereIn('id',$all_courses_distributed_to_this_room_in_this_rotation_ids)->wherePivot('date',$curr_date)->wherePivot('time','<=',$curr_time)->wherePivot('time','>=',gmdate('H:i:s',strtotime($curr_time)-strtotime($curr_duration)))->get())))
                        return false;

                return true;
    }

    public function distribute($rotation,$course,$roomBase,$temporory_counter,$temp){
            if($temporory_counter >= $temp){
                $temporory_counter-=$temp;
                $course->distributionRoom()->attach($roomBase->id,['rotation_id'=>$rotation->id,'course_id'=>$course->id,'num_student_in_room'=> $temp]);
            }else{
                $course->distributionRoom()->attach($roomBase->id,['rotation_id'=>$rotation->id,'course_id'=>$course->id,'num_student_in_room'=> $temporory_counter]);
                $temporory_counter=0;
            }
        return $temporory_counter;
    }

    public function distributeStudents(Rotation $rotation){
        ini_set('max_execution_time', 360); //6 minutes
        //dd($rotation->coursesProgram->pluck('id'));
        foreach ($rotation->coursesProgram as $course) {
            $course->distributionRoom()->wherePivot('rotation_id',$rotation->id)->detach();//clear the previous distribution
            $curr_students_number=$course->rotationsProgram()->wherePivot('rotation_id',$rotation->id)->first()->pivot->students_number;
            $temporory_counter=$curr_students_number;
            $rotation = Rotation::find($rotation->id);//high level we reget it because we modified it in the function isAvailableRoom line 23 24
            foreach (Room::all() as $roomBase)
                if($this->isAvailableRoom($rotation, $course, $roomBase) && $roomBase->is_active){
                    if($curr_students_number <= Stock::getMinDistribution())//it is garantee that the curr_students_number is less than $this->getMaxDistribution() that is done in the method in controller CourseRotation_ExamProgram/store_course_to_the_program
                        if(in_array($course->studing_year, [4,5]))
                            $temporory_counter=$this->distribute($rotation,$course,$roomBase,$temporory_counter,(int)((($roomBase->capacity+$roomBase->extra_capacity)/2)+1));//problem without (int) add one student !!!!!
                        else
                            $temporory_counter=$this->distribute($rotation,$course,$roomBase,$temporory_counter,$roomBase->capacity);
                    else
                        $temporory_counter=$this->distribute($rotation,$course,$roomBase,$temporory_counter,$roomBase->capacity+$roomBase->extra_capacity);
                        //dd($temporory_counter);
                    if(!$temporory_counter) break;
                }
        }
        return redirect("/rotations/$rotation->id/show")
        ->withSuccess(__('You have successfully distribute all students to the sutable rooms'));
    }
//distribute students into rooms
//distributeMembersOfFaculty
public function current_user_observations($user){
    return $user->id;
}
/**
 * Save the role assignments produced by the distribution, then copy
 * members into rooms shared by courses held at the same time.
 * Must run inside a transaction so a failure leaves no partial result.
 */
private function saveDistribution(Rotation $rotation, array $paths_info_room_heads, array $paths_info_secertaries, array $paths_info_observers){
    foreach ($paths_info_room_heads['users_observations'] as $room_head_id => $room_heads_observations){
        $s=User::where('id',$room_head_id)->first();
        foreach ($room_heads_observations as $room_head_observation)
            $s->courses()->attach($room_head_observation['course'],['rotation_id'=>$rotation->id,'room_id'=>$room_head_observation['room'],'roleIn'=>$room_head_observation['roleIn']]);
    }
    foreach ($paths_info_secertaries['users_observations'] as $secertary_id => $secertarys_observations){
        $s=User::where('id',$secertary_id)->first();
        foreach ($secertarys_observations as $secertary_observation)
            $s->courses()->attach($secertary_observation['course'],['rotation_id'=>$rotation->id,'room_id'=>$secertary_observation['room'],'roleIn'=>$secertary_observation['roleIn']]);
    }
    foreach ($paths_info_observers['users_observations'] as $observer_id => $observers_observations){
        $s=User::where('id',$observer_id)->first();
        foreach ($observers_observations as $observer_observation)
            $s->courses()->attach($observer_observation['course'],['rotation_id'=>$rotation->id,'room_id'=>$observer_observation['room'],'roleIn'=>$observer_observation['roleIn']]);
    }
    //start fill common rooms between multiplue courses
    $course_taken=[];
    foreach ($rotation->coursesProgram()->get() as $course){
        list($disabled_rooms, $joining_rooms, $courses_common_with_time)=Stock::getDisabledAndJoiningRoomsAndCommonCoursesWithTime($rotation, $course);                    //calc Joining rooms and disabled rooms and courses_common_with_time
        if(count($courses_common_with_time)===1) continue;
        if(!isset($course_taken[$course->id])) $course_taken[$course->id]=[];
        $rooms_this_course=Stock::getRoomsForSpecificCourse($rotation, $course);
        foreach ($course->distributionRoom()->wherePivot('rotation_id',$rotation->id)->get() as $room){
            if(in_array($room->id,$course_taken[$course->id])) continue;
            $room_heads_in_this_rotation_course_room=$secertaries_in_this_rotation_course_room=$observers_in_this_rotation_course_room=null;
            if(in_array($room->id, $disabled_rooms) && in_array($room->id, $rooms_this_course) ){//Manage Room
                list($room_heads_in_this_rotation_course_room,$secertaries_in_this_rotation_course_room, $observers_in_this_rotation_course_room)=Stock::getUsersInSpecificRotationCourseRoom($rotation,$course,$room->id);
                $courses_have_not_members=[];
                $members_taken=false;
                foreach ($courses_common_with_time as $course_common_with_time) {//fill the remaining rooms that belongs to the other courses with the same members in catched course
                    $rooms_in_courses_common_with_time=Stock::getRoomsForSpecificCourse($rotation, $course_common_with_time);
                    if(!in_array($room->id,$rooms_in_courses_common_with_time)) continue;
                    if($members_taken || count($room->users()->wherePivot('rotation_id',$rotation->id)->wherePivot('course_id',$course_common_with_time->id)->toBase()->get())){
                        list($room_heads_in_this_rotation_course_room, $secertaries_in_this_rotation_course_room, $observers_in_this_rotation_course_room)=Stock::getUsersInSpecificRotationCourseRoom($rotation,$course_common_with_time,$room->id);
                        $members_taken=true;
                    }else{
                        array_push($courses_have_not_members,$course_common_with_time);
                    }
                    $course_taken[$course_common_with_time->id][]=$room->id;
                }
                //publish members to the all related sameTime courses
                foreach ($courses_have_not_members as $course_have_not_members) {//will the remaining rooms that belongs to the other courses with the same members in catched course
                    //$room->users()->wherePivot('rotation_id',$rotation->id)->wherePivot('course_id',$course_have_not_members->id)->detach();
                    $course_have_not_members->users()->attach($room_heads_in_this_rotation_course_room, ['rotation_id'=>$rotation->id,'room_id'=>$room->id,'roleIn'=> 'RoomHead']);
                    $course_have_not_members->users()->attach($secertaries_in_this_rotation_course_room, ['rotation_id'=>$rotation->id,'room_id'=>$room->id,'roleIn'=> 'Secertary']);
                    $course_have_not_members->users()->attach($observers_in_this_rotation_course_room, ['rotation_id'=>$rotation->id,'room_id'=>$room->id,'roleIn'=> 'Observer']);
                }
            }
        }
    }
}

public function distributeMembersOfFaculty(Rotation $rotation){

    ini_set('max_execution_time', 360); //6 minutes
    $graph_room_heads=new Graph(EnumPersonType::RoomHead, $rotation);
    list($paths_room_heads,$paths_info_room_heads)=$graph_room_heads->applyMaxFlowAlgorithm();//dd($paths_room_heads,$paths_info_room_heads,$paths_info_room_heads['users_observations']);
    //dd("stop");
    //dd($paths_room_heads,$paths_info_room_heads);
    if(count($paths_room_heads)){
        //dump("_____________________");
        $graph_secertaries=new Graph(EnumPersonType::Secertary, $rotation, $paths_info_room_heads);//dd("Alignment");
        list($paths_secertaries,$paths_info_secertaries)=$graph_secertaries->applyMaxFlowAlgorithm();
        //dd($paths_secertaries,$paths_info_secertaries);
        //dd("stop");

        if(count($paths_secertaries)){
            //dump("_____________________");

            $graph_observers=new Graph(EnumPersonType::Observer, $rotation, $paths_info_room_heads, $paths_info_secertaries);
            list($paths_observers,$paths_info_observers)=$graph_observers->applyMaxFlowAlgorithm();
            //dd($paths_room_heads,$paths_info_room_heads,$paths_secertaries,$paths_info_secertaries,$paths_observers,$paths_info_observers);
            //dd("stop");
            if(count($paths_observers)){
                DB::transaction(function () use ($rotation, $paths_info_room_heads, $paths_info_secertaries, $paths_info_observers) {
                    $this->saveDistribution($rotation, $paths_info_room_heads, $paths_info_secertaries, $paths_info_observers);
                });
            }else{
                return redirect()->back()->withWarning(__('لا يوجد مراقبين كفايه للفرز من فضلك قم بتعديل تعيينات الأعضاء وإضافة مراقبين '));
            }
        }else{
            return redirect()->back()->withWarning(__('لا يوجد امناء سر كفايه للفرز من فضلك قم بتعديل تعيينات الأعضاء وإضافة أمناء سر جدد '));
        }
    }else{
        return redirect()->back()->withWarning(__('لا يوجد رؤساء قاعات كفايه للفرز من فضلك قم بتعديل تعيينات الأعضاء وإضافة رؤساء قاعات جدد '));
    }
    //dd("allli");
    return redirect("/rotations/$rotation->id/show")
    ->withSuccess(__('You have successfully distribute all Members to the sutable rooms'));
}

// public function verifyTakeRoomInCourse(array $course_rooms,int $course_id,int $room_id){
//     if(isset($course_rooms[$course_id][$room_id]))
//         return true;
//     return false;
// }

//distributeMembersOfFaculty
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {//$x=NULL;$x='2000'-'1997';dd($x);

        //$has_program=User::with('rooms','rotations')->get();
        //dd($has_program);
        $existing_rotation=Rotation::where('year',date("Y"))->pluck('year','name')->map(function($i){
            return $i;
        })->toArray();
        $count_existing_rotation=count($existing_rotation);
        $rotations = Rotation::orderBy('id','DESC')->get();
        list($all_rotations_table, $observations_number_in_latest_rotation)=Stock::calcInfoForEachRotationForSpecificuser(auth()->user());
        return view('rotations.index', compact('rotations','count_existing_rotation','observations_number_in_latest_rotation'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $existing_rotation=Rotation::where('year',date("Y"))->pluck(/*'year',*/'name')->map(function($i){
            return $i;
        })->toArray();
        $general_rotations=['الدورة الفصلية الأولى','الدورة الفصلية الثانية','الدورة الفصلية الثالثة'];
        $insertion_enabled_rotation = array_diff($general_rotations, $existing_rotation);

        return view('rotations.create',compact('insertion_enabled_rotation'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {    //dd($request['year']);
        $request['year']=(int)date('Y');
        //dd($request['year']);
        $this->validate($request,[
            'name' => [
                'required',
                Rule::unique('rotations')//verify that the name with year don't repated (both are unique)
                       ->where('year', $request['year'])
            ],
            //'year' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'faculty_id' => 'required'
        ],[
            'name.unique'=>'اسم الدورة موجود في السنة المحددة'
        ]);
        Rotation::create(
            [
                'name'=> $request->name,
                'year'=> $request->year,
                'start_date'=> $request->start_date,
                'end_date'=> $request->end_date,
                'faculty_id' => $request->faculty_id
            ]
        );
        return redirect()->route('rotations.index')
            ->withSuccess(__('rotation created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\rotation  $rotation
     * @return \Illuminate\Http\Response
     */
    public function edit(Rotation $rotation)
    {
        // dd($rotation->coursesProgram()->wherePivot('course_id','>',1)->where('semester',1)
        // ->get()
        // ->toArray());
        return view('rotations.edit', ['rotation' => $rotation]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\rotation  $rotation
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Rotation $rotation)
    {
        dd($rotation);
        $this->validate($request,[
            'name' => [
                'required',
                Rule::unique("rotations")->where(function ($query) use ($rotation) {
                        return $query->where(
                            [
                                ["year", "=", $rotation->year]
                            ]
                        );
                    })->ignore($rotation->id)//verify that the name with year don't repated (both are unique)(unique for escape comparing this routation)
            ],
            'year' => [
                'required',
                Rule::unique("rotations")->where(function ($query) use ($rotation) {
                    return $query->where(
                        [
                            ["name", "=", $rotation->name]
                        ]
                    );
                })->ignore($rotation->id)//verify that the name with year don't repated (both are unique)(unique for escape comparing this routation)
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'faculty_id' => $request->faculty_id
        ],[
            'name.unique'=>'هذه السنة موجوده تتضمن الدورة المحددة',
            'year.unique'=>'هذه الدوره موجوده في السنة المحددة'
        ]);
        $this->validate($request,[
            'name' => 'required']);
        if($rotation->update($request->all())){
            return redirect()->route('rotations.index')
            ->withSuccess(__('rotation updated successfully.'));
        }else{
            return redirect()->route('rotations.index')
            ->withDanger(__('faild updated rotation.'));
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\rotation  $rotation
     * @return \Illuminate\Http\Response
     */
    public function destroy(Rotation $rotation)
    {
        $rotation->delete();

        return redirect()->back()
            ->withSuccess(__('rotation deleted successfully.'));
    }

    public function create_initial_members(Rotation $rotation)
    {
        $room_heads=User::where('role','دكتور')->where('temporary_role')->get()->pluck('username','id')->toarray();
        $users= User::where('temporary_role','<>','رئيس شعبة الامتحانات')->where('role','!=','دكتور')->
                      orWhere('temporary_role')->where('role','!=','دكتور')->get()->pluck('username','id')->
                      toarray();
        $users_and_roomHeads=$room_heads+$users;
        $disabled_users=User::where('is_active',0)->pluck('id')->toarray();
        return view('Rotations.create_initial_members',compact('rotation','users','users_and_roomHeads','disabled_users'));
    }

    public function store_initial_members(Request $request, Rotation $rotation)
    {
        if(isset($request->users)){
            foreach ($request->users as $user_id => $options)
                $rotation->initial_members()->attach($user_id,['options'=> json_encode($options)]);
        }else{
            return redirect()->back()
            ->withWarning(__('please select at least on user'));
        }
        return redirect()->route('rotations.program.show',$rotation->id)
            ->withSuccess(__('store members successfully.'));
    }
    public function edit_initial_members(Rotation $rotation)
    {
        $room_heads=User::where('role','دكتور')->where('temporary_role')->get()->pluck('username','id')->toarray();
        $users= User::where('temporary_role','<>','رئيس شعبة الامتحانات')->where('role','!=','دكتور')->
                      orWhere('temporary_role')->where('role','!=','دكتور')->get()->pluck('username','id')->
                      toarray();
        $users_and_roomHeads=$room_heads+$users;
        $disabled_users=User::where('is_active',0)->pluck('id')->toarray();
        foreach ($rotation->initial_members()->get() as $user){
            $users_with_options[$user->id]=(array)json_decode($user->pivot->options);
        }
        return view('Rotations.edit_initial_members',compact('rotation','users','users_and_roomHeads','users_with_options','disabled_users'));
    }
    public function update_initial_members(Request $request, Rotation $rotation)
    {
        if(isset($request->users)){
            $rotation->initial_members()->detach();
            foreach ($request->users as $user_id => $options)
                $rotation->initial_members()->attach($user_id,['options'=> json_encode($options)]);
        }else{
            return redirect()->back()
            ->withWarning(__('please select at least on user'));
        }
        return redirect()->route('rotations.program.show',$rotation->id)
            ->withSuccess(__('update members successfully.'));
    }
    public function showObservationsInSpecificRotationForSpecificUser(Rotation $rotation, User $user){
        list($all_rotations_table, $observations_number_in_latest_rotation)=Stock::calcInfoForEachRotationForSpecificuser($user);
        if(array_key_exists($rotation->id,$all_rotations_table))
            return view('Rotations.Observations.User.show', [
                'user' => $user,
                'rotation_table' => $all_rotations_table[$rotation->id],
            ]);
        return view('Rotations.Observations.User.show',compact('rotation','user'));
    }



        //Exel
        /**
    * @return \Illuminate\Support\Collection
    */
    public function exportObservations(Rotation $rotation)
    {
        return Excel::download(new ObservationsExport($rotation), 'المراقبات الإمتحانية.xlsx');
        //return redirect()->back()->withSuccess(__('تم تحميل ملف المراقبات بنجاح'));
    }
    public function exportObservationsInSpecificDay(Rotation $rotation,$day)
    {
        return Excel::download(new exportObservationsInSpecificDay($rotation,$day), 'المراقبات الإمتحانية.xlsx');
        //return redirect()->back()->withSuccess(__('تم تحميل ملف المراقبات بنجاح'));
    }
    // /**
    // * @return \Illuminate\Support\Collection
    // */
    // public function import()
    // {
    //     Excel::import(new UsersImport,request()->file('file'));

    //Excel::import(new UsersImport,request()->file('file'));

    }
