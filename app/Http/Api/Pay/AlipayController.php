<?php
namespace App\Http\Api\Pay;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsLittleOrder;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\Tms\TmsPayment;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use App\Models\User\UserIdentity;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use EasyWeChat\Foundation\Application;


class AlipayController extends Controller{
    /**
     * 群推送（根据clientid推送）
     * */
    public function send_push_msg($group_name,$title,$content){
        $where = [
            ['type','=','carriage'],
        ];
        $select = ['user_id','clientid','type'];
        $info = UserIdentity::with(['logLogin'=>function($query)use($select) {
            $query->where('type', '!=', 'after');
            $query->select($select);
        }])
            ->where($where)->orWhere('type','TMS3PL')->orWhere('type','business')
            ->select('total_user_id','type')
            ->get();
        $clientid_list = [];
        foreach ($info as $key =>$value){
            if ($value->logLogin){
                foreach ($value->logLogin as $k =>$v){
                    if ($v->clientid != null  && $v->clientid != "null" && $v->clientid != 'undefined' && $v->clientid != 'clientid'){
                        $clientid_list[] = $v->clientid;
                    }
                }
            }
        }
        $cid = array_unique($clientid_list);
        $cid_list = [];
        foreach ($cid as $kk =>$vv){
            array_push($cid_list,$vv);
        }
        include_once base_path( '/vendor/push/GeTui.php');
        $geTui = new \GeTui();
        $result = $geTui->pushToList($group_name,$title,$content,$cid_list);
    }

