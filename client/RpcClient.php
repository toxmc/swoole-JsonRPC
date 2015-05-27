<?php

if (! class_exists('JsonProtocol')) {
	createJsonProtocol();
}

/**
 * RpcClient Rpc客户端
 */
class RpcClient
{

	/**
	 * 发送数据和接收数据的超时时间 单位S
	 * @var integer
	 */
	const TIME_OUT = 1;

	/**
	 * swoole客户端设置
	 */
	protected $swooleClientSets = array(
		'open_eof_check' => true, 
		'package_eof' => "\r\n"
	);
	
	/**
	 * 服务端地址
	 * @var array
	 */
	protected static $addressArray = array();

	/**
	 * 异步调用实例
	 * @var string
	 */
	protected static $asyncInstances = array();

	/**
	 * 同步调用实例
	 * @var string
	 */
	protected static $instances = array();

	/**
	 * 到服务端的socket连接
	 * @var resource
	 */
	protected $connection = null;
	
	/**
	 * 到服务端的swoole client连接
	 * @var resource
	 */
	protected $swooleClient = null;

	/**
	 * 实例的服务名
	 * @var string
	 */
	protected $serviceName = '';
	
	/**
	 * 使用swoole方式
	 * @var string
	 */
	protected static $useSwoole = false;

	/**
	 * 设置/获取服务端地址
	 * 
	 * @param array $address_array        	
	 */
	public static function config($address_array = array())
	{
		if (! empty($address_array)) {
			self::$addressArray = $address_array;
		}
		return self::$addressArray;
	}

	/**
	 * 获取一个实例
	 * 
	 * @param string $service_name        	
	 * @return instance of RpcClient
	 */
	public static function instance($service_name)
	{
		if (! isset(self::$instances[$service_name])) {
			self::$instances[$service_name] = new self($service_name);
		}
		return self::$instances[$service_name];
	}

	/**
	 * 构造函数
	 * 
	 * @param string $service_name        	
	 */
	protected function __construct($service_name)
	{
		$this->serviceName = $service_name;
	}

	public static function setSwooleClient()
	{
		self::$useSwoole = true;
	}
	
	/**
	 * 调用
	 * 
	 * @param string $method        	
	 * @param array $arguments        	
	 * @throws Exception
	 * @return
	 *
	 */
	public function __call($method, $arguments)
	{
		// 同步发送接收
		$this->sendData($method, $arguments);
		return $this->recvData();
	}

	/**
	 * 发送数据给服务端
	 * 
	 * @param string $method        	
	 * @param array $arguments        	
	 */
	public function sendData($method, $arguments)
	{
		$bin_data = JsonProtocol::encode(array(
			'class' => $this->serviceName,
			'method' => $method,
			'param_array' => $arguments
		))."\r\n";
		$this->openConnection();
		if (self::$useSwoole) {
			return $this->swooleClient->send($bin_data);
		} else {
			return fwrite($this->connection, $bin_data) == strlen($bin_data);
		}
	}

	/**
	 * 从服务端接收数据
	 * 
	 * @throws Exception
	 */
	public function recvData()
	{
		if (self::$useSwoole) {
			$res = $this->swooleClient->recv();
			$this->swooleClient->close();
		} else {
			$res = fgets($this->connection);
			$this->closeConnection();
		}
		if (! $res) {
			throw new Exception("recvData empty");
		}
		return JsonProtocol::decode($res);
	}

	/**
	 * 打开到服务端的连接
	 * 
	 * @return void
	 */
	protected function openConnection()
	{
		$address = self::$addressArray[array_rand(self::$addressArray)];
		if (self::$useSwoole) {
			$address = explode(':', $address);
			$this->swooleClient = new swoole_client(SWOOLE_SOCK_TCP);
			$this->swooleClient->set($this->swooleClientSets);
			if (!$this->swooleClient->connect($address[0], $address[1], self::TIME_OUT)) {
				exit("connect failed. Error: {$this->swooleClient->errCode}\n");
			}
		} else {
			$this->connection = stream_socket_client($address, $err_no, $err_msg);
			if (! $this->connection) {
				throw new Exception("can not connect to $address , $err_no:$err_msg");
			}
			stream_set_blocking($this->connection, true);
			stream_set_timeout($this->connection, self::TIME_OUT);
		}
	}

	/**
	 * 关闭到服务端的连接
	 * 
	 * @return void
	 */
	protected function closeConnection()
	{
		fclose($this->connection);
		$this->connection = null;
	}
}

function createJsonProtocol()
{

	/**
	 * RPC 协议解析 相关
	 * 协议格式为 [json字符串\n]
	 * 
	 * @author walkor <worker-man@qq.com>
	 *        
	 */
	class JsonProtocol
	{
		/**
		 * 将数据打包成Rpc协议数据
		 * 
		 * @param mixed $data        	
		 * @return string
		 */
		public static function encode($data)
		{
			return json_encode($data);
		}

		/**
		 * 解析Rpc协议数据
		 * 
		 * @param string $bin_data        	
		 * @return mixed
		 */
		public static function decode($bin_data)
		{
			return json_decode(trim($bin_data), true);
		}
	}
}