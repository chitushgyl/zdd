<?php
namespace App\Http\Api\Pay;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\Tms\TmsPayment;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use EasyWeChat\Foundation\Application;


class AlipayController extends Controller{

    /** H5支付宝支付**/
    public function alipay(Request $request){
        $out_trade_no=$request->input('order_id');
        $price=$request->input('price');

//        require base_path('vendor/Alipay/aop/AopClient.php');
        include_once base_path('/vendor/alipay/wappay/service/AlipayTradeService.php');
        include_once base_path('/vendor/alipay/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php');

        $subject = '订单在线支付'; //订单名称，必填
        $total_amount = $price; //付款金额，必填
        $body = '测试支付';//商品描述，可空
        $timeExpress = '10m';
        $notify_url = 'http://api.56cold.com/alipay/notify';//异步通知地址
        $return_url = "http://front.56cold.com/tms/pay/pay_success?order_id=".$out_trade_no."&from=2";//同步跳转
        //构造参数
        $payRequestBuilder = new \AlipayTradeWapPayContentBuilder;
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
//        $payRequestBuilder->setPassBack_params(3);
        $payRequestBuilder->setTimeExpress($timeExpress);
        $config = config('tms.alipay_config');//引入配置文件参数
        $aop = new \AlipayTradeService($config);
        /**
         * pagePay 电脑网站支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
         */
        $response = $aop->wapPay($payRequestBuilder, $return_url, $notify_url);
        //输出表单
        return $response;
        // echo json_encode($response);
    }


    /** 支付宝支付回调**/
    public function notify(){
        require base_path('vendor/Alipay/wappay/service/AlipayTradeService.php');
        $arr=$_POST;

        $alipaySevice = new \AlipayTradeService(config('tms.alipay_config'));
        $alipaySevice->writeLog(var_export($_POST,true));
        $result = $alipaySevice->check($arr);
        $data = array();
        if ($result){
            if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                $data['order_sn'] = $_POST['out_trade_no'];
                $data['platformorderid'] = $_POST['trade_no'];
                $data['money'] = $_POST['total_amount'] * 100;
                $data['create_time'] = date('Y-m-d H:i:s', time());
                $data['user_id'] = $_POST['passback_params'];
                $data['payname'] = $_POST['buyer_logon_id'];
                $data['paytype'] = 'Alipay';
                $data['paystate'] = '1';
                $data['produce_type'] = 'IN';
                $data['produce_cause'] = '余额充值';
                $data['self_id'] = generate_id('wallet_');
                $order = TmsOrder::where('self_id',$_POST['out_trade_no'])->select(['order_status'])->first();

                if ($order->order_status == 2){
                     echo 'success';
                     return false;
                }
                $order_update['order_status'] = 2;
                $order_update['update_time'] = date('Y-m-d H:i:s',time());
                TmsOrder::where('self_id',$_POST['out_trade_no'])->update($order_update);
                return 'success';
            }
        }else{
            echo "fail";
        }


    }
/*
 * 选","app_id":"wxdc3dff8cfd3db5cb","secret":"39f8bfcfd099af3ede363af5355d7d6d",
 * "pay_app_id":"wxdc3dff8cfd3db5cb","mch_id":"1519218071","key":"89c383df1bd197f2d398b7a0f07db030"}
 * */

    /*** wxe83015a059e35239  1481595522  FdzK0xScm6GRS0zUW4LRYOak5rZA9k3o  */
    /**
     * H5微信支付  /alipay/wechat
     * */
    public function wechat(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();
        $out_trade_no=$request->input('order_id');
//        $out_trade_no= '123456489789';
        $price=$request->input('price');
//        $price= 100;
//        dd($user_info);
        include_once base_path( '/vendor/wxAppPay/weixin.php');
//        $user_info->token_id = 'o5e6c5lZuMPaV7cFktZlHsZaHTO0';
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        $noturl = 'http://api.56cold.com/alipay/wxpaynotify';
        $appid = 'wxdc3dff8cfd3db5cb';
        $mch_id = '1519218071';
        $notify_url = $noturl;
        $key = '89c383df1bd197f2d398b7a0f07db030';
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify_url,$key);
        $params['openid'] = $user_info->token_id;                    //用户唯一标识
        $params['body'] = '订单支付';                       //商品描述
        $params['out_trade_no'] = $out_trade_no;              //自定义的订单号
        $params['total_fee'] = $price*100;                    //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'JSAPI';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                        //附加参数（用户ID）
        $result = $wechatAppPay->jsapi($params);
