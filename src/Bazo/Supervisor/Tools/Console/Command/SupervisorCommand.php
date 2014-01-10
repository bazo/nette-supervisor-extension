<?php

namespace Bazo\Supervisor\Tools\Console\Command;

use Indigo\Supervisor\Exception\InvalidResponseException;
use Indigo\Supervisor\Supervisor;
use Symfony\Component\Console;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use InvalidArgumentException;



/**
 * @author Martin Bažík <martin@bazo.sk>
 */
class SupervisorCommand extends Console\Command\Command
{

	const OBJECT_GROUP = 'group';
	const OBJECT_PROCESS = 'process';
	const OBJECT_ALL_PROCESSES = 'allProcesses';
	const ACTION_INFO = 'info';
	const ACTION_STOP = 'stop';
	const ACTION_START = 'start';
	const ACTION_RESTART = 'restart';
	const ACTION_REMOVE = 'remove';



	/** @var Supervisor */
	private $supervisor;
	private $validObjects = [
		self::OBJECT_GROUP,
		self::OBJECT_PROCESS,
		self::OBJECT_ALL_PROCESSES
	];
	private $validActions = [
		self::OBJECT_GROUP => [
			self::ACTION_STOP,
			self::ACTION_START,
			self::ACTION_RESTART,
			self::ACTION_REMOVE
		],
		self::OBJECT_PROCESS => [
			self::ACTION_INFO,
			self::ACTION_STOP,
			self::ACTION_START,
			self::ACTION_RESTART
		],
		self::OBJECT_ALL_PROCESSES => [
			self::ACTION_INFO,
			self::ACTION_START,
			self::ACTION_STOP,
			self::ACTION_RESTART
		]
	];
	private $infoHeaders = ['pid', 'name', 'group', 'statename', 'start', 'stop', 'state'];



	function __construct(Supervisor $supervisor)
	{
		parent::__construct();
		$this->supervisor = $supervisor;
	}


	protected function configure()
	{
		$this->setName('supervisor')
				->addArgument('object', InputArgument::REQUIRED, 'can be group, process, allProcesses')
				->addArgument('action', InputArgument::REQUIRED, 'stop, start, remove')
				->addArgument('name', InputArgument::OPTIONAL, 'name of process or group')
				->setDescription('Simulate weather');
	}


	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$name = NULL;

