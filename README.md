# swoole-JsonRPC
use swoole for jsonRpc（运用swoole简单实现jsonRPC）
## Requirements

* PHP 5.3+
* Swoole 1.7.16
* Linux, OS X and basic Windows support (Thinks to cygwin)

## Installation Swoole

1. Install via pecl
    
    ```
    pecl install swoole
    ```

2. Install from source

    ```
    sudo apt-get install php5-dev
    git clone https://github.com/swoole/swoole-src.git
    cd swoole-src
    phpize
    ./configure
    make && make install
    ```

## 使用

* 下载swoole-JsonRPC
* 解压swoole-JsonRPC并进入目录
* 服务端执行命令php index.php
* 客户端例子见client/client.php
    ```
  <?php
      include 'RpcClient.php';
    	//配置服务端列表
    	$address_array = array(
    		'127.0.0.1:6666',
    		'127.0.0.1:6666'
    	);
    	RpcClient::config($address_array);
    	
    	//检测是否装了swoole扩展有则用swoole_client方式，没有则用socket方式
    	if (extension_loaded('swoole')) {
    		RpcClient::setSwooleClient();
    	}
    	
    	// User对应/Jsonrpc/Services/User.php 中的User类
    	$user_client = RpcClient::instance('User');
    	// getInfoByUid对应User类中的getInfoByUid方法
    	$uid = 567;
    	$ret_sync = $user_client->getInfoByUid($uid);
    	var_dump($ret_sync);
    	//通过rpc操作redis的例子
    	$redis_client = RpcClient::instance('MyRedis');
    	$set_res = $redis_client->set('jsonrpc','hello json rpc654', 30);
    	var_dump($set_res);
    	$get_res = $redis_client->get('jsonrpc');
    	var_dump($get_res);
    	//调用不存在的函数
    	$list_res = $redis_client->list('jsonrpc');
    	var_dump($list_res);
    ```