//        print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id

        //2.创建公众号端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getPayParams($result);
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    public function wxpaynotify(){
        ini_set('date.timezone','Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $array_data['out_trade_no'];//订单号
            $pay['pay_number'] = $array_data['total_fee'];//价格
            $pay['platformorderid'] = $array_data['transaction_id'];//微信交易号
            $pay['create_time']  = $pay['update_time'] = $now_time;
            $pay['payname'] = $array_data['openid'];//微信账号
            $pay['paytype'] = 'WECHAT';//微信账号
            $pay['pay_result'] = 'SU';//微信账号
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');//微信账号
            $order = TmsOrder::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type'])->first();

            if ($order->order_status == 2){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);
            if ($order->order_type == 'line'){
                $order_update['order_status'] = 3;
            }else{
                $order_update['order_status'] = 2;
            }
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$array_data['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $moneys['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';

            $tmsOrderCost = TmsOrderCost::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->where('shouk_type','PLATFORM')->update($money);
                TmsOrderCost::whereIn('self_id',$money_list)->where('fk_type','PLATFORM')->update($moneys);
            }

            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }

        } else {
            echo "fail";
        }
    }

    /**
     * 货到付款微信支付  /alipay/paymentWechat
     * */
    public function paymentWechat(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
        //$user_id = 1;
        $data['price'] = $price = 0.01;
        $data['ordernumber'] = $self_id;
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        $out_trade_no = $data['ordernumber'];
        $noturl = 'http://api.56cold.com/alipay/paymentWechatNotify';
        $appid = 'wxe2d6b74ba8fa43e7';
        $mch_id = '1481595522';
        $notify_url = $noturl;
        $key = 'FdzK0xScm6GRS0zUW4LRYOak5rZA9k3o';
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify_url,$key);
        $params['body'] = '订单支付';                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->unifiedOrder($params);
        // print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
        // exit();
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
        return json_encode($data);
    }

    /**
     * 货到付款微信支付回调
     * */
    public function paymentWechatNotify(){
        ini_set('date.timezone','Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $array_data['out_trade_no'];//订单号
            $pay['pay_number'] = $array_data['total_fee'];//价格
            $pay['platformorderid'] = $array_data['transaction_id'];//微信交易号
            $pay['create_time']  = $pay['update_time'] = $now_time;
            $pay['payname'] = $array_data['openid'];//微信账号
            $pay['paytype'] = 'WECHAT';//微信账号
            $pay['pay_result'] = 'SU';//微信账号
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');//微信账号
            $order = TmsOrder::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','pay_state'])->first();

            if ($order->pay_state == 'Y'){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);

//            $order_update['order_status'] = 6;
            $order_update['pay_state'] = 'Y';

            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$array_data['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }


            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }
        }else{
            echo 'fail';
        }
    }

    /**
     * APP微信支付
     * */
    public function appWechat(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
        //$user_id = 1;
        $data['price'] = $price = 0.01;
        $data['ordernumber'] = $self_id;
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        $out_trade_no = $data['ordernumber'];
        $noturl = 'http://api.56cold.com/alipay/appWechat_notify';
        $appid = 'wxe2d6b74ba8fa43e7';
        $mch_id = '1481595522';
        $notify_url = $noturl;
        $key = 'FdzK0xScm6GRS0zUW4LRYOak5rZA9k3o';
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify_url,$key);
        $params['body'] = '订单支付';                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->unifiedOrder($params);
        // print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
        // exit();
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
        return json_encode($data);
    }

    /**
     * APP微信支付回调
     * */
    public function appWechat_notify(){
        ini_set('date.timezone','Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $array_data['out_trade_no'];//订单号
            $pay['pay_number'] = $array_data['total_fee'];//价格
            $pay['platformorderid'] = $array_data['transaction_id'];//微信交易号
            $pay['create_time']  = $pay['update_time'] = $now_time;
            $pay['payname'] = $array_data['openid'];//微信账号
            $pay['paytype'] = 'WECHAT';//微信账号
            $pay['pay_result'] = 'SU';//微信账号
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');//微信账号
            $order = TmsOrder::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type'])->first();

            if ($order->order_status == 2){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);
            if ($order->order_type == 'line'){
                $order_update['order_status'] = 3;
            }else{
                $order_update['order_status'] = 2;
            }
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$array_data['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }

            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }
        }else{
            echo 'fail';
        }
    }

    /**
     * APP支付宝支付
     * */
    public function appAlipay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        // 订单ID
        $self_id = $request->input('self_id');
        // 支付金额
        $price = $request->input('price');
        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $self_id = 'order_202103090937308279552773';
        * */
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }

        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();
        $request = new \AlipayTradeAppPayRequest();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2017052307318743";
        $aop->rsaPrivateKey = 'MIIEpAIBAAKCAQEAuWqafyecwj1VxcHQjFHrPIqhKrfMPjQRVRTs7/PvGlCXOxV34KaAop4XWEBKgvWhdQX2JkMDLSwPkH790TBJVS84/zQ6sjanpHjgT82/AimuS+/Vk8pB/pAfnOnRN3dhe6y2i9kzJPU62Uj9qn5jJXbWJhyM16Zxdk7GBOChis3C3KvB2WN8qAQawqfUvgHRm/yUgNfVUutKRMdDdQxQypwxkEP50+U9qKeSQecZRyo6xmJ5CWbULQ7FpV5q6lmM7SbyBuyDVk7z4itLIgE8qpt6B3cp9Qm3U3f6DoVJA2LAjinP4v6kNVb/f5qu8VpmR0DD+dRJ1+ujDz1EC/f/lwIDAQABAoIBAHrS0DcM8X2GDcxrQA/DsDUxi+N1T1mhOh4HN5EYILpoylU8OmXZRfrzCHnQVMt9lQ+k/FKKL4970W+hf9dTyjAgkPwVCBDHvbNo0wZqP25aV/g7jlpRL/hGVnqmNI4uiafYWDA5l/SScgI/pLGM+XZ2yxMB9JZhzmVVdz0B5GDCHcjQUkY3//8Tpgw6ylngrq67KjWDbZPAZQHcpj/hdYPOu7Z1kXp30jtdEZi6S+7ZJe/AWMSuEtwWsM53ZOyxqPjSwbW8XfWHHbG3yKF6sngCmwRpwX5rp1EjSsVhA5rbpCM0jbYCKp977XwkGtG6xAOydZdz0WHyirDUTA3PMTECgYEA4lzvyfcg0SyaOWVszwxcWntVm6sQG7deaSlW92Urhy7qaDnv4Ad8TEe0M0QGVllnZUDJA3x8NzoD5DlFROUGZpI/uJk5a0dQlvMbyzS2rx2v4TP19Xm5D7iQk0RK5Zry0K/Fj1kZusIVm3qwsl1DlunAfGipZ1TV0C7QNUJcW0kCgYEA0bE/3ljnSPsKjpc+projOuaLqf7+0x3ITaYle60MbwZrjUnX3cSwbqN3Iu12Npa3mI+RwTyDifFgWB/8hFoqTecFGDnxRa1e7DLlJX9FkIMtoroVsDJUMD+HUx01t9V8fEqVPNyRmnbFyXfdHrRb7zYefwuPZcoE18reADc9o98CgYB1zDl5F+L7F8P2ZIK4SM1yxMYrKV1LnyRBg6LfQcXiJpcTwDrFkf+sTpBHMXo+y23UMl+pMcoOj2FhDjCvBqRLEoaYkRxhaI5Wz5LCL991x/Q0NO8lXL/in4CVMq/rRrRfx2j/DTYni0LlU3bKi2BWE7T4yRqHTI2sNgBiBvO7CQKBgQCDsHNR6jdmR/J7VlTMVH2nkf4IRtI2N7ABw+QqZaU3XKrS0ps09T9wXEyHrOXepoyqzQ9WcfCSAvrknUHyxMVoozs52bnCbnz8jYIHKITBmwBf/8l7HEBvBJayBdgkmXhSfmx3CnaOsSTJv/MoQ1CxTCWe1924qUSdWRROwmJ9tQKBgQCgWUnO0z1O46N1p66gcA0NrRMFsncotg42MipvUpCrMN6lJ80/H7Kj1tGOizJazLXPKN9NKl/lco0xJyAyZS4vFacZXbH2OO0jHyfovPblSY5O10g3d1PC4mbZ/wd4HU4QVO21+U5dIH/HPubhOGQWcpAO+3Fqxx7VFuaZPbsC7g==';
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";

        //运单支付
        $subject = '订单支付';

        $notifyurl = "api.56cold.com/alipay/appAlipay_notify";
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $bizcontent = json_encode([
            'body' => '支付宝支付',
            'subject' => $subject,
            'out_trade_no' => $self_id,//此订单号为商户唯一订单号
            'total_amount' => $price,//保留两位小数
            'product_code' => 'QUICK_MSECURITY_PAY',
            'passback_params' => $user_id
        ]);
        $request->setNotifyUrl($notifyurl);
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        return $response;
    }

    /**
     * 货到付款（在线支付）  /alipay/paymentAlipay
     * */
    public function paymentAlipay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        // 订单ID
        $self_id = $request->input('self_id');
        // 支付金额
        $price = $request->input('price');
        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $self_id = 'order_202105121125005375186318';
         * */
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }

        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();
        $request = new \AlipayTradeAppPayRequest();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2017052307318743";
        $aop->rsaPrivateKey = 'MIIEpAIBAAKCAQEAuWqafyecwj1VxcHQjFHrPIqhKrfMPjQRVRTs7/PvGlCXOxV34KaAop4XWEBKgvWhdQX2JkMDLSwPkH790TBJVS84/zQ6sjanpHjgT82/AimuS+/Vk8pB/pAfnOnRN3dhe6y2i9kzJPU62Uj9qn5jJXbWJhyM16Zxdk7GBOChis3C3KvB2WN8qAQawqfUvgHRm/yUgNfVUutKRMdDdQxQypwxkEP50+U9qKeSQecZRyo6xmJ5CWbULQ7FpV5q6lmM7SbyBuyDVk7z4itLIgE8qpt6B3cp9Qm3U3f6DoVJA2LAjinP4v6kNVb/f5qu8VpmR0DD+dRJ1+ujDz1EC/f/lwIDAQABAoIBAHrS0DcM8X2GDcxrQA/DsDUxi+N1T1mhOh4HN5EYILpoylU8OmXZRfrzCHnQVMt9lQ+k/FKKL4970W+hf9dTyjAgkPwVCBDHvbNo0wZqP25aV/g7jlpRL/hGVnqmNI4uiafYWDA5l/SScgI/pLGM+XZ2yxMB9JZhzmVVdz0B5GDCHcjQUkY3//8Tpgw6ylngrq67KjWDbZPAZQHcpj/hdYPOu7Z1kXp30jtdEZi6S+7ZJe/AWMSuEtwWsM53ZOyxqPjSwbW8XfWHHbG3yKF6sngCmwRpwX5rp1EjSsVhA5rbpCM0jbYCKp977XwkGtG6xAOydZdz0WHyirDUTA3PMTECgYEA4lzvyfcg0SyaOWVszwxcWntVm6sQG7deaSlW92Urhy7qaDnv4Ad8TEe0M0QGVllnZUDJA3x8NzoD5DlFROUGZpI/uJk5a0dQlvMbyzS2rx2v4TP19Xm5D7iQk0RK5Zry0K/Fj1kZusIVm3qwsl1DlunAfGipZ1TV0C7QNUJcW0kCgYEA0bE/3ljnSPsKjpc+projOuaLqf7+0x3ITaYle60MbwZrjUnX3cSwbqN3Iu12Npa3mI+RwTyDifFgWB/8hFoqTecFGDnxRa1e7DLlJX9FkIMtoroVsDJUMD+HUx01t9V8fEqVPNyRmnbFyXfdHrRb7zYefwuPZcoE18reADc9o98CgYB1zDl5F+L7F8P2ZIK4SM1yxMYrKV1LnyRBg6LfQcXiJpcTwDrFkf+sTpBHMXo+y23UMl+pMcoOj2FhDjCvBqRLEoaYkRxhaI5Wz5LCL991x/Q0NO8lXL/in4CVMq/rRrRfx2j/DTYni0LlU3bKi2BWE7T4yRqHTI2sNgBiBvO7CQKBgQCDsHNR6jdmR/J7VlTMVH2nkf4IRtI2N7ABw+QqZaU3XKrS0ps09T9wXEyHrOXepoyqzQ9WcfCSAvrknUHyxMVoozs52bnCbnz8jYIHKITBmwBf/8l7HEBvBJayBdgkmXhSfmx3CnaOsSTJv/MoQ1CxTCWe1924qUSdWRROwmJ9tQKBgQCgWUnO0z1O46N1p66gcA0NrRMFsncotg42MipvUpCrMN6lJ80/H7Kj1tGOizJazLXPKN9NKl/lco0xJyAyZS4vFacZXbH2OO0jHyfovPblSY5O10g3d1PC4mbZ/wd4HU4QVO21+U5dIH/HPubhOGQWcpAO+3Fqxx7VFuaZPbsC7g==';
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";

        //运单支付
        $subject = '订单支付';

        $notifyurl = "api.56cold.com/alipay/paymentAlipayNotify";
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $bizcontent = json_encode([
            'body' => '支付宝支付',
            'subject' => $subject,
            'out_trade_no' => $self_id,//此订单号为商户唯一订单号
            'total_amount' => $price,//保留两位小数
            'product_code' => 'QUICK_MSECURITY_PAY',
            'passback_params' => $user_id
        ]);
        $request->setNotifyUrl($notifyurl);
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        return $response;
    }

    /**
     * 货到付款支付支付宝支付回调
     * */
    public function paymentAlipayNotify(){
        include_once base_path( '/vendor/alipay/aop/AopClient.php');

        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $_POST['out_trade_no'];
            $pay['pay_number'] = $_POST['total_amount'] * 100;
            $pay['platformorderid'] = $_POST['trade_no'];
            $pay['create_time'] = $pay['update_time'] = $now_time;
            $pay['payname'] = $_POST['buyer_logon_id'];
            $pay['paytype'] = 'ALIPAY';//
            $pay['pay_result'] = 'SU';//
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');
//            file_put_contents(base_path('/vendor/alipay.txt'),$pay);
            $order = TmsOrder::where('self_id',$_POST['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','pay_state'])->first();
            if ($order->pay_state == 'Y'){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);

//            $order_update['order_status'] = 6;
            $order_update['pay_state'] = 'Y';

            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$_POST['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$_POST['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }

        } else {
            echo 'fail';
        }
    }

    /**
     * APP支付宝支付回调
     * */
    public function appAlipay_notify(){
        include_once base_path( '/vendor/alipay/aop/AopClient.php');

        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $_POST['out_trade_no'];
            $pay['pay_number'] = $_POST['total_amount'] * 100;
            $pay['platformorderid'] = $_POST['trade_no'];
            $pay['create_time'] = $pay['update_time'] = $now_time;
            $pay['payname'] = $_POST['buyer_logon_id'];
            $pay['paytype'] = 'ALIPAY';//
            $pay['pay_result'] = 'SU';//
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');
//            file_put_contents(base_path('/vendor/alipay.txt'),$pay);
            $order = TmsOrder::where('self_id',$_POST['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type'])->first();
            if ($order->order_status == 2){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);
            if ($order->order_type == 'line'){
                $order_update['order_status'] = 3;
            }else{
                $order_update['order_status'] = 2;
            }
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$_POST['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$_POST['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }

            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$_POST['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }

        } else {
            echo 'fail';
        }

    }

    /**
     * 余额支付
     * */
    public function balancePay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        // 订单ID
        $self_id = $request->input('self_id');
        // 支付金额
        $price = $request->input('price');
