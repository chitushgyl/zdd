<?php
namespace App\Http\Api\Tms;
use app\models\AppSetParam;
use App\Models\Tms\TmsCarType;
use App\Models\Tms\TmsCity;
use App\Models\Tms\TmsGroup;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderMoney;
use App\Models\Tms\TmsParam;
use App\Models\Tms\TmsPayment;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\Tms\TmsLine;
use App\Http\Controllers\TmsController as Tms;
class OrderController extends Controller{

    /*
    **    用户端订单列表      /api/order/orderPage
    */
    public function orderPage(Request $request){
        $pay_status     =config('tms.tms_order_status_type');
        $tms_order_type        = array_column(config('tms.tms_order_type'),'name','key');
        $project_type       =$request->get('project_type');
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $order_status     = $request->post('status');//接收中间件产生的参数
        $button_info =     $request->get('buttonInfo');
        $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');

        $total_user_id = $user_info->total_user_id;
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $order_type    = $request->input('order_type') ?? '';//vehicle  lcl   line
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
        ];

        switch ($project_type){
            case 'user':
                $search[] =['type'=>'=','name'=>'delete_flag','value'=>'Y'];
                $search[] =    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id];
                break;
            case 'customer':
                $search[] =  ['type'=>'=','name'=>'delete_flag','value'=>'Y'];
                $search[] =    ['type'=>'=','name'=>'company_id','value'=>$user_info->userIdentity->company_id];
                break;
            case 'company':
                $search[] =  ['type'=>'=','name'=>'delete_flag','value'=>'Y'];
                $search[] =    ['type'=>'=','name'=>'group_code','value'=>$user_info->group_code];
                break;
        }
        if ($order_type) {
            $search[] = ['type'=>'=','name'=>'order_type','value'=>$order_type];
        }
        $where  = get_list_where($search);
        $select = ['self_id','group_name','company_name','create_user_name','create_time','use_flag','order_type','order_status','car_type','clod','pick_flag','send_flag',
            'gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_qu_name','gather_address','pay_state',
            'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_qu_name','send_address','total_money','pay_type',
            'good_name','good_number','good_weight','good_volume','gather_shi_name','send_shi_name','gather_time','send_time'];
        $select2 = ['self_id','parame_name'];
        $data['info'] = TmsOrder::with(['TmsCarType' => function($query) use($select2){
            $query->select($select2);
        }])
            ->where($where);

        switch ($project_type){
            case 'user':
                if ($order_status){
                    if ($order_status == 1){
                        $data['info'] = $data['info']->where('order_status',1);
                    }elseif($order_status == 2){
                        $data['info'] = $data['info']->where('order_status',2);
                    }elseif($order_status == 3){
                        $data['info'] = $data['info']->whereIn('order_status',[3,4,5]);
                    }elseif($order_status == 4){
                        $data['info'] = $data['info']->where('order_status',6);
                    }else{
                        $data['info'] = $data['info']->where('order_status',7);
                    }
                }
                break;
            case 'customer':
                if ($order_status){
                    if ($order_status == 2){
                        $data['info'] = $data['info']->where('order_status',3);
                    }elseif($order_status == 3){
                        $data['info'] = $data['info']->whereIn('order_status',[4,5]);
                    }elseif($order_status == 4){
                        $data['info'] = $data['info']->where('order_status',6);
                    }else{
                        $data['info'] = $data['info']->where('order_status',7);
                    }
                }
                break;
            case 'company':
                if ($order_status){
                    if ($order_status == 1){
                        $data['info'] = $data['info']->where('order_status',1);
                    }elseif($order_status == 2){
                        $data['info'] = $data['info']->where('order_status',2);
                    }elseif($order_status == 3){
                        $data['info'] = $data['info']->whereIn('order_status',[3,4,5]);
                    }elseif($order_status == 4){
                        $data['info'] = $data['info']->where('order_status',6);
                    }else{
                        $data['info'] = $data['info']->where('order_status',7);
                    }
                }
                break;
            case 'business':

                break;
        }

        $data['info'] = $data['info']->offset($firstrow)
            ->limit($listrows)
            ->orderBy('update_time', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->total_money       = number_format($v->total_money/100, 2);
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->order_status_show=$pay_status[$v->order_status-1]['pay_status_text']??null;
            $v->order_type_show   = $tms_order_type[$v->order_type] ?? null;
            $v->self_id_show = substr($v->self_id,15);
            $v->clod=json_decode($v->clod,true);
            $v->send_time = date('m-d H:i',strtotime($v->send_time));
            $info_clod = $v->clod;
            foreach ($info_clod as $key => $value){
                $info_clod[$key]=$tms_control_type[$value];
            }
            $v->clod = $info_clod;
            $temperture = $v->clod;
            foreach ($temperture as $key => $value){
                $temperture[$key] = $value;
            }
            $v->temperture = implode(',',$temperture);
            if ($v->TmsCarType){
                $v->car_type_show = $v->TmsCarType->parame_name;
            }
            $button1 = [];
            $button2 = [];
            $button3 = [];
            $button4 = [];
            switch ($project_type){
                case 'user':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 118 ){
                            $button1[] = $value;
                        }
                        if ($value->id == 182){
                            $button3[] = $value;
                            $button4[] = $value;
                        }
                        if ($value->id == 119){
                            $button2[] = $value;
                            $button3[] = $value;
                        }
                        if ($v->order_status == 2){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 5){
                            $v->button  = $button2;
                        }
                        if ($v->order_status  == 5 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button3;
                        }
                        if ($v->order_status  == 6 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button4;
                        }
                    }
                    break;
                case 'company':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 161){
                            $button1[] = $value;
                        }
                        if ($value->id == 183){
                            $button3[] = $value;
                            $button4[] = $value;
                        }
                        if ($value->id == 162){
                            $button2[] = $value;
                            $button3[] = $value;
                        }
                        if ($v->order_status == 2){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 5){
                            $v->button  = $button2;
                        }
                        if ($v->order_status  == 5 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button3;
                        }
                        if ($v->order_status  == 6 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button4;
                        }
                    }
                    break;
            }

        }
//        dd($data['info']->toArray());
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /*
    **    新建订单     /api/order/createOrder
    */
    public function createOrder(Request $request){
        /** 接收数据*/
        $data['tms_order_type']   = config('tms.tms_order_type');
        $data['tms_control_type'] = config('tms.tms_control_type');
        $data['tms_pick_type']    = config('tms.tms_pick_type');
        $data['tms_send_type']    = config('tms.tms_send_type');
        $self_id = $request->input('self_id');
        // $self_id = 'order_202101121015236213312314';
        $where   = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id','company_id','group_code','info','pick_flag','send_flag','price','more_money','total_money','pick_money','send_money','order_type','line_id'];
        $detail = TmsOrder::where($where)->select($select)->first();
        if ($detail) {
            $detail->info =json_decode($detail->info,true);
            $detail->price      = $detail->price ? number_format($detail->price/100, 2) : '';
            $detail->more_money = $detail->more_money ? number_format($detail->more_money/100, 2) : '';
            $detail->pick_money = $detail->pick_money ? number_format($detail->pick_money/100, 2) : '';
            $detail->send_money = $detail->send_money ? number_format($detail->send_money/100, 2) : '';
        }
        $data['info'] = $detail;
        $msg['code']  = 200;
        $msg['msg']   = "数据拉取成功";
        $msg['data']  = $data;
        return $msg;
    }

    /***    新建订单数据提交      /api/order/addOrder
     */
    public function addOrder(Request $request,Tms $tms){
        $project_type       =$request->get('project_type');
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name = 'tms_order';
        $user_info  = $request->get('user_info');//接收中间件产生的参数
//        dd($project_type);
//        dd($user_info);
        // $project_type = 'company';
        $total_user_id  = $user_info->total_user_id;
//        $token_name     = $user_info->token_name;
        $input      =$request->all();

        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $order_type    = $request->input('order_type');//订单类型 vehicle  lcl   line
        $line_id       = $request->input('line_id');//线路 id
        $pick_flag     = $request->input('pick_flag');//是否提货、装货
        $send_flag     = $request->input('send_flag');//是否配送、卸货
        $pick_money    = $request->input('pick_money');//提货、装货费
        $send_money    = $request->input('send_money');//配送、卸货费
        $price         = $request->input('price');//运输费
        $line_price    = $request->input('line_price')??0;//运输费
        $total_money   = $request->input('total_money');//总计费用
        $good_name_n   = $request->input('good_name');
        $good_number_n = $request->input('good_number');
        $good_weight_n = $request->input('good_weight');
        $good_volume_n = $request->input('good_volume');
        $dispatcher    = $request->input('dispatcher') ?? [];
        $clod          = $request->input('clod');
        $more_money    = $request->input('more_money') ? $request->input('more_money') - 0 : null ;//多点费用
        $gather_time    = $request->input('gather_time')??null;
        $send_time    = $request->input('send_time')??null;
        $pay_type    = $request->input('pay_type');
        $car_type    = $request->input('car_type')??''; //车型
        $remark    = $request->input('remark')??''; //备注
        $depart_time    = $request->input('depart_time')??null; //干线发车时间
        $reduce_price    = $request->input('reduce_price');//立减金额
       /*** 虚拟数据
        //$input['self_id']   = $self_id='';
        $input['order_type']  = $order_type='line';  //vehicle  lcl   line
        $input['line_id']     = $line_id='line_202105090959191228535704';
        $input['pick_flag']   = $pick_flag='Y';
        $input['send_flag']   = $send_flag='Y';
        $input['pick_money']  = $pick_money='0';
        $input['price']       = $price='200';
        $input['send_money']  = $send_money='0';
        $input['total_money'] = $total_money='300';
        $input['more_money']  = $more_money=20;//多点费用
        $input['good_name']   = $good_name_n='GDFG';
        $input['good_number'] = $good_number_n=20;
        $input['good_weight'] = $good_weight_n=5000;
        $input['good_volume'] = $good_volume_n=5;
        $input['clod']        = $clod='refrigeration';
        $input['gather_time']        = $gather_time='2021-05-02 15:00';
        $input['send_time']        = $send_time='2021-04-30 15:00';
        $input['pay_type']        = $pay_type='offline';
        $input['car_type']        = $car_type='type_202102051755118039490396';
        $input['remark']        = $remark='';
        $input['depart_time'] = $depart_time = '2021-04-30 15:00';
        $input['reduce_price'] = $reduce_price = '100';

        $input['dispatcher']  = $dispatcher = [
            '0'=>[
                'send_address_id'=>'',
                'send_qu'=>'43',
                'send_qu_name'=>'嘉定区',
                'send_shi_name'=>'上海市',
                'send_address'=>'小浪底001',
                'send_contacts_id'=>'',
                'send_address_longitude'=>'121.471732',
                'send_address_latitude'=>'31.231518',
                'send_name'=>'张三001',
                'send_tel'=>'001',
                'good_name'=>'产品名称001',
                'good_number'=>'10',
                'good_weight'=>'55',
                'good_volume'=>'12',
                'clod'=>'refrigeration',
                'gather_address_id'=>'',
                'gather_qu'=>'43',
                'gather_qu_name'=>'东城区',
                'gather_shi_name'=>'北京市',
                'gather_address'=>'小浪底001_ga',
                'gather_address_longitude'=>'121.471732',
                'gather_address_latitude'=>'31.231518',
                'gather_name'=>'张三',
                'gather_tel'=>'123456',
            ],

             '1'=>[
                 'send_address_id'=>'',

                 'send_qu'=>'43',
                 'send_qu_name'=>'',
                 'send_shi_name'=>'',
                 'send_address'=>'小浪底002',
                 'send_address_longitude'=>'121.471732',
                 'send_address_latitude'=>'31.231518',
                 'send_name'=>'张三002',
                 'send_tel'=>'002',
                 'good_name'=>'产品名称002',
                 'good_number'=>'20',
                 'good_weight'=>'55',
                 'good_volume'=>'13',
                 'clod'=>'freeze',
                 'gather_address_id'=>'',
                 'gather_qu'=>'43',
                 'gather_qu_name'=>'',
                 'gather_shi_name'=>'',
                 'gather_address'=>'小浪底002_ga',
                 'gather_address_longitude'=>'121.471732',
                 'gather_address_latitude'=>'31.231518',
                 'gather_name'=>'张三002_ga',
                 'gather_tel'=>'002_ga',
             ],

             '2'=>[
                 'send_address_id'=>'',
                 'send_qu'=>'43',
                 'send_qu_name'=>'',
                 'send_shi_name'=>'',
                 'send_address'=>'小浪底003',
                 'send_address_longitude'=>'121.471732',
                 'send_address_latitude'=>'31.231518',
                 'send_name'=>'张三003',
                 'send_tel'=>'003',
                 'good_name'=>'产品名称003',
                 'good_number'=>'50.22',
                 'good_weight'=>'55.22',
                 'good_volume'=>'14.22',
                 'clod'=>'freeze',
                 'gather_address_id'=>'',
                 'gather_qu'=>'43',
                 'gather_qu_name'=>'',
                 'gather_shi_name'=>'',
                 'gather_address'=>'小浪底003_ga',
                 'gather_address_longitude'=>'121.471732',
                 'gather_address_latitude'=>'31.231518',
                 'gather_name'=>'张三003_ga',
                 'gather_tel'=>'123456_ga',
             ],
        ];
        **/
        $rules = [
            'order_type'=>'required',
        ];
        $message = [
            'order_type.required'=>'必须选择',
        ];
        switch ($project_type){
            case 'user':
                $company_id    = null;
                $company_name    =null;
                $group_code     = null;
                $group_name     =null;
                $receiver_id    = null;
                break;
            case 'customer':
                $company_id    = $user_info->company_id;
                $company_name    =$user_info->company_name;
                $group_code     = $user_info->group_code;
                $group_name     =$user_info->group_name;
                $total_user_id = null;
                $receiver_id = $user_info->group_code;
                break;
            case 'company':
                $company_id     = null;
                $company_name   = null;
                $group_code     = $user_info->group_code;
                $group_name     =$user_info->group_name;
                $total_user_id = null;
                $receiver_id = null;
                break;
            default:
                $company_id    = null;
                $company_name    =null;
                $group_code     = null;
                $group_name     =null;
                break;
        }
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            /***开始做二次效验**/
            if ($order_type == 'vehicle' || $order_type == 'lcl') {
                if (count($dispatcher) == 0) {
                    $msg['code'] = 302;
                    $msg['msg'] = '请填写订单信息！';
                    return $msg;
                }
            }
            if ($order_type == 'line') {
                if ($pick_flag =='N' && $send_flag =='N') {
                    if (empty($good_name_n)) {
                        $msg['code'] = 306;
                        $msg['msg'] = '货物名称不能为空！';
                        return $msg;
                    }

                    if (empty($good_number_n) || $good_number_n <= 0) {
                        $msg['code'] = 307;
                        $msg['msg'] = '货物件数错误！';
                        return $msg;
                    }

                    if (empty($good_weight_n) || $good_weight_n <= 0) {
                        $msg['code'] = 308;
                        $msg['msg'] = '货物重量错误！';
                        return $msg;
                    }

                    if (empty($good_volume_n) || $good_volume_n <= 0) {
                        $msg['code'] = 309;
                        $msg['msg'] = '货物体积错误！';
                        return $msg;
                    }

                    if (empty($clod)) {
                        $msg['code'] = 309;
                        $msg['msg'] = '请选择温度！';
                        return $msg;
                    }
                }

                $send_t = '提货';
                $pick_t = '配送';
            } else {
                $send_t = '发货';
                $pick_t = '收货';
            }

            /** 处理一下发货地址  及联系人**/
            foreach ($dispatcher as $k => $v){
                if ($order_type == 'vehicle' || $order_type == 'lcl' || ($order_type == 'line' && $send_flag == 'Y')) {
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],'',$user_info,$now_time);

                    if(empty($gather_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = $pick_t.'地址不存在';
                        return $msg;
                    }
                } else if($order_type == 'line' && $send_flag == 'N') {
                    $gather_address = [
                        'self_id'           => '',
                        'sheng'             => '',
                        'sheng_name'        => '',
                        'shi'               => '',
                        'shi_name'          => '',
                        'qu'                => '',
                        'qu_name'           => '',
                        'address'           => '',
                        'longitude'         => '',
                        'dimensionality'    => '',
                        'contacts'  => '',
                        'tel'  => ''
                    ];
                    $gather_address = (Object)$gather_address;
                }
                if ($order_type == 'vehicle' || $order_type == 'lcl' || ($order_type == 'line' && $pick_flag == 'Y')) {
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],'',$user_info,$now_time);
                    if(empty($send_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = $send_t.'地址不存在';
                        return $msg;
                    }
                } else if($order_type == 'line' && $pick_flag == 'N') {
                    $send_address = [
                        'self_id'        => '',
                        'sheng'          => '',
                        'sheng_name'     => '',
                        'shi'            => '',
                        'shi_name'       => '',
                        'qu'             => '',
                        'qu_name'        => '',
                        'address'        => '',
                        'longitude'      => '',
                        'dimensionality' => '',
                        'contacts' => '',
                        'tel'      => ''
                    ];
                    $send_address = (Object)$send_address;
                }
                if (empty($v['good_name'])) {
                    $msg['code'] = 306;
                    $msg['msg']  = '货物名称不能为空！';
                    return $msg;
                }

                if (empty($v['good_number']) || $v['good_number'] <= 0) {
                    $msg['code'] = 307;
                    $msg['msg']  = '货物件数错误！';
                    return $msg;
                }

                if (empty($v['good_weight']) || $v['good_weight'] <= 0) {
                    $msg['code'] = 308;
                    $msg['msg']  = '货物重量错误！';
                    return $msg;
                }

                if (empty($v['good_volume']) || $v['good_volume'] <= 0) {
                    $msg['code'] = 309;
                    $msg['msg']  = '货物体积错误！';
                    return $msg;
                }

                if (empty($v['clod'])) {
                    $msg['code'] = 309;
                    $msg['msg']  = '请选择温度！';
                    return $msg;
                }
                $dispatcher[$k]['send_address_id']        = $send_address->self_id;
                $dispatcher[$k]['send_sheng']             = $send_address->sheng;
                $dispatcher[$k]['send_sheng_name']        = $send_address->sheng_name;
                $dispatcher[$k]['send_shi']               = $send_address->shi;
                $dispatcher[$k]['send_shi_name']          = $send_address->shi_name;
                $dispatcher[$k]['send_qu']                = $send_address->qu;
                $dispatcher[$k]['send_qu_name']           = $send_address->qu_name;
                $dispatcher[$k]['send_address']           = $send_address->address;
                $dispatcher[$k]['send_address_longitude'] = $send_address->longitude;
                $dispatcher[$k]['send_address_latitude']  = $send_address->dimensionality;