    /**
     * app支付宝支付
     * */
    public function appAlipay(Request $request){
        $config    = config('tms.alipay_config_user');//引入配置文件参数
        $input     = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $type      = $request->input('type'); // 1  2  3
        $pay_type  = array_column(config('tms.alipay_notify'),'notify','key');
        $self_id   = $request->input('self_id');// 订单ID
        $price     = $request->input('price');// 支付金额
        $price     = 0.01;
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        if($type == 3){
            $payment = TmsPayment::where('dispatch_id',$self_id)->where('dispatch_id','!=','')->select('dispatch_id','pay_result','paytype','state')->first();
            if($payment){
                $msg['code'] = 302;
                $msg['msg']  = '此订单不能重复上线';
                return $msg;
            }
        }
        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();
        $request = new \AlipayTradeAppPayRequest();
        $aop->gatewayUrl = $config['gatewayUrl'];
        $aop->appId = $config['app_id'];
        $aop->rsaPrivateKey = $config['merchant_private_key'];
        $aop->format = $config['format'];
        $aop->charset = $config['charset'];
        $aop->signType = $config['sign_type'];
        //运单支付
        $subject = '订单支付';
//        $notifyurl = "http://api.56cold.com/alipay/appAlipay_notify";
        $notifyurl = $pay_type[$type];
        $aop->alipayrsaPublicKey = $config['alipay_public_key'];
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

    public function testAlipay(Request $request){
        $config    = config('tms.alipay_config');//引入配置文件参数
        $input     = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $type      = $request->input('type'); // 1  2  3
        $pay_type  = array_column(config('tms.alipay_notify'),'notify','key');
        $self_id   = $request->input('self_id');// 订单ID
        $price     = $request->input('price');// 支付金额
//        $price     = 0.01;
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        if($type == 3){
            $payment = TmsPayment::where('dispatch_id',$self_id)->where('dispatch_id','!=','')->select('dispatch_id','pay_result','paytype','state')->first();
            if($payment){
                $msg['code'] = 302;
                $msg['msg']  = '此订单不能重复上线';
                return $msg;
            }
        }
        include_once base_path( '/vendor/alipay/aop/AopCertClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayOpenPublicTemplateMessageIndustryModifyRequest.php');
        $c = new \AopCertClient();
        $appCertPath =  base_path( 'vendor\alipay\cert\appCertPublicKey.crt');
        $alipayCertPath =  base_path( 'vendor\alipay/cert\alipayCertPublicKey.crt');
        $rootCertPath =  base_path( 'vendor\alipay\cert\alipayRootCert.crt');
//dd($appCertPath);
        $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $c->appId = $config['app_id'];
        $c->rsaPrivateKey = $config['merchant_private_key'];
        $c->format = $config['format'];
        $c->charset= $config['charset'];
        $c->signType= $config['sign_type'];
        $subject = '订单支付';
        //调用getPublicKey从支付宝公钥证书中提取公钥
        $c->alipayrsaPublicKey = $c->getPublicKey($alipayCertPath);
        //是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
        $c->isCheckAlipayPublicCert = true;
        //调用getCertSN获取证书序列号
        $c->appCertSN = $c->getCertSN($appCertPath);
        //调用getRootCertSN获取支付宝根证书序列号
        $c->alipayRootCertSN = $c->getRootCertSN($rootCertPath);

        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.open.public.template.message.industry.modify
        $request = new \AlipayOpenPublicTemplateMessageIndustryModifyRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        //此次只是参数展示，未进行字符串转义，实际情况下请转义
        $request->setBizContent = "{" .
        "    \"body\":\"支付宝支付\"," .
        "    \"subject\":\"$subject\"," .
        "    \"out_trade_no\":\"$self_id\"," .
        "    \"total_amount\":\"$price\"," .
        "    \"product_code\":\"QUICK_MSECURITY_PAY\"," .
        "    \"passback_params\":\"$user_id\"" .
        " }";
        $response= $c->sdkExecute($request);
        return $response;
    }

    /**
     * APP支付宝支付回调  /alipay/depositWechat
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
            file_put_contents(base_path('/vendor/alipay.txt'),$pay);
            $order = TmsOrder::where('self_id',$_POST['out_trade_no'])->select(['self_id','total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
            if ($order->order_status == 2 || $order->order_status == 3){
                echo 'success';
                return false;
            }
            $payment_info = TmsPayment::where('order_id',$_POST['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $_POST['passback_params'];
                $wallet['total_user_id'] = $_POST['passback_params'];
                $where['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_code'] = $_POST['passback_params'];
                $wallet['group_code'] = $_POST['passback_params'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $_POST['passback_params'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $_POST['total_amount'] * 100;
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            UserWallet::insert($wallet);
            file_put_contents(base_path('/vendor/alipay1.txt'),$wallet);
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
            file_put_contents(base_path('/vendor/alipay2.txt'),$tmsOrderCost);
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
                file_put_contents(base_path('/vendor/alipay3.txt'),'123');
            }
            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$_POST['out_trade_no'])->select('self_id','dispatch_flag')->get();
            if ($tmsOrderDispatch){
//                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                foreach ($tmsOrderDispatch as $key =>$value){
                    if ($value->dispatch_flag != 'N'){
                        $orderStatus = TmsOrderDispatch::where('self_id',$value->self_id)->update($order_update);
                    }
                }
            }
            /**推送**/
            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
            $push_contnect = array('title' => "装多多",'content' => $center_list , 'payload' => "订单信息");
//                        $A = $this->send_push_message($push_contnect,$data['send_shi_name']);
            if ($order->order_type == 'vehicle'){
                if($order->group_code){
                    $group = SystemGroup::where('self_id',$order->group_code)->select('self_id','group_name','company_type')->first();
                    if($group->company_type != 'TMS3PL'){
                        $A = $this->send_push_msg('订单信息','有新订单',$center_list);
                    }
                }else{
                    $A = $this->send_push_msg('订单信息','有新订单',$center_list);
                }
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
            $payment_info = TmsPayment::where('order_id',$_POST['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $_POST['passback_params'];
                $wallet['total_user_id'] = $_POST['passback_params'];
                $where['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_code'] = $_POST['passback_params'];
                $wallet['group_code'] = $_POST['passback_params'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $_POST['passback_params'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $_POST['total_amount'] * 100;
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            UserWallet::insert($wallet);
//            $order_update['order_status'] = 6;
            $order_update['pay_state'] = 'Y';
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$_POST['out_trade_no'])->update($order_update);
            if($order->order_type == 'vehicle'){
                $dispatch_where['pay_status'] = 'Y';
                $dispatch_where['update_time'] = $now_time;
                TmsOrderDispatch::where('order_id',$_POST['out_trade_no'])->update($dispatch_where);
            }
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
     * 上线支付回调
     * */
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
            $order = TmsOrderDispatch::where('self_id',$_POST['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
            $payment_info = TmsPayment::where('dispatch_id',$_POST['out_trade_no'])->select(['pay_result','state','dispatch_id'])->first();
            if ($payment_info){
                echo 'fail';
                return false;
            }
            if (substr($_POST['passback_params'],0,4) == 'user'){
                $pay['total_user_id'] = $_POST['passback_params'];
                $wallet['total_user_id'] = $_POST['passback_params'];
                $where['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $_POST['passback_params'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $_POST['passback_params'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $_POST['total_amount'] * 100;
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            UserWallet::insert($wallet);
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
            /**推送**/
            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
            $push_contnect = array('title' => "装多多",'content' => $center_list , 'payload' => "订单信息");
            $A = $this->send_push_msg($push_contnect);

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
     * APP微信支付
     * */
    public function appWechat(Request $request){
        $pay_type  = array_column(config('tms.wechat_notify'),'notify','key');
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
        $type  = $request->input('type');
        $user_type  = $request->input('user_type');
        if (empty($type)){
            $msg['code'] = 303;
            $msg['msg']  = '请选择支付类型';
            return $msg;
        }
//        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if($type == 3){
            $payment = TmsPayment::where('dispatch_id',$self_id)->select('dispatch_id','pay_result','paytype','state')->first();
            if($payment){
                $msg['code'] = 302;
                $msg['msg']  = '此订单不能重复上线';
                return $msg;
            }
        }
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        $out_trade_no = $self_id;
        $noturl = $pay_type[$type];
        if ($user_type == 'user'){
            $config    = config('tms.wechat_config_user');//引入配置文件参数
        }else{
            $config    = config('tms.wechat_config_driver');//引入配置文件参数
        }

        $appid  = $config['appid'];
        $mch_id = $config['mch_id'];
        $key    = $config['key'];

        $notify_url = $noturl;
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify_url,$key);
        $params['body'] = '订单支付';                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->unifiedOrder($params);
        // print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
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
            $order = TmsOrder::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
            if ($order->order_status == 2 || $order->order_status == 3){
                echo 'success';
                return false;
            }
            $payment_info = TmsPayment::where('order_id',$array_data['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
                $where['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $array_data['attach'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $array_data['attach'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $array_data['total_fee'];
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            UserWallet::insert($wallet);
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
            /**推送**/
            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
            $push_contnect = array('title' => "装多多",'content' => $center_list , 'payload' => "订单信息");
//                        $A = $this->send_push_message($push_contnect,$data['send_shi_name']);
            if ($order->order_type == 'vehicle') {
                if ($order->group_code) {
                    $group = SystemGroup::where('self_id', $order->group_code)->select('self_id', 'group_name', 'company_type')->first();
                    if ($group->company_type != 'TMS3PL') {
                        $A = $this->send_push_msg('订单信息', '有新订单', $center_list);
                    }
                } else {
                    $A = $this->send_push_msg('订单信息', '有新订单', $center_list);
                }
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
            $payment_info = TmsPayment::where('order_id',$array_data['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
                $where['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $array_data['attach'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $array_data['attach'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $array_data['total_fee'];
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
//            $order_update['order_status'] = 6;
            $order_update['pay_state'] = 'Y';
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$array_data['out_trade_no'])->update($order_update);
            if($order->order_type == 'vehicle'){
                $dispatch_where['pay_status'] = 'Y';
                $dispatch_where['update_time'] = $now_time;
                TmsOrderDispatch::where('order_id',$array_data['out_trade_no'])->update($dispatch_where);
            }
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
     * 上线微信支付回调
     * */
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
            $order = TmsOrderDispatch::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
            $payment_info = TmsPayment::where('dispatch_id',$array_data['out_trade_no'])->select(['pay_result','state','dispatch_id'])->first();
            if ($payment_info){
                echo 'fail';
                return false;
            }
            if (substr($_POST['passback_params'],0,4) == 'user'){
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
                $where['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $array_data['attach'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $array_data['attach'];
            }
            TmsPayment::insert($pay);

            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $array_data['total_fee'];
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';

            $order_update['order_status'] = 2;
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $order_update['pay_status'] = 'Y';
            $order_update['on_line_flag'] = 'Y';
            $order_update['dispatch_flag'] = 'N';
            $order_update['receiver_id'] = null;
            $order_update['pay_type']    = 'online';
            $order_update['on_line_money'] = $array_data['total_fee'];
            $id = TmsOrderDispatch::where('self_id',$array_data['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }
            /**推送**/
            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
            $push_contnect = array('title' => "装多多",'content' => $center_list , 'payload' => "订单信息");
            $A = $this->send_push_msg($push_contnect);

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
        $type  = $request->input('type'); // 支付宝 alipay  微信 wechat
        /**虚拟数据
//        $price   = 0.01;
//        $self_id = 'order_202103121712041799645968';
         * */
        $now_time = date('Y-m-d H:i:s',time());
        $pay['order_id'] = $self_id;
        $pay['pay_number'] = $price*100;
        $pay['platformorderid'] = generate_id('');
        $pay['create_time'] = $pay['update_time'] = $now_time;
        $pay['payname'] = $user_info->tel;
        $pay['paytype'] = 'BALANCE';//
        $pay['pay_result'] = 'SU';//
        $pay['state'] = 'in';//支付状态
        $pay['self_id'] = generate_id('pay_');
        $order = TmsOrder::where('self_id',$self_id)->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
//        if ($order->order_status == 2){
//            $msg['code'] = 301;
//            $msg['msg']  = '该订单已支付';
//            return $msg;
//        }
        if ($user_info->type == 'user'){
            $pay['total_user_id'] = $user_info->total_user_id;
            $wallet['total_user_id'] = $user_info->total_user_id;
            $capital_where['total_user_id'] = $user_info->total_user_id;
        }else{
            $pay['group_code'] = $user_info->group_code;
            $pay['group_name'] = $user_info->group_name;
            $wallet['group_code'] = $user_info->group_code;
            $wallet['group_name'] = $user_info->group_name;
            $capital_where['group_code'] = $user_info->group_code;
        }
        $userCapital = UserCapital::where($capital_where)->first();
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
        $wallet['money']       = $price*100;
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
        if($userCapital->money >= $price){
            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$self_id)->select('self_id')->get();
            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
            }
            /**推送**/
            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的订单';
            $push_contnect = array('title' => "装多多",'content' => $center_list , 'payload' => "订单信息");
//                        $A = $this->send_push_message($push_contnect,$data['send_shi_name']);
            if($order->order_type == 'vehicle'){
                if($order->group_code){
                    $group = SystemGroup::where('self_id',$order->group_code)->select('self_id','group_name','company_type')->first();
                    if($group->company_type != 'TMS3PL'){
                        $A = $this->send_push_msg('订单信息','有新订单',$center_list);
                    }
                }else{
                    $A = $this->send_push_msg('订单信息','有新订单',$center_list);
                }
            }
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
     * 货到付款余额支付 /alipay/walletPay
     * */
    public function walletPay(Request $request){
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
        $type  = $request->input('type'); // 支付宝 alipay  微信 wechat
        /**虚拟数据
        $price   = 0.01;
        $self_id = 'order_202103121712041799645968';
         * */
        $now_time = date('Y-m-d H:i:s',time());
        $pay['order_id'] = $self_id;
        $pay['pay_number'] = $price*100;
        $pay['platformorderid'] = generate_id('');
        $pay['create_time'] = $pay['update_time'] = $now_time;
        $pay['payname'] = $user_info->tel;
        $pay['paytype'] = 'BALANCE';//
        $pay['pay_result'] = 'SU';//
        $pay['state'] = 'in';//支付状态
        $pay['self_id'] = generate_id('pay_');
        $order = TmsOrder::where('self_id',$self_id)->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name','pay_state','order_type'])->first();
//        if ($order->order_status == 2){
//            $msg['code'] = 301;
//            $msg['msg']  = '该订单已支付';
//            return $msg;
//        }
        if ($user_info->type == 'user'){
            $pay['total_user_id'] = $user_info->total_user_id;
            $wallet['total_user_id'] = $user_info->total_user_id;
            $capital_where['total_user_id'] = $user_info->total_user_id;
        }else{
            $pay['group_code'] = $user_info->group_code;
            $pay['group_name'] = $user_info->group_name;
            $wallet['group_code'] = $user_info->group_code;
            $wallet['group_name'] = $user_info->group_name;
            $capital_where['group_code'] = $user_info->group_code;
        }
        $userCapital = UserCapital::where($capital_where)->first();
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
        $wallet['money']       = $price*100;
        $wallet['now_money'] = $capital['money'];
        $wallet['now_money_md'] = get_md5($capital['money']);
        $wallet['wallet_status'] = 'SU';
        UserWallet::insert($wallet);
        TmsPayment::insert($pay);
        $order_update['pay_state'] = 'Y';
        $order_update['update_time'] = date('Y-m-d H:i:s',time());
        $id = TmsOrder::where('self_id',$self_id)->update($order_update);
        if($order->order_type == 'vehicle'){
            $dispatch_where['pay_status'] = 'Y';
            $dispatch_where['update_time'] = $now_time;
            TmsOrderDispatch::where('order_id',$self_id)->update($dispatch_where);
        }
        /**修改费用数据为可用**/
        $money['delete_flag']                = 'Y';
        $money['settle_flag']                = 'W';
        $tmsOrderCost = TmsOrderCost::where('order_id',$self_id)->select('self_id')->get();
        if ($tmsOrderCost){
            $money_list = array_column($tmsOrderCost->toArray(),'self_id');
            TmsOrderCost::whereIn('self_id',$money_list)->update($money);
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
     * 小程序支付 /alipay/routinePay  routine_config_user
     * */
    public function routinePay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
        $openid = $request->input('openid');//小程序用户唯一标识
        $type = $request->input('type');//1下单支付 2货到付款支付
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
//        $price = 0.01;
        $body = '订单支付';
        $out_trade_no = $self_id;
        if ($type == 1){
            $notify = 'http://api.zdd021.com/alipay/appWechat_notify';
        }else{
            $notify = 'http://api.zdd021.com/alipay/paymentWechatNotify';
        }

        $config    = config('tms.routine_config_user');//引入配置文件参数
        $appid  = $config['appid'];
        $mch_id = $config['mch_id'];
        $key    = $config['key'];
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify,$key);
        $params['openid'] = $openid;                    //用户唯一标识
        $params['body'] = $body;                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'JSAPI';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->jsapi($params);
//        print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
//        exit();
        //2.创建小程序端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getPayParams($result);
        return json_encode(['code'=>200,'msg'=>'请求成功','data'=>$data]);
    }


    /**
     * 微信扫码支付  /alipay/nativePay
     * */
    public function nativePay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
//        $input = $request->post();
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
        if ($user_info->type == 'user'|| $user_info->type == 'carriage'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
//        $price = 0.01;
        $body = '订单支付';
        $out_trade_no = $self_id;
        $notify_url = 'https://api.zdd021.com/alipay/nativeNotify';
        include_once base_path('/vendor/wxpay/lib/WxPay.Data.php');
        include_once base_path('/vendor/wxpay/NativePay.php');
        $notify = new \NativePay;
        $params = new \WxPayUnifiedOrder;
        $params->SetBody($body);//商品描述
        $params->SetAttach($user_id);//设置附加数据，在查询API和支付通知中原样返回
        $params->SetOut_trade_no($out_trade_no);//订单ID
        $params->SetTotal_fee($price*100);//支付金额
        $params->SetTime_start(date("YmdHis"));
        $params->SetTime_expire(date("YmdHis", time() + 15*60));
        $params->SetGoods_tag("test");//设置商品标记，代金券或立减优惠功能的参数 */
        $params->SetNotify_url($notify_url);//回调地址
        $params->SetTrade_type("NATIVE");//支付类型
        $params->SetProduct_id($out_trade_no);//商品ID
        $result = $notify->GetPayUrl($params);
        $url = $result["code_url"];
        $res = $this->qrcode($url);
        if($res){
            $msg['code'] = 200;
            $msg['msg'] = '请求成功';
            $msg['data'] = 'http://api.zdd021.com/'.$res;
            return $msg;
        }else{
            $msg['code'] = 301;
            $msg['msg'] = '请求失败，请刷新重试';
            return $msg;
        }

    }

    /**
     * 微信扫码支付回调
     * */
    public function nativeNotify(Request $request){
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
            $order = TmsOrderDispatch::where('self_id',$array_data['out_trade_no'])->select(['order_id','total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name','pay_type','pay_status'])->first();
            $payment_info = TmsPayment::where('dispatch_id',$array_data['out_trade_no'])->select(['pay_result','state','dispatch_id'])->first();
            if ($payment_info){
                echo 'fail';
                return false;
            }
            if (substr($array_data['attach'],0,4) == 'user'){
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
                $where['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $array_data['attach'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $array_data['attach'];
            }
            TmsPayment::insert($pay);

            $capital = UserCapital::where($where)->select(['self_id','money','group_code','total_user_id'])->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $array_data['total_fee'];
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';

            $wallet_su = UserWallet::insert($wallet);
            $dispatch_update['update_time'] = date('Y-m-d H:i:s',time());
            $dispatch_update['pay_status'] = 'Y';
            $id = TmsOrderDispatch::where('self_id',$array_data['out_trade_no'])->update($dispatch_update);

            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $order_update['pay_state'] = 'Y';
            $order_su = TmsOrder::where('self_id',$order->order_id)->update($order_update);
            file_put_contents(base_path('/vendor/qrcodeAlipay.txt'),$wallet_su.$order_su);
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
     * 支付宝扫码支付
     * */
    public function   qrcodeAlipay(Request $request){
        $config    = config('tms.alipay_config');//引入配置文件参数
        $input     = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $type      = $request->input('type'); // 1  2  3
        $pay_type  = array_column(config('tms.alipay_notify'),'notify','key');
        $self_id   = $request->input('self_id');// 订单ID
        $price     = $request->input('price');// 支付金额
//        $price     = 0.01;
        $type      = 3;
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_2021030945673082733451';
         * */
        if ($user_info->type == 'user' || $user_info->type == 'carriage'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayTradePrecreateRequest.php');
        $aop = new \AopClient();
//        $request = new \AlipayTradeAppPayRequest();
        $request = new \AlipayTradePrecreateRequest();
        $aop->gatewayUrl = $config['gatewayUrl'];
        $aop->appId = $config['app_id'];
        $aop->rsaPrivateKey = $config['merchant_private_key'];
        $aop->format = $config['format'];
        $aop->charset = $config['charset'];
        $aop->signType = $config['sign_type'];
        //运单支付
        $subject = '订单支付';
        $notifyurl = "http://api.zdd021.com/alipay/qrcode_notify";

        $aop->alipayrsaPublicKey = $config['alipay_public_key'];
        $bizcontent = json_encode([
            'body' => '支付宝支付',
            'subject' => $subject,
            'out_trade_no' => $self_id,//此订单号为商户唯一订单号
            'total_amount' => $price,//保留两位小数
            'product_code' => 'FACE_TO_FACE_PAYMENT',
            'passback_params' => $user_id,
        ]);

        $request->setNotifyUrl($notifyurl);
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是execute
        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        $qr_code_url = $result->$responseNode->qr_code;
//        dd($qr_code_url);
        $res = $this->qrcode($qr_code_url);
        if($res){
            $msg['code'] = 200;
            $msg['msg'] = '请求成功';
            $msg['data'] = 'https://ytapi.56cold.com/'.$res;
            return $msg;
        }else{
            $msg['code'] = 301;
            $msg['msg'] = '请求失败，请刷新重试';
            return $msg;
        }
    }

    /**
     * 支付宝扫码支付回调
     * */
    public function qrcode_notify(Request $request){
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
            file_put_contents(base_path('/vendor/alipay_tt.txt'),$_POST);
            $order = TmsOrderDispatch::where('self_id',$_POST['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','pay_status','order_id'])->first();
            if ($order->pay_status == 'Y'){
                echo 'success';
                return false;
            }
            $payment_info = TmsPayment::where('dispatch_id',$_POST['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            file_put_contents(base_path('/vendor/text.txt'),substr($_POST['passback_params'],0,4));
            if (substr($_POST['passback_params'],0,4) == 'user'){
                $pay['total_user_id'] = $_POST['passback_params'];
                $wallet['total_user_id'] = $_POST['passback_params'];
                $where['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_code'] = $_POST['passback_params'];
                $wallet['group_code'] = $_POST['passback_params'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $_POST['passback_params'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->select(['money','self_id','group_code','total_user_id'])->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $_POST['total_amount'] * 100;
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            $wallet_su = UserWallet::insert($wallet);
//            $order_update['order_status'] = 6;
            $dispatch_update['pay_status'] = 'Y';
            $dispatch_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrderDispatch::where('self_id',$_POST['out_trade_no'])->update($dispatch_update);

            $order_update['pay_state'] = 'Y';
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $order_su = TmsOrder::where('self_id',$order->order_id)->update($order_update);
            file_put_contents(base_path('/vendor/qrcodeAlipay.txt'),$id.$wallet_su.$order_su);
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('dispatch_id',$_POST['out_trade_no'])->select('self_id')->get();
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
     * 生成二维码
     * */
    public function qrcode($value){
        include_once base_path('/vendor/phpqrcode/phpqrcode.php');
//         include_once base_path('/vendor/wxpay/lib/phpqrcode.php');
        $qrcode = new \QRcode();
        //二维码内容
//        $value = 'https://api.56cold.com/alipay/getClientType';
        $errorCorrectionLevel = 'H';//容错级别
        $matrixPointSize = 5;//生成图片大小
        //生成二维码图片
        $qrcode->png($value,'qrcode.png',$errorCorrectionLevel, $matrixPointSize, 5,false);
//        return $QrCode;
        $logo =  base_path('/uploads/logo/logo.png');//准备好的logo图片
        $QR = 'qrcode.png';//已经生成的原始二维码图
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring(file_get_contents($QR));
            $logo = imagecreatefromstring(file_get_contents($logo));
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                $logo_qr_height, $logo_width, $logo_height);
        }
        //输出图片
//        $path = base_path('uploads/qrcode').'/';
        $path = 'uploads/qrcode/';
        $filename = $path.date('YmdHis').'.png';
//        Header("Content-type: image/png");
//        imagepng($QR);
        imagepng($QR,$filename);
//        imagedestroy($QR);
        return $filename;

    }

    /**
     * 判断微信端还是支付宝端
     * */
    public function getClientType(Request $request){
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            //判断是不是微信
//            return "您正在使用 微信 扫码";
            $url = $this->nativePay();
            return $url;
            dd($url);
        }elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
            //判断是不是支付宝
//            return "您正在使用 支付宝 扫码";
            $url = $this->qrcodeAlipay();
            return $url;
            dd($url);
        }elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'QQ') !== false) {
            //判断是不是QQ
//            return "您正在使用 手机QQ 扫码";
        }else{
            //哪个都不是
            $msg['code'] = 301;
            $msg['msg']  = '请使用支付宝、QQ、微信扫码';
            return $msg;
        }


    }


    /**
     * 微信查询订单是否支付  alipay/queryWechat
     * */
    public function queryWechat(Request $request){
        $self_id = $request->input('self_id');//订单ID
//        $self_id = 'order_202109151834352525376788';
        include_once base_path('/vendor/wxpay/lib/WxPay.Api.php');
        $input = new \WxPayOrderQuery();
        $input->SetOut_trade_no($self_id);
        $result = WxPayQ::orderQuery($input);
        if($result['result_code'] == 'SUCCESS' && $result['return_code']=='SUCCESS' && $result['return_msg'] == 'OK'){
            $msg['code'] = 200;
            $msg['msg']  = '支付成功';
            return $msg;
        }else{
            $msg['code'] = 301;
            $msg['msg']  = $result['err_code_des'];
            return $msg;
        }
    }

    /**
     * 查询支付宝订单是否支付 /alipay/queryAlipay
     * */
    public function queryAlipay(){
        include_once base_path('/vendor/alipay/pagepay/service/AlipayTradeService.php');
        include_once base_path('/vendor/alipay/pagepay/buildermodel/AlipayTradeQueryContentBuilder.php');
        $config    = config('tms.alipay_config');//引入配置文件参数
        $self_id = 'order_202109251115091435951796';
        //商户订单号，商户网站订单系统中唯一订单号
        $out_trade_no = trim($self_id);

        //支付宝交易号
//        $trade_no = trim($_POST['WIDTQtrade_no']);
        //请二选一设置
        //构造参数
        $RequestBuilder = new \AlipayTradeQueryContentBuilder();
        $RequestBuilder->setOutTradeNo($out_trade_no);
//        $RequestBuilder->setTradeNo($trade_no);

        $aop = new \AlipayTradeService($config);

        /**
         * alipay.trade.query (统一收单线下交易查询)
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @return $response 支付宝返回的信息
         */
        $response = $aop->Query($RequestBuilder);
        dd($response);
    }

    /**
     * 查询订单是否支付  /alipay/queryPayment
     * */
    public function queryPayment(Request $request){
        $order_id = $request->input('order_id');
        $dispatch_id = $request->input('dispatch_id');
//        $order_id = 'order_202109080956400191126933';
        $info = TmsPayment::where('order_id',$order_id)->orWhere('dispatch_id',$dispatch_id)->first();
        if ($info){
            if ($info->pay_result == 'SU'){
                $msg['code'] = 200;
                $msg['msg']  = '支付成功';
                return $msg;
            }else{
                $msg['code'] = 301;
                $msg['msg']  = '该未支付成功';
                return $msg;
            }
        }else{
            $msg['code'] = 301;
            $msg['msg']  = '该未支付成功';
            return $msg;
        }
    }

    /**
     * App支付宝充值  /alipay/depositAlipay
     * */
    public function depositAlipay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');
        $price = $request->input('price'); //充值金额
        $type = $request->input('type');
        if (empty($price)){
            $msg['code'] = 301;
            $msg['msg']  = '请填写价格';
            return $msg;
        }
//        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if ($user_info->type == 'user' || $user_info->type == 'carriage'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }

        include_once base_path('/vendor/alipay/aop/AopClient.php');
        include_once base_path('vendor/alipay/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();
        $request = new \AlipayTradeAppPayRequest();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        if ($type == 'user'){
            $aop->appId = "2021003158683126";
        }else{
            $aop->appId = "2021003159610134";
        }
        $aop->rsaPrivateKey = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCnKSBGdRUuaJgejEMO1rslCpmRanEVZnjFrdZ5CUWLHm+lsTCBVH0KuARmtEtYdBBKZaYOuhmWs0BTuLNWeiESZpraUkE42NyP7p7jMwi/nIeVN3MBvlggNfUI/RivdLk6n0oknm/B12Ao227PTVsJGYR+YKWfMDNEFcHFbMo22Of8JwsHixeDZRjrUo1FuQqXCyiUKpSRwqnomvx5czDDcIUJIcOnrugj+M2+9Opk1EsEXICiIZZUMHgyBS0K+0I8lDuxCTLPY9jqq6WdfCSvuaygJPmjD9GjfKF7zwNHvNcLGPgpvdLs7EzD6tR6fk8HTNHmVhj7tKdRTJFjh6M9AgMBAAECggEBAJlbTKX3MniCMtULv1W0wLqp79uN+LM2cKSC6JngXLHWOX2cgrCUL6eOzVLgI6PBz1RBz0gBigpM5z4n3DgBEahNA9I51mZt5mQR+ijcoDESTP0jgtpdo4Hhnq0hbe1CO9FBZAcWZ9dBXZH+Rrne8R73DyvWRPw3f0D+aOhT92y6s64BEm7UKf57awN2o8bSLFceF4e5+aDPFaiQDIwkyF5f0DeEcdqyjSwQ/7zI6x3JF+/RfwzYPCRwYL7Mded0LiCYUYjPwLEYIchKCFPtgU3ljzhAekzJah1hSTs/2M98B7m3XG/xRimYH4eivinADVS+DGJ1TrpdAs7td1T8WaECgYEA4NsAGF6QYHLIjAiOBGL4JwyRSsxburK7LzY2mhE6mkxjbiWukT7a2B/+BpKnO4JfQ45P3X3faCDiZePseyY93CB2BKxx6uE7l4VRCLpqs79y0EJ2xnMIO1jx04Z/xut8XyfUJQfsq2AGVqI6/XP2n7phi4ffCbE8RAzM14Q9YPUCgYEAvlBdF6kmwRXB5/gWhZWBJiCapgre0yYpQ03uQFo5aV2k4sahYkd/IgK38+byBqbdN/ZJv/7J6AMUNiaUOBY7ayxePjv9/nD+7gfdxqBXNVuG6iLuxF+CcSN2YsL9lFrVrGIandYCF9kjHZtNGA+t2e9FficJ1FWoF2TXCQtFLCkCgYBF7hZDffVs6YXRdz+jwZvnmRL+rjvA/qEUd8nXdJBDOIqb0QBPj7rECH8aUDXOXid+fR39ho4adk+y3IXJbwVCFp5dqbvsDoxX/VBMZJ2WtW3hsyn5YgEnY+whmuzWUpplhZ+GvMYwLjcKbn3mIVCMMIzxthn5i7NwfoKebDQimQKBgF0ANJ3NYUzV7w4GpCrfZl9Va31crosMiPmE6bq03H1q75qKam72dWAPaAlegENT46LnTh7uyYgBiSz1KVVHN/4ljmBnPLXMTifP3EamMDe45HMiYv+/lKTpKX8Vvoly4hv9TPh4jklNKOXc8I2ji9eGH7WIKjuDKENWWebnhQQRAoGBAJn9C4HKkziQyhMQmqEy585kIDV8KxjH82WGAslNn2keS1PuJWOnuyR1ayWTI/eNDrpSPEQ8zAOGXiq2ojmU+ELrY45Gu5Mvsh9hAYq5lZuU82q3GZzPhkp8yt02lfCBrMGIhZBBWXLLz0QefDGT+Z8T4PEJyJT5hzs/WUVd3vn/';
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApykgRnUVLmiYHoxDDta7JQqZkWpxFWZ4xa3WeQlFix5vpbEwgVR9CrgEZrRLWHQQSmWmDroZlrNAU7izVnohEmaa2lJBONjcj+6e4zMIv5yHlTdzAb5YIDX1CP0Yr3S5Op9KJJ5vwddgKNtuz01bCRmEfmClnzAzRBXBxWzKNtjn/CcLB4sXg2UY61KNRbkKlwsolCqUkcKp6Jr8eXMww3CFCSHDp67oI/jNvvTqZNRLBFyAoiGWVDB4MgUtCvtCPJQ7sQkyz2PY6qulnXwkr7msoCT5ow/Ro3yhe88DR7zXCxj4Kb3S7OxMw+rUen5PB0zR5lYY+7SnUUyRY4ejPQIDAQAB';
        $bizcontent = json_encode([
            'body' => '支付宝充值',
            'subject' => '余额支付宝充值',
            'out_trade_no' => date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8),//此订单号为商户唯一订单号
            'total_amount' => $price,//保留两位小数
            'product_code' => 'QUICK_MSECURITY_PAY',
            'passback_params' => $user_id
        ]);

        $request->setNotifyUrl("http://api.zdd021.com/alipay/depositAlipayNotify");
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        echo $response;
    }

    /**
     * 支付宝充值回调
     * */
    public function depositAlipayNotify(Request $request){
        include_once base_path( 'vendor/alipay/pagepay/service/AlipayTradeService.php');
        $now_time = date('Y-m-d H:i:s',time());
        $config = config('tms.alipay_config');
        $alipaySevice = new \AlipayTradeService($config);
        $alipaySevice->writeLog(var_export($_POST, true));
        $result = $alipaySevice->check($_POST);
        if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
            if(substr($_POST['passback_params'],0,4) == 'user'){
                $userCapital = UserCapital::where('total_user_id','=',$_POST['passback_params'])->first();
                $flag = TmsPayment::where([['total_user_id','=',$_POST['passback_params']],['order_id','=',$_POST['out_trade_no']]])->first();
                $pay['total_user_id'] = $_POST['passback_params'];
                $wallet['total_user_id'] = $_POST['passback_params'];
            }else{
                $userCapital = UserCapital::where('group_code','=',$_POST['passback_params'])->first();
                $flag = TmsPayment::where([['group_code','=',$_POST['passback_params']],['order_id','=',$_POST['out_trade_no']]])->first();
                $pay['group_code'] = $_POST['passback_params'];
                $wallet['group_code'] = $_POST['passback_params'];
            }

            if ($flag){
                echo 'success';
                return false;
            }
            $pay['order_id'] = $_POST['out_trade_no'];
            $pay['pay_number'] = $_POST['total_amount'] * 100;
            $pay['platformorderid'] = $_POST['trade_no'];
            $pay['create_time'] = $pay['update_time'] = $now_time;
//            $pay['payname'] = $_POST['buyer_logon_id'];
            $pay['paytype'] = 'ALIPAY';//
            $pay['pay_result'] = 'SU';//
            $pay['state'] = 'recharge';//支付状态
            $pay['self_id'] = generate_id('pay_');
//            file_put_contents(base_path('/vendor/5555.txt'),$pay);
            TmsPayment::insert($pay);

            $capital['money'] = $userCapital->money + $_POST['total_amount']*100;
            $capital['update_time'] = $now_time;
            if (substr($_POST['passback_params'],0,4) == 'user'){
                UserCapital::where('total_user_id','=',$_POST['passback_params'])->update($capital);
            }else{
                UserCapital::where('group_code','=',$_POST['passback_params'])->update($capital);
            }
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'recharge';
            $wallet['capital_type'] = 'wallet';
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['money'] = $_POST['total_amount'] * 100;
            $wallet['now_money'] = $capital['money'];
            $wallet['now_money_md'] = get_md5($capital['money']);
            $wallet['wallet_status'] = 'SU';

            UserWallet::insert($wallet);
            echo 'success';
        } else {
            echo 'fail';
        }
    }


    /**
     * APP微信支付  /alipay/depositWechat
     * */
    public function depositWechat(Request $request){
        include_once base_path('vendor/wxAppPay/weixin.php');
        $input = $request->all();
        $user_info = $request->get('user_info');
        $price = $request->input('price'); //充值金额
        $type  = $request->input('type');
        if (empty($price)){
            $msg['code'] = 301;
            $msg['msg']  = '请填写价格';
            return $msg;
        }
//        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if ($user_info->type == 'user' || $user_info->type == 'carriage'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }

        if ($type == 'user'){
            $config    = config('tms.wechat_config_user');//引入配置文件参数
        }else{
            $config    = config('tms.wechat_config_driver');//引入配置文件参数
        }
        $appid  = $config['appid'];
        $mch_id = $config['mch_id'];
        $key    = $config['key'];
        $notify_url = 'http://api.zdd021.com/alipay/depositWechatNotify';
        $wechatAppPay = new \wxAppPay($appid, $mch_id, $notify_url, $key);
        $params['body'] = '微信余额充值';                       //商品描述
        $params['out_trade_no'] = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);    //自定义的订单号
        $params['total_fee'] = $price * 100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->unifiedOrder($params);
        // print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
        // 根据上行取得的支付参数请求支付即可
        return json_encode($data);
    }

    /**
     * 微信充值回调  /alipay/depositWechatNotify
     * */
    public function depositWechatNotify(Request $request){
        $now_time = date('Y-m-d H:i:s');
        ini_set('date.timezone', 'Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            if(substr($array_data['attach'],0,4) == 'user'){
                $userCapital = UserCapital::where('total_user_id','=',$array_data['attach'])->first();
                $flag = TmsPayment::where([['total_user_id','=',$array_data['attach']],['order_id','=',$array_data['out_trade_no']]])->first();
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
            }else{
                $userCapital = UserCapital::where('group_code','=',$array_data['attach'])->first();
                $flag = TmsPayment::where([['group_code','=',$array_data['attach']],['order_id','=',$array_data['out_trade_no']]])->first();
                $pay['group_code'] = $array_data['attach'];
                $wallet['group_code'] = $array_data['attach'];
            }

            if ($flag){
                echo 'success';
                return false;
            }
            $pay['order_id'] = $array_data['out_trade_no'];
            $pay['pay_number'] = $array_data['total_fee'];
            $pay['platformorderid'] = $array_data['transaction_id'];
            $pay['create_time'] = $pay['update_time'] = $now_time;
//            $pay['payname'] = $_POST['buyer_logon_id'];
            $pay['paytype'] = 'Wechat';//
            $pay['pay_result'] = 'SU';//
            $pay['state'] = 'recharge';//支付状态
            $pay['self_id'] = generate_id('pay_');

//            file_put_contents(base_path('/vendor/5555.txt'),$pay);
            TmsPayment::insert($pay);

            $capital['money'] = $userCapital->money + $array_data['total_fee'];
            $capital['update_time'] = $now_time;
            if(substr($array_data['attach'],0,4) == 'user'){
                UserCapital::where('total_user_id','=',$array_data['attach'])->update($capital);
            }else{
                UserCapital::where('group_code','=',$array_data['attach'])->update($capital);
            }
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'recharge';
            $wallet['capital_type'] = 'wallet';
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['money'] = $array_data['total_fee'];
            $wallet['now_money'] = $capital['money'];
            $wallet['now_money_md'] = get_md5($capital['money']);
            $wallet['wallet_status'] = 'SU';

            UserWallet::insert($wallet);
            echo 'success';
        } else {
            echo "fail";
        }
    }


    /**
     * 小程序充值 /alipay/routineDeposit
     * */
    public function routineDeposit(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $price = $request->input('price');//支付金额
        $openid = $request->input('openid');//小程序用户唯一标识
        $type = $request->input('type');//1下单支付 2货到付款支付
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        if ($user_info->type == 'user' || $user_info->type == 'carriage'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
//        $price = 0.01;
        $body = '余额充值';
        $out_trade_no = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $notify = 'https://api.zdd021.com/alipay/depositWechatNotify';
        $config    = config('tms.routine_config_user');//引入配置文件参数
        $appid  = $config['appid'];
        $mch_id = $config['mch_id'];
        $key    = $config['key'];
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify,$key);
        $params['openid'] = $openid;                    //用户唯一标识
        $params['body'] = $body;                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'JSAPI';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->jsapi($params);
//        print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
//        exit();
        //2.创建小程序端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getPayParams($result);
        return json_encode(['code'=>200,'msg'=>'请求成功','data'=>$data]);
    }



}
?>
