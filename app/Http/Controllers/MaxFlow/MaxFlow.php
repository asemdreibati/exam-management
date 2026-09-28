<?php

namespace App\Http\Controllers\MaxFlow;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Self_;
class MaxFlow extends Controller
{
    public static int $V; // Number of vertices in graph
    //=======================  Asem  ===================
    public static $previous_user=0;
    public static $current_user=0;
    public static $count=0;
    public static $science_room_visited=false;
    private array $members;
    private array $courses;
    private array $rooms;
    private int $count_arr_same_time_courses;

    private array $observers_taken;
    public static int $count_of_paths=0;
    //=====================  Asem  =====================

    /**
     * Reset the static state carried between runs. Each distribution
     * request must start from these values, as in a fresh PHP process.
     */
    public static function resetState(): void
    {
        self::$previous_user = 0;
        self::$current_user = 0;
        self::$count = 0;
        self::$science_room_visited = false;
        self::$count_of_paths = 0;
    }

    public function __construct(int $length_graph,$members,$courses,$count_arr_same_time_courses , $rooms){
        Self::$V = $length_graph;
        $this->members=$members;

        $this->courses=$courses;
        $this->count_arr_same_time_courses=$count_arr_same_time_courses;

        ///dd($this->members ,"----",array_reverse($this->members ) );
        $this->rooms=$rooms;
        $this->observers_taken=array();
    }
    public function bfs(array &$rGraph,int $s, int $t, array &$parent) : bool {
        $is_postponed=[];
        $visited=[];
        for ($i = 0; $i < Self::$V; ++$i)
            $visited[$i] = false;

        $visited[count($this->members)+$this->count_arr_same_time_courses+count($this->courses)+1]=Self::$science_room_visited;
        $queue=[];
        array_push($queue,$s);
        $visited[$s] = true;
        $parent[$s] = -1;
        while (count($queue) != 0) {
            // Asem Very important !!!!!!!!!!!!!!!!!!!!!!!!!!!!

            $u = array_shift($queue);
            //if(Self::$count_of_paths>=643){
                //dump("Vertex: ".$u." ,Parent: ".$parent[$u] );
            //}
            if(in_array($u,$this->observers_taken) && $parent[$u]==$s && !in_array($u,$is_postponed) ){
                //dump("hi");
                array_push($queue,$u);
                array_push($is_postponed,$u);
                continue;
            }
            //dump($u);
            for ( $v = 0; $v < Self::$V; $v++) {
                if ($visited[$v] == false && $rGraph[$u][$v] > 0) {
                    if ($v == $t) {
                        $parent[$v] = $u;
                        Self::$count=(Self::$count+1)%4;
                        Self::$current_user=$parent[$parent[$parent[$parent[$v]]]];
                        return true;
                    }
                    array_push($queue,$v);
                    $parent[$v] = $u;
                    $visited[$v] = true;
                }
            }

        }
        return false;
    }


