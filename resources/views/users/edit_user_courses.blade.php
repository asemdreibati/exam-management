@extends('layouts.app-master')

@section('content')
<div class="bg-light p-4 rounded">
    <div class="container mt-4">
        <h1 class="text-center">
            تعديل مواد {{ $user->username }}
            <div class="float-right">
                <a href="{{ route('users.create_user_courses',$user->id) }}" class="btn btn-outline-dark">إضافة مادة</a>
                <a href="{{ URL::previous() }}" class="btn btn-dark">Back</a>
            </div>
        </h1>
        <div class="mt-2">
            @include('layouts.partials.messages')
        </div>
        @if(count($user->teaches()->get()))
            <div class="row" style="margin-top:70px;">
                <div class="col-sm-10">
                    <form method="post" action="{{ route('users.update_user_courses', $user->id) }}">
                        @method('patch')
                        @csrf
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col" width="25%">اسم المادة</th>
                                <th scope="col" width="25%">السنة</th>
                                <th scope="col" width="5%">القسم المكلف فيه</th>
                                <th scope="col" width="25%">مشترك مع</th>
                            </tr>
                            </thead>
                            <tbody>
                                @foreach(App\Models\Course::with('teachesBy')->whereHas('teachesBy',function($q) use($user){
                                        $q->where('user_id',$user->id);
                                })->where('faculty_id',auth()->user()->faculty->id)->get() as $course)
                                    <tr>
                                        <td><span class="badge bg-success">{{ $course->course_name }}</span></td>
                                        <td>{{ $course->studing_year }}</td>
                                        <td>
                                            <div class="multiselect">
                                                <div id="checkboxes" class="d-flex">
                                                <label for="one">
                                                    <input type="checkbox" id="one"
                                                    name="sections_types[{{ $course->id }}][1]"
                                                    class='toggler-wrapper style-4'
                                                    {{ count($course->teachesBy()->wherePivot('user_id',$user->id)->wherePivot('section_type','نظري')->get())
                                                        ? 'checked'
                                                        : '' }}
                                                    {{ count($course->teachesBy()->wherePivot('user_id',$user->id)->wherePivot('section_type','نظري - عملي')->get())
                                                    ? 'checked'
                                                    : '' }}/>نظري</label>
                                                <label for="two">
                                                    <input type="checkbox" id="two" 
                                                    name="sections_types[{{ $course->id }}][2]"
                                                    class='toggler-wrapper style-4'
                                                    {{ count($course->teachesBy()->wherePivot('user_id',$user->id)->wherePivot('section_type','عملي')->get())
                                                        ? 'checked'
                                                        : '' }}
                                                    {{ count($course->teachesBy()->wherePivot('user_id',$user->id)->wherePivot('section_type','نظري - عملي')->get())
                                                    ? 'checked'
                                                    : '' }}/>عملي</label>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                        @if(isset($courses_common_with_users[$course->id]))
                                            @foreach ($courses_common_with_users[$course->id] as $username)
                                                <span class="badge bg-warning">{{ $username }}</span>
                                            @endforeach
                                        @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-primary">تعديل</button>
                    </form>
                </div>
                <div class="col-sm-1">
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col">خيارات</th>
                                <th scope="col" colspan="3" width="100%"></th>
                            </tr>
                            </thead>
                            <tbody>
                                @foreach(App\Models\Course::with('teachesBy')->whereHas('teachesBy',function($q) use($user){
                                        $q->where('user_id',$user->id);
                                })->where('faculty_id',auth()->user()->faculty->id)->get() as $course)
                                    <tr>
                                        <td>
                                            <form method="POST" action="{{ route('users.destroy_user_courses', ['user' => $user, 'course' => $course]) }}" style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <input type="submit" value="Delete" class="btn btn-danger btn-sm mb-3">
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </div>
            </div>
        @else
        <div class="alert alert-dark alert-dismissible fade show mt-4" role="alert">
            <strong>هذا الشخص لا يدرس ولا مادة , قم بإضافة مواد له من ثم يمكنك تعديلها</strong>
        </div>       
    @endif
    </div>
</div>
@endsection