//                $dispatcher[$k]['send_contacts_id']       = $send_contacts->self_id;
                $dispatcher[$k]['send_contacts_name']     = $send_address->contacts;
                $dispatcher[$k]['send_contacts_tel']      = $send_address->tel;

                $dispatcher[$k]['gather_address_id']        = $gather_address->self_id;
                $dispatcher[$k]['gather_sheng']             = $gather_address->sheng;
                $dispatcher[$k]['gather_sheng_name']        = $gather_address->sheng_name;
                $dispatcher[$k]['gather_shi']               = $gather_address->shi;
                $dispatcher[$k]['gather_shi_name']          = $gather_address->shi_name;
                $dispatcher[$k]['gather_qu']                = $gather_address->qu;
                $dispatcher[$k]['gather_qu_name']           = $gather_address->qu_name;
                $dispatcher[$k]['gather_address']           = $gather_address->address;
                $dispatcher[$k]['gather_address_longitude'] = $gather_address->longitude;
                $dispatcher[$k]['gather_address_latitude']  = $gather_address->dimensionality;
//                $dispatcher[$k]['gather_contacts_id']       = $gather_contacts->self_id;
                $dispatcher[$k]['gather_contacts_name']     = $gather_address->contacts;
                $dispatcher[$k]['gather_contacts_tel']      = $gather_address->tel;
            }
            /** 处理一下发货地址  及联系人 结束**/

            /** 开始处理正式的数据*/
            $lki    = [];
            $lki2   = [];
            $gather = [];               //定义一个收货地址的集合
            $send   = [];                 //定义一个送货地址的集合
            $good_name   = [];
            $good_number = 0;
            $good_weight = 0;
            $good_volume = 0;
            $clodss = [];

            $abccxxxxx = [];
            foreach ($dispatcher as $k => $v){
                $gather[] = $v['gather_address_id'];
                $send[] = $v['send_address_id'];
                $good_number+=$v['good_number'];
                $good_weight+=$v['good_weight'];
                $good_volume+=$v['good_volume'];
                $good_name[] = $v['good_name'];
                $clodss[] = $v['clod'];
                $abcc222['good_name']   = $v['good_name'];
                $abcc222['good_number'] = $v['good_number'];
                $abcc222['good_weight'] = $v['good_weight'];
                $abcc222['good_volume'] = $v['good_volume'];
                $abcc222['clod']        = $v['clod'];
                $abccxxxxx[] = $abcc222;
            }

            if($pick_flag == 'N' && $send_flag == 'N' && $order_type == 'line'){
                $abcc222 = [];
                $clodss = [];
                $abccxxxxx = [];
                $abcc222['good_name']   = $good_name_n;
                $abcc222['good_number'] = $good_number_n;
                $abcc222['good_weight'] = $good_weight_n;
                $abcc222['good_volume'] = $good_volume_n;
                $abcc222['clod']        = $clod;
                $abccxxxxx[] = $abcc222;
                $good_number = $good_number_n;
                $good_weight = $good_weight_n;
                $good_volume = $good_volume_n;
                $clodss[] = $clod;
            }

            $gather = array_unique($gather);
            $send   = array_unique($send);
            $clodss = array_unique($clodss);

            foreach ($gather as $k => $v){
                $lki[$k]['good_number'] = 0;
                $lki[$k]['good_weight'] = 0;
                $lki[$k]['good_volume'] = 0;
                $abccxx = [];
                $clod = [];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['gather_address_id']){
                        $lki[$k]['good_number']+=$vv['good_number'];
                        $lki[$k]['good_weight']+=$vv['good_weight'];
                        $lki[$k]['good_volume']+=$vv['good_volume'];
                        $lki[$k]['gather_address_id'] = $vv['gather_address_id'];

                        $lki[$k]['gather_qu'] = $vv['gather_qu'];
                        $lki[$k]['gather_qu_name'] = $vv['gather_qu_name'];
                        $lki[$k]['gather_address'] = $vv['gather_address'];
                        $lki[$k]['gather_address_longitude'] = $vv['gather_address_longitude'];
                        $lki[$k]['gather_address_latitude'] = $vv['gather_address_latitude'];
                        $lki[$k]['gather_sheng'] = $vv['gather_sheng'];
                        $lki[$k]['gather_sheng_name'] = $vv['gather_sheng_name'];
                        $lki[$k]['gather_shi'] = $vv['gather_shi'];
                        $lki[$k]['gather_shi_name'] = $vv['gather_shi_name'];
                        $lki[$k]['gather_contacts_name'] = $vv['gather_contacts_name'];
                        $lki[$k]['gather_contacts_tel'] = $vv['gather_contacts_tel'];
                        $abcc['good_name'] = $vv['good_name'];
                        $abcc['good_number'] = $vv['good_number'];
                        $abcc['good_weight'] = $vv['good_weight'];
                        $abcc['good_volume'] = $vv['good_volume'];
                        $abcc['clod']        = $vv['clod'];
                        $abccxx[] = $abcc;
                        $clod[] = $vv['clod'];
                    }
                }
                $lki[$k]['clod'] = $clod;
                $lki[$k]['good_josn'] = json_encode($abccxx,JSON_UNESCAPED_UNICODE);
            }
