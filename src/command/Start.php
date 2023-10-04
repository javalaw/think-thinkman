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
            $output->writeln('Starting Workerman http server...');
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