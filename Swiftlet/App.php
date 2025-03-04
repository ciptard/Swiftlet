<?php

namespace Swiftlet;

class App implements Interfaces\App
{
	protected
		$action     = 'index',
		$args       = array(),
		$config     = array(),
		$controller,
		$hooks      = array(),
		$plugins    = array(),
		$rootPath   = '/',
		$singletons = array(),
		$view
		;

	/**
	 * Run the application
	 */
	public function run()
	{
		// Determine the client-side path to root
		if ( !empty($_SERVER['REQUEST_URI']) ) {
			$this->rootPath = preg_replace('/(index\.php)?(\?.*)?$/', '', $_SERVER['REQUEST_URI']);

			if ( !empty($_GET['q']) ) {
				$this->rootPath = preg_replace('/' . preg_quote($_GET['q'], '/') . '$/', '', $this->rootPath);
			}
		}

		// Extract controller name, view name, action name and arguments from URL
		$controllerName = 'Index';

		if ( !empty($_GET['q']) ) {
			$this->args = explode('/', $_GET['q']);

			if ( $this->args ) {
				$controllerName = str_replace(' ', '/', ucwords(str_replace('_', ' ', array_shift($this->args))));
			}

			if ( $action = $this->args ? array_shift($this->args) : '' ) {
				$this->action = $action;
			}
		}

		if ( !is_file('Swiftlet/Controllers/' . $controllerName . '.php') ) {
			$controllerName = 'Error404';
		}

		$this->view = new View($this, strtolower($controllerName));

		// Instantiate the controller
		$controllerName = 'Swiftlet\Controllers\\' . basename($controllerName);

		$this->controller = new $controllerName($this, $this->view);

		// Load plugins
		if ( $handle = opendir('Swiftlet/Plugins') ) {
			while ( ( $file = readdir($handle) ) !== FALSE ) {
				if ( is_file('Swiftlet/Plugins/' . $file) && preg_match('/^(.+)\.php$/', $file, $match) ) {
					$pluginName = 'Swiftlet\Plugins\\' . $match[1];

					$this->plugins[$pluginName] = array();

					foreach ( get_class_methods($pluginName) as $methodName ) {
						$method = new \ReflectionMethod($pluginName, $methodName);

						if ( $method->isPublic() && !$method->isFinal() && !$method->isConstructor() ) {
							$this->plugins[$pluginName][] = $methodName;
						}
					}
				}
			}

			ksort($this->plugins);

			closedir($handle);
		}

		// Call the controller action
		$this->registerHook('actionBefore');

		if ( method_exists($this->controller, $this->action) ) {
			$method = new \ReflectionMethod($this->controller, $this->action);

			if ( $method->isPublic() && !$method->isFinal() && !$method->isConstructor() ) {
				$this->controller->{$this->action}();
			} else {
				$this->controller->notImplemented();
			}
		} else {
			$this->controller->notImplemented();
		}

		$this->registerHook('actionAfter');

		return array($this->view, $this->controller);
	}

	/**
	 * Serve the page
	 */
	public function serve()
	{
		$this->view->render();
	}

	/**
	 * Get a configuration value
	 * @param string $variabl
	 * @return mixed
	 */
	public function getConfig($variable)
   	{
		if ( isset($this->config[$variable]) ) {
			return $this->config[$variable];
		}
	}

	/**
	 * Set a configuration value
	 * @param string $variable
	 * @param mixed $value
	 */
	public function setConfig($variable, $value)
   	{
		$this->config[$variable] = $value;
	}

	/**
	 * Get the client-side path to root
	 * @return string
	 */
	public function getRootPath()
	{
		return $this->rootPath;
	}

	/**
	 * Get the action name
	 * @return string
	 */
	public function getAction()
   	{
		return $this->action;
	}

	/**
	 * Get the arguments
	 * @return array
	 */
	public function getArgs()
   	{
		return $this->args;
	}

	/**
	 * Get a model
	 * @param string $modelName
	 * @return object
	 */
	public function getModel($modelName)
   	{
		$modelName = 'Swiftlet\Models\\' . ucfirst($modelName);

		// Instantiate the model
		return new $modelName($this, $this->view, $this->controller);
	}

	/**
	 * Get a model singleton
	 * @param string $modelName
	 * @return object
	 */
	public function getSingleton($modelName)
	{
		if ( isset($this->singletons[$modelName]) ) {
			return $this->singletons[$modelName];
		}

		$model = $this->getModel($modelName);

		$this->singletons[$modelName] = $model;

		return $model;
	}

	/**
	 * Register a hook for plugins to implement
	 * @param string $hookName
	 * @param array $params
	 */
	public function registerHook($hookName, array $params = array())
	{
		$this->hooks[] = $hookName;

		foreach ( $this->plugins as $pluginName => $hooks ) {
			if ( in_array($hookName, $hooks) ) {
				$plugin = new $pluginName($this, $this->view, $this->controller);

				$plugin->{$hookName}($params);
			}
		}
	}

	/**
	 * Class autoloader
	 * @param $className
	 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
	 */
	public function autoload($className)
	{
		preg_match('/(^.+\\\)?([^\\\]+)$/', ltrim($className, '\\'), $match);

		$file = str_replace('\\', '/', $match[1]) . str_replace('_', '/', $match[2]) . '.php';

		require $file;
	}

	/**
	 * Error handler
	 * @param int $number
	 * @param string $string
	 * @param string $file
	 * @param int $line
	 */
	public function error($number, $string, $file, $line)
	{
		throw new \Exception('Error #' . $number . ': ' . $string . ' in ' . $file . ' on line ' . $line);
	}
}
