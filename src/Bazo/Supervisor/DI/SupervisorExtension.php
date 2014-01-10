<?php

namespace Bazo\Supervisor\DI;

/**
 * @author Martin Bažík <martin@bazo.sk>
 */
class SupervisorExtension extends \Nette\DI\CompilerExtension
{

	/** @var array */
	public $defaults = [
		'host' => '127.0.0.1',
		'port' => 9001,
		'username' => NULL,
		'password' => NULL
	];



	/**
	 * Processes configuration data
	 *
	 * @return void
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults, TRUE);

		$container->addDefinition($this->prefix('connector'))
				->setClass('\Indigo\Supervisor\Connector\InetConnector', [$config['host'], $config['port']])
				->addSetup('setCredentials', [$config['username'], $config['password']]);

		$container->addDefinition($this->prefix('client'))
				->setClass('\Indigo\Supervisor\Supervisor');

		$container->addDefinition($this->prefix('command'))
				->setClass('\Bazo\Supervisor\Tools\Console\Command\SupervisorCommand')
				->addTag('console.command')
				->addTag('kdyby.console.command')
				->setAutowired(FALSE);
	}


}
