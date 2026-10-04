<div class="modal fade" id="exampleModalToggle{{ isset($route_info[2])?$route_info[2]:$route_info[1] }}" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        {{-- @dd($route_info) --}}
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalToggleLabel"><b>حذف {{ ($route_info[0]=='rotations.course.delete_course_from_program')?'مقرر من البرنامج الإمتحاني':'' }}{{ ($route_info[0]=='rooms.destroy')?'الغرفة':'' }}{{ ($route_info[0]=='users.destroy')?'الشخص':'' }}{{ ($route_info[0]=='courses.destroy')?'المقرر':'' }}{{ ($route_info[0]=='rotations.destroy')?'الدورة':'' }}</b></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          {{$description}}
      </div>
        <div class="modal-footer">
          {{-- <button class="btn btn-primary" data-bs-target="#exampleModalToggle2" data-bs-toggle="modal" data-bs-dismiss="modal">Open second modal</button> --}}
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <form method="POST" action="{{ route($route_info[0], array_slice($route_info, 1)) }}" style="display:inline">
            @csrf
            @method('DELETE')
            <input type="submit" value="Delete" class="btn btn-danger">
          </form>
        </div>
      </div>
    </div>
  </div>
  {{-- <div class="modal fade" id="exampleModalToggle2" aria-hidden="true" aria-labelledby="exampleModalToggleLabel2" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalToggleLabel2">Modal 2</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Hide this modal and show the first with the button below.
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" data-bs-target="#exampleModalToggle" data-bs-toggle="modal" data-bs-dismiss="modal">Back to first</button>
        </div>
      </div>
    </div>
  </div> --}}