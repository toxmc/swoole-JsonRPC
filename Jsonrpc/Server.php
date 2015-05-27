<?php

/**
 * Jsonrpc 服务端
 * @author smalleyes
 */
namespace Jsonrpc;

class Server {
	
	protected $config = array(
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
		'daemonize' => false	//守护进程改成true
	);
	protected $services = array(); 
	
	public function __construct($ip="0.0.0.0", $port=6666, $mode = SWOOLE_PROCESS)
	{
		$serv = new \swoole_server($ip, $port, $mode);
		$serv->set($this->config);
		$serv->config = $this->config;
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
		echo "MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}\n";
		echo "Server: start.Swoole version is [" . SWOOLE_VERSION . "]\n";
	}
	
	public function log($msg)
	{
		echo "#" . $msg . PHP_EOL;
	}
	
	public function processRename($serv, $worker_id)
	{
		global $argv;
		if ($worker_id >= $serv->setting['worker_num']) {
			swoole_set_process_name("php {$argv[0]}: task");
		} else {
			swoole_set_process_name("php {$argv[0]}: worker");
		}
		echo "WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}";
		echo "|WorkerId={$serv->worker_id}|WorkerPid={$serv->worker_pid}\n";
	}
	
	public function setTimerInWorker(\swoole_server $serv, $worker_id)
	{
		if ($worker_id == 0) {
			echo "Start: " . microtime(true) . "\n";
			$serv->addtimer(3000);
		}
	}
	
	public function onShutdown($serv)
	{
		echo "Server: onShutdown\n";
	}
	
	public function onTimer($serv, $interval)
	{
		echo "Timer#$interval: " . microtime(true) . "\n";
		$serv->task("hello");
	}
	
	public function onClose($serv, $fd, $from_id)
	{
		$this->log("Worker#{$serv->worker_pid} Client[$fd@$from_id]: fd=$fd is closed");
	}
	
	public function onConnect(\swoole_server $serv, $fd, $from_id)
	{
		// 	var_dump($serv->connection_info($fd));
		echo "Worker#{$serv->worker_pid} Client[$fd@$from_id]: Connect.\n";
	}
	
	public function onWorkerStart($serv, $worker_id)
	{
		$this->processRename($serv, $worker_id);
		// setTimerInWorker($serv, $worker_id);
	}
	
	public function onWorkerStop($serv, $worker_id)
	{
		echo "WorkerStop[$worker_id]|pid=" . $serv->worker_pid . ".\n";
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
		if ($data == 'taskwait') {
			$fd = str_replace('task-', '', $data);
			$serv->send($fd, "hello world");
			return array(
				"task" => 'wait'
			);
		} else {
			$fd = str_replace('task-', '', $data);
			$serv->send($fd, "hello world in taskworker.");
			return;
		}
	}
	
	public function onFinish(\swoole_server $serv, $task_id, $data)
	{
		list ($str, $fd) = explode('-', $data);
		$serv->send($fd, 'taskok');
		var_dump($str, $fd);
		echo "AsyncTask Finish: result={$data}. PID=" . $serv->worker_pid . PHP_EOL;
	}
	
	public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
	{
		echo "worker abnormal exit. WorkerId=$worker_id|Pid=$worker_pid|ExitCode=$exit_code\n";
	}
	

}