<?php

namespace Jsonrpc\Protocols;

/**
 * RPC 协议解析
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
	 * 打包
	 * @param string $buffer        	
	 * @return string
	 */
	public static function encode($buffer)
	{
		// json序列化，并加上换行符作为请求结束的标记
		return json_encode($buffer)."\r\n";
	}

	/**
	 * 解包
	 * @param string $buffer        	
	 * @return string
	 */
	public static function decode($buffer)
	{
		// 去掉换行，还原成数组
		return json_decode(trim($buffer), true);
	}
}
