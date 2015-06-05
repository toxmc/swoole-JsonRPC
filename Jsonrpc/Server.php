<?php

/**
 * Jsonrpc 服务端
 * @author smalleyes
 */
namespace Jsonrpc;

class Server {
	
	/**
	 * MasterPid命令时格式化输出
	 * ManagerPid命令时格式化输出
	 * WorkerId命令时格式化输出
	 * WorkerPid命令时格式化输出
	 * @var int
	 */
	protected static $_maxMasterPidLength = 12;
	protected static $_maxManagerPidLength = 12;
	protected static $_maxWorkerIdLength = 12;
	protected static $_maxWorkerPidLength = 12;
	/**
	 * 用于存放实例化过的service类
	 * @var unknown
	 */
	protected $services = array(); 
	
	public function __construct($ip="0.0.0.0", $port=6666, $mode = SWOOLE_PROCESS)
	{
		$serv = new \swoole_server($ip, $port, $mode);
		$serv->config = \Jsonrpc\Config\Server::getConfig();
		$serv->set($serv->config);
		$serv->on('Start', array($this, 'onStart'));
		$serv->on('Connect', array($this, 'onConnect'));
		$serv->on('Receive', array($this, 'onReceive'));
		$serv->on('Close', array($this, 'onClose'));
		$serv->on('Shutdown', array($this, 'onShutdown'));
		$serv->on('Timer', array($this, 'onTimer'));
		$serv->on('WorkerStart', array($this, 'onWorkerStart'));
		$serv->on('WorkerStop', array($this, 'onWorkerStop'));
		$serv->on('Task', array($this, 'onTask'));
		$serv->on('Finish', array($this, 'onFinish'));
		$serv->on('WorkerError', array($this, 'onWorkerError'));
		$serv->on('ManagerStart', function ($serv) {
			global $argv;
			swoole_set_process_name("php {$argv[0]}: manager");
		});
		$serv->start();
	}
	
	public function onStart(\swoole_server $serv)
	{
		global $argv;
		swoole_set_process_name("php {$argv[0]}: master");
		echo "\033[1A\n\033[K-----------------------\033[47;30m SWOOLE \033[0m-----------------------------\n\033[0m";
		echo 'swoole version:' . swoole_version() . "          PHP version:".PHP_VERSION."\n";
		echo "------------------------\033[47;30m WORKERS \033[0m---------------------------\n";
		echo "\033[47;30mMasterPid\033[0m", str_pad('', self::$_maxMasterPidLength + 2 - strlen('MasterPid')), "\033[47;30mManagerPid\033[0m", str_pad('', self::$_maxManagerPidLength + 2 - strlen('ManagerPid')), "\033[47;30mWorkerId\033[0m", str_pad('', self::$_maxWorkerIdLength + 2 - strlen('WorkerId')),  "\033[47;30mWorkerPid\033[0m\n";
	}
	
	public function log($msg)
	{
		echo "#" . $msg . PHP_EOL;
	}
	
	public function processRename($serv, $worker_id)
	{
		global $argv;
		$worker_num = isset($serv->setting['worker_num']) ? $serv->setting['worker_num'] : 1;
		$task_worker_num = isset($serv->setting['task_worker_num']) ? $serv->setting['task_worker_num'] : 0;
		
		if ($worker_id >= $worker_num) {
			swoole_set_process_name("php {$argv[0]}: task");
		} else {
			swoole_set_process_name("php {$argv[0]}: worker");
		}
		echo str_pad($serv->master_pid, self::$_maxMasterPidLength+2),
			  str_pad($serv->manager_pid, self::$_maxManagerPidLength+2),
			  str_pad($serv->worker_id, self::$_maxWorkerIdLength+2),
			  str_pad($serv->worker_pid, self::$_maxWorkerIdLength), "\n";
	}
	
	public function setTimerInWorker(\swoole_server $serv, $worker_id)
	{
		if ($worker_id == 0) {
			echo "Start: " . microtime(true) . "\n";
			$serv->addtimer(3000);
		}
	}

	
	public function onTimer($serv, $interval)
	{
		echo "Timer#$interval: " . microtime(true) . "\n";
		$serv->task("hello");
	}

	
	public function onConnect(\swoole_server $serv, $fd, $from_id)
	{
		echo "Worker#{$serv->worker_pid} Client[$fd@$from_id]: Connect.\n";
	}
	
	public function onWorkerStart($serv, $worker_id)
	{
		$this->processRename($serv, $worker_id);
		// setTimerInWorker($serv, $worker_id);
	}

	
	public function onReceive(\swoole_server $serv, $fd, $from_id, $data)
	{
		$protocal = new Protocols\Json();
		$data = $protocal->decode($data);
		// 判断数据是否正确
		if(empty($data['class']) || empty($data['method']) || !isset($data['param_array'])) {
			// 发送数据给客户端，请求包错误
			return $serv->send($fd,$protocal->encode(array('code'=>400, 'msg'=>'bad request', 'data'=>null)));
		}
		// 获得要调用的类、方法、及参数
		$class = $data['class'];
		$method = $data['method'];
		$param_array = $data['param_array'];
		
		$success = false;
		// 判断类对应文件是否载入
		if (!isset($this->services[$class]) OR empty($this->services[$class])) {
			if (! class_exists($class)) {
				$include_file = __DIR__ . "/Services/$class.php";
				if (is_file($include_file)) {
					require_once $include_file;
				}
				if (! class_exists($class)) {
					$code = 404;
					$msg = "class $class not found";
					// 发送数据给客户端 类不存在
					return $serv->send($fd,$protocal->encode(array('code' => $code, 'msg' => $msg, 'data' => null)));
				}
				$this->services[$class] = new $class();
			}
		}
		
		// 调用类的方法
		if (method_exists($this->services[$class], $method)) {
			$ret = call_user_func_array(array($this->services[$class], $method), $param_array);
			// 发送数据给客户端，调用成功，data下标对应的元素即为调用结果
			return $serv->send($fd,$protocal->encode(array('code' => 0, 'msg' => 'ok', 'data' => $ret)));
		} else {
			return $serv->send($fd,$protocal->encode(array('code' => 404, 'msg' => "method $method not found", 'data' => null)));
		}
		
	}
	
	public function onTask(\swoole_server $serv, $task_id, $from_id, $data)
	{
		//这里是task任务的回调函数
		//一些处理时间比较长的流程可以放在这里执行
		echo "this is onTask\n";
		var_dump($data);
	}
	
	public function onFinish(\swoole_server $serv, $task_id, $data)
	{
		//ontask执行完毕自动调用onFinish
		echo "taskid={$task_id} is over\n";
	}

	public function onClose($serv, $fd, $from_id)
	{
		$this->log("Worker#{$serv->worker_pid} Client[$fd@$from_id]: fd=$fd is closed");
	}

	public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
	{
		echo "worker abnormal exit. WorkerId=$worker_id|Pid=$worker_pid|ExitCode=$exit_code\n";
	}
	
	public function onWorkerStop($serv, $worker_id)
	{
		echo "WorkerStop[$worker_id]|pid=" . $serv->worker_pid . ".\n";
	}
	
	
	public function onShutdown($serv)
	{
		echo "Server: onShutdown\n";
	}
	

}