    public function fordFulkerson(array & $graph, int $s, int $t) : array {
        $u=0;
        $v=0;
        $paths=[];
        $rGraph = [];
        for ($u = 0; $u < Self::$V; $u++)
            for ($v = 0; $v < Self::$V; $v++)
                $rGraph[$u][$v] = $graph[$u][$v];
        $parent =[];
        for ($v = 0; $v <Self::$V ; $v++) {
            $parent[$v] =0;
        }
        $max_flow = 0; // There is no flow initially
        //$counter=0;
        while ($this->bfs($rGraph, $s, $t, $parent)) {
            $path=array();
            $path_flow = PHP_INT_MAX;
            for ($v = $t; $v != $s; $v = $parent[$v]) {
                $u = $parent[$v];
                $path_flow= min($path_flow, $rGraph[$u][$v]);
            }
            for ($v = $t; $v != $s; $v = $parent[$v]) {////dump($u,$v);
                array_push($path,$v);
                $u = $parent[$v];
                $rGraph[$u][$v] -= $path_flow;
                $rGraph[$v][$u] += $path_flow;
            }
            $max_flow += $path_flow;
            $path=array_reverse($path);

            //dump($path);
            array_push($this->observers_taken,$path[0]);
            //var_dump($this->observers_taken);
            if(count($this->observers_taken)==count($this->members)){
                $this->observers_taken=array();
            }
            // //===================Asem==============================================
            if(count($path)==5){
                //Undo Me:
                //Self::$count_of_paths=Self::$count_of_paths+1;
                $pure_parent=array(); // Build pure_parent array with |path| whereas parent array with |V|
                $counter_index=4;
                $k=Self::$V-1;
                while($parent[$k] !== -1){
                    $pure_parent[$counter_index] = $parent[$k];
                    $k=$parent[$k];
                    $counter_index--;
                }
                if(Self::$previous_user !==0 && Self::$previous_user !== Self::$current_user){
                    //dump("new user",Self::$count_of_paths);
                    Self::$count=0;
                    Self::$science_room_visited=false;
                }else{ //same user
                    if(Self::$count !== 0){
                        //dump("same user",Self::$count_of_paths);
                        $outdegree=0;
                        if($pure_parent[2]< count($this->members)+$this->count_arr_same_time_courses){ //Check if the there is another same_time
                            $next_course=-1;
                            for($i=count($this->members)+$this->count_arr_same_time_courses+1;$i<=count($this->members)+$this->count_arr_same_time_courses+count($this->courses);$i++){
                                if($rGraph[$pure_parent[2]][$i]>0 ){
                                    $next_course=$i;
                                    break;
                                }
                            }
                            if($next_course !== -1){
                                for($i =count($this->members)+$this->count_arr_same_time_courses+count($this->courses)+1;$i<Self::$V;$i++){
                                    if($rGraph[$next_course][$i]==1){
                                        $outdegree++;
                                    }
                                }
                            }
                            if($outdegree>1 && $rGraph[$next_course][count($this->members)+$this->count_arr_same_time_courses+count($this->courses)+1]==1){
                                Self::$science_room_visited=true;
                            }
                        }
                    }else{
                        Self::$science_room_visited=false;
                    }
                }
                Self::$previous_user = Self::$current_user;
            }
            // //===================Asem==============================================
            $path_length=count($path);
            $hint_paths=array();
            //$undo_path_from_user_x_and_give_it_to_user_y=array();

            //dump("max_flow ".$max_flow);
            //Delete Me
            Self::$count_of_paths=$max_flow;
            // if(Self::$count_of_paths>=643){
            //     dump("+========".Self::$count_of_paths);
            // }
            if($path_length>5){

                //dump($max_flow);
                $i=$path_length-1;
                $undo_path_from_user_x_and_give_it_to_user_y=array();
                $new_path_for_user_x=array();
                $user_x=-1;
                $user_y=-1;
                while($i != -1){
                    if(count($new_path_for_user_x)<5){
                        array_unshift($new_path_for_user_x,$path[$i]);
                        if(count($new_path_for_user_x)==5){
                            $user_x=$new_path_for_user_x[0];
                            array_push($hint_paths,$new_path_for_user_x);
                        }
                    }else{
                        if(($i%2)==0){
                            $user_y=$path[$i];
                            $undo_path_from_user_x_and_give_it_to_user_y[0][0]=$user_y;
                            array_push($hint_paths,$undo_path_from_user_x_and_give_it_to_user_y[0]);
                            $user_x=$user_y;
                        }else{
                            // find the current same time($i%2!=0) for $user_x to extract it and give it to the next user
                            //in the next iteration ($i%2==0)
                            $undo_path_from_user_x_and_give_it_to_user_y=array_filter($paths ,function($v)use ($user_x,$path,$i) {return ($v[0]==$user_x  && $v[1]==$path[$i]);});
                            //We need it! brcause we will dereferencing index [0] in the next iteration (($i%2)==0)
                            $undo_path_from_user_x_and_give_it_to_user_y=array_values($undo_path_from_user_x_and_give_it_to_user_y);
                            $paths=array_filter($paths ,function($v)use ($user_x,$path,$i) {return($v[0]!=$user_x || ($v[0]==$user_x && $v[1]!=$path[$i]));});
                            //No need! because $paths=array_merge($paths, $hint_paths); will take care of reindexing
                            $paths=array_values($paths);
                        }
                    }
                    $i--;
                }
            }
            if($path_length>5){
                //dump($path);
                //dump($undo_path_from_user_x_and_give_it_to_user_y);
                //dump($hint_paths);
                //dump('---------');
                $paths=array_merge($paths, $hint_paths);
                //dd($paths);
            }else{
                array_push($paths,$path);
            }
            $parent =[];
            for ($v = 0; $v <Self::$V ; $v++) {
                $parent[$v] =-2;
            }
        }
        $graph = $rGraph;
        //dump("max_flow ".$max_flow);
        $pathsDebug=array();
        //for($i=500;$i<count($paths);$i++)
            //array_push($pathsDebug,$paths[$i]);
       // dump($pathsDebug);
        //dd($graph);
        return $paths;
    }

}