//            dd($lki);
            $good_info = json_encode($abccxxxxx,JSON_UNESCAPED_UNICODE);

            foreach ($send as $k => $v){
                $lki2[$k]['good_number'] = 0;
                $lki2[$k]['good_weight'] = 0;
                $lki2[$k]['good_volume'] = 0;
                $abccxx2 = [];
                $clod2 = [];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['send_address_id']){
                        $lki2[$k]['good_number']+=$vv['good_number'];
                        $lki2[$k]['good_weight']+=$vv['good_weight'];
                        $lki2[$k]['good_volume']+=$vv['good_volume'];
                        $lki2[$k]['send_address_id'] = $vv['send_address_id'];

                        $lki2[$k]['send_qu'] = $vv['send_qu'];
                        $lki2[$k]['send_qu_name'] = $vv['send_qu_name'];
                        $lki2[$k]['send_address'] = $vv['send_address'];
                        $lki2[$k]['send_address_longitude'] = $vv['send_address_longitude'];
                        $lki2[$k]['send_address_latitude'] = $vv['send_address_latitude'];
                        $lki2[$k]['send_sheng'] = $vv['send_sheng'];
                        $lki2[$k]['send_sheng_name'] = $vv['send_sheng_name'];
                        $lki2[$k]['send_shi'] = $vv['send_shi'];
                        $lki2[$k]['send_shi_name'] = $vv['send_shi_name'];
                        $lki2[$k]['send_contacts_name'] = $vv['send_contacts_name'];
                        $lki2[$k]['send_contacts_tel'] = $vv['send_contacts_tel'];
                        $abcc2['good_name']  = $vv['good_name'];
                        $abcc2['good_number'] = $vv['good_number'];
                        $abcc2['good_weight'] = $vv['good_weight'];
                        $abcc2['good_volume'] = $vv['good_volume'];
                        $abcc2['clod']        = $vv['clod'];
                        $abccxx2[] = $abcc2;
                        $clod2[] = $vv['clod'];
                    }
                }

                $lki2[$k]['good_josn'] = json_encode($abccxx2,JSON_UNESCAPED_UNICODE);
                $lki2[$k]['clod'] = $clod2;
            }

            /***现在处理收货地址的控制**/
            $order_id = generate_id('order_');
             /***现在处理费用的部分控制**/
            $money=[];
            /** 货到付款 **/

            switch ($order_type){
                case 'line':
                    // $line_id= 'line_202101061755043806385994';
                    $where_line = [
                        ['delete_flag','=','Y'],
                        ['self_id','=',$line_id],
                    ];
                    $select_line = ['self_id','shift_number','type','price','use_flag','group_name','type','group_code','special','min_number','max_number','unit_price','start_price','max_price',
                        'pick_price','send_price','pick_type','more_price','send_type','all_weight','all_volume','trunking','control','carriage_id',
                        'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_sheng_name','send_shi','send_shi_name',
                        'send_qu','send_qu_name','send_address','send_address_longitude','send_address_latitude',
                        'gather_address_id','gather_contacts_id','gather_name','gather_tel','depart_time',
                        'gather_sheng','gather_sheng_name','gather_shi','gather_shi_name','gather_qu','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude'];
                    $selectList = ['line_id','yuan_self_id'];

                    $line_info = TmsLine::with(['tmsLineList' => function($query)use($selectList,$select_line){
                        $query->where('delete_flag','=','Y');
                        $query->select($selectList);
                        $query->with(['tmsLine' => function($query)use($select_line){
                            $query->select($select_line);
                        }]);
                    }])->where($where_line)->select($select_line)->first();
                    // dd($line_info->toArray());
                    if(empty($line_info)){
                        $msg['code'] = 310;
                        $msg['msg']  = '线路不存在';
                        return $msg;
                    }

                     $inserttt = [];
                     $sort = 1;
                    /** 以上是提货 的调度的地方 **/
                    if($pick_flag == 'Y'){
                        foreach ($lki2 as $k => $v){
                            $list['self_id']                  = generate_id('patch_');
                            $list['order_id']                 = $order_id;
                            $list['company_id']                 = $company_id;
                            $list['company_name']               = $company_name;
                            $list['receiver_id']                = $line_info->group_code;
                            $list['group_code']                 = $group_code;
                            $list['group_name']                 = $group_name;
                            $list['total_user_id']              = $total_user_id;
//                            $list['create_user_id']           = $total_user_id;
//                            $list['create_user_name']         = $token_name;
                            $list['create_time']              = $list['update_time']=$now_time;
                            $list['gather_address_id']        = $line_info['send_address_id'];

                            $list['gather_name']              = $line_info['send_name'];
                            $list['gather_tel']               = $line_info['send_tel'];
                            $list['gather_sheng']             = $line_info['send_sheng'];
                            $list['gather_sheng_name']        = $line_info['send_sheng_name'];
                            $list['gather_shi']               = $line_info['send_shi'];
                            $list['gather_shi_name']          = $line_info['send_shi_name'];
                            $list['gather_qu']                = $line_info['send_qu'];
                            $list['gather_qu_name']           = $line_info['send_qu_name'];
                            $list['gather_address']           = $line_info['send_address'];
                            $list['gather_address_longitude'] = $line_info['send_address_longitude'];
                            $list['gather_address_latitude']  = $line_info['send_address_latitude'];

                            $list['send_address_id']          = $v['send_address_id'];

                            $list['send_name']                = $v['send_contacts_name'];
                            $list['send_tel']                 = $v['send_contacts_tel'];
                            $list['send_sheng']               = $v['send_sheng'];
                            $list['send_sheng_name']          = $v['send_sheng_name'];
                            $list['send_shi']                 = $v['send_shi'];
                            $list['send_shi_name']            = $v['send_shi_name'];
                            $list['send_qu']                  = $v['send_qu'];
                            $list['send_qu_name']             = $v['send_qu_name'];
                            $list['send_address']             = $v['send_address'];
                            $list['send_address_longitude']   = $v['send_address_longitude'];
                            $list['send_address_latitude']    = $v['send_address_latitude'];

                            $list['line_gather_address_id']        = $line_info['send_address_id'];

                            $list['line_gather_name']              = $line_info['send_name'];
                            $list['line_gather_tel']               = $line_info['send_tel'];
                            $list['line_gather_sheng']             = $line_info['send_sheng'];
                            $list['line_gather_sheng_name']        = $line_info['send_sheng_name'];
                            $list['line_gather_shi']               = $line_info['send_shi'];
                            $list['line_gather_shi_name']          = $line_info['send_shi_name'];
                            $list['line_gather_qu']                = $line_info['send_qu'];
                            $list['line_gather_qu_name']           = $line_info['send_qu_name'];
                            $list['line_gather_address']           = $line_info['send_address'];
                            $list['line_gather_address_longitude'] = $line_info['send_address_longitude'];
                            $list['line_gather_address_latitude']  = $line_info['send_address_latitude'];

                            $list['line_send_address_id']          = $v['send_address_id'];

                            $list['line_send_name']                = $v['send_contacts_name'];
                            $list['line_send_tel']                 = $v['send_contacts_tel'];
                            $list['line_send_sheng']               = $v['send_sheng'];
                            $list['line_send_sheng_name']          = $v['send_sheng_name'];
                            $list['line_send_shi']                 = $v['send_shi'];
                            $list['line_send_shi_name']            = $v['send_shi_name'];
                            $list['line_send_qu']                  = $v['send_qu'];
                            $list['line_send_qu_name']             = $v['send_qu_name'];
                            $list['line_send_address']             = $v['send_address'];
                            $list['line_send_address_longitude']   = $v['send_address_longitude'];
                            $list['line_send_address_latitude']    = $v['send_address_latitude'];

                            $list['good_number']              = $v['good_number'];
                            $list['good_weight']              = $v['good_weight'];
                            $list['good_volume']              = $v['good_volume'];
                            $list['dispatch_flag']            = 'Y';
                            $list['sort']                     = $sort++;
                            $list['order_type']               = $order_type;
                            $list['pick_flag']                = $pick_flag;
                            $list['send_flag']                = $send_flag;
                            $list['good_info']                = $v['good_josn'];
                            $list['clod']                     = json_encode($v['clod'],JSON_UNESCAPED_UNICODE);
                            $list['pay_type']                 = $pay_type;
                            $list['remark']                   = $remark;
                            $list['order_status'] = 1;
                            $list['send_time']                = $send_time;
                            $list['gather_time']              = $depart_time;
                            $list['reduce_price']             = 0;
                            $list['total_money']              = $pick_money*100;
                            $list['on_line_money']            = $pick_money*100;
                            if ($k>0){
                                $list['total_money']              = $line_info['more_price']*100;
                                $list['on_line_money']            = $line_info['more_price']*100;
                            }
                            if ($project_type == 'customer'){
                                $list['order_status'] = 3;
                            }
                            $inserttt[]=$list;

                            /** 存储费用 **/
                            if ($pay_type == 'offline'){
                                switch ($project_type){
                                    case 'user':
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = $user_info->group_code;
                                        $money['shouk_type']                 = 'GROUP_CODE';
                                        $money['fk_company_id']              = $user_info->company_id;
                                        $money['fk_type']                    = 'COMPANY';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        break;
                                    case 'company':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                }
                            }else{
                                switch ($project_type){
                                    case 'user':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'company':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                }
                            }

                            $money['self_id']                    = generate_id('order_money_');
                            $money['order_id']                   = $order_id;
                            $money['dispatch_id']                = $list['self_id'];
                            $money['create_time']                = $now_time;
                            $money['update_time']                = $now_time;
                            $money['money']                      = $pick_money*100;
                            $money['money_type']                 = 'gather';
                            $money['type']                       = 'out';
                            $money['settle_flag']                = 'W';
                            $money_list[] = $money;

                            $money_['self_id']                    = generate_id('order_money_');
                            $money_['order_id']                   = $order_id;
                            $money_['dispatch_id']                = $list['self_id'];
                            $money_['create_time']                = $now_time;
                            $money_['update_time']                = $now_time;
                            $money_['money']                      = $pick_money*100;
                            $money_['money_type']                 = 'gather';
                            $money_['type']                       = 'in';
                            $money_['settle_flag']                = 'W';
                            $money_list_[] = $money_;
                        }

                    }

                    /** 以上是收货的调度的地方 结束**/

                    /** 以下是处理线路中的调度的地方**/
                    if($line_info['type'] == 'combination'){
                        // dd($line_info->tmsLineList->toArray());
                        foreach ($line_info->tmsLineList as $k => $v){
                            $list['self_id']                    = generate_id('patch_');
                            $list['order_id']                   = $order_id;
                            $list['company_id']                 = $company_id;
                            $list['company_name']               = $company_name;
                            $list['receiver_id']                = $line_info->group_code;
                            $list['group_code']                 = $group_code;
                            $list['group_name']                 = $group_name;
                            $list['total_user_id']              = $total_user_id;
//                            $list['create_user_id']             = $total_user_id;
//                            $list['create_user_name']           = $token_name;
                            $list['create_time']                = $list['update_time'] = $now_time;
                            $list['gather_address_id']          = $v['tmsLine']['gather_address_id'];

                            $list['gather_name']                = $v['tmsLine']['gather_name'];
                            $list['gather_tel']                 = $v['tmsLine']['gather_tel'];
                            $list['gather_sheng']               = $v['tmsLine']['gather_sheng'];
                            $list['gather_sheng_name']          = $v['tmsLine']['gather_sheng_name'];
                            $list['gather_shi']                 = $v['tmsLine']['gather_shi'];
                            $list['gather_shi_name']            = $v['tmsLine']['gather_shi_name'];
                            $list['gather_qu']                  = $v['tmsLine']['gather_qu'];
                            $list['gather_qu_name']             = $v['tmsLine']['gather_qu_name'];
                            $list['gather_address']             = $v['tmsLine']['gather_address'];
                            $list['gather_address_longitude']   = $v['tmsLine']['gather_address_longitude'];
                            $list['gather_address_latitude']    = $v['tmsLine']['gather_address_latitude'];
                            $list['send_address_id']            = $v['tmsLine']['send_address_id'];

                            $list['send_name']                  = $v['tmsLine']['send_name'];
                            $list['send_tel']                   = $v['tmsLine']['send_tel'];
                            $list['send_sheng']                 = $v['tmsLine']['send_sheng'];
                            $list['send_sheng_name']            = $v['tmsLine']['send_sheng_name'];
                            $list['send_shi']                   = $v['tmsLine']['send_shi'];
                            $list['send_shi_name']              = $v['tmsLine']['send_shi_name'];
                            $list['send_qu']                    = $v['tmsLine']['send_qu'];
                            $list['send_qu_name']               = $v['tmsLine']['send_qu_name'];
                            $list['send_address']               = $v['tmsLine']['send_address'];
                            $list['send_address_longitude']     = $v['tmsLine']['send_address_longitude'];
                            $list['send_address_latitude']      = $v['tmsLine']['send_address_latitude'];

                            $list['line_gather_address_id']          = $v['tmsLine']['gather_address_id'];
                            $list['line_gather_name']                = $v['tmsLine']['gather_name'];
                            $list['line_gather_tel']                 = $v['tmsLine']['gather_tel'];
                            $list['line_gather_sheng']               = $v['tmsLine']['gather_sheng'];
                            $list['line_gather_sheng_name']          = $v['tmsLine']['gather_sheng_name'];
                            $list['line_gather_shi']                 = $v['tmsLine']['gather_shi'];
                            $list['line_gather_shi_name']            = $v['tmsLine']['gather_shi_name'];
                            $list['line_gather_qu']                  = $v['tmsLine']['gather_qu'];
                            $list['line_gather_qu_name']             = $v['tmsLine']['gather_qu_name'];
                            $list['line_gather_address']             = $v['tmsLine']['gather_address'];
                            $list['line_gather_address_longitude']   = $v['tmsLine']['gather_address_longitude'];
                            $list['line_gather_address_latitude']    = $v['tmsLine']['gather_address_latitude'];
                            $list['line_send_address_id']            = $v['tmsLine']['send_address_id'];

                            $list['line_send_name']                  = $v['tmsLine']['send_name'];
                            $list['line_send_tel']                   = $v['tmsLine']['send_tel'];
                            $list['line_send_sheng']                 = $v['tmsLine']['send_sheng'];
                            $list['line_send_sheng_name']            = $v['tmsLine']['send_sheng_name'];
                            $list['line_send_shi']                   = $v['tmsLine']['send_shi'];
                            $list['line_send_shi_name']              = $v['tmsLine']['send_shi_name'];
                            $list['line_send_qu']                    = $v['tmsLine']['send_qu'];
                            $list['line_send_qu_name']               = $v['tmsLine']['send_qu_name'];
                            $list['line_send_address']               = $v['tmsLine']['send_address'];
                            $list['line_send_address_longitude']     = $v['tmsLine']['send_address_longitude'];
                            $list['line_send_address_latitude']      = $v['tmsLine']['send_address_latitude'];


                            $list['good_number']                = $good_number;
                            $list['good_weight']                = $good_weight;
                            $list['good_volume']                = $good_volume;
                            $list['order_type']                 = $order_type;
                            $list['sort']                       = $sort++;
                            $list['good_info']                  = $good_info;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['dispatch_flag']              = 'Y';
                            $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                            $list['pay_type']                   = $pay_type;
                            $list['remark']                     = $remark;
                            $list['order_status']               = 1;
                            $list['send_time']                  = $depart_time;
                            $list['gather_time']                = date('Y-m-d H:i:s',strtotime($depart_time)+24*$line_info->trunking*3600);
                            $list['reduce_price']               = $reduce_price;
                            $list['total_money']                = $line_price*100;
                            $list['on_line_money']              = $line_price*100;
//                            if($pick_flag == 'Y'){
//                                $list['total_money']              = $total_money*100 -$line_info->pick_price;
//                                $list['on_line_money']            = $total_money*100 -$line_info->pick_price;
//                            }
//                            if ($send_flag == 'Y'){
//                                $list['total_money']              = $total_money*100 - $line_info->send_price;
//                                $list['on_line_money']            = $total_money*100 - $line_info->send_price;
//                            }
//                            if ($pick_flag == 'Y' && $send_flag == 'Y'){
//                                $list['total_money']              = $total_money*100 - $line_info->send_price - $line_info->pick_price;
//                                $list['on_line_money']            = $total_money*100 - $line_info->send_price - $line_info->pick_price;
//                            }

                            if ($project_type == 'customer'){
                                $list['order_status'] = 3;
                            }
                            $inserttt[]=$list;
                            /** 存储费用 **/
                            if ($pay_type == 'offline'){
                                switch ($project_type){
                                    case 'user':
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = $user_info->group_code;
                                        $money['shouk_type']                 = 'GROUP_CODE';
                                        $money['fk_company_id']              = $user_info->company_id;
                                        $money['fk_type']                    = 'COMPANY';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        break;
                                    case 'company':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                }
                            }else{
                                switch ($project_type){
                                    case 'user':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'company':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                }
                            }
                            $money['self_id']                    = generate_id('order_money_');
                            $money['order_id']                   = $order_id;
                            $money['dispatch_id']                = $list['self_id'];
                            $money['create_time']                = $now_time;
                            $money['update_time']                = $now_time;
                            $money['money']                      = $price*100;
                            $money['money_type']                 = 'freight';
                            $money['type']                       = 'out';
                            $money['settle_flag']                = 'W';
                            $money_list[] = $money;

                            $money_['self_id']                    = generate_id('order_money_');
                            $money_['order_id']                   = $order_id;
                            $money_['dispatch_id']                = $list['self_id'];
                            $money_['create_time']                = $now_time;
                            $money_['update_time']                = $now_time;
                            $money_['money']                      = $price*100;
                            $money_['money_type']                 = 'freight';
                            $money_['type']                       = 'in';
                            $money_['settle_flag']                = 'W';
                            $money_list_[] = $money_;
                        }

                    }else{
                        $list['self_id']                    = generate_id('patch_');
                        $list['order_id']                   = $order_id;
                        $list['company_id']                 = $company_id;
                        $list['company_name']               = $company_name;
                        $list['receiver_id']                = $line_info->group_code;
                        $list['group_code']                 = $group_code;
                        $list['group_name']                 = $group_name;
                        $list['total_user_id']              = $total_user_id;
//                        $list['create_user_id']             = $total_user_id;
//                        $list['create_user_name']           = $token_name;
                        $list['create_time']                = $list['update_time'] = $now_time;
                        $list['gather_address_id']          = $line_info->gather_address_id;

                        $list['gather_name']                = $line_info->gather_name;
                        $list['gather_tel']                 = $line_info->gather_tel;
                        $list['gather_sheng']               = $line_info->gather_sheng;
                        $list['gather_sheng_name']          = $line_info->gather_sheng_name;
                        $list['gather_shi']                 = $line_info->gather_shi;
                        $list['gather_shi_name']            = $line_info->gather_shi_name;
                        $list['gather_qu']                  = $line_info->gather_qu;
                        $list['gather_qu_name']             = $line_info->gather_qu_name;
                        $list['gather_address']             = $line_info->gather_address;
                        $list['gather_address_longitude']   = $line_info->gather_address_longitude;
                        $list['gather_address_latitude']    = $line_info->gather_address_latitude;
                        $list['send_address_id']            = $line_info->send_address_id;

                        $list['send_name']                  = $line_info->send_name;
                        $list['send_tel']                   = $line_info->send_tel;
                        $list['send_sheng']                 = $line_info->send_sheng;
                        $list['send_sheng_name']            = $line_info->send_sheng_name;
                        $list['send_shi']                   = $line_info->send_shi;
                        $list['send_shi_name']              = $line_info->send_shi_name;
                        $list['send_qu']                    = $line_info->send_qu;
                        $list['send_qu_name']               = $line_info->send_qu_name;
                        $list['send_address']               = $line_info->send_address;
                        $list['send_address_longitude']     = $line_info->send_address_longitude;
                        $list['send_address_latitude']      = $line_info->send_address_latitude;

                        $list['line_gather_address_id']          = $line_info->gather_address_id;
                        $list['line_gather_name']                = $line_info->gather_name;
                        $list['line_gather_tel']                 = $line_info->gather_tel;
                        $list['line_gather_sheng']               = $line_info->gather_sheng;
                        $list['line_gather_sheng_name']          = $line_info->gather_sheng_name;
                        $list['line_gather_shi']                 = $line_info->gather_shi;
                        $list['line_gather_shi_name']            = $line_info->gather_shi_name;
                        $list['line_gather_qu']                  = $line_info->gather_qu;
                        $list['line_gather_qu_name']             = $line_info->gather_qu_name;
                        $list['line_gather_address']             = $line_info->gather_address;
                        $list['line_gather_address_longitude']   = $line_info->gather_address_longitude;
                        $list['line_gather_address_latitude']    = $line_info->gather_address_latitude;
                        $list['line_send_address_id']            = $line_info->send_address_id;

                        $list['line_send_name']                  = $line_info->send_name;
                        $list['line_send_tel']                   = $line_info->send_tel;
                        $list['line_send_sheng']                 = $line_info->send_sheng;
                        $list['line_send_sheng_name']            = $line_info->send_sheng_name;
                        $list['line_send_shi']                   = $line_info->send_shi;
                        $list['line_send_shi_name']              = $line_info->send_shi_name;
                        $list['line_send_qu']                    = $line_info->send_qu;
                        $list['line_send_qu_name']               = $line_info->send_qu_name;
                        $list['line_send_address']               = $line_info->send_address;
                        $list['line_send_address_longitude']     = $line_info->send_address_longitude;
                        $list['line_send_address_latitude']      = $line_info->send_address_latitude;

                        $list['good_number']                = $good_number ;
                        $list['good_weight']                = $good_weight ;
                        $list['good_volume']                = $good_volume ;
                        $list['dispatch_flag']              = 'Y';
                        $list['sort']                       = $sort++;
                        $list['order_type']                 = $order_type;
                        $list['pick_flag']                  = $pick_flag;
                        $list['send_flag']                  = $send_flag;
                        $list['good_info']                  = $good_info;
                        $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                        $list['pay_type']                   = $pay_type;
                        $list['remark']                   = $remark;
                        $list['order_status'] = 1;
                        $list['send_time']                = $depart_time;
                        $list['gather_time']              = date('Y-m-d H:i:s',strtotime($depart_time)+24*$line_info->trunking*3600);
                        $list['reduce_price']             = $reduce_price;
                        $list['total_money']              = $line_price*100;
                        $list['on_line_money']              = $line_price*100;
//                        if($pick_flag == 'Y'){
//                            $list['total_money']              = $total_money*100 -$line_info->pick_price;
//                            $list['on_line_money']              = $total_money*100 -$line_info->pick_price;
//                        }
//                        if ($send_flag == 'Y'){
//                            $list['total_money']              = $total_money*100 - $line_info->send_price;
//                            $list['on_line_money']              = $total_money*100 - $line_info->send_price;
//                        }
//                        if ($pick_flag == 'Y' && $send_flag == 'Y'){
//                            $list['total_money']              = $total_money*100 - $line_info->send_price - $line_info->pick_price;
//                            $list['on_line_money']              = $total_money*100 - $line_info->send_price - $line_info->pick_price;
//                        }
                        if ($project_type == 'customer'){
                            $list['order_status'] = 3;
                        }
                        $inserttt[]=$list;

                        /** 存储费用 **/
                        if ($pay_type == 'offline'){
                            switch ($project_type){
                                case 'user':
                                    $money['fk_total_user_id']           = $user_info->total_user_id;
                                    $money['fk_type']                    = 'USER';
                                    $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                    break;
                                case 'customer':
                                    $money['shouk_group_code']           = $user_info->group_code;
                                    $money['shouk_type']                 = 'GROUP_CODE';
                                    $money['fk_company_id']              = $user_info->company_id;
                                    $money['fk_type']                    = 'COMPANY';
                                    $money['ZIJ_company_id']             = $user_info->company_id;
                                    break;
                                case 'company':
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_group_code']             = $user_info->group_code;
                                    break;
                            }
                        }else{
                            switch ($project_type){
                                case 'user':
                                    $money_['shouk_group_code']           = $line_info->group_code;
                                    $money_['shouk_type']                 = 'GROUP_CODE';
                                    $money_['fk_group_code']              = '1234';
                                    $money_['fk_type']                    = 'PLATFORM';
                                    $money_['ZIJ_group_code']             = '1234';
                                    $money_['delete_flag']                = 'N';

                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_total_user_id']           = $user_info->total_user_id;
                                    $money['fk_type']                    = 'USER';
                                    $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                    $money['delete_flag']                = 'N';
                                    break;
                                case 'customer':
                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_company_id']             = $user_info->company_id;
                                    $money['delete_flag']                = 'N';
                                    break;
                                case 'company':
                                    $money_['shouk_group_code']           = $line_info->group_code;
                                    $money_['shouk_type']                 = 'GROUP_CODE';
                                    $money_['fk_group_code']              = '1234';
                                    $money_['fk_type']                    = 'PLATFORM';
                                    $money_['ZIJ_group_code']             = '1234';
                                    $money_['delete_flag']                = 'N';

                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_group_code']             = $user_info->group_code;
                                    $money['delete_flag']                = 'N';
                                    break;
                            }
                        }
                        $money['self_id']                    = generate_id('order_money_');
                        $money['order_id']                   = $order_id;
                        $money['dispatch_id']                = $list['self_id'];
                        $money['create_time']                = $now_time;
                        $money['update_time']                = $now_time;
                        $money['money']                      = $price*100;
                        $money['money_type']                 = 'freight';
                        $money['type']                       = 'out';
                        $money['settle_flag']                = 'W';
                        $money_list[] = $money;

                        $money_['self_id']                    = generate_id('order_money_');
                        $money_['order_id']                   = $order_id;
                        $money_['dispatch_id']                = $list['self_id'];
                        $money_['create_time']                = $now_time;
                        $money_['update_time']                = $now_time;
                        $money_['money']                      = $price*100;
                        $money_['money_type']                 = 'freight';
                        $money_['type']                       = 'in';
                        $money_['settle_flag']                = 'W';
                        $money_list_[] = $money_;

                    }

                    /** 以上是处理线路中的调度的地方 结束**/
                    $dispatch= [];
                    /** 以上是收的调度的地方 **/
                    if($send_flag == 'Y'){
                        foreach ($lki as $k => $v){
                            $list['self_id']                    = generate_id('patch_');
                            $list['order_id']                   = $order_id;
                            $list['company_id']                 = $company_id;
                            $list['company_name']               = $company_name;
                            $list['receiver_id']                = $line_info->group_code;
                            $list['group_code']                 = $group_code;
                            $list['group_name']                 = $group_name;
                            $list['total_user_id']              = $total_user_id;
//                            $list['create_user_id']             = $total_user_id;
//                            $list['create_user_name']           = $token_name;
                            $list['create_time']                = $list['update_time'] = $now_time;

                            $list['send_address_id']            = $line_info['gather_address_id'];

                            $list['send_name']                  = $line_info['gather_name'];
                            $list['send_tel']                   = $line_info['gather_tel'];
                            $list['send_sheng']                 = $line_info['gather_sheng'];
                            $list['send_sheng_name']            = $line_info['gather_sheng_name'];
                            $list['send_shi']                   = $line_info['gather_shi'];
                            $list['send_shi_name']              = $line_info['gather_shi_name'];
                            $list['send_qu']                    = $line_info['gather_qu'];
                            $list['send_qu_name']               = $line_info['gather_qu_name'];
                            $list['send_address']               = $line_info['gather_address'];
                            $list['send_address_longitude']     = $line_info['gather_address_longitude'];
                            $list['send_address_latitude']      = $line_info['gather_address_latitude'];
                            $list['gather_address_id']          = $v['gather_address_id'];
                            $list['gather_name']                = $v['gather_contacts_name'];
                            $list['gather_tel']                 = $v['gather_contacts_tel'];
                            $list['gather_sheng']               = $v['gather_sheng'];
                            $list['gather_sheng_name']          = $v['gather_sheng_name'];
                            $list['gather_shi']                 = $v['gather_shi'];
                            $list['gather_shi_name']            = $v['gather_shi_name'];
                            $list['gather_qu']                  = $v['gather_qu'];
                            $list['gather_qu_name']             = $v['gather_qu_name'];
                            $list['gather_address']             = $v['gather_address'];
                            $list['gather_address_longitude']   = $v['gather_address_longitude'];
                            $list['gather_address_latitude']    = $v['gather_address_latitude'];

                            $list['line_gather_address_id']        = $line_info['send_address_id'];
                            $list['line_gather_name']              = $line_info['send_name'];
                            $list['line_gather_tel']               = $line_info['send_tel'];
                            $list['line_gather_sheng']             = $line_info['send_sheng'];
                            $list['line_gather_sheng_name']        = $line_info['send_sheng_name'];
                            $list['line_gather_shi']               = $line_info['send_shi'];
                            $list['line_gather_shi_name']          = $line_info['send_shi_name'];
                            $list['line_gather_qu']                = $line_info['send_qu'];
                            $list['line_gather_qu_name']           = $line_info['send_qu_name'];
                            $list['line_gather_address']           = $line_info['send_address'];
                            $list['line_gather_address_longitude'] = $line_info['send_address_longitude'];
                            $list['line_gather_address_latitude']  = $line_info['send_address_latitude'];
                            $list['line_send_address_id']          = $v['gather_address_id'];
                            $list['line_send_name']                = $v['gather_contacts_name'];
                            $list['line_send_tel']                 = $v['gather_contacts_tel'];
                            $list['line_send_sheng']               = $v['gather_sheng'];
                            $list['line_send_sheng_name']          = $v['gather_sheng_name'];
                            $list['line_send_shi']                 = $v['gather_shi'];
                            $list['line_send_shi_name']            = $v['gather_shi_name'];
                            $list['line_send_qu']                  = $v['gather_qu'];
                            $list['line_send_qu_name']             = $v['gather_qu_name'];
                            $list['line_send_address']             = $v['gather_address'];
                            $list['line_send_address_longitude']   = $v['gather_address_longitude'];
                            $list['line_send_address_latitude']    = $v['gather_address_latitude'];

                            $list['good_number']                = $v['good_number'];
                            $list['good_weight']                = $v['good_weight'];
                            $list['good_volume']                = $v['good_volume'];
                            $list['dispatch_flag']              = 'Y';
                            $list['sort']                       = $sort++;
                            $list['order_type']                 = $order_type;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['good_info']                  = $v['good_josn'];
                            $list['clod']                       = json_encode($v['clod'],JSON_UNESCAPED_UNICODE);
                            $list['pay_type']                   = $pay_type;
                            $list['remark']                     = $remark;
                            $list['order_status'] = 1;
                            $list['send_time']                = date('Y-m-d H:i:s',strtotime($depart_time)+24*$line_info->trunking*3600);
                            $list['gather_time']              = null;
                            $list['reduce_price']             = 0;
                            $list['total_money']              = $send_money*100;
                            $list['on_line_money']            = $send_money*100;
                            if ($line_info->special == 'Y'){
                                $list['total_money'] = line_count_price($line_info,$list['good_number']);
                                $list['on_line_money'] = line_count_price($line_info,$list['good_number']);
                            }
                            if ($project_type == 'customer'){
                                $list['order_status'] = 3;
                            }
                            $inserttt[]=$list;
                            $dispatch[]=$list['self_id'];

                            /** 存储费用 **/
                            if ($pay_type == 'offline'){
                                switch ($project_type){
                                    case 'user':
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = $user_info->group_code;
                                        $money['shouk_type']                 = 'GROUP_CODE';
                                        $money['fk_company_id']              = $user_info->company_id;
                                        $money['fk_type']                    = 'COMPANY';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        break;
                                    case 'company':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                }
                            }else{
                                switch ($project_type){
                                    case 'user':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'company':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                }
                            }
                            $money['self_id']                    = generate_id('order_money_');
                            $money['order_id']                   = $order_id;
                            $money['dispatch_id']                = $list['self_id'];
                            $money['create_time']                = $now_time;
                            $money['update_time']                = $now_time;
                            $money['money']                      = $send_money*100;
                            $money['money_type']                 = 'send';
                            $money['type']                       = 'out';
                            $money['settle_flag']                = 'W';
                            $money_list[] = $money;

                            $money_['self_id']                    = generate_id('order_money_');
                            $money_['order_id']                   = $order_id;
                            $money_['dispatch_id']                = $list['self_id'];
                            $money_['create_time']                = $now_time;
                            $money_['update_time']                = $now_time;
                            $money_['money']                      = $send_money*100;
                            $money_['money_type']                 = 'send';
                            $money_['type']                       = 'in';
                            $money_['settle_flag']                = 'W';
                            $money_list_[] = $money_;
                        }
                    }

                    /** 以上是送货的调度的地方 结束**/
                    $data['self_id']          = $order_id;       //order ID
                    $data['group_code']       = $group_code;
                    $data['group_name']       = $group_name;
                    $data['total_user_id']    = $total_user_id;
                    $data['pay_type']         = $pay_type;
//                    $data['create_user_id']   = $total_user_id;
//                    $data['create_user_name'] = $token_name;
                    $data['create_time']      = $data['update_time'] = $now_time;
                    $data['company_id']       = $company_id;
                    $data['company_name']     = $company_name;
                    $data['order_type']       = $order_type;
                    $data['pick_money']       = ($pick_money - 0)*100;
                    $data['send_money']       = ($send_money - 0)*100;
                    $data['price']            = ($price - 0)*100;
                    $data['more_money']       = $more_money;
                    // 配送
                    if($send_flag == 'Y'){
                        $data['gather_address_id']          = $lki[0]['gather_address_id'];

                        $data['gather_name']                = $lki[0]['gather_contacts_name'];
                        $data['gather_tel']                 = $lki[0]['gather_contacts_tel'];
                        $data['gather_sheng']               = $lki[0]['gather_sheng'];
                        $data['gather_sheng_name']          = $lki[0]['gather_sheng_name'];
                        $data['gather_shi']                 = $lki[0]['gather_shi'];
                        $data['gather_shi_name']            = $lki[0]['gather_shi_name'];
                        $data['gather_qu']                  = $lki[0]['gather_qu'];
                        $data['gather_qu_name']             = $lki[0]['gather_qu_name'];
                        $data['gather_address']             = $lki[0]['gather_address'];
                        $data['gather_address_longitude']   = $lki[0]['gather_address_longitude'];
                        $data['gather_address_latitude']    = $lki[0]['gather_address_latitude'];
                    }else{
                        $data['gather_address_id']          = $line_info['gather_address_id'];

                        $data['gather_name']                = $line_info['gather_name'];
                        $data['gather_tel']                 = $line_info['gather_tel'];
                        $data['gather_sheng']               = $line_info['gather_sheng'];
                        $data['gather_sheng_name']          = $line_info['gather_sheng_name'];
                        $data['gather_shi']                 = $line_info['gather_shi'];
                        $data['gather_shi_name']            = $line_info['gather_shi_name'];
                        $data['gather_qu']                  = $line_info['gather_qu'];
                        $data['gather_qu_name']             = $line_info['gather_qu_name'];
                        $data['gather_address']             = $line_info['gather_address'];
                        $data['gather_address_longitude']   = $line_info['gather_address_longitude'];
                        $data['gather_address_latitude']    = $line_info['gather_address_latitude'];
                    }
                    //提货
                    if($pick_flag == 'Y'){
                        $data['send_address_id']            = $lki2[0]['send_address_id'];
                        $data['send_name']                  = $lki2[0]['send_contacts_name'];
                        $data['send_tel']                   = $lki2[0]['send_contacts_tel'];
                        $data['send_sheng']                 = $lki2[0]['send_sheng'];
                        $data['send_sheng_name']            = $lki2[0]['send_sheng_name'];
                        $data['send_shi']                   = $lki2[0]['send_shi'];
                        $data['send_shi_name']              = $lki2[0]['send_shi_name'];
                        $data['send_qu']                    = $lki2[0]['send_qu'];
                        $data['send_qu_name']               = $lki2[0]['send_qu_name'];
                        $data['send_address']               = $lki2[0]['send_address'];
                        $data['send_address_longitude']     = $lki2[0]['send_address_longitude'];
                        $data['send_address_latitude']      = $lki2[0]['send_address_latitude'];
                    }else{
                        $data['send_address_id']            = $line_info->send_address_id;
                        $data['send_name']                  = $line_info->send_name;
                        $data['send_tel']                   = $line_info->send_tel;
                        $data['send_sheng']                 = $line_info->send_sheng;
                        $data['send_sheng_name']            = $line_info->send_sheng_name;
                        $data['send_shi']                   = $line_info->send_shi;
                        $data['send_shi_name']              = $line_info->send_shi_name;
                        $data['send_qu']                    = $line_info->send_qu;
                        $data['send_qu_name']               = $line_info->send_qu_name;
                        $data['send_address']               = $line_info->send_address;
                        $data['send_address_longitude']     = $line_info->send_address_longitude;
                        $data['send_address_latitude']      = $line_info->send_address_latitude;

                    }

                    $data['good_number'] = $good_number;
                    $data['good_weight'] = $good_weight;
                    $data['good_volume'] = $good_volume;
                    $data['pick_flag']   = $pick_flag;
                    $data['send_flag']   = $send_flag;
                    $data['total_money'] = ($total_money - 0)*100;
                    $data['line_id']     = $line_id;
                    $data['line_info']   = json_encode($line_info,JSON_UNESCAPED_UNICODE);
                    $data['info']        = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $data['good_info']   = $good_info;
                    $data['clod']        = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $data['remark']      = $remark;
                    $data['send_time']   = $send_time;
                    $data['reduce_price'] = $reduce_price;
                    $wheres['self_id'] = $self_id;

//                    dd(123);
                    $old_info = TmsOrder::where($wheres)->first();



                    if($old_info) {
                        $orderid = $self_id;
                        $data['update_time'] = $now_time;
                        $id = TmsOrder::where($wheres)->update($data);
                    }else{
//                        $data['self_id']          = generate_id('order_');
//                        $data['create_user_id']   = $total_user_id;
//                        $data['create_user_name'] = $token_name;
                        $data['create_time']      = $data['update_time'] = $now_time;
                        $orderid = $data['self_id'];
                        $data['order_status'] = 1;
                        if ($project_type == 'customer'){
                            $list['order_status'] = 3;
                            $data['order_status'] = 3;
                        }
                        $id = TmsOrder::insert($data);
                        TmsOrderDispatch::insert($inserttt);
                        TmsOrderCost::insert($money_list);
                        TmsOrderCost::insert($money_list_);
                        //落地配线路 配送订单直接调度
//                        if ($line_info->special == 'Y'){
//                            $tmsOrder['order_status'] = 4;
//                            $tmsOrder['update_time']  = $now_time;
//                            TmsOrder::whereIn('self_id',$data['self_id'])->update($tmsOrder);
//                            $dispatch_list = TmsOrderDispatch::whereIn('self_id',$dispatch)->get();
//                            foreach ($dispatch_list as $kkk => $vvv){
//                                $company_info = TmsGroup::where('self_id','=',$company_id)->select('self_id','company_name')->first();
//                                $carriage_list['self_id']            =generate_id('c_d_');
//                                $carriage_list['order_dispatch_id']  = $dispatch_list->self_id;
//                                $carriage_list['carriage_id']        = $carriage_id;
//                                $carriage_list['group_code']         = $group_info->group_code;
//                                $carriage_list['group_name']         = $group_info->group_name;
//                                $carriage_list['create_user_id']     = $user_info->admin_id;
//                                $carriage_list['create_user_name']   = $user_info->name;
//                                $carriage_list['create_time']        = $list['update_time']=$now_time;
//                                $carriage_list['company_id']         = $company_info->self_id;
//                                $carriage_list['company_name']       = $company_info->company_name;
//
//                            }
//
//                        }

                    }

                    if($id){
                        $msg['code'] = 200;
                        $msg['msg']  = "操作成功";
                        $msg['order_id'] = $orderid;
                        $msg['order_id_show'] = substr($orderid,15);
                        return $msg;
                    }else{
                        $msg['code'] = 302;
                        $msg['msg']  = "操作失败";
                        return $msg;
                    }
                    break;
                default:
                    $wheres['self_id'] = $self_id;
                    $old_info=TmsOrder::where($wheres)->first();

                    if($old_info){
                        // $operationing->access_cause='修改订单';
                        // $operationing->operation_type='update';

                    }else{
                        // $operationing->access_cause='新建订单';
                        // $operationing->operation_type='create';
                    }

                    $data['order_type']                 = $order_type;
                    $data['gather_time']                = $gather_time;
                    $data['gather_address_id']          = $dispatcher[0]['gather_address_id'];

                    $data['gather_name']                = $dispatcher[0]['gather_contacts_name'];
                    $data['gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
                    $data['gather_sheng']               = $dispatcher[0]['gather_sheng'];
                    $data['gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
                    $data['gather_shi']                 = $dispatcher[0]['gather_shi'];
                    $data['gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
                    $data['gather_qu']                  = $dispatcher[0]['gather_qu'];
                    $data['gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
                    $data['gather_address']             = $dispatcher[0]['gather_address'];
                    $data['gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
                    $data['gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
                    $data['send_time']                  = $send_time;
                    $data['send_address_id']            = $dispatcher[0]['send_address_id'];

                    $data['send_name']                  = $dispatcher[0]['send_contacts_name'];
                    $data['send_tel']                   = $dispatcher[0]['send_contacts_tel'];
                    $data['send_sheng']                 = $dispatcher[0]['send_sheng'];
                    $data['send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
                    $data['send_shi']                   = $dispatcher[0]['send_shi'];
                    $data['send_shi_name']              = $dispatcher[0]['send_shi_name'];
                    $data['send_qu']                    = $dispatcher[0]['send_qu'];
                    $data['send_qu_name']               = $dispatcher[0]['send_qu_name'];
                    $data['send_address']               = $dispatcher[0]['send_address'];
                    $data['send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
                    $data['send_address_latitude']      = $dispatcher[0]['send_address_latitude'];
                    $data['good_number']                = $good_number;
                    $data['good_weight']                = $good_weight;
                    $data['good_volume']                = $good_volume;
                    $data['pick_flag']                  = $pick_flag;
                    $data['send_flag']                  = $send_flag;
                    $data['total_money']                = ($total_money - 0) * 100;
                    $data['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $data['good_info']                  = $good_info;
                    $data['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $data['pick_money']                 = ($pick_money - 0)*100;
                    $data['send_money']                 = ($send_money - 0)*100;
                    $data['price']                      = ($price - 0)*100;
                    $data['more_money']                 = $more_money;
                    $data['pay_type']                   = $pay_type;
                    $data['car_type']                   = $car_type;
                    $data['remark']                     = $remark;
                    $data['reduce_price']               = $reduce_price;
                    /*** 现在根据用户的这个是否提货产生出可调度的数据出来以及费用出来**/
                    $inserttt=[];

                    /** 做一个调度数据出来*/
                    $list['order_type']                 = $order_type;
                    $list['order_id']                   = $order_id;
                    $list['company_id']                 = $company_id;
                    $list['company_name']               = $company_name;
                    $list['receiver_id']                = $receiver_id;
                    $list['group_code']                 = $group_code;
                    $list['group_name']                 = $group_name;
                    $list['total_user_id']              = $total_user_id;
                    $list['gather_time']                = $gather_time;
                    $list['gather_address_id']          = $dispatcher[0]['gather_address_id'];
                    $list['gather_name']                = $dispatcher[0]['gather_contacts_name'];
                    $list['gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
                    $list['gather_sheng']               = $dispatcher[0]['gather_sheng'];
                    $list['gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
                    $list['gather_shi']                 = $dispatcher[0]['gather_shi'];
                    $list['gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
                    $list['gather_qu']                  = $dispatcher[0]['gather_qu'];
                    $list['gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
                    $list['gather_address']             = $dispatcher[0]['gather_address'];
                    $list['gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
                    $list['gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
                    $list['send_time']                  = $send_time;
                    $list['send_address_id']            = $dispatcher[0]['send_address_id'];

                    $list['send_name']                  = $dispatcher[0]['send_contacts_name'];
                    $list['send_tel']                   = $dispatcher[0]['send_contacts_tel'];
                    $list['send_sheng']                 = $dispatcher[0]['send_sheng'];
                    $list['send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
                    $list['send_shi']                   = $dispatcher[0]['send_shi'];
                    $list['send_shi_name']              = $dispatcher[0]['send_shi_name'];
                    $list['send_qu']                    = $dispatcher[0]['send_qu'];
                    $list['send_qu_name']               = $dispatcher[0]['send_qu_name'];
                    $list['send_address']               = $dispatcher[0]['send_address'];
                    $list['send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
                    $list['send_address_latitude']      = $dispatcher[0]['send_address_latitude'];

                    $list['line_gather_address_id']          = $dispatcher[0]['gather_address_id'];
//                    $list['line_gather_contacts_id']         = $dispatcher[0]['gather_contacts_id'];
                    $list['line_gather_name']                = $dispatcher[0]['gather_contacts_name'];
                    $list['line_gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
                    $list['line_gather_sheng']               = $dispatcher[0]['gather_sheng'];
                    $list['line_gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
                    $list['line_gather_shi']                 = $dispatcher[0]['gather_shi'];
                    $list['line_gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
                    $list['line_gather_qu']                  = $dispatcher[0]['gather_qu'];
                    $list['line_gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
                    $list['line_gather_address']             = $dispatcher[0]['gather_address'];
                    $list['line_gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
                    $list['line_gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
                    $list['line_send_address_id']            = $dispatcher[0]['send_address_id'];
                    $list['line_send_name']                  = $dispatcher[0]['send_contacts_name'];
                    $list['line_send_tel']                   = $dispatcher[0]['send_contacts_tel'];
                    $list['line_send_sheng']                 = $dispatcher[0]['send_sheng'];
                    $list['line_send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
                    $list['line_send_shi']                   = $dispatcher[0]['send_shi'];
                    $list['line_send_shi_name']              = $dispatcher[0]['send_shi_name'];
                    $list['line_send_qu']                    = $dispatcher[0]['send_qu'];
                    $list['line_send_qu_name']               = $dispatcher[0]['send_qu_name'];
                    $list['line_send_address']               = $dispatcher[0]['send_address'];
                    $list['line_send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
                    $list['line_send_address_latitude']      = $dispatcher[0]['send_address_latitude'];

                    $list['on_line_flag']               = 'Y';
                    $list['on_line_money']              = $total_money*100;
                    $list['good_number']                = $good_number;
                    $list['good_weight']                = $good_weight;
                    $list['good_volume']                = $good_volume;
                    $list['dispatch_flag']              = 'Y';
                    $list['pick_flag']                  = $pick_flag;
                    $list['send_flag']                  = $send_flag;
                    $list['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $list['good_info']                  = $good_info;
                    $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $list['pay_type']                   = $pay_type;
                    $list['remark']                   = $remark;
                    $list['car_type']                   = $car_type;
                    $list['reduce_price']               = $reduce_price;
                    if ($pay_type == 'offline'){
                        $list['order_status'] = 2;
                    }
//                    if ($project_type == 'customer'){
//                        $list['order_status'] = 3;
//                    }
                    if($old_info){

                    }else{
                        $list['self_id']          = generate_id('patch_');
                        $list['total_user_id']    = $total_user_id;
//                        $list['create_user_id']   = $total_user_id;
//                        $list['create_user_name'] = $token_name;
                        $list['create_time']      = $list['update_time'] = $now_time;
                    }
                    /** 存储费用 **/
                    if ($pay_type == 'offline'){
                        switch ($project_type){
                            case 'user':
                                $money['fk_total_user_id']           = $user_info->total_user_id;
                                $money['fk_type']                    = 'USER';
                                $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                break;
                            case 'customer':
                                $money['shouk_group_code']           = $user_info->group_code;
                                $money['shouk_type']                 = 'GROUP_CODE';
                                $money['fk_company_id']              = $user_info->company_id;
                                $money['fk_type']                    = 'COMPANY';
                                $money['ZIJ_company_id']             = $user_info->company_id;
                                break;
                            case 'company':
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_group_code']             = $user_info->group_code;
                                break;
                        }
                    }else{
                        switch ($project_type){
                            case 'user':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_total_user_id']           = $user_info->total_user_id;
                                $money['fk_type']                    = 'USER';
                                $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                $money['delete_flag']                = 'N';
                                break;
                            case 'customer':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_company_id']             = $user_info->company_id;
                                $money['delete_flag']                = 'N';
                                break;
                            case 'company':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_group_code']             = $user_info->group_code;
                                $money['delete_flag']                = 'N';
                                break;
                        }
                    }
                    $money['self_id']                    = generate_id('order_money_');
                    $money['order_id']                   = $order_id;
                    $money['dispatch_id']                = $list['self_id'];
                    $money['create_time']                = $now_time;
                    $money['update_time']                = $now_time;
                    $money['money']                      = $total_money*100;
                    $money['money_type']                 = 'freight';
                    $money['type']                       = 'in';
                    $money['settle_flag']                = 'W';
                    $inserttt[] = $list;
                    if($old_info){
                        $orderid = $self_id;
                        $data['update_time'] = $now_time;
                        $id = TmsOrder::where($wheres)->update($data);
                        // $operationing->access_cause='修改订单';
                        // $operationing->operation_type='update';
                    }else{
                        $data['self_id']          = $order_id;
                        $data['group_code']       = $group_code;
                        $data['group_name']       = $group_name;
                        $data['company_id']       = $company_id;
                        $data['company_name']       = $company_name;
                        $data['total_user_id']   = $total_user_id;
//                        $data['create_user_name'] = $token_name;
                        $data['create_time']      = $data['update_time'] = $now_time;
                        $orderid = $data['self_id'];
                        if ($pay_type == 'offline'){
                            $data['order_status'] = 2;
                        }
//                        if ($project_type == 'customer'){
//                            $data['order_status'] = 3;
//                        }
                        $id = TmsOrder::insert($data);
                        TmsOrderDispatch::insert($inserttt);
                        TmsOrderCost::insert($money);


                    }

                    if($id){
                        $msg['code'] = 200;
                        $msg['msg']  = "操作成功";
                        $msg['order_id'] = $orderid;
                        $msg['order_id_show'] = substr($orderid,15);
                        return $msg;
                    }else{
                        $msg['code'] = 302;
                        $msg['msg']  = "操作失败";
                        return $msg;
                    }
                    break;
            }

            /***二次效验结束**/
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg'] = null;
            foreach ($erro as $k => $v){
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }

    }


    /***    订单明细详情     /api/order/details
     */
    public function  details(Request $request,Details $details){
        $tms_money_type    = array_column(config('tms.tms_money_type'),'name','key');
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_order_type        = array_column(config('tms.tms_order_type'),'name','key');
        $tms_pay_type    = array_column(config('tms.pay_type'),'name','key');
        $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
        $self_id = $request->input('self_id');
//         $self_id = 'order_202105221407579107811509';
        $table_name = 'tms_order';
        $select = ['self_id','group_code','group_name','company_name','create_user_name','create_time','use_flag','order_type','order_status','gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_time','send_time',
            'gather_address','send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_address','remark','total_money','price','pick_money','send_money','good_name','good_number','good_weight','good_volume','pick_flag','send_flag','info'
            ,'good_info','clod','line_info','pay_type','pay_state'];

        $list_select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','remark',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','clod','pick_flag','send_flag',
            'pay_type','order_id','pay_status','pay_time','receiver_type','gather_name','gather_tel','send_name','send_tel','receipt_flag'
        ];
//        $list_select = ['self_id','pay_type','order_id','pay_status','pay_time','receiver_type','order_status'];
        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];

        $select1=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
            'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
            'send_sheng_name','send_shi_name','send_qu_name','send_address','order_id',
            'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];

        $select2 = ['self_id','carriage_id','order_dispatch_id'];
        $select3 = ['self_id','company_id','company_name','carriage_flag','total_money'];
        $select4 = ['carriage_id','car_number','contacts','tel','price','car_id'];
        $selectList = ['self_id','receipt','order_id','total_user_id','group_code','group_name'];
        $info = TmsOrder::with(['TmsOrderDispatch' => function($query) use($list_select,$selectList,$select1,$select2,$select3,$select4){
            $query->select($list_select);
            $query->with(['tmsCarriageDispatch'=>function($query)use($select1,$select2,$select3,$select4){
                $query->where('delete_flag','=','Y');
                $query->select($select2);
                $query->with(['tmsCarriage'=>function($query)use($select3){
                    $query->where('delete_flag','=','Y');
                    $query->select($select3);
                }]);
                $query->with(['tmsCarriageDriver'=>function($query)use($select4){
                    $query->where('delete_flag','=','Y');
                    $query->select($select4);
                }]);
            }]);
            $query->with(['tmsReceipt'=>function($query)use($selectList) {
                $query->where('delete_flag', '=', 'Y');
                $query->select($selectList);
            }]);

        }])->where($where)->select($select)->first();
        if($info) {
            $info->order_status_show = $tms_order_status_type[$info->order_status] ?? null;
            $info->order_type_show   = $tms_order_type[$info->order_type] ??null;
            $info->pay_status = $tms_pay_type[$info->pay_type];
            if ($info->pay_state == 'Y' && $info->pay_type == 'offline'){
                $info->pay_state = '已付款';
            }elseif($info->pay_type == 'online'){
                $info->pay_state = '已付款';
            }elseif($info->pay_type == 'offline' && $info->pay_state == 'N'){
                $info->pay_state = '未付款';
            }
            $receipt_info = [];
            $receipt_info_list= [];
            foreach ($info->TmsOrderDispatch as $k =>$v){

                $v->pay_type_show = $tms_pay_type[$v->pay_type]??null;
                $v->good_info     = json_decode($v->good_info,true);
                $temperture = json_decode($v->clod,true);
                foreach ($temperture as $key => $value){
                    $temperture[$key] = $tms_control_type[$value];
                }
                $info->receipt_flag = $v->receipt_flag;
                $v->temperture = implode(',',$temperture);
                if ($v->tmsReceipt){
//                    $info->receipt = json_decode($v->tmsReceipt->receipt,true);
                    $receipt_info = img_for($v->tmsReceipt->receipt,'more');
                    $receipt_info_list[] = $receipt_info;

                }
                $car_list = [];
            if ($v->tmsCarriageDispatch){
                if ($v->tmsCarriageDispatch->tmsCarriageDriver){
                    foreach ($v->tmsCarriageDispatch->tmsCarriageDriver as $kk => $vv){
                        $carList['car_number'] = $vv->car_number;
                        $carList['tel'] = $vv->tel;
                        $carList['contacts'] = $vv->contacts;
                        $car_list[] = $carList;
                    }
                    $info->car_info = $car_list;
                }
              }
            }

            /** 零担发货收货仓**/
            $line_info = [];
            $pick_store_info = [];
            $send_store_info = [];
            if($info->line_info){
                $info->line_info = json_decode($info->line_info,true);
//                dd($info->line_info);
                $pick_store['pick_store'] = $info->line_info['send_sheng_name'].$info->line_info['send_shi_name'].$info->line_info['send_qu_name'].$info->line_info['send_address'];
                $pick_store['contacts']   = $info->line_info['send_name'];
                $pick_store['tel']   = $info->line_info['send_tel'];
                $pick_store_info[] = $pick_store;
                $send_store['send_store'] = $info->line_info['gather_sheng_name'].$info->line_info['gather_shi_name'].$info->line_info['gather_qu_name'].$info->line_info['gather_address'];
                $send_store['contacts']   = $info->line_info['gather_name'];
                $send_store['tel']   = $info->line_info['gather_tel'];
                $send_store_info[] = $send_store;
                $info->shift_number = $info->line_info['shift_number'];
                $info->trunking = $info->line_info['trunking'].'天';
            }
            $info->pick_store_info = $pick_store_info;
            $info->send_store_info = $send_store_info;
            $info->receipt = $receipt_info_list;
            $order_info              = json_decode($info->info,true);
            $send_info = [];
            $gather_info = [];
            foreach ($order_info as $kkk => $vvv){
//                dd($vvv);
                if ($info->pick_flag == 'Y'){
                    $send['address_info'] = $vvv['send_sheng_name'].$vvv['send_shi_name'].$vvv['send_qu_name'];
                    $send['contacts']     = $vvv['send_name'];
                    $send['tel']     = $vvv['send_tel'];
                    $send_info[] =$send;
                }

                if ($info->send_flag == 'Y'){
                    $gather['address_info'] = $vvv['gather_sheng_name'].$vvv['gather_shi_name'].$vvv['gather_qu_name'];
                    $gather['contacts'] = $vvv['gather_name'];
                    $gather['tel']  = $vvv['gather_tel'];
                    $gather['good_number'] = $vvv['good_number'];
                    $gather['good_weight'] = $vvv['good_weight'];
                    $gather['good_volume'] = $vvv['good_volume'];
                    $gather['good_cold']   = $vvv['clod_name'];
                    $gather['good_name']   = $vvv['good_name'];
                    $gather_info[] = $gather;
                }
                $vvv['clod'] =  $tms_control_type[$vvv['clod']];
                if ($info->order_type == 'vehicle'){
                    $order_info[$kkk]['good_weight'] = ($vvv['good_weight']/1000).'吨';
                }
            }
            $info->info = $order_info;
//            dd($send_info,$gather_info);
            $info->send_info = $send_info;
            $info->gather_info = $gather_info;
            $info->self_id_show = substr($info->self_id,15);
            $info->good_info         = json_decode($info->good_info,true);
            $info->clod              = json_decode($info->clod,true);
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->total_money = number_format($info->total_money/100, 2);
            $info->price       = number_format($info->price/100, 2);
            $info->pick_money  = number_format($info->pick_money/100, 2);
            $info->send_money  = number_format($info->send_money/100, 2);
            if ($info->order_type == 'vehicle'){
                $info->good_weight = ($info->good_weight/1000).'吨';
            }
//            dd($info->toArray());
            $data['info'] = $info;
            $msg['code'] = 200;
            $msg['msg']  = "数据拉取成功";
            $msg['data'] = $data;
            return $msg;
        } else {
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }

    }

    /**
     * 完成订单(用户点击运输完成) /api/order/order_done
     * */
    public function order_done(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input         = $request->all();
        $now_time    = date('Y-m-d H:i:s',time());
        $self_id     = $request->input('self_id');
        $rules = [
            'self_id'=>'required',
        ];
        $message = [
            'self_id.required'=>'请选择订单',
        ];
        /**虚拟数据
         $input['self_id']       = $self_id       = 'order_202104101356286664799683';
         **/

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where = [
               ['self_id','=',$self_id],
                ['order_status','!=',7]
            ];
            $select = ['self_id','order_status','total_money','pay_type'];
            $order = TmsOrder::where($where)->select($select)->first();
            if ($order->order_status == 6){
                $msg['code'] = 301;
                $msg['msg'] = '订单已完成';
                return $msg;
            }
            $update['update_time'] = $now_time;
            $update['order_status'] = 6;
            $id = TmsOrder::where($where)->update($update);

            /** 查找所有的运输单 修改运输状态**/
            $TmsOrderDispatch = TmsOrderDispatch::where('order_id',$self_id)->select('self_id')->get();
            if ($TmsOrderDispatch){
                $dispatch_list = array_column($TmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::where('delete_flag','=','Y')->whereIn('self_id',$dispatch_list)->update($update);

                /*** 订单完成后，如果订单是在线支付，添加运费到承接司机或3pl公司余额 **/
                if ($orderStatus){
//                    if ($order->pay_type == 'online'){
//                        dd($dispatch_list);
                        foreach ($dispatch_list as $key => $value){

                            $carriage_order = TmsOrderDispatch::where('self_id','=',$value)->first();
                            $idit = substr($carriage_order->receiver_id,0,5);
                            if ($idit == 'user_'){
                                $wallet_where = [
                                    ['total_user_id','=',$carriage_order->receiver_id]
                                ];
                                $data['wallet_type'] = 'user';
                                $data['total_user_id'] = $carriage_order->receiver_id;
                            }else{
                                $wallet_where = [
                                    ['group_code','=',$carriage_order->receiver_id]
                                ];
                                $data['wallet_type'] = '3PLTMS';
                                $data['group_code'] = $carriage_order->receiver_id;
                            }
                            $wallet = UserCapital::where($wallet_where)->select(['self_id','money'])->first();

                            $money['money'] = $wallet->money + $carriage_order->on_line_money;
                            $data['money'] = $carriage_order->on_line_money;
                            if ($carriage_order->group_code == $carriage_order->receiver_id){
                                $money['money'] = $wallet->money + $carriage_order->total_money;
                                $data['money'] = $carriage_order->total_money;
                            }

                            $money['update_time'] = $now_time;
                            UserCapital::where($wallet_where)->update($money);

                            $data['self_id'] = generate_id('wallet_');
                            $data['produce_type'] = 'in';
                            $data['capital_type'] = 'wallet';
                            $data['create_time'] = $now_time;
                            $data['update_time'] = $now_time;
                            $data['now_money'] = $money['money'];
                            $data['now_money_md'] = get_md5($money['money']);
                            $data['wallet_status'] = 'SU';

                            UserWallet::insert($data);
                        }
//                    }
                }
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
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

    /**
     * 取消订单(用户)  /api/order/order_cancel
     * */
    public function order_cancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $order_id       = $request->input('order_id');

        /*** 虚拟数据
        $input['order_id']           =$order_id='order_202103061730081629411962';
         **/
        $rules = [
            'order_id'=>'required',
        ];
        $message = [
            'order_id.required'=>'请取消的订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$order_id],
            ];
            $select=['self_id','order_status','pay_type','total_money'];
            $wait_info=TmsOrder::where($where)->select($select)->first();


            if ($wait_info->order_status == 4 || $wait_info->order_status == 5){
                $msg['code'] = 305;
                $msg['msg'] = '此订单运输中不可以取消';
                return $msg;
            }
            if ($wait_info->order_status == 6 ){
                $msg['code'] = 305;
                $msg['msg'] = '此订单已完成不可以取消';
                return $msg;
            }
            if ($wait_info->order_status == 7 ){
                $msg['code'] = 305;
                $msg['msg'] = '此订单已取消';
                return $msg;
            }
            /***判断订单时线上支付还是货到付款，线上支付要退款 **/
            if($wait_info->pay_type == 'online'){
                $wallet = UserCapital::where('total_user_id',$user_info->total_user_id)->select(['self_id','money'])->first();
                $wallet_update['money'] = $wait_info->total_money + $wallet->money;
                $wallet_update['update_time'] = $now_time;
                UserCapital::where('total_user_id',$user_info->total_user_id)->update($wallet_update);
                $data['self_id'] = generate_id('wallet_');
                $data['produce_type'] = 'refund';
                $data['capital_type'] = 'wallet';
                $data['money'] = $wait_info->total_money;
                $data['create_time'] = $now_time;
                $data['update_time'] = $now_time;
                $data['now_money'] = $wallet_update['money'];
                $data['now_money_md'] = get_md5($wallet_update['money']);
                $data['wallet_status'] = 'SU';
                $data['wallet_type'] = 'user';
                $data['total_user_id'] = $user_info->total_user_id;
                UserWallet::insert($data);
            }
            /** 修改订单状态**/
            $update['order_status']      = 7;
            $update['update_time']        =$now_time;
            $id = TmsOrder::where($where)->update($update);
            /** 修改运输单状态为已取消**/
            $dispatch_list = TmsOrderDispatch::where('order_id',$order_id)->select('self_id')->get();
            $dispatch_id_list = array_column($dispatch_list->toArray(),'self_id');
            TmsOrderDispatch::whereIn('self_id',$dispatch_id_list)->update($update);
            /** 取消订单应该删除应付费用**/
            $money_where = [
                ['order_id','=',$order_id],
            ];
            $money_update['delete_flag'] = 'N';
            $money_update['update_time'] = $now_time;
            $money_list = TmsOrderCost::where($money_where)->update($money_update);

            //如果订单已被承接，通知司机订单已取消
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
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }


    /**整车计算预估价格**/
    public function count_klio(Request $request){
        $input         = $request->all();
        $car_type = $request->input('car_type');
        $gather_address= $request->input('gather_address');
        $send_address = $request->input('send_address');

        /**虚拟数据
        $input['gather_address']      = $gather_address      = [["area"=>"东城区","city"=> "北京市","info"=> "123123","pro"=> "北京市"]];
        $input['send_address']        = $send_address        = [['area'=>'城关区',"city"=> "拉萨市","info"=>"1234区","pro"=> "西藏"]];
        $input['car_type']            = $car_type           = 'type_202102051755118039490396';
        * **/
        $rules = [
            'car_type'=>'required',
            'gather_address'=>'required',
            'send_address'=>'required',
        ];
        $message = [
            'car_type.required'=>'请选择车辆类型',
            'gather_address.required'=>'请选择发货地址',
            'send_address.required'=>'请选择收货地址',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
//            $startcity_str = json_decode($gather_address,true);
//            $endcity_str  = json_decode($send_address,true);
            $startcity_str = $gather_address;
            $endcity_str  = $send_address;
            // 查询系数类型 2 整车
            $type = 2;
            // 起步价系数
            $scale_startprice = 1;
            // 里程偏离系数
            $scale_km = 1;
            // 单公里价格系数
            $scale_price_km = 1;
            // 查找选定的车型0
            $car_type = TmsCarType::where('self_id','=',$car_type)->select('self_id','low_price','costkm_price','parame_name')->first();
            // 查找系数比例
            $scale = TmsParam::where('type','=',$type)->select('scale_startprice','scale_km','scale_price_km','type')->first();
            if($scale->type){
                $scale_startprice = $scale->scale_startprice;
                $scale_km = $scale->scale_km;
                $scale_price_km = $scale->scale_price_km;
            }
            $kilo = $this->countKlio(2,$startcity_str,$endcity_str);
            // 计算起步价
            $startPrice = $car_type->low_price*$scale_startprice/100;
            // 运费 公里数*单价
            $freight = $kilo*$car_type->costkm_price*$scale_price_km/100;
            $allmoney = $startPrice+$freight;
            $kilo1 = round($kilo);
            // 总运费

            $re_data['km'] = $kilo1;//预计里程数
            $re_data['countprice'] = round($allmoney/100)*100;  //预计费用
            $re_data['maxprice'] = round($allmoney*1.1/100)*100;//预计最大价格
            if ($kilo1){
                $msg['data'] = $re_data;
                $msg['code'] = 200;
                $msg['msg']  = "数据拉取成功";
                return $msg;
            }else{
                $msg['code'] = 300;
                $msg['msg']  = "查不到数据";
                return $msg;
            }
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }


    /***整车预估价格  /api/order/count_price

     **/
    public function count_price(Request $request){
        $input         = $request->all();
        $car_type = $request->input('car_type');
        $gather_address= $request->input('gather_address');
        $send_address = $request->input('send_address');
        $picktype = $request->input('pick_flag')??null;
        $sendtype = $request->input('send_flag')??null;
        /**虚拟数据

         **/
        $rules = [
            'car_type'=>'required',
            'gather_address'=>'required',
            'send_address'=>'required',
        ];
        $message = [
            'car_type.required'=>'请选择车辆类型',
            'gather_address.required'=>'请选择发货地址',
            'send_address.required'=>'请选择收货地址',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $type = 2;
            // 装货费
            $pickPrice = 0;
            // 卸货费
            $sendPrice = 0;
            // 起步价系数
            $scale_startprice = 1;
            // 里程偏离系数
            $scale_km = 1;
            // 单公里价格系数
            $scale_price_km =1;
            // 装货费用系数
            $scale_pickgood = 1;
            // 卸货费用系数
            $scale_sendgood = 1;
            // 多点提配系数
            $scale_multistore = 1;
            // 促销优惠折扣系数 折扣为 总价格乘以折扣
            $scale_discount = 1;
            // 查找选定的车型
            $car_type = TmsCarType::where('self_id',$car_type)->select('self_id','low_price','costkm_price','parame_name','pickup_price','unload_price','morepickup_price')->first()->toArray();
            if (empty($car_type)){
                $msg['code'] = 301;
                $msg['msg']  = "请选择正确的车型";
                return $msg;
            }
            // 查找系数比例
            $scale = TmsParam::where('type',$type)->select('scale_startprice','scale_multistore','scale_km','scale_price_km','type','scale_pickgood','scale_sendgood','scale_discount')->first()->toArray();
            // 如果有系数值则写入
            if($scale['type']){
                $scale_startprice = $scale['scale_startprice'];
                $scale_km = $scale['scale_km'];
                $scale_price_km = $scale['scale_price_km'];
                $scale_pickgood = $scale['scale_pickgood'];
                $scale_sendgood = $scale['scale_sendgood'];
                $scale_multistore = $scale['scale_multistore'];
                $scale_discount = $scale['scale_discount'];
            }
            // 获取整车公里数
            $km = $this->countKlio(2,$gather_address,$send_address);
            // 里程费 公里数*单价
            $freight = $km*$car_type['costkm_price']*$scale_price_km/100;

            // 装货费
            if ($picktype == 2) {
                $pickPrice = $car_type['pickup_price']*$scale_pickgood/100;
            }
            // 卸货费
            if ($sendtype == 2) {
                $sendPrice = $car_type['unload_price']*$scale_sendgood/100;
            }
            // 装卸费
            $psPrice = $pickPrice+$sendPrice;

            // 计算起步价
            $startPrice = $car_type['low_price']*$scale_startprice/100;

            // 多点提配费用
            $multistorePrice = (count($gather_address)+count($send_address)-2)*$car_type['morepickup_price']*$scale_multistore/100;
            if ($picktype == 1 || $sendtype == 1){
                $multistorePrice = 0;
            }
            // 起步价费用取整
            $startPrice = round($startPrice,2);
            // 里程费费用取整
            $freight = round($freight,2);
            // 装卸费费用取整
            $psPrice = round($psPrice,2);
            // 多点提配费费用取整
            $multistorePrice = round($multistorePrice,2);
            //根据时间判断计费价格
            //获取当天时间戳
            $nowday = mktime(23, 59, 59, date('m'), date('d'), date('Y'))*1000;
            //获取第二天时间戳
            $seconday = $nowday+24*60*60*1000;
            // 总运费 = 起步价 + 里程费 + 装卸费 + 多点提配费

            $allmoney = $startPrice+$freight+$psPrice+$multistorePrice;
            $freight1 = floor($freight+$startPrice);
            // 折扣价
            $discount = $allmoney*$scale_discount;
            $price['singleprice'] = round($allmoney/100)*100;
            $price['allmoney'] = round($allmoney/100)*100; // 总费用
            $price['discount'] = round($discount); // 优惠价
            $price['kilometre'] = round($km); // 公里数
            $price['freight'] = $freight1; // 里程费
            $price['psPrice'] = $psPrice; // 装卸费
            $price['pickprice'] = $pickPrice;//装货费
            $price['sendprice'] = $sendPrice;//卸货费
            $price['multistorePrice'] = $multistorePrice; // 多点提配费
            $price['maxprice'] = round($allmoney*1.1/100)*100;//预计最大费用
            $msg['info'] = $price;
            $msg['code'] = 200;
            $msg['msg']  = "数据拉取成功";
            return $msg;
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }

    }

    public function countKlio($type,$startcity_str,$endcity_str){
        if (count($startcity_str) == 1 && count($endcity_str) == 1){
            // 起点城市经纬度
            $start_action = bd_location($type,'',$startcity_str[0]['city'],$startcity_str[0]['area'],$startcity_str[0]['info']);//经纬度
            // 终点城市经纬度
            $end_action = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);//经纬度
            $list = direction($start_action['lat'], $start_action['lng'], $end_action['lat'], $end_action['lng']);
            $finally = $list['distance']/1000;
            $kilo = $this->mileage_interval(2,(int)$finally);
        }elseif(count($startcity_str) >1 && count($endcity_str) ==1){
            $km =0;
            for ($i=1;$i<count($startcity_str);$i++){
                $start_action = bd_location($type,'',$startcity_str[$i-1]['city'],$startcity_str[$i-1]['area'],$startcity_str[$i-1]['info']);//经纬度
                $start_action1= bd_location($type,'',$startcity_str[$i]['city'],$startcity_str[$i]['area'],$startcity_str[$i]['info']);
                // 获取百度返回的结果
                $list = direction($start_action['lat'], $start_action['lng'], $start_action1['lat'], $start_action1['lng']);
                $finally = $list['distance']/1000;
                $km1 = $this->mileage_interval(2,(int)$finally);
                $km += $km1;
            }
            $start_action2 = bd_location($type,'',end($startcity_str)['city'],end($startcity_str)['area'],end($startcity_str)['info']);
            $end_action = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);//经纬度
            $list2 = direction($start_action2['lat'], $start_action2['lng'], $end_action['lat'], $end_action['lng']);
            $finally1 = $list2['distance']/1000;
            $kilo1 = $this->mileage_interval(2,(int)$finally1);
            $kilo = $kilo1 + $km;
        }elseif(count($startcity_str) ==1 && count($endcity_str)>1){
            $km = 0;
            for ($i=1;$i<count($endcity_str);$i++){
                $end_action = bd_location($type,'',$endcity_str[$i-1]['city'],$endcity_str[$i-1]['area'],$endcity_str[$i-1]['info']);
                $end_action1 = bd_location($type,'',$endcity_str[$i]['city'],$endcity_str[$i]['area'],$endcity_str[$i]['info']);
                $list = direction($end_action['lat'], $end_action['lng'], $end_action1['lat'], $end_action1['lng']);
                $finally = $list['distance']/1000;
                $km1 = $this->mileage_interval(2,(int)$finally);
                $km += $km1;
            }
            $end_action2 = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);
            $start_action = bd_location($type,'',$startcity_str[0]['city'],$startcity_str[0]['area'],$startcity_str[0]['info']);//经纬度
            $list2 = direction($start_action['lat'], $start_action['lng'], $end_action2['lat'], $end_action2['lng']);
            $finally1 = $list2['distance']/1000;
            $kilo1 = $this->mileage_interval(2,(int)$finally1);
            $kilo = $kilo1 + $km;
        }else{
            $km =0;
            for ($i=1;$i<count($startcity_str);$i++){
                $start_action = bd_location($type,'',$startcity_str[$i-1]['city'],$startcity_str[$i-1]['area'],$startcity_str[$i-1]['info']);//经纬度
                $start_action1= bd_location($type,'',$startcity_str[$i]['city'],$startcity_str[$i]['area'],$startcity_str[$i]['info']);
                // 获取百度返回的结果
                $list = direction($start_action['lat'], $start_action['lng'], $start_action1['lat'], $start_action1['lng']);
                $finally = $list['distance']/1000;
                $km1 = $this->mileage_interval(2,(int)$finally);
                $km += $km1;
            }
            $km2 = 0;
            for ($j=1;$j<count($endcity_str);$j++){
                $end_action = bd_location($type,'',$endcity_str[$j-1]['city'],$endcity_str[$j-1]['area'],$endcity_str[$j-1]['info']);
                $end_action1 = bd_location($type,'',$endcity_str[$j]['city'],$endcity_str[$j]['area'],$endcity_str[$j]['info']);
                $list1 = direction($end_action['lat'],$end_action['lng'],$end_action1['lat'],$end_action1['lng']);
                $finally1 = $list1['distance']/1000;
                $km3 = $this->mileage_interval(2,(int)$finally1);
                $km2 += $km3;
            }
            $end_action3 = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);
            $start_action3 = bd_location($type,'',end($startcity_str)['city'],end($startcity_str)['area'],end($startcity_str)['info']);//经纬度
            $list2 = direction($start_action3['lat'], $start_action3['lng'], $end_action3['lat'], $end_action3['lng']);
            $finally2 = $list2['distance']/1000;
            $kilo1 = $this->mileage_interval(2,(int)$finally2);

            $kilo = $km + $km2 + $kilo1;

        }
        return $kilo;
    }

    /*
     * 计算公里数
     * */
    function mileage_interval($type,$km){
        // 查询里程系数标准
        $result = TmsParam::where(['type'=>$type])->select('scale_km','scale_km_two','scale_km_three','scale_km_four')->first()->toArray();
        // 默认计算后的里程数
        $finally = $km;
        // 获取0-100里程系数
        $scale_km = $result['scale_km'] == '' ? 1 : $result['scale_km'];
        // 获取100-300里程系数
        $scale_km_two = $result['scale_km_two'] == '' ? 1 : $result['scale_km_two'];
        // 获取300-1000里程系数
        $scale_km_three = $result['scale_km_three'] == '' ? 1 : $result['scale_km_three'];
        // 获取1000以上里程系数
        $scale_km_four = $result['scale_km_four'] == '' ? 1 : $result['scale_km_four'];
        // 判断0-100里程数所在范围并返回相应的里程数
        if($km >=0 && $km<= 100){
            $finally = $km*$scale_km;
            return $finally;
        }
        // 判断100-300里程数所在范围并返回相应的里程数
        if($km > 100 && $km<= 300){
            $finally = $km*$scale_km_two;
            return $finally;
        }
        // 判断300-1000里程数所在范围并返回相应的里程数
        if($km > 300 && $km<= 1000){
            $finally = $km*$scale_km_three;
            return $finally;
        }
        // 判断1000以上里程数所在范围并返回相应的里程数
        if($km > 1000){
            $finally = $km*$scale_km_four;
            return $finally;
        }
    }

    /**
     * 市内整车计算价格  /api/order/cityVehical
     * */
    public function cityVehical(Request $request){
        $input         = $request->all();
        $car_type = $request->input('car_type');
        $gather_address= $request->input('gather_address');
        $send_address = $request->input('send_address');
        $city = $request->input('city');
        /**虚拟数据
        $input['gather_address']      = $gather_address      = [["area"=>"徐汇区","city"=> "上海市","info"=> "植物园","pro"=> "上海市"]];
        $input['send_address']        = $send_address        = [["area"=>"嘉定区","city"=> "上海市","info"=> "爱特路855号","pro"=> "上海市"]];
        $input['car_type']             = $car_type            = 'type_202102051755118039490396';
        $input['city']                 = $city                = '上海市';
         **/
        $rules = [
            'car_type'=>'required',
            'gather_address'=>'required',
            'send_address'=>'required',
        ];
        $message = [
            'car_type.required'=>'请选择车辆类型',
            'gather_address.required'=>'请选择发货地址',
            'send_address.required'=>'请选择收货地址',
        ];
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
//            $startstr = json_decode($gather_address,true);
//            $endstr = json_decode($send_address,true);
            $startstr = $gather_address;
            $endstr = $send_address;
            $kilo = $this->countKlio(2,$startstr,$endstr);
            $city_role = TmsCity::where('city',$city)->select('self_id','scale_klio','start_fare','c_city','scale_price','scale_hour','scale_one_km','scale_two_km','sum_kilo')->first();
            $car = TmsCarType::where('self_id',$car_type)->select('self_id','low_price','costkm_price','parame_name','pickup_price','unload_price','morepickup_price')->first();
            //起步价
            $start_price = $car->low_price/100 * $city_role->start_fare;

            //里程费
            if ($kilo<=$city_role->sum_kilo){
                //小于分段公里数
                if ($kilo <= $city_role->scale_klio){
                    //小于起步公里数 不计里程费
                    $kilo_price = 0;
                }else{
                    $kilo_price = $kilo*$city_role->scale_one_km*$city_role->scale_price*$car->costkm_price/100;
                }
            }else{
                //大于分段公里数
                $kilo_price = $kilo*$city_role->scale_two_km*$city_role->scale_price*$car->costkm_price/100;
            }
            //总运费
            $all_money = $start_price + $kilo_price;
            $list = [
                'kilo'=>round($kilo),
                'all_money'=>round($all_money),
            ];
            $msg['data'] = $list;
            $msg['code'] = 200;
            $msg['msg']  = "数据拉取成功";
            return $msg;
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

}
?>
