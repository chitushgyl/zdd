<?php
namespace App\Http\Admin\tms;



use App\Http\Controllers\CommonController;
use App\Models\Tms\AppCar;
use App\Models\Tms\AppCarousel;
use App\Models\Tms\CarBrand;
use App\Models\Tms\ChargeAddress;
use App\Models\Tms\TmsCarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;

class PlatformController extends CommonController{
   /**
    * 添加轮播图
    * */
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
                $data['picture']            =img_for($picture,'on_json');
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

    /***    轮播图列表头部      /tms/car/carList
     */
    public function  carouselList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='轮播图';
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

    /**
     * 轮播图列表
     * */
    public function carouselPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
        ];

        $where=get_list_where($search);
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$search;
        $select=['self_id','picture','sort'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=AppCarousel::where($where)->count(); //总的数据量
                $data['items']=AppCarousel::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=AppCarousel::where($where)->count(); //总的数据量
                $data['items']=AppCarousel::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=AppCarousel::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=AppCarousel::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        foreach ($data['items'] as $k=>$v) {
                $v->picture = img_for('no_json',$v->picture);
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
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
                $operationing->access_cause='新建品牌';
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
     * 品牌列表
     * */
    public function brandList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='车辆品牌';
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

    public function brandPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
//            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
//            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
        ];

        $where=get_list_where($search);

        $select=['self_id','brand'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=CarBrand::where($where)->count(); //总的数据量
                $data['items']=CarBrand::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=CarBrand::where($where)->count(); //总的数据量
                $data['items']=CarBrand::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=CarBrand::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=CarBrand::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        foreach ($data['items'] as $k=>$v) {

        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
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
        $brand              =$request->input('brand');
        $type               =$request->input('type');
        $car_type           =$request->input('car_type');
        $price              =$request->input('price');
        $view               =$request->input('view');
        $car_name           =$request->input('car_name');
        $picture            =$request->input('picture');
//        $control            =$request->input('control');
//        $check_time         =$request->input('check_time');
//        $license            =$request->input('license');
//        $medallion          =$request->input('medallion');
//        $board_time         =$request->input('board_time');
//        $car_type_id        =$request->input('car_type_id');
//        $contacts        =$request->input('contacts');
//        $tel        =$request->input('tel');


        /*** 虚拟数据
        //        $input['self_id']         =$self_id='good_202007011336328472133661';
        $input['group_code']        =$group_code='1234';
        $input['brand']             =$brand='64';
        $input['car_type']          =$type='沪A45612';
        $input['type']              =$type='64';
        $input['price']             =$price='GIUIUIT';
        $input['view']              =$view='GIUIUIT';
        $input['car_name']          =$car_name='GIUIUIT';
        $input['picture']           =$picture='GIUIUIT';
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
            'brand'=>'required',
            'car_name'=>'required',
            'price'=>'required',
        ];
        $message=[
            'brand.required'=>'请填写车辆品牌',
            'car_name.required'=>'请填写车辆名称',
            'price.required'=>'请填写车辆价格',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {


            $where_car_type=[
                ['delete_flag','=','Y'],
                ['self_id','=',$car_type],
            ];
            $info2 = TmsCarType::where($where_car_type)->select('self_id','parame_name')->first();
            if (empty($info2)) {
                $msg['code'] = 302;
                $msg['msg'] = '车型不存在';
                return $msg;
            }

            $data['car_type']          =$car_type;
            $data['brand']             =$brand;
            $data['type']              =$type;
            $data['car_name']          =$car_name;
            $data['view']              =$view;
            $data['price']             =$price;
            $data['picture']           =img_for($picture,'more');

//            $data['control']           =$control;
//            $data['check_time']        =$check_time;
//            $data['license']           =$license;
//            $data['medallion']         =$medallion;
//            $data['board_time']        =$board_time;
//            $data['car_type_id']       =$info2->self_id;
//            $data['car_type_name']     =$info2->parame_name;

            //dump($data);

            //dd($input);
            $wheres['self_id'] = $self_id;
            $old_info=AppCar::where($wheres)->first();

            if($old_info){
                //dd(1111);
                $data['update_time']=$now_time;
                $id=AppCar::where($wheres)->update($data);

                $operationing->access_cause='添加车辆';
                $operationing->operation_type='update';


            }else{

                $data['self_id']            =generate_id('car_');
                $data['group_code']         = $group_code;

                $data['create_user_id']     =$user_info->admin_id;
                $data['create_user_name']   =$user_info->name;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=AppCar::insert($data);
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

    /**
     * 删除车辆
     * */
    public function delCar(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='app_car';
        $medol_name='AppCar';
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

    /**
     * 获取城市地址
     * */
    public function getAddress(Request $request){
//        https://restapi.amap.com/v3/config/district?keywords=北京&subdistrict=2&key=807cab15630979094c098adebadd5fec


    }

    /**
     * 添加充电桩地址  /tms/platform/addChargeAddress
     * */
    public function addChargeAddress(Request $request){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='charge_address';
        $operationing->access_cause     ='创建/修改充电桩地址';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info                      = $request->get('user_info');//接收中间件产生的参数
        $input                          =$request->all();

        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $name               =$request->input('name');
        $address            =$request->input('address');
        $open_time          =$request->input('open_time');
        $view               =$request->input('view');
        $picture            =$request->input('picture');
        $lat                =$request->input('lat');
        $lnt                =$request->input('lnt');


        /*** 虚拟数据
        //        $input['self_id']         =$self_id='good_202007011336328472133661';
        //        $input['brand']           =$brand='good_202007011336328472133661';
         **/
        $rules=[
            'address'=>'required',
        ];
        $message=[
            'address.required'=>'请填写充电桩地址',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $wheres['self_id'] = $self_id;
            $old_info=ChargeAddress::where($wheres)->first();

            if($old_info){
                $data['update_time']=$now_time;
                $data['address']    =$address;
                $data['lat']        =$lat;
                $data['lnt']        =$lnt;
                $data['name']       =$name;
                $data['open_time']  =$open_time;
                $data['picture']    =img_for($picture,'more');
                $data['view']       =$view;
                $id=ChargeAddress::where($wheres)->update($data);

                $operationing->access_cause='修改充电桩地址';
                $operationing->operation_type='update';

            }else{
                $data['self_id']            =generate_id('charge_');
                $data['name']               =$name;
                $data['address']            =$address;
                $data['open_time']          =$open_time;
                $data['view']               =$view;
                $data['picture']            =img_for($picture,'in');
                $data['lat']                =$lat;
                $data['lnt']                =$lnt;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=ChargeAddress::insert($data);
                $operationing->access_cause='新建充电桩地址';
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
     * 充电桩地址列表 /tms/platform/chargeAddressList
     * */
    public function chargeAddressList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='车辆品牌';
        $data['import_info']    =[

        ];

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 充电桩地址列表 /tms/platform/chargeAddressPage
     * */
    public function chargeAddressPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $name           =$request->input('name');
        $address        =$request->input('address');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'like','name'=>'name','value'=>$name],
            ['type'=>'like','name'=>'address','value'=>$address],
        ];

        $where=get_list_where($search);

        $select=['self_id','name','address','open_time','view','picture','lat','lnt','create_time'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=ChargeAddress::where($where)->count(); //总的数据量
                $data['items']=ChargeAddress::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=ChargeAddress::where($where)->count(); //总的数据量
                $data['items']=ChargeAddress::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=ChargeAddress::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=ChargeAddress::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        foreach ($data['items'] as $k=>$v) {
                 $v->picture = img_for($v->picture,'more');
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 删除充电桩地址  /tms/platform/delChargeAddress
     * */
    public function delChargeAddress(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='charge_address';
        $medol_name='ChargeAddress';
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

    /**
     * 添加分类
     * */
    public function addType(Request $request){

    }











































}
