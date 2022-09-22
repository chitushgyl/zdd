<?php
namespace App\Http\Api\Tms;



use App\Http\Controllers\Controller;
use App\Models\Tms\AppCar;
use App\Models\Tms\AppCarousel;
use App\Models\Tms\CarBrand;
use App\Models\Tms\TmsCarType;
use App\Models\Tms\TmsConnact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


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
          $select = ['self_id','car_brand'];
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
        $select = [];
        $select1 = ['self_id','parame_name'];
        $info = AppCar::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])->where($where)->select($select)->get();

        if($info){
            foreach($info as $k => $v){
                $v->picture = img_for($v->picture,'more');
            }
        }

        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $info;
        return $msg;

    }

    /*
     * 联系客服
     * */
    public function customer_service(Request $request){
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_group';

        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();
        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $company_name       =$request->input('company_name');
        $group_code         =$request->input('group_code');
        $connact            =$request->input('connact');
        $name       	    =$request->input('name');
        $address            =$request->input('company_address');
        $type               =$request->input('type');

        /*** 虚拟数据
        $input['self_id']           =$self_id='';
        $input['company_name']      =$company_name='company_name';
        $input['group_code']           =$group_code='group_code';
        $input['connact']         =$connact     ='connact';
        $input['name']              =$name   ='name';
        $input['address']          =$address  ='company_address';
        $input['type']             =$type  ='客户';
         ***/
//        dd($input);
        $rules=[
            'type'=>'required',
            'name'=>'required',
            'connact'=>'required',
        ];
        $message=[
            'type.required'=>'请选择类型',
            'name.required'=>'请填写姓名',
            'connact.required'=>'请填写联系方式',
        ];
        $validator=Validator::make($input,$rules,$message);

        if($validator->passes()){

            $wheres['self_id'] = $self_id;
            $old_info=TmsConnact::where($wheres)->first();

            if($old_info){

            }else{
                $data['self_id']            = generate_id('company_');		//优惠券表ID
                $data['company_name']       = $company_name;
                $data['connact']            = $connact;
                $data['group_code']         = $group_code;
                $data['name']               = $name;
                $data['type']      		    = $type;
                $data['address']      		= $address;
                $data['create_time']        = $data['update_time']	= $now_time;
                $data['address']      		= $address;
                $id=TmsConnact::insert($data);
                
            }

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }


        }else{
            //前端用户验证没有通过
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;
            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }


}
