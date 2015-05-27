<?php

namespace Jsonrpc\Protocols;

/**
 * RPC 协议解析 相关
 * 协议格式为 [json字符串\r\n]
 */
class Json
{

	public static $instance;
	
	/**
	 * 初始化
	 * @return \Protocols\Json
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			$instance = new self();
			self::$instance = $instance;
		}
		return self::$instance;
	}

	/**
	 * 打包，当向客户端发送数据的时候会自动调用
	 * 
	 * @param string $buffer        	
	 * @return string
	 */
	public static function encode($buffer)
	{
		// json序列化，并加上换行符作为请求结束的标记
		return json_encode($buffer)."\r\n";
	}

	/**
	 * 解包，当接收到的数据字节数等于input返回的值（大于0的值）自动调用
	 * 并传递给onMessage回调函数的$data参数
	 * 
	 * @param string $buffer        	
	 * @return string
	 */
	public static function decode($buffer)
	{
		// 去掉换行，还原成数组
		return json_decode(trim($buffer), true);
	}
}
