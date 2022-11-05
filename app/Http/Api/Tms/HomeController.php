<?php
namespace App\Http\Api\Tms;



use App\Http\Controllers\Controller;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Group\SystemGroup;
use App\Models\Tms\AppCar;
use App\Models\Tms\AppCarousel;
use App\Models\Tms\CarBrand;
use App\Models\Tms\ChargeAddress;
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

          /**接收数据*/
          $num      = $request->input('num')??10;
          $page     = $request->input('page')??1;
          $car_type     = $request->input('car_type');
          $brand_type   = $request->input('brand_type');
          $listrows = $num;
          $firstrow = ($page-1)*$listrows;

          $search = [
              ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
              ['type'=>'=','name'=>'car_type','value'=>$car_type],
              ['type'=>'=','name'=>'brand','value'=>$brand_type],
          ];

          $where = get_list_where($search);
          $select = ['self_id','type','brand','car_type','create_time','update_time','price','view','car_name','picture','param'];
          $select1 = ['self_id','parame_name'];
          $select2 =['self_id','brand'];
          $data['info'] = AppCar::with(['tmsCarType'=>function($query)use($select1){
              $query->select($select1);
          }])
              ->with(['carBrand'=>function($query)use($select2){
                  $query->select($select2);
              }])->where($where)
              ->offset($firstrow)
              ->limit($listrows)
              ->orderBy('create_time', 'desc')
              ->select($select)
              ->get();

          foreach ($data['info'] as $k=>$v) {
              $v->picture = img_for($v->picture,'more');
              $v->car_type_show = $v->tmsCarType->parame_name;
              $v->car_type = $v->tmsCarType->parame_name;
              $v->brand = $v->carBrand->brand;
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
        $select = ['self_id','type','brand','car_type','create_time','price','view','car_name','picture'];
        $select1 = ['self_id','parame_name'];
        $info = AppCar::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])->where($where)->select($select)->get();

        if($info){

        }

        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $info;
        return $msg;

    }

    /**
     * 车辆详情
     * */
    public function carView(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'app_car';
        $select = ['self_id','type','brand','car_type','create_time','price','view','car_name','picture','param'];
        // $self_id = 'car_202101111749191839630920';
        $select1 = ['self_id','parame_name'];
        $select2 =['self_id','brand'];
        $info = AppCar::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])
            ->with(['carBrand'=>function($query)use($select2){
                $query->select($select2);
            }])->where('self_id',$self_id)
            ->select($select)
            ->first();

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->picture = img_for($info->picture,'more');
            $info->car_type_show = $info->tmsCarType->parame_name;
            $info->car_type = $info->tmsCarType->parame_name;
            $info->brand = $info->carBrand->brand;
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

    /*
     * 申请贷款
     * */
    public function customer_service(Request $request){
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_group';

        $user_info      = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();
        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $company_name       =$request->input('company_name');
        $group_code         =$request->input('group_code');
        $connact            =$request->input('connact');
        $name       	    =$request->input('name');
        $type       	    =$request->input('type');
        $channel_way        =$request->input('channel_way');
        $identity           =$request->input('identity');
        $id_front           =$request->input('id_front');
        $id_back            =$request->input('id_back');
        $auth_serch         =$request->input('auth_serch');
        $hold_img           =$request->input('hold_img');
        $auth_serch_company =$request->input('auth_serch_company');

        /*** 虚拟数据
        $input['self_id']           =$self_id     ='';
        $input['company_name']      =$company_name='company_name';
        $input['group_code']        =$group_code  ='group_code';
        $input['connact']           =$connact     ='connact';
        $input['name']              =$name        ='name';
        $input['type']              =$type        ='客户';
        $input['channel_way']       =$channel_way ='';//推荐渠道
        $input['identity']          =$identity    ='';//身份证号
        $input['id_front']          =$id_front    ='';//身份证正面
        $input['id_back']           =$id_back     ='';//身份证反面
        $input['auth_serch']        =$auth_serch  ='';//个人授权查询书
        $input['hold_img']          =$hold_img    ='';//手持照片
        $input['auth_serch_company']=$auth_serch_company    ='';//手持照片
         ***/
//        dd($input);
        if($type == 'personal'){
            $rules=[
                'name'=>'required',
                'connact'=>'required',
                'channel_way'=>'required',
                'identity'=>'required',
                'id_front'=>'required',
                'id_back'=>'required',
                'auth_serch'=>'required',
                'hold_img'=>'required',
            ];
            $message=[
                'name.required'=>'请填写姓名',
                'connact.required'=>'请填写联系方式',
                'company_name.required'=>'请填写公司名称',
                'channel_way.required'=>'请填写推荐渠道公司名称',
                'identity.required'=>'请填写身份证号',
                'id_front.required'=>'请上传身份证正面照',
                'id_back.required'=>'请上传身份证反面照',
                'auth_serch.required'=>'请上传个人授权查询书',
                'hold_img.required'=>'请上传手持照片',
            ];
        }else{
            $rules=[
                'name'=>'required',
                'company_name'=>'required',
                'connact'=>'required',
                'channel_way'=>'required',
                'auth_serch_company'=>'required',
            ];
            $message=[
                'name.required'=>'请填写姓名',
                'connact.required'=>'请填写联系方式',
                'company_name.required'=>'请填写公司名称',
                'channel_way.required'=>'请填写推荐渠道公司名称',
                'auth_serch_company.required'=>'请上传公司授权书',
            ];
        }
        $where = [];
        if($user_info->total_user_id){
            $where=[
                ['total_user_id','=',$user_info->total_user_id],
                ['delete_flag','=','Y'],
            ];
        }else{
            $where=[
                ['group_code','=',$user_info->group_code],
                ['delete_flag','=','Y'],
            ];
        }
        $connact_count = TmsConnact::where($where)->count();
        if ($connact_count >0){
            $connact_info = TmsConnact::where($where)->select('self_id','pass','first_trail')->orderBy('create_time','desc')->first();
            if ($connact_info->pass == 'W' && $connact_info->first_trail != 'N' ){
                $msg['code'] = 307;
                $msg['msg'] = '您的贷款申请正在审核中，请勿重复提交！';
                return $msg;
            }
        }


        if($channel_way){
            $name_where=[
                ['place_num','=',$channel_way],
                ['delete_flag','=','Y'],
            ];
            $group = SystemGroup::where($name_where)->count();

            if ($group<=0){
                $msg['code'] = 308;
                $msg['msg'] = '请填写正确的渠道公司！';
                return $msg;
            }
        }

        $validator=Validator::make($input,$rules,$message);

        if($validator->passes()){
            $wheres['self_id'] = $self_id;
            $old_info=TmsConnact::where($wheres)->first();
            $data['self_id']            = generate_id('connect_');		//优惠券表ID
            $data['company_name']       = $company_name;
            $data['connact']            = $connact;
            $data['name']               = $name;
            $data['type']      		    = $type;
            $data['channel_way']      	= $channel_way;
            $data['identity']      		= $identity;
            $data['id_front']      		= img_for($id_front,'one_in');
            $data['id_back']      		= img_for($id_back,'one_in');
            $data['auth_serch']      	= img_for($auth_serch,'one_in');
            $data['hold_img']      		= img_for($hold_img,'one_in');
            $data['auth_serch_company'] = img_for($auth_serch_company,'one_in');
            if($old_info){
                $data['first_trail']      		= 'W';
                $data['update_time'] = $now_time;
                $id = TmsConnact::where($wheres)->update($data);
            }else{
                $data['group_code']         = $group_code;
                $data['total_user_id']      = $user_info->total_user_id;
                $data['create_time']        = $data['update_time']	= $now_time;
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
                $msg['msg'].='：'.$v;
            }
            return $msg;
        }
    }

    /**
     *
     * */

    /**
     * 优惠充电 /api/home/getChargeAddress
     * */
    public function getChargeAddress(Request $request){
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type  = $request->get('project_type');
        //接收数据
        $address     = $request->input('address');
        $name     = $request->input('name');

        $search = [
            ['type'=>'like','name'=>'address','value'=>$address],
            ['type'=>'like','name'=>'name','value'=>$name],
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
        ];
        $where=get_list_where($search);
        $select = ['self_id','name','address','open_time','view','picture','lat','lnt','create_time'];
        $info = ChargeAddress::where($where)->select($select)->get();

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

    /**
     * 我的贷款
     * */
    public function myLoan(Request $request){
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type  = $request->get('project_type');
        $total_user_id = $user_info->total_user_id;

        /**接收数据*/
        $num      = $request->input('num')??10;
        $page     = $request->input('page')??1;
        $listrows = $num;
        $firstrow = ($page-1)*$listrows;

        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'total_user_id','value'=>$total_user_id],
            ['type'=>'=','name'=>'group_code','value'=>$user_info->group_code],
        ];


        $where = get_list_where($search);
        $select = ['self_id','name','connact','type','fail_reason','company_name','address','read_flag','delete_flag','group_code','channel_way','identity','id_front','id_back','auth_serch','auth_serch_company','hold_img','first_trail','pass','total_user_id','create_time','update_time'];
        $data['info'] = TmsConnact::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->id_front   = img_for($v->id_front,'no_json');
            $v->id_back    = img_for($v->id_back,'no_json');
            $v->auth_serch = img_for($v->auth_serch,'no_json');
            $v->auth_serch_company = img_for($v->auth_serch_company,'no_json');
            $v->hold_img = img_for($v->hold_img,'no_json');

        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;

        return $msg;
    }

    /**
     * 身份证验证
     * */
    public function testId(Request $request){
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type  = $request->get('project_type');
        $total_user_id = $user_info->total_user_id;

        /**接收数据*/
        $body      = $request->input('bodys');

        $host = "https://zid.market.alicloudapi.com";
        $path = "/thirdnode/ImageAI/idcardfrontrecongnition";
        $method = "POST";
        $appcode = "c107e03b181e422c87a6e3a4f92db756";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $bodys = "base64Str=".$body;
        $url = $host . $path;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        $result = curl_exec($curl);

        $result = json_decode($result,true);
        if ($result['error_code'] != 0){
            $msg['code'] = 300;
            $msg['msg']  = "验证失败，请重新拍照上传！";
            return $msg;
        }

        if($result['error_code'] == 0 && $result['result']['direction'] >= 0){
            $msg['code'] = 200;
            $msg['msg']  = "验证成功";
            $data['name'] = $result['result']['name'];
            $data['ID'] = $result['result']['idcardno'];
            $msg['data'] = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "验证失败，请重新拍照上传！";
            return $msg;
        }


    }


}
