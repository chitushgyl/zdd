<?php
namespace App\Http\Admin\Tms;
use App\Http\Controllers\FileController as File;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Tools\Import;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsCar;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsCarType;
use App\Models\Tms\TmsGroup;

class CarController extends CommonController{

    /***    车辆列表头部      /tms/car/carList
     */
    public function  carList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='车辆';
        $data['import_info']    =[
            'import_text'=>'下载'.$abc.'导入示例文件',
            'import_color'=>'#FC5854',
            'import_url'=>config('aliyun.oss.url').'execl/2020-07-02/TMS车辆导入文件范本.xlsx',
        ];

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    车辆分页      /tms/car/carPage
     */
    public function carPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tms_car_possess_type    =array_column(config('tms.tms_car_possess_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $car_brand      =$request->input('car_brand');
        $car_number     =$request->input('car_number');
        $car_type_name  =$request->input('car_type_name');
        $car_possess    =$request->input('car_possess');

        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'like','name'=>'car_number','value'=>$car_number],
            ['type'=>'like','name'=>'car_brand','value'=>$car_brand],
            ['type'=>'=','name'=>'car_possess','value'=>$car_possess],
            ['type'=>'=','name'=>'car_type_name','value'=>$car_type_name]
        ];

        $where=get_list_where($search);

        $select=['self_id','car_brand','car_number','car_possess','weight','volam','control','car_type_name','use_flag','contacts','tel','license','medallion','group_code','group_name','total_user_id'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsCar::where($where)->count(); //总的数据量
                $data['items']=TmsCar::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsCar::where($where)->count(); //总的数据量
                $data['items']=TmsCar::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsCar::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsCar::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
			$v->car_possess_show=$tms_car_possess_type[$v->car_possess]??null;
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }



    /***    新建车辆      /tms/car/createCar
     */
    public function createCar(Request $request){
        /** 接收数据*/
        $self_id=$request->input('self_id');
		$data['tms_car_possess_type']    =config('tms.tms_car_possess_type');
        $tms_car_possess_type    =array_column(config('tms.tms_car_possess_type'),'name','key');
        $where=[
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select=['self_id','car_brand','car_number','car_type_id','car_type_name','group_code','car_possess','weight','volam','control','license','medallion','board_time','contacts','tel'];
        $select1 = ['self_id','parame_name'];
        $data['info']=TmsCar::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])->where($where)->select($select)->first();

        if ($data['info']){
            $data['info']->car_possess_show=$tms_car_possess_type[$data['info']->car_possess]??null;
            $data['info']->license = img_for($data['info']->license,'more');
            $data['info']->medallion = img_for($data['info']->medallion,'more');
            $data['info']->car_type  = $data['info']->tmsCarType->parame_name;
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
//        dd($msg);
        return $msg;


    }


