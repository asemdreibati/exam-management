<?php

namespace App\Services\Distribution;

/**
 * The roles a member can fill in an exam room. Each case's name is what
 * course_room_rotation_user.roleIn stores, so "Secertary" keeps its
 * original spelling.
 */
enum MemberRole
{
    case RoomHead;
    case Secertary;
    case Observer;
}