		try {
			$object = $input->getArgument('object');
			$this->verifyObject($object);
			$action = $input->getArgument('action');
			$this->verifyAction($object, $action);

			if ($this->objectNeedsName($object)) {
				$name = $input->getArgument('name');
				$this->verifyName($object, $name);
			}
			$this->process($object, $action, $name, $output);
		} catch (InvalidArgumentException $e) {
			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
		} catch (InvalidResponseException $e) {
			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
		}
	}


	private function process($object, $action, $name, OutputInterface $output)
	{
		switch ($object) {
			case self::OBJECT_ALL_PROCESSES:
				switch ($action) {
					case self::ACTION_INFO:
						$this->allProcessesInfo($output);
						break;

					case self::ACTION_STOP:
						$this->stopAllProcesses($output);
						break;

					case self::ACTION_START:
						$this->startAllProcesses($output);
						break;

					case self::ACTION_RESTART:
						$this->stopAllProcesses($output);
						$this->startAllProcesses($output);
						break;
				}
				break;

			case self::OBJECT_GROUP:
				switch ($action) {
					case self::ACTION_STOP:
						$this->stopGroup($name, $output);
						break;

					case self::ACTION_START:
						$this->startGroup($name, $output);
						break;

					case self::ACTION_RESTART:
						$this->stopGroup($name, $output);
						$this->startGroup($name, $output);
						break;

					case self::ACTION_REMOVE:
						$output->writeln('<error>this is not implemented. please remove group manually.</error>');
						break;
				}
				break;

			case self::OBJECT_PROCESS:
				switch ($action) {

					case self::ACTION_INFO:
						$this->processInfo($name, $output);
						break;

					case self::ACTION_STOP:
						$this->stopProcess($name, $output);
						break;

					case self::ACTION_START:
						$this->startProcess($name, $output);
						break;

					case self::ACTION_RESTART:
						$this->stopProcess($name, $output);
						$this->startProcess($name, $output);
						break;
				}
				break;
		}
	}


	private function allProcessesInfo(OutputInterface $output)
	{
		$info = $this->supervisor->getAllProcessInfo();

		if (count($info) > 0) {
			$tableHelper = new Console\Helper\TableHelper;

			//$headers = array_keys(current($info));


			$tableHelper->setHeaders($this->infoHeaders);
			foreach ($info as $processInfo) {

				$row = [];
				foreach ($this->infoHeaders as $key) {
					$row[$key] = $processInfo[$key];
				}

				$tableHelper->addRow($row);
			}

			$tableHelper->render($output);
		}
	}


	private function stopAllProcesses(OutputInterface $output)
	{
		$output->writeln('stopping all processes...');
		$this->supervisor->stopAllProcesses();
		$output->writeln('stopped');
	}


	private function startAllProcesses(OutputInterface $output)
	{
		$output->writeln('starting all processes...');
		$this->supervisor->startAllProcesses();
		$output->writeln('started');
	}


	private function stopGroup($group, OutputInterface $output)
	{
		$output->writeln(sprintf('stopping group "%s"...', $group));
		$this->supervisor->stopProcessGroup($group);
		$output->writeln('stopped');
	}


	private function startGroup($group, OutputInterface $output)
	{
		$output->writeln(sprintf('starting group "%s"...', $group));
		$this->supervisor->startProcessGroup($group);
		$output->writeln('started');
	}


	private function processInfo($name, OutputInterface $output)
	{
		$processInfo = $this->supervisor->getProcessInfo($name);

		$tableHelper = new Console\Helper\TableHelper;
		$tableHelper->setHeaders($this->infoHeaders);

		$row = [];
		foreach ($this->infoHeaders as $key) {
			$row[$key] = $processInfo[$key];
		}

		$tableHelper->addRow($row);

		$tableHelper->render($output);
	}


	private function stopProcess($name, OutputInterface $output)
	{
		$output->writeln(sprintf('stopping process "%s"...', $name));
		$this->supervisor->stopProcess($name);
		$output->writeln('stopped');
	}


	private function startProcess($name, OutputInterface $output)
	{
		$output->writeln(sprintf('starting group "%s"...', $name));
		$this->supervisor->startProcess($name);
		$output->writeln('started');
	}


	private function verifyName($object, $name)
	{
		switch ($object) {
			case self::OBJECT_GROUP:
				$this->verifyGroupName($name);
				break;

			case self::OBJECT_PROCESS:
				$this->verifyProcessName($name);
				break;
		}
	}


	private function verifyProcessName($name)
	{

	}


	private function verifyGroupName($name)
	{
		$info = $this->supervisor->getAllProcessInfo();

		$groups = array_map(function($processInfo) {
			return $processInfo['group'];
		}, $info);

		$groups = array_unique($groups);

		if (!in_array($name, $groups)) {
			throw new InvalidArgumentException(sprintf('Group "%s" does not exist.', $name));
		}
	}


	private function verifyObject($value)
	{
		if (!in_array($value, $this->validObjects)) {
			throw new InvalidArgumentException(sprintf('"%s" is not a valid object.', $value));
		}
	}


	private function verifyAction($type, $value)
	{
		if (!in_array($value, $this->validActions[$type])) {
			throw new InvalidArgumentException(sprintf('"%s" is not a valid action for object "%s".', $value, $type));
		}
	}


	private function objectNeedsName($object)
	{
		if ($object === 'group' or $object === 'process') {
			return TRUE;
		}

		return FALSE;
	}


}