    /***    新建车辆数据提交      /tms/car/addCar
     */
    public function addCar(Request $request){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_car';
        $operationing->access_cause     ='创建/修改车辆';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();

        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $group_code         =$request->input('group_code');
        $car_brand          =$request->input('car_brand');
        $car_number         =$request->input('car_number');
        $car_nuclear        =$request->input('car_nuclear');
        $car_possess        =$request->input('car_possess');
        $remark             =$request->input('remark');
        $weight             =$request->input('weight');
        $volam              =$request->input('volam');
        $check_time         =$request->input('check_time');
        $license            =$request->input('license');
        $medallion          =$request->input('medallion');
        $board_time         =$request->input('board_time');
        $car_type_id        =$request->input('car_type_id');
        $contacts        =$request->input('contacts');
        $tel        =$request->input('tel');


        /*** 虚拟数据
        $input['self_id']         =$self_id='';
        $input['group_code']        =$group_code='1234';
        $input['car_brand']         =$car_brand='64';
        $input['car_number']        =$car_number='沪A45612';
        $input['car_nuclear']       =$car_nuclear='64';
        $input['car_possess']       =$car_possess='GIUIUIT';
        $input['remark']            =$remark='GIUIUIT';
        $input['weight']            =$weight='GIUIUIT';
        $input['volam']             =$volam='GIUIUIT';
        $input['check_time']        =$check_time='2020-01-02';
        $input['license']           =$license='GIUIUIT';
        $input['medallion']         =$medallion='GIUIUIT';
        $input['board_time']        =$board_time='2020-05-06';
        $input['car_type_id']       =$car_type_id='type_202102051755118039490396';
        $input['contacts']          =$contacts='haha';
        $input['tel']               =$tel='18564516123';
        **/
        $rules=[
            'car_number'=>'required',
            'car_type_id'=>'required',
            'contacts'=>'required',
            'tel'=>'required',
        ];
        $message=[
            'car_number.required'=>'车牌号必须填写',
            'car_type_id.required'=>'车型必须选择',
            'contacts.required'=>'车辆联系人必须填写',
            'tel.required'=>'车辆联系电话必须填写',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $group_name     =SystemGroup::where('group_code','=',$group_code)->value('group_name');
            if(empty($group_name)){
                $msg['code'] = 301;
                $msg['msg'] = '公司不存在';
                return $msg;
            }
            $where_car_type=[
                ['delete_flag','=','Y'],
                ['self_id','=',$car_type_id],
            ];
            $info2 = TmsCarType::where($where_car_type)->select('self_id','parame_name')->first();
            if (empty($info2)) {
                $msg['code'] = 302;
                $msg['msg'] = '车型不存在';
                return $msg;
            }

            if($self_id){
                $name_where=[
                    ['self_id','!=',$self_id],
                    ['car_number','=',$car_number],
                    ['delete_flag','=','Y'],
                    ['group_code','=',$group_code]
                ];
            }else{
                $name_where=[
                    ['car_number','=',$car_number],
                    ['delete_flag','=','Y'],
                    ['group_code','=',$group_code]
                ];
            }

            $carnumber = TmsCar::where($name_where)->count();

            if ($carnumber>0){
                $msg['code'] = 308;
                $msg['msg'] = '车牌号已存在，请重新填写';
                return $msg;
            }

            $data['car_number']        =$car_number;
            $data['car_brand']         =$car_brand;
            $data['car_nuclear']       =$car_nuclear;
            $data['car_possess']       =$car_possess;
            $data['remark']            =$remark;
            $data['weight']            =$weight;
            $data['volam']             =$volam;
            $data['check_time']        =$check_time;
            $data['license']           =img_for($license,'in');
            $data['medallion']         =img_for($medallion,'in');
            $data['board_time']        =$board_time;
            $data['car_type_id']       =$info2->self_id;
            $data['car_type_name']     =$info2->parame_name;
            $data['contacts']          =$contacts;
            $data['tel']               =$tel;

            //dump($data);

            //dd($input);
            $wheres['self_id'] = $self_id;
            $old_info=TmsCar::where($wheres)->first();

            if($old_info){
				//dd(1111);
                $data['update_time']=$now_time;
                $id=TmsCar::where($wheres)->update($data);

                $operationing->access_cause='修改车辆';
                $operationing->operation_type='update';


            }else{

                $data['self_id']            =generate_id('car_');
                $data['group_code']         = $group_code;
                $data['group_name']         = $group_name;
                $data['create_user_id']     =$user_info->admin_id;
                $data['create_user_name']   =$user_info->name;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=TmsCar::insert($data);
                $operationing->access_cause='新建车辆';
                $operationing->operation_type='create';

            }

			$operationing->table_id=$old_info?$self_id:$data['self_id'];
            $operationing->old_info=$old_info;
            $operationing->new_info=$data;

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



    /***    车辆禁用/启用      /tms/car/carUseFlag
     */
    public function carUseFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_car';
        $medol_name='TmsCar';
        $self_id=$request->input('self_id');
        $flag='useFlag';
//        $self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;


    }

    /***    车辆删除      /tms/car/carDelFlag
     */
    public function carDelFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_car';
        $medol_name='TmsCar';
        $self_id=$request->input('self_id');
        $flag='delFlag';
//        $self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;
    }

