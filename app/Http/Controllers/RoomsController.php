<?php

namespace App\Http\Controllers;
use App\Models\room;
use Illuminate\Http\Request;

class roomsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $rooms = room::orderBy('id')->get();
        return view('rooms.index', compact('rooms'));
    }

    public function isActive(Request $request, Room $room){
        if($room->is_active == true){
            $room->is_active = 0;
            $room->notes = "القاعة حاليا خارج الخدمة ";
            $room->save();
            return redirect()->route('rooms.index')
            ->withSuccess($room->room_name.' not active now.');
        }else {   
            $room->is_active = 1;
            $room->notes ="";
            $room->save();
            return redirect()->route('rooms.index')
            ->withSuccess($room->room_name.' active now.');
        }
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('rooms.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'room_name' => 'required|min:1|max:20|unique:rooms,room_name',
            'capacity' => 'required|min:1|max:1000',
            'faculty_id' => 'required'
        ],[
            'room_name.unique'=>'the name of room already exist'
        ]);
        room::create(
            [
                'room_name'=> $request->room_name,
                'capacity'=> $request->capacity,
                'extra_capacity'=> $request->extra_capacity,
                'location'=> $request->location,
                'notes'=> $request->notes,
                'faculty_id' =>  $request['faculty_id'],
            ]
        );
        return redirect()->route('rooms.index')
            ->withSuccess(__('room created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\room  $room
     * @return \Illuminate\Http\Response
     */
    public function edit(Room $room)
    {
        return view('rooms.edit', ['room' => $room]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\room  $room
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Room $room)
    {
        $this->validate($request,[
            'room_name' => 'required|min:1|max:20',
            'capacity' => 'required|min:1|max:1000',
            'faculty_id' => 'required'
        ],[
            'room_name.exists'=>'the name of room does not exist'
        ]);
        $room->update($request->all());
        return redirect()->route('rooms.index')
            ->withSuccess(__('room updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\room  $room
     * @return \Illuminate\Http\Response
     */
    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()->back()
        ->withSuccess(__('room deleted successfully.'));
    }
}
