<?php

/**
 *  测试
 */
class User
{

	public static function getInfoByUid($uid)
	{
		return array(
			'uid' => $uid,
			'name' => '小萝莉',
			'age' => 18,
			'sex' => '女'
		);
	}

	public static function getEmail($uid)
	{
		return 'test@test.com';
	}
}
