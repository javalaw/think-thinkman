<?php
// +----------------------------------------------------------------------
// | ThinkMan [A Simple Web Framework For ThinkPHP]
// +----------------------------------------------------------------------
// | ThinkMan
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: axguowen <axguowen@qq.com>
// +----------------------------------------------------------------------

return [
	// 监听地址
	'host' => '0.0.0.0',
	// 监听端口
	'port' => 1246,
	// 上下文
	'context' => [],
	// 是否以守护进程启动
	'daemonize' => false,
	// 内容输出文件路径
	'stdout_file' => '',
	// pid文件路径
	'pid_file' => '',
	// 日志文件路径
	'log_file' => '',
	// 是否开启PHP文件更改监控(仅Linux下有效)
	'file_monitor_status' => false,
	// 文件监控检测时间间隔(单位：秒)
	'file_monitor_interval' => 2,
	// 文件监控目录, 默认监控app和config目录
	'file_monitor_paths' => [],
	// Worker配置
	'worker' => [
		// 进程名称
		'name' => 'thinkman',
		// 进程数量
		'count' => 1,
		// 支持workerman的其它配置参数
	]
];