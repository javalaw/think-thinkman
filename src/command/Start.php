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

namespace think\thinkman\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\thinkman\ThinkMan;

class Start extends Command
{
	/**
     * 配置
     * @access protected
     * @return void
     */
	protected function configure()
	{
		// 指令配置
		$this->setName('thinkman')
			->addArgument('action', Argument::OPTIONAL, 'start|stop|restart|reload|status|connections', 'start')
			->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the thinkman in daemon mode.')
			->setDescription('A Simple Web Framework For ThinkPHP');
	}

	/**
     * 命令执行
     * @access protected
     * @param Input $input 输入
     * @param Output $output 输出
     * @return void
     */
	protected function execute(Input $input, Output $output)
	{
		// 需要启用的函数
		$enableFunctions = [
			'stream_socket_server',
			'stream_socket_client',
			'pcntl_signal_dispatch',
			'pcntl_signal',
			'pcntl_alarm',
			'pcntl_fork',
			'posix_getuid',
			'posix_getpwuid',
			'posix_kill',
			'posix_setsid',
			'posix_getpid',
			'posix_getpwnam',
			'posix_getgrnam',
			'posix_getgid',
			'posix_setgid',
			'posix_initgroups',
			'posix_setuid',
			'posix_isatty',
			'pcntl_wait'
		];
		// 当前禁用的函数
		$disableFunctions = explode(',', ini_get('disable_functions'));
		foreach ($enableFunctions as $item) {
			if (in_array($item, $disableFunctions, true)) {
				$output->writeln('<error>function [' . $item . '] not enabled, workerman failed to successfully start.</error>');
				return;
			}
		}
		// 获取当前参数
		$action = $input->getArgument('action');
		// 如果是linux系统
		if (DIRECTORY_SEPARATOR !== '\\') {
            if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
                $output->writeln('<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>');
                return false;
            }

			global $argv;
			array_shift($argv);
			array_shift($argv);
			array_unshift($argv, 'think', $action);
        }
		// windows只支持start方法
		elseif ('start' != $action) {
            $output->writeln('<error>Not Support action:{$action} on Windows.</error>');
            return false;
        }

		if ('start' == $action) {
            $output->writeln('Starting thinkman...');
        }

		// 获取当前配置
		$config = $this->app->config->get('thinkman');
		
		// 实例化
		$thinkman = new ThinkMan($config);

		if (DIRECTORY_SEPARATOR == '\\') {
            $output->writeln('You can exit with <info>`CTRL-C`</info>');
        }

		// 启动
		$thinkman->start();
	}
}