    /***    拿去车辆数据     /tms/car/getCar
     */
    public function  getCar(Request $request){
        $group_code=$request->input('group_code');
        //$input['group_code'] =  $group_code = '1234';
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['group_code','=',$group_code],
        ];
        $data['info']=TmsCar::where($where)->get();

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    车辆导入     /tms/car/import
     */
    public function import(Request $request){
        $table_name         ='tms_car';
        $now_time           = date('Y-m-d H:i:s', time());

        $operationing       = $request->get('operationing');//接收中间件产生的参数
        $operationing->access_cause     ='导入创建车辆';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='import';

        $user_info          = $request->get('user_info');//接收中间件产生的参数

        /** 接收数据*/
        $input              =$request->all();
        $importurl          =$request->input('importurl');
        $group_code         =$request->input('group_code');
        $file_id            =$request->input('file_id');
//	dump($input);
        /****虚拟数据
        $input['importurl']     =$importurl="uploads/import/TMS车辆导入文件范本.xlsx";
        $input['group_code']       =$group_code='1234';
         ***/
        $rules = [
            'group_code' => 'required',
            'importurl' => 'required',
        ];
        $message = [
            'group_code.required' => '请选择所属公司',
            'importurl.required' => '请上传文件',
        ];

        $validator = Validator::make($input, $rules, $message);

        if ($validator->passes()) {
            /**发起二次效验，1效验文件是不是存在， 2效验文件中是不是有数据 3,本身数据是不是重复！！！* */
            if (!file_exists($importurl)) {
                $msg['code'] = 301;
                $msg['msg'] = '文件不存在';
                return $msg;
            }

            $res = Excel::toArray((new Import),$importurl);
            $info_check=[];
            if(array_key_exists('0', $res)){
                $info_check=$res[0];
            }



            /**  定义一个数组，需要的数据和必须填写的项目
             键 是EXECL顶部文字，
             * 第一个位置是不是必填项目    Y为必填，N为不必须，
             * 第二个位置是不是允许重复，  Y为允许重复，N为不允许重复
             * 第三个位置为长度判断
             * 第四个位置为数据库的对应字段
             */
            $shuzu=[
               '车牌号' =>['Y','Y','10','car_number'],
               '车辆属性(1:自有，2:租赁)' =>['Y','Y','1','car_possess'],
               '车辆类型' =>['Y','Y','64','car_type_name'],
               '温控类型' =>['Y','Y','16','control'],
               '承重(kg)' =>['N','Y','64','weight'],
               '体积(立方)' =>['N','Y','64','volam'],
               '联系人' =>['N','Y','64','contacts'],
               '联系电话' =>['N','Y','64','tel'],
                ];
            $ret=arr_check($shuzu,$info_check);

            // dump($ret);
            if($ret['cando'] == 'N'){
                $msg['code'] = 304;
                $msg['msg'] = $ret['msg'];
                return $msg;
            }

            $info_wait=$ret['new_array'];
            /** 二次效验结束**/
            $where_check=[
                ['delete_flag','=','Y'],
                ['self_id','=',$group_code],
            ];

            $info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();
            if(empty($info)){
                $msg['code'] = 305;
                $msg['msg'] = '所属公司不存在';
                return $msg;
            }

            // dd($info);

            $datalist=[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=2;

            //dump($info_wait);
            /** 现在开始处理$car***/
            $tms_control_type        =array_column(config('tms.tms_control_type'),'key','name');

            foreach($info_wait as $k => $v){
                if (!check_carnumber($v['car_number'])) {
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行车牌号错误！".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }

                $where = [
                    ['delete_flag','=','Y'],
                    ['group_code','=',$info->group_code],
                    ['car_number','=',$v['car_number']]
                ];

                $is_car_info = TmsCar::where($where)->value('group_code');

                if($is_car_info){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行车辆已存在".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }

                if (!in_array($v['car_possess'],[1,2])) {
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行车辆属性：1或2！".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }
                $where_car_type = [
                    ['delete_flag','=','Y'],
                    ['parame_name','=',$v['car_type_name']]
                ];
                $car_type = TmsCarType::where($where_car_type)->select('self_id','parame_name')->first();
                // dd($car_type);
                if(!$car_type){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行车辆类型不存在！".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }

				$control         = $tms_control_type[$v['control']]??null;
                if(empty($control)){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行温控类型错误！".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }

                $list=[];
                if($cando =='Y'){

                    $list['self_id']            =generate_id('car_');
                    $list['car_number']         = $v['car_number'];
                    $list['car_possess']        = $v['car_possess'] == 1 ? 'oneself' : 'lease';
                    $list['weight']             = $v['weight'];
                    $list['volam']              = $v['volam'];
                    $list['control']            = $control ;
                    $list['group_code']         = $info->group_code;
                    $list['group_name']         = $info->group_name;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        =$list['update_time']=$now_time;
                    $list['file_id']            =$file_id;
                    $list['car_type_id']        =$car_type->self_id;
                    $list['car_type_name']      =$car_type->parame_name;
                    $list['contacts']           =$v['contacts'];
                    $list['tel']                =$v['tel'];



                    $datalist[]=$list;
                }

                $a++;
            }
            $operationing->old_info=null;
            $operationing->new_info=(object)$datalist;

            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }
            $count=count($datalist);
            $id= TmsCar::insert($datalist);

            if($id){
                $msg['code']=200;
                /** 告诉用户，你一共导入了多少条数据，其中比如插入了多少条，修改了多少条！！！*/
                $msg['msg']='操作成功，您一共导入'.$count.'条数据';

                return $msg;
            }else{
                $msg['code']=307;
                $msg['msg']='操作失败';
                return $msg;
            }
        }else{
            $erro = $validator->errors()->all();
            $msg['msg'] = null;
            foreach ($erro as $k => $v) {
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            $msg['code'] = 300;
            return $msg;
        }


    }

    /***    车辆详情     /tms/car/details
     */
    public function  details(Request $request,Details $details){
        $self_id=$request->input('self_id');
        $table_name='tms_car';
        $select=['self_id','car_brand','car_number','car_nuclear','car_possess',
            'group_name','remark','weight','weight','volam','control','check_time','license','medallion','board_time','car_type_id','car_type_name','contacts','tel'];
        // $self_id='car_202012291341297595587871';
        $info=$details->details($self_id,$table_name,$select);

        if($info){

            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            $tms_car_possess_type    =array_column(config('tms.tms_car_possess_type'),'name','key');
            // dump($tms_control_type);
            // dump($info['control']);
            if ($info->car_possess && !empty($tms_car_possess_type[$info->car_possess])) {
                $info->car_possess = $tms_car_possess_type[$info->car_possess];
            }
            if ($info->control && !empty($tms_control_type[$info->control])) {
                $info->control = $tms_control_type[$info->control];
            }
            if ($info->license){
                $info->license_show = img_for($info->license,'more');
            }
            if ($info->medallion){
                $info->medallion_show = img_for($info->medallion,'more');
            }


            // dump($tms_control_type[$info['control']]);
            // dd($tms_car_possess_type);
            $data['info']=$info;
            $log_flag='Y';
            $data['log_flag']=$log_flag;
            $log_num='10';
            $data['log_num']=$log_num;
            $data['log_data']=null;

            if($log_flag =='Y'){
                $data['log_data']=$details->change($self_id,$log_num);

            }
            // dd($data);

            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
        }else{
            $msg['code']=300;
            $msg['msg']="没有查询到数据";
            return $msg;
        }

    }

    /***    车辆导出     /tms/car/execl
     */
    public function execl(Request $request,File $file){
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $now_time   =date('Y-m-d H:i:s',time());
        $input      =$request->all();
        /** 接收数据*/
        $group_code     =$request->input('group_code');
//        $group_code  =$input['group_code']   ='group_202012251449437824125582';
        //dd($group_code);
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'必须选择公司',
        ];
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()){
            /** 下面开始执行导出逻辑**/
            $group_name     =SystemGroup::where('group_code','=',$group_code)->value('group_name');
            //查询条件
            $search=[
                ['type'=>'=','name'=>'group_code','value'=>$group_code],
                ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ];
            $where=get_list_where($search);

            $select=['self_id','car_number','car_possess','weight','volam','control','car_type_name','contacts','tel','remark'];
            $info=TmsCar::where($where)->orderBy('create_time', 'desc')->select($select)->get();
//dd($info);
            if($info){
                //设置表头
                $row = [[
                    "id"=>'ID',
                    "car_number"=>'车牌号',
                    "car_type_name"=>'车辆类型',
                    "car_possess"=>'属性',
                    "control"=>'温控',
                    "weight"=>'承重(kg)',
                    "volam"=>'体积(立方)',
                    "contacts"=>'联系人',
                    "tel"=>'联系电话',
                    "remark"=>'备注'
                ]];

                /** 现在根据查询到的数据去做一个导出的数据**/
                $data_execl=[];
                $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
                $tms_car_possess_type = array_column(config('tms.tms_car_possess_type'),'name','key');

                foreach ($info as $k=>$v){
                    $list=[];

                    $list['id']=($k+1);
                    $list['car_number']=$v->car_number;
                    $list['car_type_name']=$v->car_type_name;
                    $control = '';
                    $car_possess = '';
                    if (!empty($tms_control_type[$v['control']])) {
                        $control = $tms_control_type[$v['control']];
                    }

                    if (!empty($tms_car_possess_type[$v['car_possess']])) {
                        $car_possess = $tms_car_possess_type[$v['car_possess']];
                    }
                    $list['car_possess']=$car_possess;
                    $list['control']    =$control;
                    $list['weight']     =$v->weight;
                    $list['volam']      =$v->volam;
                    $list['contacts']   =$v->contacts;
                    $list['tel']        =$v->tel;
                    $list['remark']     =$v->remark;

                    $data_execl[]=$list;
                }
                /** 调用EXECL导出公用方法，将数据抛出来***/
                $browse_type=$request->path();
                $msg=$file->export($data_execl,$row,$group_code,$group_name,$browse_type,$user_info,$where,$now_time);

                //dd($msg);
                return $msg;

            }else{
                $msg['code']=301;
                $msg['msg']="没有数据可以导出";
                return $msg;
            }
        }else{
            $erro=$validator->errors()->all();
            $msg['msg']=null;
            foreach ($erro as $k=>$v) {
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            $msg['code']=300;
            return $msg;
        }

    }

}
?>
