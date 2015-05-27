<?php

/**
 *  测试
 */
class MyRedis
{
	private $redis;

	public function __construct()
	{
		$this->redis = new \Redis();
		$this->redis->connect('127.0.0.1',6379);
	}
	
	
	
	public function get($key)
	{
		if (empty($key)) {
			return '参数不能为空';
		}
		return $this->redis->get($key);
	}

	public function set($key, $data, $expire=0)
	{
		if (empty($key)) {
			return '参数不能为空';
		}
		return $this->redis->set($key, $data, $expire);
	}
}
