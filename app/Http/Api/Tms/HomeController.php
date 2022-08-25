<?php
namespace App\Http\Api\Tms;



use App\Http\Controllers\Controller;
use App\Models\Tms\AppCarousel;
use App\Models\Tms\CarBrand;
use App\Models\Tms\TmsCarType;
use Illuminate\Http\Request;


class HomeController extends Controller {

      public function index(Request $request){
          $user_info     = $request->get('user_info');//接收中间件产生的参数
          $project_type       =$request->get('project_type');

          //获取轮播图
          $info['carousel'] = AppCarousel::where('delete_flag','Y')->limit(5)->get();
          //获取订单

          //
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
          $user_info     = $request->get('user_info');//接收中间件产生的参数
          $project_type       =$request->get('project_type');

          //获取轮播图
          $info = AppCarousel::where('delete_flag','Y')->limit(5)->get();
          if($info){
              foreach($info as $k =>$v){
                  $v->img = img_for($v->picture,'more');
              }
          }

          $msg['code'] = 200;
          $msg['msg']  = "数据拉取成功";
          $msg['data'] = $info;

          return $msg;
      }

      /*
       * 车型 品牌
       * */
      public function getCar(Request $request){
          $user_info     = $request->get('user_info');//接收中间件产生的参数
          $project_type       =$request->get('project_type');

          //获取品牌
          $select = ['self_id','brand','delete_flag','use_flag'];
          $info['brand'] = CarBrand::where('delete_flag','Y')->select($select)->get();
          //获取车型
          $select1 = ['self_id','parame_name','delete_flag'];
          $info['type']=TmsCarType::where('delete_flag','Y')->select($select1)->get();

          $msg['code'] = 200;
          $msg['msg']  = "数据拉取成功";
          $msg['data'] = $info;
          return $msg;

      }

      /**
       * 获取车辆
       * */
    public  function getCars(Request $request){
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
        //接收数据
        $car_type     = $request->input('car_type');
        $brand_type   = $request->input('brand_type');

        $search = [
            ['type'=>'=','name'=>'car_type','value'=>$car_type],
            ['type'=>'=','name'=>'brand_type','value'=>$brand_type],
        ];
        $where=get_list_where($search);
    }
}
