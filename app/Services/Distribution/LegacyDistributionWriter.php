<?php

namespace App\Services\Distribution;

use App\Http\Controllers\MaxMinRoomsCapacity\Stock;
use App\Models\Rotation;
use App\Models\User;

/**
 * Saves a distribution the original way: one insert per assignment, then
 * copies the members of rooms shared by same-time courses to the courses
 * that have none there yet, reading back from the database as it goes.
 *
 * Must run inside a transaction so a failure leaves no partial result.
 */
final class LegacyDistributionWriter
{
    public function save(Rotation $rotation, DistributionResult $result): void
    {
        $paths_info_room_heads = $result->roomHeads;
        $paths_info_secertaries = $result->secretaries;
        $paths_info_observers = $result->observers;

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
}
