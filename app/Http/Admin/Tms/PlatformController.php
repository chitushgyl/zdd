<?php
namespace App\Http\Admin\tms;



use App\Http\Controllers\CommonController;
use App\Http\Controllers\DetailsController as Details;
use App\Http\Controllers\FileController as File;
use App\Models\Tms\AppCar;
use App\Models\Tms\AppCarousel;
use App\Models\Tms\CarBrand;
use App\Models\Tms\ChargeAddress;
use App\Models\Tms\InfiniteType;
use App\Models\Tms\TmsCarType;
use App\Models\Tms\TmsConnact;
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
                $v->picture = img_for($v->picture,'more');
                $v->button_info = $button_info;
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 删除轮播图
     * */
    public function delCarousel(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='app_carousel';
        $medol_name='AppCarousel';
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
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
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
             $v->button_info = $button_info;
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 删除品牌
     * */
    public function delBrand(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='car_brand';
        $medol_name='CarBrand';
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
     * 车辆列表头部
     * */
    public function carList(Request $request){
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

    /**
     *车辆列表
     * */
    public function carPage(Request $request){
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

        $select=['self_id','brand','type','car_type','price','view','car_name','picture','delete_flag','use_flag'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=AppCar::where($where)->count(); //总的数据量
                $data['items']=AppCar::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=AppCar::where($where)->count(); //总的数据量
                $data['items']=AppCar::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=AppCar::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=AppCar::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        foreach ($data['items'] as $k=>$v) {
                $v->button_info = $button_info;
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
        $input                          =$request->all();

        /** 接收数据*/
        $self_id            =$request->input('self_id');
        $brand              =$request->input('brand');//品牌
        $type               =$request->input('type');//品牌型号
        $car_type           =$request->input('car_type');//车型
        $price              =$request->input('price');//价格
        $view               =$request->input('view');//详情
        $car_name           =$request->input('car_name');//车名
        $picture            =$request->input('picture');//图片
        $param              =$request->input('param');//配置参数


        /*** 虚拟数据
        $input['self_id']                   =$self_id='good_202007011336328472133661';
        $input['group_code']                =$group_code='1234';
        $input['brand']                     =$brand='64';
        $input['car_type']                  =$type='沪A45612';
        $input['type']                      =$type='64';
        $input['price']                     =$price='GIUIUIT';
        $input['view']                      =$view='GIUIUIT';
        $input['car_name']                  =$car_name='GIUIUIT';
        $input['picture']                   =$picture='GIUIUIT';
        $input['param']                     =$param='GIUIUIT';
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

            $data['type']           =$type;
            $data['brand']          =$brand;
            $data['car_type']       =$car_type;
            $data['price']          =$price;
            $data['view']           =$view;
            $data['car_name']       =$car_name;
            $data['picture']        =img_for($picture,'in');
            $data['param']          =json_encode($param,JSON_UNESCAPED_UNICODE);

            //dump($data);

            $wheres['self_id'] = $self_id;
            $old_info=AppCar::where($wheres)->first();

            if($old_info){
                $data['update_time']=$now_time;
                $id=AppCar::where($wheres)->update($data);

                $operationing->access_cause='添加车辆';
                $operationing->operation_type='update';

            }else{
                $data['self_id']            = generate_id('car_');
                $data['group_code']         = $user_info->group_code;
                $data['group_name']         = $user_info->group_name;
                $data['create_user_id']     = $user_info->admin_id;
                $data['create_user_name']   = $user_info->name;
                $data['create_time']        = $data['update_time']=$now_time;

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
     * 车辆启用/禁用
     * */
    public function carUseFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='app_car';
        $medol_name='AppCar';
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
                $data['picture']    =img_for($picture,'in');
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
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
        ];

        $where=get_list_where($search);

        $select=['self_id','name','address','open_time','view','picture','lat','lnt','create_time','use_flag','delete_flag'];
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
            $v->button_info = $button_info;
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 删除充电桩地址  /tms/platform/delChargeAddress
     * */
    public function delChargeAddress(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='charge_address';
        $self_id=$request->input('self_id');
        $flag='delete_flag';
//        $self_id = 'charge_202209241459564105870102';
        $old_info = ChargeAddress::where('self_id',$self_id)->select('self_id','address','use_flag','delete_flag','update_time')->first();
        $update['delete_flag'] = 'N';
        $update['update_time'] = $now_time;
        $id = ChargeAddress::where('self_id',$self_id)->update($update);

        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$old_info;
        $operationing->new_info=(object)$update;
        $operationing->operation_type=$flag;
        if($id){
            $msg['code']=200;
            $msg['msg']='删除成功！';
            $msg['data']=(object)$update;
        }else{
            $msg['code']=300;
            $msg['msg']='删除失败！';
        }

        return $msg;
    }

    /**
     * /tms/platform/useChargeAddress
     * */
    public function useChargeAddress(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='charge_address';
        $medol_name='ChargeAddress';
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

    /**
     * 分类列表
     * */
    public function typePage(Request $request){
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
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
        ];

        $where=get_list_where($search);

        $select=['self_id','name','pid','level','use_flag','sort','normal_flag','delete_flag','create_time','update_time'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=InfiniteType::where($where)->count(); //总的数据量
                $data['items']=InfiniteType::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('sort', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=InfiniteType::where($where)->count(); //总的数据量
                $data['items']=InfiniteType::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('sort', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=InfiniteType::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=InfiniteType::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('sort', 'desc')
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
     * 添加分类  /tms/platform/addType
     * */
    public function addType(Request $request){
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
        $self_id            = $request->input('self_id');
        $name               = $request->input('name');
        $pid                = $request->input('pid');
        $level              = $request->input('level');
        $sort               = $request->input('sort');
        $normal_flag        = $request->input('normal_flag');


        /*** 虚拟数据
             $input['self_id']        = $self_id ='';
             $input['name']           = $name    ='外形尺寸';
             $input['pid']            = $pid     ='1';
             $input['level']          = $level   ='1';
             $input['sort']           = $sort    ='1';
             $input['normal_flag']    = $normal_flag    ='4865x1715x2060';
         **/
        $rules=[
            'name'=>'required',
            'pid'=>'required',
        ];
        $message=[
            'name.required'=>'名称不能为空',
            'pid.required'=>'分类不能为空',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $wheres['self_id'] = $self_id;
            $old_info=InfiniteType::where($wheres)->first();

            if($old_info){
                $data['update_time']= $now_time;
                $data['name']       = $name;
                $data['pid']        = $pid;
                $data['level']      = $level;
                $data['sort']       = $sort;
                $data['normal_flag']        =$normal_flag;

                $id=InfiniteType::where($wheres)->update($data);

                $operationing->access_cause='修改充电桩地址';
                $operationing->operation_type='update';

            }else{
                $data['self_id']            =generate_id('type_');
                $data['name']               =$name;
                $data['pid']                =$pid;
                $data['level']              =$level;
                $data['sort']               =$sort;
                $data['normal_flag']        =$normal_flag;
                $data['create_time']        =$data['update_time']=$now_time;

                $id=InfiniteType::insert($data);
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
     * 获取分类数据
     * */
    public function getType(Request $request){
//        $result = InfiniteType::with('allChildren')->orderBy('sort','asc')->get();
//        dd($result->toArray());

        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];
        $result = InfiniteType::where($where)->orderBy('sort','asc')->get()->toArray();
        $res = list_to_tree($result);
//        dd($res);

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$res;
        return $msg;
    }

    /**
     * 贷款申请列表
     * */
    public function loanList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $data['user_info']    =$request->get('user_info');
        $abc='贷款申请列表';


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    public function loanPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $connact        =$request->input('connact');
        $type           =$request->input('type');
        $place_num      =$request->input('place_num');
        $name           =$request->input('name');
        $pass           =$request->input('pass');
        $start_time     =$request->input('start_time');
        $end_time       =$request->input('end_time');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'channel_way','value'=>$place_num],
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'connact','value'=>$connact],
            ['type'=>'=','name'=>'name','value'=>$name],
            ['type'=>'=','name'=>'pass','value'=>$pass],
            ['type'=>'>','name'=>'create_time','value'=>$start_time],
            ['type'=>'<=','name'=>'create_time','value'=>$end_time],
        ];

        $where=get_list_where($search);

        $select=['self_id','name','connact','type','company_name','address','read_flag','delete_flag','group_code','channel_way','identity','id_front','id_back','auth_serch','pass','first_trail','create_time','update_time','hold_img','auth_serch_company'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsConnact::where($where)->count(); //总的数据量
                $data['items']=TmsConnact::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('pass', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsConnact::where($where)->count(); //总的数据量
                $data['items']=TmsConnact::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('pass', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsConnact::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsConnact::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('pass', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        $button_info1 = [];
        $button_info2 = [];
        $button_info3 = [];
        $button_info4 = [];
        $button_info5 = [];
        $button_info6 = [];
        $button_info7 = [];
        $button_info8 = [];
        foreach ($button_info as $k => $v){
            if($v->id == 788){
                $button_info1[] = $v;
                $button_info2[] = $v;
                $button_info3[] = $v;
                $button_info4[] = $v;
                $button_info5[] = $v;


            }
            if($v->id == 789){
                $button_info2[] = $v;
            }
            if($v->id == 790){
                $button_info2[] = $v;
            }
            if($v->id == 811){
                $button_info3[] = $v;

            }
            if($v->id == 792){

            }
            if($v->id == 793){
                $button_info1[] = $v;
                $button_info2[] = $v;
                $button_info3[] = $v;
                $button_info4[] = $v;
            }

        }

//        dump($button_info1,$button_info2,$button_info3,$button_info4);
        foreach ($data['items'] as $k=>$v) {
            $v->id_front   = img_for($v->id_front,'no_json');
            $v->id_back    = img_for($v->id_back,'no_json');
            $v->auth_serch = img_for($v->auth_serch,'no_json');
            $v->button_info=$button_info;
            if ($v->first_trail == 'Y' && $v->pass == 'W'){
                $v->button_info=$button_info2;
            }elseif($v->first_trail == 'Y' && $v->pass == 'Y'){
                $v->button_info=$button_info3;
            }elseif($v->first_trail == 'Y' && $v->pass == 'N'){
                $v->button_info=$button_info3;
            }elseif($v->first_trail == 'N' && $v->pass == 'W'){
                $v->button_info=$button_info4;
            }elseif($v->first_trail == 'W' && $v->pass == 'W'){
                $v->button_info=$button_info1;
            }
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }
    /**
     * 贷款申请详情
     * */
    public function loanDetails(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_connact';
        $select = ['self_id','name','connact','type','fail_reason','company_name','address','read_flag','delete_flag','group_code','channel_way','identity','id_front','id_back','auth_serch','auth_serch_company','hold_img','first_trail','pass','total_user_id','create_time','update_time'];
        // $self_id = 'car_202101111749191839630920';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->id_front   = img_for($info->id_front,'no_json');
            $info->id_back    = img_for($info->id_back,'no_json');
            $info->auth_serch = img_for($info->auth_serch,'no_json');
            $info->auth_serch_company = img_for($info->auth_serch_company,'no_json');
            $info->hold_img = img_for($info->hold_img,'no_json');

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

    /**
     * 删除贷款申请
     * */
    public function delLoan(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_connact';
        $self_id=$request->input('self_id');
        $flag='delete_flag';
//        $self_id = 'charge_202209241459564105870102';
        $old_info = TmsConnact::where('self_id',$self_id)->select('self_id','use_flag','delete_flag','update_time')->first();
        $update['delete_flag'] = 'N';
        $update['update_time'] = $now_time;
        $id = TmsConnact::where('self_id',$self_id)->update($update);

        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$old_info;
        $operationing->new_info=(object)$update;
        $operationing->operation_type=$flag;
        if($id){
            $msg['code']=200;
            $msg['msg']='删除成功！';
            $msg['data']=(object)$update;
        }else{
            $msg['code']=300;
            $msg['msg']='删除失败！';
        }

        return $msg;

    }

    /**
     * 贷款申请审核  /tms/platform/loanPass
     * */
    public function loanPass(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $user_info          = $request->get('user_info');//接收中间件产生的参数
        $table_name='tms_connact';
        $input              =$request->all();
        $self_id=$request->input('self_id'); //数据ID
        $type = $request->input('type');//操作类别:pass 通过  fail失败
        $trail_type = $request->input('trail_type');// 预审 Y  or  E
        $reason = $request->input('reason');
        $rules=[
            'self_id'=>'required',
        ];
        $message=[
            'self_id.required'=>'请选择要操作的数据',
        ];
//        $input['self_id'] = $self_id = 'atte_202104221343175828707184';
//        $input['type'] =  $type = 'pass';
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $select = ['self_id','name','connact','type','company_name','address','read_flag','delete_flag','group_code','channel_way','identity','id_front','id_back','auth_serch','auth_serch_company','hold_img','first_trail','pass'];
            $info = TmsConnact::where('self_id',$self_id)->select($select)->first();
            $old_info = [
                'state'=>$info->state,
                'update_time'=>$now_time
            ];
            switch($type){
                case 'pass':
                    $new_info['update_time'] = $now_time;
                    if ($trail_type == 'Y'){
                        $new_info['first_trail'] = 'Y';
                    }else{
                        $new_info['pass'] = 'Y';
                    }
                    $id = TmsConnact::where('self_id',$self_id)->update($new_info);

                    break;
                case 'fail':
                    $new_info['update_time'] = $now_time;
                    if ($trail_type == 'Y'){
                        $new_info['first_trail'] = 'N';
                    }else{
                        $new_info['pass'] = 'N';
                    }
                    $new_info['fail_reason'] = $reason;
                    $id = TmsConnact::where('self_id',$self_id)->update($new_info);
                    break;

            }
            $operationing->access_cause='通过/失败';
            $operationing->table=$table_name;
            $operationing->table_id=$self_id;
            $operationing->now_time=$now_time;
            $operationing->old_info=$old_info;
            $operationing->new_info=$new_info;
            if ($id){
                $msg['code']=200;
                $msg['msg']='操作成功！';
                $msg['data']=$new_info;
                return $msg;
            }else{
                $msg['code']=303;
                $msg['msg']='操作失败！';
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
     * 修改贷款审核
     * */

    /**
     * 车辆详情
     * */
    public function carView(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'app_car';
        $select = ['self_id','type','brand','car_type','create_time','update_time','price','view','car_name','picture','param'];
        // $self_id = 'car_202101111749191839630920';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->picture = img_for($info->picture,'more');
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

    /**
     * 贷款导出
     * */
    public function loanExcel(Request $request,File $file){
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $now_time   =date('Y-m-d H:i:s',time());
        $input      =$request->all();
        /** 接收数据*/
        $id_list     =$request->input('id_list');
        $rules=[
            'id_list'=>'required',
        ];
        $message=[
            'id_list.required'=>'请选择要导出的数据',
        ];
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()){
            /** 下面开始执行导出逻辑**/

            //查询条件
            $search=[
                ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ];
            $where=get_list_where($search);

            $select=['self_id','name','connact','type','company_name','address','read_flag','delete_flag','group_code','channel_way','identity','id_front','id_back','auth_serch','auth_serch_company','hold_img','first_trail','pass'];
            $info=TmsConnact::where($where)->whereIn(explode(',',$id_list))->orderBy('create_time', 'desc')->select($select)->get();
            dd($info);
            if($info){
                //设置表头
                $row = [[
                    "id"=>'ID',
                    "company_name"=>'姓名',
                    "external_sku_id"=>'商品编号',
                    "good_name"=>'商品名称',
                    "good_english_name"=>'商品英文名称',
                    "wms_unit"=>'入库单位',
                    "good_zhuanhua"=>'商品包装换算',
                    "period"=>'商品有效期',
                    "use_flag"=>'状态',
                    "wms_length"=>'箱长（米）',
                    "wms_wide"=>'箱长（米）',
                    "wms_high"=>'箱长（米）',
                    "wms_weight"=>'箱重（KG）',
                ]];

                /** 现在根据查询到的数据去做一个导出的数据**/
                $data_execl=[];
                foreach ($info as $k=>$v){
                    $list=[];

                    $list['id']=($k+1);
                    $list['company_name']=$v->company_name;
                    $list['good_english_name']=$v->good_english_name;
                    $list['external_sku_id']=$v->external_sku_id;
                    $list['good_name']=$v->good_name;
                    $list['wms_unit']=$v->wms_unit;

                    if($v->wms_scale && $v->wms_target_unit){
                        $list['good_zhuanhua']='1'.$v->wms_target_unit.'='.$v->wms_scale.$v->wms_unit;

                    }else{
                        $list['good_zhuanhua']=null;
                    }


                    if($v->use_flag == 'Y'){
                        $list['use_flag']='使用中';
                    }else{
                        $list['use_flag']='禁止使用';
                    }

                    $list['wms_length']=$v->wms_length;
                    $list['wms_wide']=$v->wms_wide;
                    $list['wms_high']=$v->wms_high;
                    $list['wms_weight']=$v->wms_weight;

                    $data_execl[]=$list;
                }
                /** 调用EXECL导出公用方法，将数据抛出来***/
                $browse_type=$request->path();
                $msg=$file->export($data_execl,$row,$user_info->group_code,$user_info->group_name,$browse_type,$user_info,$where,$now_time);

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
