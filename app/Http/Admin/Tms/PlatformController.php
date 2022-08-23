<?php
namespace App\Http\Admin\tms;



use App\Http\Controllers\CommonController;
use App\Models\Tms\AppCarousel;
use App\Models\Tms\CarBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class PlatformController extends CommonController{

    public function addCarousel(Request $request){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_car';
        $operationing->access_cause     ='创建/修改轮播图';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();

        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $picture            =$request->input('picture');
        $sort               =$request->input('sort');

        /*** 虚拟数据
        //        $input['self_id']         =$self_id='good_202007011336328472133661';
        //        $input['picture']         =$picture='good_202007011336328472133661';
        //        $input['sort']            =$sort='good_202007011336328472133661';

         **/
        $rules=[
            'picture'=>'required',
            'sort'=>'required',
        ];
        $message=[
            'picture.required'=>'请选择要上传的图片',

        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $wheres['self_id'] = $self_id;
            $old_info=AppCarousel::where($wheres)->first();

            if($old_info){
                $data['update_time']=$now_time;
                $id=AppCarousel::where($wheres)->update($data);

                $operationing->access_cause='修改图片';
                $operationing->operation_type='update';

            }else{

                $data['self_id']            =generate_id('c_');
                $data['picture']            =img_for($picture,'in');
                $data['sort']               =$sort;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=AppCarousel::insert($data);
                $operationing->access_cause='新建轮播图';
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


    /**
     * 添加品牌
     * */
    public function addCarbrand(Request $request){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='car_brand';
        $operationing->access_cause     ='创建/修改车辆品牌';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();

        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $brand            =$request->input('brand');


        /*** 虚拟数据
        //        $input['self_id']         =$self_id='good_202007011336328472133661';
        //        $input['brand']           =$brand='good_202007011336328472133661';

         **/
        $rules=[
            'brand'=>'required',
        ];
        $message=[
            'brand.required'=>'请选择要上传的图片',

        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $wheres['self_id'] = $self_id;
            $old_info=CarBrand::where($wheres)->first();

            if($old_info){
                $data['update_time']=$now_time;
                $id=CarBrand::where($wheres)->update($data);

                $operationing->access_cause='修改轮播图';
                $operationing->operation_type='update';

            }else{

                $data['self_id']            =generate_id('brand_');
                $data['brand']               =$brand;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=CarBrand::insert($data);
                $operationing->access_cause='新建轮播图';
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

    /*
     * 添加车辆
     * */
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
        $control            =$request->input('control');
        $check_time         =$request->input('check_time');
        $license            =$request->input('license');
        $medallion          =$request->input('medallion');
        $board_time         =$request->input('board_time');
        $car_type_id        =$request->input('car_type_id');
        $contacts        =$request->input('contacts');
        $tel        =$request->input('tel');


        /*** 虚拟数据
        //        $input['self_id']         =$self_id='good_202007011336328472133661';
        $input['group_code']        =$group_code='1234';
        $input['car_brand']         =$car_brand='64';
        $input['car_number']        =$car_number='沪A45612';
        $input['car_nuclear']       =$car_nuclear='64';
        $input['car_possess']       =$car_possess='GIUIUIT';
        $input['remark']            =$remark='GIUIUIT';
        $input['weight']            =$weight='GIUIUIT';
        $input['volam']             =$volam='GIUIUIT';
        $input['control']           =$control='GIUIUIT';
        $input['check_time']        =$check_time='2020-01-02';
        $input['license']           =$license='GIUIUIT';
        $input['medallion']         =$medallion='GIUIUIT';
        $input['board_time']        =$board_time='2020-05-06';
        $input['car_type_id']       =$car_type_id='type_202101061323561478489954';
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
            $data['control']           =$control;
            $data['check_time']        =$check_time;
            $data['license']           =$license;
            $data['medallion']         =$medallion;
            $data['board_time']        =$board_time;
            $data['car_type_id']       =$info2->self_id;
            $data['car_type_name']     =$info2->parame_name;

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










































}
