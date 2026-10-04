{{-- @extends('layouts.app-master')

@section('content')
    <div class="bg-light p-4 rounded">
        <div class="container mt-4">
            @if ($password_message = Session::get('password-message'))
            <div class="alert alert-success alert-block">
                <strong>{{ $password_message }}</strong>
            </div>
            @endif
            <h1>
                تعديل مواد {{ $user->username }}
                <div class="float-right">
                    <a href="{{ URL::previous() }}" class="btn btn-dark">Back</a>
                </div>
            </h1>
            <form method="post" action="{{ route('users.update', $user->id) }}">
                @method('patch')
                @csrf
                <div class="mb-3">
                    <label for="courses_user_teach" class="form-label">courses_user_teach</label>
                    <select class="form-control" name="courses_user_teach" class="form-control" required>
                       @foreach (App\Models\Course::where('faculty_id',auth()->user()->faculty->id)->get() as $course)
                                <option value='{{ $course->id }}'>
                                    {{ $course->course_name }}
                                </option>
                       @endforeach
                    </select>
                    @if ($errors->has('courses_user_teach'))
                        <span class="text-danger text-left">{{ $errors->first('courses_user_teach') }}</span>
                    @endif
                </div>
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th scope="col" width="15%">Name</th>
                        <th scope="col">Year</th>
                        <th scope="col">Section</th>
                        <th scope="col" colspan="3" width="1%"></th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach(App\Models\Course::with('teachesBy')->whereHas('teachesBy',function($q) use($user){
                                $q->where('user_id',$user->id);
                        })->where('faculty_id',auth()->user()->faculty->id)->get() as $course)
                            <tr>
                                <td>{{ $course->course_name }}</td>
                                <td>{{ $course->studing_year }}</td>
                                <td>
                                      <div class="multiselect">
                                        <div id="checkboxes" class="d-flex">
                                          <label for="one">
                                            <input type="checkbox" id="one"
                                            name="sections_types[1]"
                                            class='rooms toggler-wrapper style-4'/>نظري</label>
                                          <label for="two">
                                            <input type="checkbox" id="two" 
                                            name="sections_types[2]"
                                            class='rooms toggler-wrapper style-4'/>عملي</label>
                                        </div>
                                      </div>
                                </td>
                                <td><a href="{{ route('courses.index', $course->id) }}" class="btn btn-info btn-sm">Edit</a></td>
                                <td>
                                    <form method="POST" action="{{ route('courses.destroy', $course->id) }}" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="submit" value="Delete" class="btn btn-danger btn-sm">
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="submit" class="btn btn-primary">Update user</button>
                <a href="{{ route('users.index') }}" class="btn btn-default">Cancel</button>
            </form>
        </div>
    </div>
@endsection --}}