//        /**虚拟数据
        $price   = 0.01;
        $self_id = 'order_202103121712041799645968';
//         * */
        $now_time = date('Y-m-d H:i:s',time());
        $pay['order_id'] = $self_id;
        $pay['pay_number'] = $price;
        $pay['platformorderid'] = generate_id('');
        $pay['create_time'] = $pay['update_time'] = $now_time;
        $pay['payname'] = $user_info->tel;
        $pay['paytype'] = 'BALANCE';//
        $pay['pay_result'] = 'SU';//
        $pay['state'] = 'in';//支付状态
        $pay['self_id'] = generate_id('pay_');
        $order = TmsOrder::where('self_id',$self_id)->select(['total_user_id','group_code','order_status','group_name','order_type'])->first();
        if ($order->order_status == 2){
            $msg['code'] = 301;
            $msg['msg']  = '该订单已支付';
            return $msg;
        }
        if ($user_info->type){
            $pay['total_user_id'] = $user_info->total_user_id;

            $capital_where = [
                ['total_user_id','=',$user_info->total_user_id],
            ];
        }else{
            $pay['group_code'] = $user_info->group_code;
            $pay['group_name'] = $user_info->group_name;

            $capital_where = [
                ['group_code','=',$user_info->group_code],
            ];
        }
        $userCapital = UserCapital::where($capital_where)->select('self_id','group_code','money')->first();
        if ($userCapital->money < $price){
            $msg['code'] = 302;
            $msg['msg']  = '余额不足';
            return $msg;
        }
        $capital['money'] = $userCapital->money - $price*100;
        $capital['update_time'] = $now_time;
        UserCapital::where($capital_where)->update($capital);

        $wallet['self_id'] = generate_id('wallet_');
        $wallet['produce_type'] = 'out';
        $wallet['capital_type'] = 'wallet';
        $wallet['create_time'] = $now_time;
        $wallet['update_time'] = $now_time;
        $wallet['now_money'] = $capital['money'];
        $wallet['now_money_md'] = get_md5($capital['money']);
        $wallet['wallet_status'] = 'SU';

        UserWallet::insert($wallet);

        TmsPayment::insert($pay);
        if ($order->order_type == 'line'){
            $order_update['order_status'] = 3;
        }else{
            $order_update['order_status'] = 2;
        }
        $order_update['update_time'] = date('Y-m-d H:i:s',time());
        $id = TmsOrder::where('self_id',$self_id)->update($order_update);
        /**修改费用数据为可用**/
        $money['delete_flag']                = 'Y';
        $money['settle_flag']                = 'W';
        $tmsOrderCost = TmsOrderCost::where('order_id',$self_id)->select('self_id')->get();
        if ($tmsOrderCost){
            $money_list = array_column($tmsOrderCost->toArray(),'self_id');
            TmsOrderCost::whereIn('self_id',$money_list)->update($money);
        }

        $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$self_id)->select('self_id')->get();
        if ($tmsOrderDispatch){
            $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
            $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
        }
        if ($id){
            $msg['code'] = 200;
            $msg['msg']  = '支付成功！';
            return $msg;
        }else{
            $msg['code'] = 303;
            $msg['msg']  = '支付失败！';
            return $msg;
        }


    }

    /**
     * TMS3PL公司上线订单 支付宝支付 /alipay/online_alipay
     * */
    public function online_alipay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        // 订单ID
        $self_id = $request->input('self_id');
        // 支付金额
        $price = $request->input('price');
        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $self_id = 'dispatch_202104211039546367931305';
         * */
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }

        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();
        $request = new \AlipayTradeAppPayRequest();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2017052307318743";
        $aop->rsaPrivateKey = 'MIIEpAIBAAKCAQEAuWqafyecwj1VxcHQjFHrPIqhKrfMPjQRVRTs7/PvGlCXOxV34KaAop4XWEBKgvWhdQX2JkMDLSwPkH790TBJVS84/zQ6sjanpHjgT82/AimuS+/Vk8pB/pAfnOnRN3dhe6y2i9kzJPU62Uj9qn5jJXbWJhyM16Zxdk7GBOChis3C3KvB2WN8qAQawqfUvgHRm/yUgNfVUutKRMdDdQxQypwxkEP50+U9qKeSQecZRyo6xmJ5CWbULQ7FpV5q6lmM7SbyBuyDVk7z4itLIgE8qpt6B3cp9Qm3U3f6DoVJA2LAjinP4v6kNVb/f5qu8VpmR0DD+dRJ1+ujDz1EC/f/lwIDAQABAoIBAHrS0DcM8X2GDcxrQA/DsDUxi+N1T1mhOh4HN5EYILpoylU8OmXZRfrzCHnQVMt9lQ+k/FKKL4970W+hf9dTyjAgkPwVCBDHvbNo0wZqP25aV/g7jlpRL/hGVnqmNI4uiafYWDA5l/SScgI/pLGM+XZ2yxMB9JZhzmVVdz0B5GDCHcjQUkY3//8Tpgw6ylngrq67KjWDbZPAZQHcpj/hdYPOu7Z1kXp30jtdEZi6S+7ZJe/AWMSuEtwWsM53ZOyxqPjSwbW8XfWHHbG3yKF6sngCmwRpwX5rp1EjSsVhA5rbpCM0jbYCKp977XwkGtG6xAOydZdz0WHyirDUTA3PMTECgYEA4lzvyfcg0SyaOWVszwxcWntVm6sQG7deaSlW92Urhy7qaDnv4Ad8TEe0M0QGVllnZUDJA3x8NzoD5DlFROUGZpI/uJk5a0dQlvMbyzS2rx2v4TP19Xm5D7iQk0RK5Zry0K/Fj1kZusIVm3qwsl1DlunAfGipZ1TV0C7QNUJcW0kCgYEA0bE/3ljnSPsKjpc+projOuaLqf7+0x3ITaYle60MbwZrjUnX3cSwbqN3Iu12Npa3mI+RwTyDifFgWB/8hFoqTecFGDnxRa1e7DLlJX9FkIMtoroVsDJUMD+HUx01t9V8fEqVPNyRmnbFyXfdHrRb7zYefwuPZcoE18reADc9o98CgYB1zDl5F+L7F8P2ZIK4SM1yxMYrKV1LnyRBg6LfQcXiJpcTwDrFkf+sTpBHMXo+y23UMl+pMcoOj2FhDjCvBqRLEoaYkRxhaI5Wz5LCL991x/Q0NO8lXL/in4CVMq/rRrRfx2j/DTYni0LlU3bKi2BWE7T4yRqHTI2sNgBiBvO7CQKBgQCDsHNR6jdmR/J7VlTMVH2nkf4IRtI2N7ABw+QqZaU3XKrS0ps09T9wXEyHrOXepoyqzQ9WcfCSAvrknUHyxMVoozs52bnCbnz8jYIHKITBmwBf/8l7HEBvBJayBdgkmXhSfmx3CnaOsSTJv/MoQ1CxTCWe1924qUSdWRROwmJ9tQKBgQCgWUnO0z1O46N1p66gcA0NrRMFsncotg42MipvUpCrMN6lJ80/H7Kj1tGOizJazLXPKN9NKl/lco0xJyAyZS4vFacZXbH2OO0jHyfovPblSY5O10g3d1PC4mbZ/wd4HU4QVO21+U5dIH/HPubhOGQWcpAO+3Fqxx7VFuaZPbsC7g==';
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";

        //运单支付
        $subject = '订单支付';

        $notifyurl = "api.56cold.com/alipay/onlineApipay_notity";
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $bizcontent = json_encode([
            'body' => '支付宝支付',
            'subject' => $subject,
            'out_trade_no' => $self_id,//此订单号为商户唯一订单号
            'total_amount' => $price,//保留两位小数
            'product_code' => 'QUICK_MSECURITY_PAY',
            'passback_params' => $user_id
        ]);
        $request->setNotifyUrl($notifyurl);
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        return $response;
    }

    public function onlineApipay_notity(){
        include_once base_path( '/vendor/alipay/aop/AopClient.php');

        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['dispatch_id'] = $_POST['out_trade_no'];
            $pay['pay_number'] = $_POST['total_amount'] * 100;
            $pay['platformorderid'] = $_POST['trade_no'];
            $pay['create_time'] = $pay['update_time'] = $now_time;
            $pay['payname'] = $_POST['buyer_logon_id'];
            $pay['paytype'] = 'ALIPAY';//
            $pay['pay_result'] = 'SU';//
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');
//            file_put_contents(base_path('/vendor/alipay.txt'),$pay);
            $order = TmsOrderDispatch::where('self_id',$_POST['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type'])->first();

            if ($order->total_user_id){
                $pay['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);

            $order_update['order_status'] = 2;
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $order_update['pay_status']  = 'Y';
            $order_update['on_line_flag'] = 'Y';
            $order_update['dispatch_flag'] = 'N';
            $order_update['receiver_id'] = null;
            $order_update['pay_type']    = 'online';
            $order_update['on_line_money'] = $_POST['total_amount'] * 100;
            $id = TmsOrderDispatch::where('self_id',$_POST['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$_POST['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }

//            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$_POST['out_trade_no'])->select('self_id')->get();
//            if ($tmsOrderDispatch){
//                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
//                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
//            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }

        } else {
            echo 'fail';
        }
    }

    /**
     * TMS3PL公司上线订单 微信支付 /alipay/online_wechat
     * */
    public function online_wechat(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
//        $self_id = 'patch_202105071628455839736801';
        $data['price'] = $price = 0.01;

        if ($user_info->type == 'user' || $user_info->type == 'carriage'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
//        dd($user_id);
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        $notify_url = 'http://api.56cold.com/alipay/onlineWechat_notify';
        $appid = 'wx1ed6b733675628df';
        $mch_id = '1487413072';
        $key = '011eaf63e5f7944c6a979de570d44aaa';
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify_url,$key);
        $params['body'] = '订单支付';                       //商品描述
        $params['out_trade_no'] = $self_id;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->unifiedOrder($params);
//         print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
//         exit();
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
        return json_encode($data);
    }

    public function onlineWechat_notify(){
        ini_set('date.timezone','Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['dispatch_id'] = $array_data['out_trade_no'];//订单号
            $pay['pay_number'] = $array_data['total_fee'];//价格
            $pay['platformorderid'] = $array_data['transaction_id'];//微信交易号
            $pay['create_time']  = $pay['update_time'] = $now_time;
            $pay['payname'] = $array_data['openid'];//微信账号
            $pay['paytype'] = 'WECHAT';//微信账号
            $pay['pay_result'] = 'SU';//微信账号
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');//微信账号
            $order = TmsOrderDispatch::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type'])->first();

            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
            }
            TmsPayment::insert($pay);

            $order_update['order_status'] = 2;
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $order_update['pay_status'] = 'Y';
            $order_update['on_line_flag'] = 'Y';
            $order_update['dispatch_flag'] = 'N';
            $order_update['receiver_id'] = null;
            $order_update['pay_type']    = 'online';
            $order_update['on_line_money'] = $array_data['total_fee'] * 100;
            $id = TmsOrderDispatch::where('self_id',$array_data['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }

//            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
//            if ($tmsOrderDispatch){
//                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
//                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
//            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }
        }else{
            echo 'fail';
        }
    }
}
?>
