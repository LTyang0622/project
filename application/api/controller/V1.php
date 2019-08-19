<?php

namespace app\api\controller;

use think\Controller;
use think\Request;
use think\Db;
use Workerman\Worker;
use Workerman\Lib\Timer;
class V1 extends Controller
{
	
	public function test_one()
	{	
		$data = $this->orderbook('BTC-USDT');
		return json_encode($data);
		
	}
	
	public function aaa()
	{
		return view();
	}
	
	protected function objectToArray($obj) 
	{
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)$this->objectToArray($v);
            }
        }

        return $obj;
    }
	
	public function abcd()
	{
		$data = $this->trade('BTC-USDT');
		return json_encode($data);
	}
	
	public function index()
    {
        require_once '/www/wwwroot/Bsw/application/workerman/Autoloader.php';
		
		// 注意：这里与上个例子不同，使用的是websocket协议
        $worker = new Worker("websocket://0.0.0.0:2345");
        // 启动4个进程对外提供服务
        $worker->count = 4;
        // 当收到客户端发来的数据后返回hello $data给客户端
        $worker->onMessage = function($connection, $data)
        {
			$data_s = json_decode($data);
            if($data_s->op == 'subscribe'){

                $data = $data_s->args;
                foreach ($data as $k=>$v) {
                    $head = strstr($v,':',true);
                    $tail = substr(strstr($v ,':'),1);
                    $data = $this->$head($tail);
                    //$ca->add($data);
                    //将调用的结果返回去
                    $f_data = array('topic'=>$v,'data'=>$data);
                    $f_data = json_encode($f_data);
                    $connection->send($f_data);
                }
            }

        };
        
        $worker->onWorkerStart = function($worker){
            Timer::add(0.5, function()use($worker){
              
                foreach($worker->connections as $connection) {
					
					//盘口数据
                    $orderbook = $this->orderbook('BTC-USDT');
                    $orderbook = array('topic'=>'orderBook:BTC-USDT','data'=>$orderbook);
                    $orderbook = json_encode($orderbook);
                    
                    //实时交易
                    $trade = $this->trade('BTC-USDT');
                    $trade = array('topic'=>'trade:BTC-USDT','data'=>$trade);
                    $trade = json_encode($trade);
                    
                    
                    //深度
                    $depth = $this->depth('BTC-USDT');
                    $depth = array('topic'=>'depth:BTC-USDT','data'=>$depth);
                    $depth = json_encode($depth);
                    
                    
                    
                    $connection->send($orderbook);
                    $connection->send($trade);
                    $connection->send($depth);
                    
                };
            });
            
        };
        Worker::runAll();
        
    }
    
    public function detail($symbol)
    {

        $header = array('User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0');
        $data1 = $this->http_get('https://api.huobi.pro/market/detail?symbol=btcusdt',[],$header);
        $data1 = json_decode($data1);
        $data = $data1->tick;
        $data->jine = $data->close*6.9;
        return $data;

    }
    
    
    
    
    //深度
	protected function depth($symbol)
	{       
	    $bn_canshu = strstr($symbol,'-',true).substr($symbol,strripos($symbol,"-")+1);
	    $hb_canshu = strtolower($bn_canshu);
	    $array_binance = $this->binance($bn_canshu);
	    $array_huobi = $this->huobi($hb_canshu);
	    $array_ok = $this->ok($symbol);
	
	    $biance_a = array_merge($array_binance['buy']);
	    $biance_b = array_merge($array_binance['sell']);
	    $huobi_a = array_merge($array_huobi['buy']);
	    $huobi_b = array_merge($array_huobi['sell']);
	    $ok_a = array_merge($array_ok['buy']);
	    $ok_b = array_merge($array_ok['sell']);
	
	    $last_names_bian = array_column($biance_a,'price');
	    array_multisort($last_names_bian,SORT_DESC,$biance_a);
	    $la_bian = array_column($biance_b,'price');
	    array_multisort($la_bian,SORT_ASC,$biance_b);
	
	    $last_names_huo = array_column($huobi_a,'price');
	    array_multisort($last_names_huo,SORT_DESC,$huobi_a);
	    $la_huo = array_column($huobi_b,'price');
	    array_multisort($la_huo,SORT_ASC,$huobi_b);
	
	
	    $last_names_ok = array_column($ok_a,'price');
	    array_multisort($last_names_ok,SORT_DESC,$ok_a);
	    $la_ok = array_column($ok_b,'price');
	    array_multisort($la_ok,SORT_ASC,$ok_b);
	
		$sum1 = null;
	    foreach($biance_a as $k=>$v){
	        $biance_a[$k]['total'] = $sum1+=$biance_a[$k]['num'];
	    }
	    
	    $sum2 = null;
	    foreach($biance_b as $k=>$v){
	        $biance_b[$k]['total'] = $sum2+=$biance_b[$k]['num'];
	    }
	    
	    $sum3 = null;
	    foreach($huobi_a as $k=>$v){
	        $huobi_a[$k]['total'] = $sum3+=$huobi_a[$k]['num'];
	    }
	    
	    $sum4 = null;
	    foreach($huobi_b as $k=>$v){
	        $huobi_b[$k]['total'] = $sum4+=$huobi_b[$k]['num'];
	    }
	    
	    $sum5 = null;
	    foreach($ok_a as $k=>$v){
	        $ok_a[$k]['total'] = $sum5+=$ok_a[$k]['num'];
	    }
	    
	    $sum6 = null;
	    foreach($ok_b as $k=>$v){
	        $ok_b[$k]['total'] = $sum6+=$ok_b[$k]['num'];
	    }
	
	    $bian = ['buy'=>$biance_a,'sell'=>$biance_b];
	    $huobi = ['buy'=>$huobi_a,'sell'=>$huobi_b];
	    $ok = ['buy'=>$ok_a,'sell'=>$ok_b];
	
	    $data = ['bian'=>$bian,'huobi'=>$huobi,'ok'=>$ok];
	
	    return $data;

	}
	
    //实时交易
    protected function trade($symbol)
	{   

        $bn_canshu = strstr($symbol,'-',true).substr($symbol,strripos($symbol,"-")+1);
        $hb_canshu = strtolower($bn_canshu);
        $header = array('User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0');
        $data1 = $this->http_get('https://api.binance.com/api/v1/trades?symbol='.$bn_canshu.'&limit=10',[],$header);
        $data2 = $this->http_get('https://api.huobi.pro/market/history/trade?symbol=btcusdt&size=10',[],$header);
        $data3 = $this->http_get('https://www.okex.com/api/spot/v3/instruments/'.$symbol.'/trades?limit=10',[],$header);
        // 把JSON字符串转成PHP数组
        $data_bian = json_decode($data1, true);
        foreach ($data_bian as $k=>$v) {
            $data_bian[$k]['num'] = $data_bian[$k]['qty'];
            if($data_bian[$k]['isBuyerMaker'] == true){
                $data_bian[$k]['type'] = 'buy';
            }else{
                $data_bian[$k]['type'] = 'sell';
            }
            $data_bian[$k]['time'] = date('Y-m-d H:i:s',$data_bian[$k]['time']/1000);
            $data_bian[$k]['typebi'] = '1';

            unset($data_bian[$k]['id']);
            unset($data_bian[$k]['qty']);
            unset($data_bian[$k]['isBuyerMaker']);
            unset($data_bian[$k]['isBestMatch']);
            unset($data_bian[$k]['quoteQty']);

        }


        
        // 把JSON字符串转成PHP数组
        $data_huobi1 = json_decode($data2, true)['data'];
        
        $data_huobi1 = $this->objectToArray($data_huobi1);
		
		$data_huobi = [];
		foreach ($data_huobi1 as $k=>$v){
			foreach ($data_huobi1[$k]['data'] as $kk=>$vv){
				array_push($data_huobi,$vv);
			}
		}
        
        
        
        
        
        
        foreach ($data_huobi as $k=>$v){
            $data_huobi[$k]['time'] = date('Y-m-d H:i:s',$data_huobi[$k]['ts']/1000);
            $data_huobi[$k]['num'] = $data_huobi[$k]['amount'];

            $data_huobi[$k]['type'] = $data_huobi[$k]['direction'];
            $data_huobi[$k]['typebi'] = '2';
            unset($data_huobi[$k]['amount']);
            unset($data_huobi[$k]['ts']);
            unset($data_huobi[$k]['direction']);
            unset($data_huobi[$k]['id']);
        }


        
        // 把JSON字符串转成PHP数组
        $data_ok = json_decode($data3, true);

        foreach ($data_ok as $k=>$v){
            $data_ok[$k]['a'] = $data_ok[$k]['price'];
            $data_ok[$k]['b'] = $data_ok[$k]['time'];

            unset($data_ok[$k]['price']);
            unset($data_ok[$k]['time']);
            unset($data_ok[$k]['timestamp']);
            unset($data_ok[$k]['trade_id']);


            $data_ok[$k]['price'] = $data_ok[$k]['a'];
            $data_ok[$k]['time'] = date('Y-m-d H:i:s',strtotime($data_ok[$k]['b']));
            $data_ok[$k]['num'] = $data_ok[$k]['size'];
            $data_ok[$k]['type'] = $data_ok[$k]['side'];
            $data_ok[$k]['typebi'] = '3';
            unset($data_ok[$k]['a']);
            unset($data_ok[$k]['b']);
            unset($data_ok[$k]['size']);
            unset($data_ok[$k]['side']);

        }

        $data = array_merge($data_bian,$data_huobi,$data_ok);
        $last_names = array_column($data,'time');
        array_multisort($last_names,SORT_DESC,$data);
        return $data;

	}
    
    //盘口数据
    protected function orderbook($symbol)
    {	
		
		$canshu = strstr($symbol,'-',true).substr($symbol,strripos($symbol,"-")+1);
        $array_binance = $this->binance($canshu);
        $array_huobi = $this->huobi(strtolower($canshu));
        $array_ok = $this->ok($symbol);

        $data_buy = array_merge($array_binance['buy'],$array_huobi['buy'],$array_ok['buy']);
        $data_sell = array_merge($array_binance['sell'],$array_huobi['sell'],$array_ok['sell']);
        $last_names = array_column($data_buy,'price');
        array_multisort($last_names,SORT_DESC,$data_buy);

        $la = array_column($data_sell,'price');
        array_multisort($la,SORT_ASC,$data_sell);
        
        $sum_a = null;
        foreach ($data_buy as $k=>$v) {
          	$data_buy[$k]['typebi'] = (string)$data_buy[$k]['typebi'];
            $data_buy[$k]['num'] = number_format($data_buy[$k]['num'],5);
            $data_buy[$k]['total'] = $sum_a+=$data_buy[$k]['num'];
        }
        
        
		$sum_b = null;
        foreach ($data_sell as $k=>$v) {
          	$data_sell[$k]['typebi'] = (string)$data_sell[$k]['typebi'];
            $data_sell[$k]['num'] = number_format($data_sell[$k]['num'],5);
            $data_sell[$k]['total'] = $sum_b+=$data_sell[$k]['num'];
        }



        $data1 = array('buy'=>$data_buy,'sell'=>$data_sell);

        return $data1;

    }
 
    //币安API
    protected function binance($symbol)
    {

        $header = array('User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0');
        $api = 'https://api.binance.com/api/v1/depth?symbol='.$symbol.'&limit=5';
        $data1 = $this->http_get($api,[],$header);
        // 把JSON字符串转成PHP数组
        $data = json_decode($data1, true);
        $buy_data = $data['bids'];
        $type = ['price','num'];
        $buy = null;
        //买
        foreach($buy_data as $k=>$v){
            $buy[$k] = array_combine($type,$v);
            $buy[$k]['type'] = 'buy';
            $buy[$k]['currency_id'] = '31';
            $buy[$k]['typebi'] = 1;
        }

        $sell_data = $data['asks'];
        $sell = null;
        //卖
        foreach($sell_data as $k=>$v){
            $sell[$k] = array_combine($type,$v);
            $sell[$k]['type'] = 'sell';
            $sell[$k]['currency_id'] = '31';
            $sell[$k]['typebi'] = 1;
        }
        $array_binance = array('buy'=>$buy,'sell'=>$sell);
        return $array_binance;
    }

    //火币API
    protected function huobi($symbol)
    {
        $header = array('User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0');
        $api = 'https://api.huobi.pro/market/depth?symbol='.$symbol.'&depth=5&type=step0';
        $data1 = $this->http_get($api,[],$header);
        // 把JSON字符串转成PHP数组
        $data = json_decode($data1, true);
        $buy_data = $data['tick']['bids'];
        $type = ['price','num'];
        $buy = null;
        //买
        foreach($buy_data as $k=>$v){
            $buy[$k] = array_combine($type,$v);
            $buy[$k]['type'] = 'buy';
            $buy[$k]['currency_id'] = '31';
            $buy[$k]['typebi'] = 2;
        }

        $sell_data = $data['tick']['asks'];
        $sell = null;
        //卖
        foreach($sell_data as $k=>$v){
            $sell[$k] = array_combine($type,$v);
            $sell[$k]['type'] = 'sell';
            $sell[$k]['currency_id'] = '31';
            $sell[$k]['typebi'] = 2;
        }
        $array_huobi = array('buy'=>$buy,'sell'=>$sell);
        return $array_huobi;
    }

    //okAPI
    protected function ok($symbol)
    {
        $header = array('User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0');
        $api = 'https://www.okex.com/api/spot/v3/instruments/'.$symbol.'/book?size=5&depth=0.1';
        // $api = 'https://www.okex.com/api/spot/v3/instruments/BTC-USDT/book?size=20&depth=0.1';
        $data1 = $this->http_get($api,[],$header);
        // 把JSON字符串转成PHP数组
        $data = json_decode($data1, true);
        $buy_data = $data['bids'];
        $type = ['price','num','a'];
        $buy = null;
        //买
        foreach($buy_data as $k=>$v){
            $buy[$k] = array_combine($type,$v);
            $buy[$k]['type'] = 'buy';
            $buy[$k]['currency_id'] = '31';
            $buy[$k]['typebi'] = 3;
            unset($buy[$k]['a']);
        }

        $sell_data = $data['asks'];
        $sell = null;
        //卖
        foreach($sell_data as $k=>$v){
            $sell[$k] = array_combine($type,$v);
            $sell[$k]['type'] = 'sell';
            $sell[$k]['currency_id'] = '31';
            $sell[$k]['typebi'] = 3;
            unset($sell[$k]['a']);
        }
        $array_ok = array('buy'=>$buy,'sell'=>$sell);
        return $array_ok;
    }


    protected function http_get($url,$params=[],$header=null)
    {
        if($params){
            if(strpos($url,'?')){
                $url.= "&".http_build_query($params);
            }else{
                $url.= "?".http_build_query($params);
            }
        }
        $timeout = 0.2;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if($header!=null){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        $result = curl_exec($ch);
        if($result===false){
            echo "CURL Error: " . curl_error($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }
   
}
