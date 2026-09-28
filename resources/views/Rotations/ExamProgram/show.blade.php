@extends('layouts.app-master')
@section('content')
    <div class="bg-light p-2 rounded">
        {{--@if(Auth::user()->temporary_role == "رئيس شعبة الامتحانات" || Auth::user()->temporary_role == "عميد")
            <h1 style="display: inline-flex;">
               @php@php
                $num_of_my_courses_objections=App\Models\Course::with('rotationsObjection')->whereHas('rotationsObjection', function($query) use($rotation){
                    $query->where('user_id',Auth::user()->id)->where('rotation_id',$rotation->id);})->pluck('id')->toArray();
                @endphp
                @php
                list($all_rotations_table, $observations_number_in_latest_rotation)=App\Http\Controllers\MaxMinRoomsCapacity\Stock::calcInfoForEachRotationForSpecificuser(Auth::user());
                @endphp
                 @if($observations_number_in_latest_rotation)
                    @if(!count($num_of_my_courses_objections))
                        <a href="{{ route('rotations.objections.create',[\App\Models\Rotation::latest()->first()->id, Auth::user()->id]) }}" class="btn btn-danger">إنشاء إعتراضات</a>
                    @else
                        <a href="{{ route('rotations.objections.edit',[\App\Models\Rotation::latest()->first()->id, Auth::user()->id]) }}" class="btn btn-danger">تعديل إعتراضاتي</a>
                    @endif
                    @endif
            </h1>
            @else
            {{-- <h1 style="display: inline-flex;">
                @php
                $num_of_my_courses_objections=App\Models\Course::with('rotationsObjection')->whereHas('rotationsObjection', function($query) use($rotation){
                    $query->where('user_id',Auth::user()->id)->where('rotation_id',$rotation->id);})->pluck('id')->toArray();
                @endphp
                @if(!count($num_of_my_courses_objections))
                    <a href="{{ route('rotations.objections.create',$rotation->id) }}"  class="btn btn-secondary float-left me-2 m4-2">إنشاء إعتراضات</a>
                @else
                    <a href="{{ route('rotations.objections.edit',$rotation->id) }}"  class="btn btn-secondary float-left me-2 m4-2">تعديل إعتراضاتي</a>
                @endif
            </h1>
        @endif --}}
        @if(Auth::user()->temporary_role == "رئيس شعبة الامتحانات" || Auth::user()->temporary_role == "عميد")
            @php
                list($all_rotations_table, $observations_number_in_latest_rotation)=App\Http\Controllers\MaxMinRoomsCapacity\Stock::calcInfoForEachRotationForSpecificuser(Auth::user());
            @endphp
            {{-- <div class="position-relative m-4">
                <div class="progress" style="height: 1px;">
                  <div class="progress-bar" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <button type="button" class="position-absolute top-0 start-0 translate-middle btn btn-sm btn-primary rounded-pill" style="width: 2rem; height:2rem;">1</button>
                <button type="button" class="position-absolute top-0 start-50 translate-middle btn btn-sm btn-primary rounded-pill" style="width: 2rem; height:2rem;">2</button>
                <button type="button" class="position-absolute top-0 start-100 translate-middle btn btn-sm btn-secondary rounded-pill" style="width: 2rem; height:2rem;">3</button>
            </div> --}}
            <div class="text-center mb-4">
                <div class="btn-group" role="group" aria-label="Basic example">
                    @if(count($rotation->coursesProgram()->toBase()->get()) )
                        @if(!count($rotation->rooms()->toBase()->get()))
                            @if(!count($rotation->initial_members()->toBase()->get()))
                                <a href="{{ route('rotations.create_initial_members',$rotation->id) }}" class="btn btn-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill-add" viewBox="0 0 16 16">
                                        <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0Zm-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                        <path d="M2 13c0 1 1 1 1 1h5.256A4.493 4.493 0 0 1 8 12.5a4.49 4.49 0 0 1 1.544-3.393C9.077 9.038 8.564 9 8 9c-5 0-6 3-6 4Z"/>
                                      </svg> إنشاء تعيينات الأعضاء</a>
                            @else
                                <a href="{{ route('rotations.edit_initial_members',$rotation->id) }}" class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
                                    <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z"/>
                                  </svg> تعديل تعيينات الأعضاء</a>
                            @endif
                        @endif
                        @if(!count($rotation->distributionCourse()->toBase()->get()))
                            <a href="#sortModalToggle" data-bs-toggle="modal" class='btn btn-secondary rounded-0 rounded-end'>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                                    <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                                  </svg> توزيع الطلاب على القاعات</a>
                            @include('layouts.partials.popUpSort',['route_info' => ['rotations.distributeStudents',$rotation->id],'name_button' => "توزيع الطلاب"])
                            <a href="{{ route('rotations.program.add_course_to_the_program',$rotation->id) }}" class="btn btn-success float-right me-2 m4-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal-plus" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M8 5.5a.5.5 0 0 1 .5.5v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2z"/>
                                    <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1z"/>
                                </svg> إضافة مقرر للبرنامج
                            </a>
                        @elseif(!count($rotation->rooms()->toBase()->get()))
                            <a href="#sortModalToggle" data-bs-toggle="modal" class='btn btn-dark rounded-0 rounded-end'>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                              </svg> توزيع الأعضاء على القاعات</a>
                            @include('layouts.partials.popUpSort',['route_info' => ['rotations.distributeMembersOfFaculty',$rotation->id]
                            ,'name_button' => "توزيع الأعضاء"])
                            <a href="#initializationRoomsInAllCourses" data-bs-toggle="modal" class='btn btn-secondary rounded-0 rounded-end'><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                            </svg> تهيئة القاعات</a>
                            @include('layouts.partials.initialization.initializationRoomsInAllCourses',['route_info' => ['rotations.initRoomsInAllCourses',$rotation->id],'header_text'=>'تهيئة القاعات','description'=>'!!هل أنت متاكد أنك تريد تهيئة القاعات , سيؤدي ذلك لحذف كل القاعات من المقررات بالكامل'])
                        @endif
                        @if(count($rotation->rooms()->toBase()->get()))
                            @if(!$expire_rotation_date)
                            {{-- Added --}}
                                <a href="{{ route('rotations.edit_initial_members',$rotation->id) }}" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
                                    <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z"/>
                                </svg> مشاهدة تعيينات الأعضاء</a>
                            {{-- Added --}}
                                <a href="#initializationInExamProgram" data-bs-toggle="modal" class='btn btn-danger'><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                </svg> تهيئة البرنامج الإمتحاني</a>
                                @include('layouts.partials.initialization.initializationInExamProgram',['route_info' => ['rotations.initExamProgram',$rotation->id],'header_text'=>'تهيئة البرنامج الإمتحاني','description'=>'!!هل أنت متاكد أنك تريد تهيئة البرنامج الإمتحاني , سيؤدي ذلك لحذف كل المقررات بالكامل'])

                                <a href="#intializationInExamProgramModalToggle" data-bs-toggle="modal" class='btn btn-dark rounded-0 rounded-end'><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-x" viewBox="0 0 16 16">
                                    <path d="M6.146 7.146a.5.5 0 0 1 .708 0L8 8.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 9l1.147 1.146a.5.5 0 0 1-.708.708L8 9.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 9 6.146 7.854a.5.5 0 0 1 0-.708z"/>
                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                                </svg> تهيئة المراقبات</a>
                                @include('layouts.partials.initialization.initializationUsersObservationsInAllCourses',['route_info' => ['rotations.initUsersObservationsInAllCourses',$rotation->id],'header_text'=>'تهيئة المراقبات','description'=>'!!هل أنت متاكد أنك تريد تهيئة المراقبات , سيؤدي ذلك لحذف كل الأشخاص الذين تم تعيينهم في القاعات وذلك في جميع المقررات'])
                            @endif
                            <a href="{{ route('observations.export',$rotation->id) }}" class="btn btn-success" onclick="showPopUpCubic()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-spreadsheet" viewBox="0 0 16 16">
                                    <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V9H3V2a1 1 0 0 1 1-1h5.5v2zM3 12v-2h2v2H3zm0 1h2v2H4a1 1 0 0 1-1-1v-1zm3 2v-2h3v2H6zm4 0v-2h3v1a1 1 0 0 1-1 1h-2zm3-3h-3v-2h3v2zm-7 0v-2h3v2H6z"/>
                                </svg> تحميل المراقبات</a>
                        @endif
                    @else
                    <a href="{{ route('rotations.program.add_course_to_the_program',$rotation->id) }}" class="btn btn-success float-right me-2 m4-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal-plus" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 5.5a.5.5 0 0 1 .5.5v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 .5-.5z"/>
                        <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2z"/>
                        <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1z"/>
                        </svg> إضافة مقرر للبرنامج
                    </a>
                    @endif
                </div>
            </div>
        @endif
        <div class="row">
            <div class="col-lg-10 col-sm-10 col-xs-10">
                <h1 class="text-center m-0">
                    {{ $rotation->faculty->name }} - برنامج امتحان {{ $rotation->name }} - {{ $rotation->year }}
                </h1>
            </div>
            <div class="col-lg-2 col-sm-2 col-xs-2">
                <div class="collect-index-btns gap-1">
                    <a href="{{url()->previous()}}" class="btn btn-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM4.5 7.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H4.5z"/>
                        </svg> رجوع
                    </a>
                </div>
            </div>
        </div>

        @if ($createUpdateObjections = Session::get('createUpdateObjections'))
        <div class="alert alert-success alert-block">
            <strong>{{ $createUpdateObjections }}</strong>
        </div>
        @endif
        <div class="container-fluid p-2 rounded">
            <div class="mt-2">
                @include('layouts.partials.messages')
            </div>
            @if(count($courses_info))
            <table class="table" class='exam-program'>
            <thead>
                <tr>
                    <td align="center" height="100" width="5%"><br>
                        <b>Day/Period</b></br>
                    </td>
                    <td align="center" height="100" width="19%">
                        <b>I<br>One Year</b>
                    </td>
                    <td align="center" height="100" width="19%">
                        <b>II<br>Two Year</b>
                    </td>
                    <td align="center" height="100" width="19%">
                        <b>III<br>Three Year</b>
                    </td>
                    <td align="center" height="100" width="19%">
                        <b>IV<br>Fourth Year</b>
                    </td>
                    <td align="center" height="100" width="19%" style="position: absolute;padding-top: 43px;">
                        <b>IIV<br>Fifth Year</b>
                    </td>
                </tr>
            </thead>
            <tbody>
                {{-- @dd($courses_info) --}}
                @foreach($courses_info as $date => $all_years)
                    <tr>
                        <td class="date" align="center" height="100">
                            <b>{{ date('D d-m-Y', strtotime($date)) }}</b>
                        </td>
                        @php
                            $counter_one=0;
                            $counter_two=0;
                            $counter_three=0;
                            $counter_four=0;
                            $counter_five=0;
                            $years=[];
                            foreach($all_years as $year_number => $courses_arrs)
                                array_push($years, $year_number);
                        @endphp
                        @foreach($all_years as $year_number => $courses_numbers_arrs)
                                @foreach ($courses_numbers_arrs as $id_course => $time)
                                    @if($year_number==2 && ! $counter_two)
                                        @if(!in_array(1,$years))
                                            <td></td>
                                        @endif
                                        @php $counter_one++; @endphp
                                    @elseif($year_number==3 && ! $counter_three)
                                        @if(!in_array(2,$years))
                                            @if(!in_array(1,$years))
                                                <td></td><td></td>
                                            @else
                                                <td></td>
                                            @endif
                                        @endif
                                        @php $counter_three++; @endphp
                                    @elseif($year_number==4 && ! $counter_four)
                                        @if(!in_array(3,$years))
                                            @if(!in_array(2,$years))
                                                @if(!in_array(1,$years))
                                                    <td></td><td></td><td></td>
                                                @else
                                                    <td></td><td></td>
                                                @endif
                                            @else
                                                <td></td>
                                            @endif
                                        @else
                                            {{-- @if(in_array(5,$years))
                                            <td></td>
                                            @endif --}}
                                        @endif
                                        @php $counter_four++; @endphp
                                    @elseif($year_number==5 && ! $counter_five)
                                        @if(!in_array(4,$years))
                                            @if(!in_array(3,$years))
                                                @if(!in_array(2,$years))
                                                    @if(!in_array(1,$years))
                                                        <td></td><td></td><td></td><td></td>
                                                    @else
                                                        <td></td><td></td><td></td>
                                                    @endif
                                                @else
                                                    <td></td><td></td>
                                                @endif
                                            @else
                                                <td></td>
                                            @endif
                                        @else
                                            {{-- @if(in_array(5,$years))
                                            <td></td>
                                            @endif --}}
                                        @endif
                                        @php $counter_five++; @endphp
                                    @endif
                                    @php
                                    $courseQ= App\Models\Course::where('id',$id_course)->first();
                                    @endphp
                                    @if($courseQ)
                                        @if($year_number===5)
                                        <td></td>
                                        @endif
                                        <td class="course" style="align-items: center;{{($year_number==4||$year_number==5) ?'display: contents;':''}}">
                                            <h5 class='course-name'>
                                                @php
                                                    if($courseQ)
                                                        echo $courseQ->course_name;
                                                @endphp
                                            </h5>
                                            @if(count($rotation->distributionRoom()->wherePivot('course_id',$courseQ->id)->toBase()->get()))
                                                <div class="controll">
                                                    {{-- {{ $year_number }} --}}
                                                        @if(Auth::user()->temporary_role == "رئيس شعبة الامتحانات" || Auth::user()->temporary_role == "عميد")
                                                            @if(!$expire_rotation_date)
                                                                <a href="{{route('rotations.course.edit',['rotation'=>$rotation->id,'course'=>$courseQ->id])}}" class="btn btn-info btn-sm btn-outline-light rounded">Edit</a>
                                                            @endif
                                                            @if(!count($rotation->rooms()->toBase()->get()))
                                                                <a href="#exampleModalToggle{{ $id_course }}" data-bs-toggle="modal"  href="{{route('rotations.course.delete_course_from_program',['rotation'=>$rotation->id,'course'=>$id_course])}}" class="btn btn-danger btn-sm btn-outline-light rounded">حذف</a>
                                                            @else
                                                                <a href="{{route('rotations.course.show',['rotation'=>$rotation->id,'course'=>$courseQ->id])}}" class="btn btn-warning btn-sm btn-outline-light rounded">Show</a>
                                                            @endif
                                                        @endif
                                                        <span class="badge bg-secondary m-0">{{gmdate('H:i A',strtotime($time))}}</span>
                                                </div>
                                            @else
                                                <a href="#exampleModalToggle{{ $id_course }}" data-bs-toggle="modal" class="badge bg-danger rounded">حذف</a>
                                                <span class="badge bg-secondary m-0">{{gmdate('H:i A',strtotime($time))}}</span>
                                            @endif
                                            @include('layouts.partials.popUpDelete',['route_info' => ['rotations.course.delete_course_from_program', $rotation->id, $id_course],'description' => "هل أنت متأكد أنك تريد حذف المقرر $courseQ->course_name"])
                                        </td>
                                    @endif
                                @endforeach
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <div class="alert text-black alert-warning text-center mt-5" role="alert">
                <h3 class="alert-heading">الدورة فارغة من المقررات<h3>
            </div>
        @endif
      </div>
    </div>
@endsection
