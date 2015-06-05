<?php
/**
 * Jsonrpc server配置
 * @author smalleyes
 */
namespace Jsonrpc\Config;

class Server
{

	/**
	 * 获取服务器配置
	 * @return multitype:number boolean string
	 */
	public static function getConfig()
	{
		return array(
			'worker_num' => 4,
			'open_eof_check' => true,
			'package_eof' => "\r\n",
			'task_ipc_mode' => 2,
			'task_worker_num' => 2,
			'user' => 'xmc',
			'group' => 'xmc',
			'log_file' => 'log/rpc.log',
			'heartbeat_check_interval' => 300,
			'heartbeat_idle_time' => 300,
			'daemonize' => false // 守护进程改成true
		);
	}
}