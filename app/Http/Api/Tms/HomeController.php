<?php
namespace App\Http\Api\Tms;



use http\Env\Request;

class HomeController extends Controller{

      public function index(Request $request){

      }

      /*
       * 车辆销售
       * */
      public function carList(Request $request){
          $user_info     = $request->get('user_info');//接收中间件产生的参数
          $project_type       =$request->get('project_type');
          $total_user_id = $user_info->total_user_id;
          $tms_car_possess_type = array_column(config('tms.tms_car_possess_type'),'name','key');
          $tms_control_type     = array_column(config('tms.tms_control_type'),'name','key');
          /**接收数据*/
          $num      = $request->input('num')??10;
          $page     = $request->input('page')??1;
          $listrows = $num;
          $firstrow = ($page-1)*$listrows;

          $search = [
              ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
              ['type'=>'=','name'=>'total_user_id','value'=>$total_user_id],
          ];

          $where = get_list_where($search);
          $select = ['self_id','car_brand','car_number','car_possess','weight','volam','control','car_type_name','use_flag','contacts','tel','medallion','license'];
          $data['info'] = TmsCar::where($where)
              ->offset($firstrow)
              ->limit($listrows)
              ->orderBy('create_time', 'desc')
              ->select($select)
              ->get();

          foreach ($data['info'] as $k=>$v) {
              $v->car_possess_show =  $tms_car_possess_type[$v->car_possess] ?? null;
              $v->tms_control_type_show = $tms_control_type[$v->control] ?? null;


          }
          $msg['code'] = 200;
          $msg['msg']  = "数据拉取成功";
          $msg['data'] = $data;

          return $msg;
      }

      /*
       * 轮播图
       * */
      public function getCarousel(Request $request){

      }

      /*
       * 车型 品牌
       * */
      public function getCar(Request $request){

      }

      /*
       *
       * */
}
