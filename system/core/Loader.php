<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2012, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Loader Class
 *
 * Loads framework components.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Loader
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/loader.html
 */
class CI_Loader {

	// All these are set automatically. Don't mess with them.
	/**
	 * CodeIgniter core
	 *
	 * @var	object
	 */
	protected $CI;

	/**
	 * Nesting level of the output buffering mechanism
	 *
	 * @var	int
	 */
	protected $_ci_ob_level;

	/**
	 * Autoload config array
	 *
	 * @var	array
	 */
	protected $_ci_autoload;

	/**
	 * List of paths to load libraries/helpers from
	 *
	 * @var	array
	 */
	protected $_ci_library_paths	= array();

	/**
	 * List of paths to load models/viewers/controllers from
	 *
	 * @var	array
	 */
	protected $_ci_mvc_paths		= array();

	/**
	 * List of loaded base classes
	 *
	 * @var	array
	 */
	protected $_base_classes =	array(); // Set by the controller class

	/**
	 * List of cached variables
	 *
	 * @var	array
	 */
	protected $_ci_cached_vars =	array();

	/**
	 * List of loaded classes
	 *
	 * @var	array
	 */
	protected $_ci_classes =	array();

	/**
	 * List of loaded files
	 *
	 * @var	array
	 */
	protected $_ci_loaded_files =	array();

	/*
	 * List of loaded controllers
	 *
	 * @var	array
	 */
	protected $_ci_controllers		= array();

	/*
	 * List of loaded models
	 *
	 * @var	array
	 */
	protected $_ci_models =	array();

	/**
	 * List of loaded helpers
	 *
	 * @var	array
	 */
	protected $_ci_helpers =	array();

	/**
	 * List of class name mappings
	 *
	 * @var	array
	 */
	protected $_ci_varmap =	array(
		'unit_test' => 'unit',
		'user_agent' => 'agent'
	);

	/**
	 * Class constructor
	 *
	 * Sets default package paths, gets the initial output buffering level,
	 * and autoloads additional paths and config files
	 *
	 * @return	void
	 */
	public function __construct()
	{
		// Attach parent reference
		$this->CI =& CodeIgniter::instance();

		$this->_ci_ob_level = ob_get_level();
		$this->_ci_library_paths = array(APPPATH, BASEPATH);
		$this->_ci_mvc_paths = array(APPPATH => TRUE);

		// Fetch autoloader array
		$autoload = $this->CI->config->get('autoload.php', 'autoload');
		if (is_array($autoload))
		{
			// Save config for ci_autoload
			$this->_ci_autoload = $autoload;

			// Autoload packages
			if (isset($autoload['packages']))
			{
				foreach ($autoload['packages'] as $package_path)
				{
					$this->add_package_path($package_path);
				}
			}

			// Load any custom config files
			if (count($autoload['config']) > 0)
			{
				foreach ($autoload['config'] as $key => $val)
				{
					$this->CI->config->load($val);
				}
			}
		}

		log_message('debug', 'Loader Class Initialized');
	}

	// --------------------------------------------------------------------

	/**
	 * Is Loaded
	 *
	 * A utility method to test if a class is in the self::$_ci_classes array.
	 *
	 * @used-by	Mainly used by Form Helper function _get_validation_object().
	 *
	 * @param 	string		$class	Class name to check for
	 * @return 	string|bool	Class object name if loaded or FALSE
	 */
	public function is_loaded($class)
	{
		return isset($this->_ci_classes[$class]) ? $this->_ci_classes[$class] : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Library Loader
	 *
	 * Loads and instantiates libraries.
	 * Designed to be called from application controllers.
	 *
	 * @param	string	$library	Library name
	 * @param	array	$params		Optional parameters to pass to the library class constructor
	 * @param	string	$object_name	An optional object name to assign to
	 * @return	void
	 */
	public function library($library = '', $params = NULL, $object_name = NULL)
	{
		if (is_array($library))
		{
			foreach ($library as $class)
			{
				$this->library($class, $params);
			}

			return;
		}

		if ($library === '' OR isset($this->_base_classes[$library]))
		{
			return;
		}

		if ( ! is_null($params) && ! is_array($params))
		{
			$params = NULL;
		}

		$this->_ci_load_class($library, $params, $object_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Controller Loader
	 *
	 * This function lets users load and instantiate (sub)controllers.
	 *
	 * @access	public
	 * @param	string	the name of the class
	 * @param	string	name for the controller
	 * @param	bool	FALSE to skip calling controller method
	 * @param	bool	TRUE to return output (depends on $call == TRUE)
	 * @return	mixed	Output if $return, TRUE on success, otherwise FALSE
	 */
	public function controller($route, $name = NULL, $call = TRUE, $return = FALSE)
	{
		// Check for missing class
		if (empty($route))
		{
			return FALSE;
		}

		// Get instance and establish segment stack
		if (is_array($route))
		{
			// Assume segments have been pre-parsed by CI_Router::validate_route() - make sure there's 4
			if (count($route) <= CI_Router::SEG_METHOD)
			{
				return FALSE;
			}
		}
		else
		{
			// Call validate_route() to break URI into segments
			$route = $this->CI->router->validate_route(explode('/', $route));
			if ($route === FALSE)
			{
				return FALSE;
			}
		}

		// Extract segment parts
		$path = array_shift($route);
		$subdir = array_shift($route);
		$class = array_shift($route);
		$method = array_shift($route);

		// Set name if not provided
		if (empty($name))
		{
			$name = strtolower($class);
		}

		// Check if already loaded
		if ( ! in_array($name, $this->_ci_controllers, TRUE))
		{
			// Check for name conflict
			if (isset($this->CI->$name))
			{
				$msg = 'The controller name you are loading is the name of a resource that is already being used: '.
					$name;
				if ($name == 'routed')
				{
					// This could be a request from Exceptions - avoid recursive calls to show_error
					exit($msg);
				}
				show_error($msg);
			}

			// Load base class(es) if not already done
			if ( ! class_exists('CI_Controller'))
			{
				// Locate base class
				foreach ($this->_ci_library_paths as $lib_path)
				{
					$file = $lib_path.'core/Controller.php';
					if (file_exists($file))
					{
						// Include class source
						include $file;
						break;
					}
				}

				// Check for subclass
				$pre = $this->CI->config->item('subclass_prefix');
				if ( ! empty($pre))
				{
					// Locate subclass
					foreach ($this->_ci_mvc_paths as $mvc_path => $cascade)
					{
						$file = $mvc_path.'core/'.$pre.'Controller.php';
						if (file_exists($file))
						{
							// Include class source
							include($file);
							break;
						}
					}
				}
			}

			// Include source and instantiate object
			include($path.'controllers/'.$subdir.'/'.strtolower($class).'.php');
			$classnm = ucfirst($class);
			$this->CI->$name = new $classnm();

			// Mark as loaded
			$this->_ci_controllers[] = $name;
		}

		// Call method unless disabled
		if ($call)
		{
			return $this->CI->call_controller($class, $method, $route, $name, $return);
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Model Loader
	 *
	 * Loads and instantiates libraries.
	 *
	 * @param	string	$model		Model name
	 * @param	string	$name		An optional object name to assign to
	 * @param	bool	$db_conn	An optional database connection configuration to initialize
	 * @return	void
	 */
	public function model($model, $name = '', $db_conn = FALSE)
	{
		if (empty($model))
		{
			return;
		}
		elseif (is_array($model))
		{
			foreach ($model as $class)
			{
				$this->model($class);
			}
			return;
		}

		$path = '';

		// Is the model in a sub-folder? If so, parse out the filename and path.
		if (($last_slash = strrpos($model, '/')) !== FALSE)
		{
			// The path is in front of the last slash
			$path = substr($model, 0, ++$last_slash);

			// And the model name behind it
			$model = substr($model, $last_slash);
		}

		// Set name if not provided
		if (empty($name))
		{
			$name = $model;
		}

		// Check if already loaded
		if (in_array($name, $this->_ci_models, TRUE))
		{
			return;
		}

		// Check for name conflict
		if (isset($this->CI->$name))
		{
			show_error('The model name you are loading is the name of a resource that is already being used: '.$name);
		}

		// Load database if needed
		if ($db_conn !== FALSE && ! class_exists('CI_DB'))
		{
			if ($db_conn === TRUE)
			{
				$db_conn = '';
			}

			$this->database($db_conn, FALSE, TRUE);
		}

		// Load base class(es) if not already done
		if ( ! class_exists('CI_Model'))
		{
			// Locate base class
			foreach ($this->_ci_library_paths as $lib)
			{
				$file = $lib.'core/Model.php';
				if (file_exists($file))
				{
					// Include class source
					include($file);
					break;
				}
			}

			// Check for subclass
			$pre = $this->CI->config->item('subclass_prefix');
			if (!empty($pre))
			{
				// Locate subclass
				foreach ($this->_ci_mvc_paths as $lib => $cascade)
				{
					$file = $lib.'core/'.$pre.'Model.php';
					if (file_exists($file))
					{
						// Include class source
						include($file);
						break;
					}
				}
			}
		}

		// Search MVC paths for model
		$model = strtolower($model);
		$file = 'models/'.$path.$model.'.php';
		foreach ($this->_ci_mvc_paths as $mod_path => $view_cascade)
		{
			// Check each path for filename
			if ( ! file_exists($mod_path.$file))
			{
				continue;
			}

			// Include source and instantiate object
			require_once($mod_path.$file);

			$model = ucfirst($model);
			$this->CI->$name = new $model();
			$this->_ci_models[] = $name;
			return;
		}

		// Couldn't find the model
		show_error('Unable to locate the model you have specified: '.$model);
	}

	// --------------------------------------------------------------------

	/**
	 * Database Loader
	 *
	 * @param	mixed	$params		Database configuration options
	 * @param	bool	$return 	Whether to return the database object
	 * @param	bool	$query_builder	Whether to enable Query Builder
	 *					(overrides the configuration setting)
	 *
	 * @return	void|object|bool	Database object if $return is set to TRUE,
	 *					FALSE on failure, void in any other case
	 */
	public function database($params = '', $return = FALSE, $query_builder = NULL)
	{
		// Do we even need to load the database class?
		if ($return === FALSE && $query_builder === NULL && isset($this->CI->db) && is_object($this->CI->db) && ! empty($this->CI->db->conn_id))
		{
			return FALSE;
		}

		require_once(BASEPATH.'database/DB.php');

		if ($return === TRUE)
		{
			return DB($params, $query_builder);
		}

		// Initialize the db variable. Needed to prevent
		// reference errors with some configurations
		$this->CI->db = '';

		// Load the DB class
		$this->CI->db =& DB($params, $query_builder);
	}

	// --------------------------------------------------------------------

	/**
	 * Load the Database Utilities Class
	 *
	 * @param	object	$db	Database object
	 * @param	bool	$return	Whether to return the DB Forge class object or not
	 * @return	void|object
	 */
	public function dbutil($db = NULL, $return = FALSE)
	{
		if ( ! is_object($db) OR ! ($db instanceof CI_DB))
		{
			class_exists('CI_DB', FALSE) OR $this->database();
			$db =& $this->CI->db;
		}

		require_once(BASEPATH.'database/DB_utility.php');
		require_once(BASEPATH.'database/drivers/'.$db->dbdriver.'/'.$db->dbdriver.'_utility.php');
		$class = 'CI_DB_'.$db->dbdriver.'_utility';

		if ($return === TRUE)
		{
			return new $class($db);
		}

		$this->CI->dbutil = new $class($db);
	}

	// --------------------------------------------------------------------

	/**
	 * Load the Database Forge Class
	 *
	 * @param	object	$db	Database object
	 * @param	bool	$return	Whether to return the DB Forge class object or not
	 * @return	void|object
	 */
	public function dbforge($db = NULL, $return = FALSE)
	{
		if ( ! is_object($db) OR ! ($db instanceof CI_DB))
		{
			class_exists('CI_DB', FALSE) OR $this->database();
			$db =& $this->CI->db;
		}

		require_once(BASEPATH.'database/DB_forge.php');
		require_once(BASEPATH.'database/drivers/'.$db->dbdriver.'/'.$db->dbdriver.'_forge.php');

		if ( ! empty($db->subdriver))
		{
			$driver_path = BASEPATH.'database/drivers/'.$db->dbdriver.'/subdrivers/'.$db->dbdriver.'_'.$db->subdriver.'_forge.php';
			if (file_exists($driver_path))
			{
				require_once($driver_path);
				$class = 'CI_DB_'.$db->dbdriver.'_'.$db->subdriver.'_forge';
			}
		}
		else
		{
			$class = 'CI_DB_'.$db->dbdriver.'_forge';
		}

		if ($return === TRUE)
		{
			return new $class($db);
		}

		$this->CI->dbforge = new $class($db);
	}

	// --------------------------------------------------------------------

	/**
	 * View Loader
	 *
	 * Loads "view" files.
	 *
	 * @param	string	$view	View name
	 * @param	array	$vars	An associative array of data
	 *				to be extracted for use in the view
	 * @param	bool	$return	Whether to return the view output
	 *				or leave it to the Output class
	 * @return	void
	 */
	public function view($view, $vars = array(), $return = FALSE)
	{
		return $this->_ci_load(array('_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_return' => $return));
	}

	// --------------------------------------------------------------------

	/**
	 * Generic File Loader
	 *
	 * @param	string	$path	File path
	 * @param	bool	$return	Whether to return the file output
	 * @return	void|string
	 */
	public function file($path, $return = FALSE)
	{
		return $this->_ci_load(array('_ci_path' => $path, '_ci_return' => $return));
	}

	// --------------------------------------------------------------------

	/**
	 * Set Variables
	 *
	 * Once variables are set they become available within
	 * the controller class and its "view" files.
	 *
	 * @param	array|object|string	$vars
	 *					An associative array or object containing values
	 *					to be set, or a value's name if string
	 * @param 	string	$val	Value to set, only used if $vars is a string
	 * @return	void
	 */
	public function vars($vars = array(), $val = '')
	{
		if ($val !== '' && is_string($vars))
		{
			$vars = array($vars => $val);
		}

		$vars = $this->_ci_object_to_array($vars);

		if (is_array($vars) && count($vars) > 0)
		{
			foreach ($vars as $key => $val)
			{
				$this->_ci_cached_vars[$key] = $val;
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get Variable
	 *
	 * Check if a variable is set and retrieve it.
	 *
	 * @param	string	$key	Variable name
	 * @return	mixed	The variable or NULL if not found
	 */
	public function get_var($key)
	{
		return isset($this->_ci_cached_vars[$key]) ? $this->_ci_cached_vars[$key] : NULL;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Variables
	 *
	 * Retrieves all loaded variables.
	 *
	 * @return	array
	 */
	public function get_vars()
	{
		return $this->_ci_cached_vars;
	}

	// --------------------------------------------------------------------

	/**
	 * Helper Loader
	 *
	 * @param	string|string[]	$helpers	Helper name(s)
	 * @return	void
	 */
	public function helper($helpers = array())
	{
		foreach ($this->_ci_prep_filename($helpers, '_helper') as $helper)
		{
			if (isset($this->_ci_helpers[$helper]))
			{
				continue;
			}

			// Is this a helper extension request?
			$ext_helper = config_item('subclass_prefix').$helper;
			$ext_loaded = FALSE;
			foreach ($this->_ci_helper_paths as $path)
			{
				if (file_exists($path.'helpers/'.$ext_helper.'.php'))
				{
					include_once($path.'helpers/'.$ext_helper.'.php');
					$ext_loaded = TRUE;
				}
			}

			// If we have loaded extensions - check if the base one is here
			if ($ext_loaded === TRUE)
			{
				$base_helper = BASEPATH.'helpers/'.$helper.'.php';
				if ( ! file_exists($base_helper))
				{
					show_error('Unable to load the requested file: helpers/'.$helper.'.php');
				}

				include_once($base_helper);
				$this->_ci_helpers[$helper] = TRUE;
				log_message('debug', 'Helper loaded: '.$helper);
				continue;
			}

			// No extensions found ... try loading regular helpers and/or overrides
			foreach ($this->_ci_helper_paths as $path)
			{
				if (file_exists($path.'helpers/'.$helper.'.php'))
				{
					include_once($path.'helpers/'.$helper.'.php');

					$this->_ci_helpers[$helper] = TRUE;
					log_message('debug', 'Helper loaded: '.$helper);
					break;
				}
			}

			// unable to load the helper
			if ( ! isset($this->_ci_helpers[$helper]))
			{
				show_error('Unable to load the requested file: helpers/'.$helper.'.php');
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Load Helpers
	 *
	 * An alias for the helper() method in case the developer has
	 * written the plural form of it.
	 *
	 * @uses	CI_Loader::helper()
	 * @param	string|string[]	$helpers	Helper name(s)
	 * @return	void
	 */
	public function helpers($helpers = array())
	{
		$this->helper($helpers);
	}

	// --------------------------------------------------------------------

	/**
	 * Language Loader
	 *
	 * Loads language files.
	 *
	 * @param	string|string[]	$files	List of language file names to load
	 * @param	string		Language name
	 * @return	void
	 */
	public function language($files = array(), $lang = '')
	{
		is_array($files) OR $files = array($files);

		foreach ($files as $langfile)
		{
			$this->CI->lang->load($langfile, $lang);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Config Loader
	 *
	 * Loads a config file (an alias for CI_Config::load()).
	 *
	 * @uses	CI_Config::load()
	 * @param	string	$file			Configuration file name
	 * @param	bool	$use_sections		Whether configuration values should be loaded into their own section
	 * @param	bool	$fail_gracefully	Whether to just return FALSE or display an error message
	 * @return	bool	TRUE if the file was loaded correctly or FALSE on failure
	 */
	public function config($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
	{
		return $this->CI->config->load($file, $use_sections, $fail_gracefully);
	}

	// --------------------------------------------------------------------

	/**
	 * Driver Loader
	 *
	 * Loads a driver library.
	 *
	 * @param	string|string[]	$library	Driver name(s)
	 * @param	array		$params		Optional parameters to pass to the driver
	 * @param	string		$object_name	An optional object name to assign to
	 *
	 * @return	void|object|bool	Object or FALSE on failure if $library is a string
	 *					and $object_name is set. void otherwise.
	 */
	public function driver($library = '', $params = NULL, $object_name = NULL)
	{
		if (is_array($library))
		{
			foreach ($library as $driver)
			{
				$this->driver($driver);
			}
			return;
		}

		if ($library === '')
		{
			return FALSE;
		}

		if ( ! class_exists('CI_Driver_Library'))
		{
			// We aren't instantiating an object here, just making the base class available
			require BASEPATH.'libraries/Driver.php';
		}

		// We can save the loader some time since Drivers will *always* be in a subfolder,
		// and typically identically named to the library
		if ( ! strpos($library, '/'))
		{
			$library = ucfirst($library).'/'.$library;
		}

		return $this->library($library, $params, $object_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Add Package Path
	 *
	 * Prepends a parent path to the library, model, helper and config
	 * path arrays.
	 *
	 * @see	CI_Loader::$_ci_library_paths
	 * @see	CI_Loader::$_ci_model_paths
	 * @see CI_Loader::$_ci_helper_paths
	 * @see CI_Config::$_config_paths
	 *
	 * @param	string	$path		Path to add
	 * @param 	bool	$view_cascade	(default: TRUE)
	 * @return	void
	 */
	public function add_package_path($path, $view_cascade = TRUE)
	{
		// Resolve path
		$path = $this->_ci_resolve_path($path);

		// Prepend path to library/helper paths
		array_unshift($this->_ci_library_paths, $path);

		// Prepend MVC path with view cascade param
		$this->_ci_mvc_paths = array($path => $view_cascade) + $this->_ci_mvc_paths;

		// Append config file path
		array_push($this->CI->config->_config_paths, $path);
	}

	// --------------------------------------------------------------------

	/**
	 * Get Package Paths
	 *
	 * Return a list of all package paths.
	 *
	 * @param	bool	$include_base	Whether to include BASEPATH (default: TRUE)
	 * @return	array
	 */
	public function get_package_paths($include_base = FALSE)
	{
		return $include_base === TRUE ? $this->_ci_library_paths : array_keys($this->_ci_mvc_paths);
	}

	// --------------------------------------------------------------------

	/**
	 * Remove Package Path
	 *
	 * Remove a path from the library, model, helper and/or config
	 * path arrays if it exists. If no path is provided, the most recently
	 * added path will be removed removed.
	 *
	 * @param	string	$path	Path to remove
	 * @return	void
	 */
	public function remove_package_path($path = '')
	{
		if ($path === '')
		{
			// Shift last added path from each list
			array_shift($this->_ci_library_paths);
			array_shift($this->_ci_mvc_paths);
			if ($remove_config_path)
			{
				array_pop($this->CI->config->_config_paths);
			}
			return;
		}

		// Resolve path
		$path = $this->_ci_resolve_path($path);

		// Prevent app path removal - it is a default for all lists
		if ($path == APPPATH)
		{
			return;
		}

		// Unset from library/helper list unless base path
		if ($path != BASEPATH && ($key = array_search($path, $this->_ci_library_paths)) !== FALSE)
		{
			unset($this->_ci_library_paths[$key]);
		}

		// Unset path from MVC list
		if (isset($this->_ci_mvc_paths[$path]))
		{
			unset($this->_ci_mvc_paths[$path]);
		}

		// Unset path from config list
		if ($remove_config_path && ($key = array_search($path, $this->CI->config->_config_paths)) !== FALSE)
		{
			unset($this->CI->config->_config_paths[$key]);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Resolves package path
	 *
	 * This function is used to identify absolute paths in the filesystem and include path
	 *
	 * @access	protected
	 * @param	string	initial path
	 * @return	string	resolved path
	 */
	protected function _ci_resolve_path($path)
	{
		// Assert trailing slash
		$path = rtrim($path, '/\\').'/';

		// See if path exists as-is
		if (file_exists($path))
		{
			return $path;
		}

		// Strip any leading slash and pair with include directories
		$dir = ltrim($path, "/\\");
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $include)
		{
			$include = rtrim($include, "/\\");
			if (file_exists($include.'/'.$dir))
			{
				// Found include path - clean up and return
				return $include.'/'.$dir;
			}
		}

		// If we got here, it's not a real path - just return as-is
		return $path;
	}

	// --------------------------------------------------------------------

	/**
	 * Internal CI Data Loader
	 *
	 * Used to load views and files.
	 *
	 * Variables are prefixed with _ci_ to avoid symbol collision with
	 * variables made available to view files.
	 *
	 * @used-by	CI_Loader::view()
	 * @used-by	CI_Loader::file()
	 * @param	array	$_ci_data	Data to load
	 * @return	void
	 */
	protected function _ci_load($_ci_data)
	{
		// Set the default data variables
		foreach (array('_ci_view', '_ci_vars', '_ci_path', '_ci_return') as $_ci_val)
		{
			$$_ci_val = isset($_ci_data[$_ci_val]) ? $_ci_data[$_ci_val] : FALSE;
		}

		// Set the path to the requested file
		$_ci_exists = FALSE;
		if (is_string($_ci_path) && $_ci_path !== '')
		{
			// General file - extract name from path
			$_ci_file = end(explode('/', $_ci_path));
			$_ci_exists = file_exists($_ci_path);
		}
		else
		{
			// View file - add extension as necessary
			$_ci_file = (pathinfo($_ci_view, PATHINFO_EXTENSION) === '') ? $_ci_view.'.php' : $_ci_view;

			// Check VIEWPATH first
			if (file_exists(VIEWPATH.$_ci_file))
			{
				$_ci_path = VIEWPATH.$_ci_file;
				$_ci_exists = TRUE;
			}
			else
		   	{
				// Search MVC package paths
				foreach ($this->_ci_mvc_paths as $_ci_mvc => $_ci_cascade)
				{
					if (file_exists($_ci_mvc.'views/'.$_ci_file))
					{
						// Set path, mark existing, and quit
						$_ci_path = $_ci_mvc.'views/'.$_ci_file;
						$_ci_exists = TRUE;
						break;
					}

					if ( ! $_ci_cascade)
					{
						// No cascade - stop looking
						break;
					}
				}
			}
		}

		// Verify file existence
		if ( ! $_ci_exists)
		{
			show_error('Unable to load the requested file: '.$_ci_file);
		}

		// This allows anything loaded using $this->load (libraries, models, etc.)
		// to become accessible from within the view or file
		foreach (get_object_vars($this->CI) as $_ci_key => $_ci_var)
		{
			if ( ! isset($this->$_ci_key))
			{
				$this->$_ci_key =& $this->CI->$_ci_key;
			}
		}

		/*
		 * Extract and cache variables
		 *
		 * You can either set variables using the dedicated $this->CI->load->vars()
		 * function or via the second parameter of this function. We'll merge
		 * the two types and cache them so that views that are embedded within
		 * other views can have access to these variables.
		 */
		if (is_array($_ci_vars))
		{
			$this->_ci_cached_vars = array_merge($this->_ci_cached_vars, $_ci_vars);
		}
		extract($this->_ci_cached_vars);

		/*
		 * Buffer the output
		 *
		 * We buffer the output for two reasons:
		 * 1. Speed. You get a significant speed boost.
		 * 2. So that the final rendered template can be post-processed by
		 *	the output class. Why do we need post processing? For one thing,
		 *	in order to show the elapsed page load time. Unless we can
		 *	intercept the content right before it's sent to the browser and
		 *	then stop the timer it won't be accurate.
		 */
		ob_start();

		// If the PHP installation does not support short tags we'll
		// do a little string replacement, changing the short tags
		// to standard PHP echo statements.
		if ( ! is_php('5.4') && (bool) @ini_get('short_open_tag') === FALSE
			&& config_item('rewrite_short_tags') === TRUE && function_usable('eval')
		)
		{
			echo eval('?>'.preg_replace('/;*\s*\?>/', '; ?>', str_replace('<?=', '<?php echo ', file_get_contents($_ci_path))));
		}
		else
		{
			include($_ci_path); // include() vs include_once() allows for multiple views with the same name
		}

		log_message('debug', 'File loaded: '.$_ci_path);

		// Return the file data if requested
		if ($_ci_return === TRUE)
		{
			return @ob_get_clean();
		}

		/*
		 * Flush the buffer... or buff the flusher?
		 *
		 * In order to permit views to be nested within
		 * other views, we need to flush the content back out whenever
		 * we are beyond the first level of output buffering so that
		 * it can be seen and included properly by the first included
		 * template and any subsequent ones. Oy!
		 */
		if (ob_get_level() > $this->_ci_ob_level + 1)
		{
			ob_end_flush();
		}
		else
		{
			$this->CI->output->append_output(@ob_get_clean());
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Internal CI Class Loader
	 *
	 * @used-by	CI_Loader::library()
	 * @uses	CI_Loader::_ci_init_class()
	 *
	 * @param	string	$class		Class name to load
	 * @param	mixed	$params		Optional parameters to pass to the class constructor
	 * @param	string	$object_name	Optional object name to assign to
	 * @return	void
	 */
	protected function _ci_load_class($class, $params = NULL, $object_name = NULL)
	{
		// Get the class name, and while we're at it trim any slashes.
		// The directory path can be included as part of the class name,
		// but we don't want a leading slash
		$class = str_replace('.php', '', trim($class, '/'));

		// Was the path included with the class name?
		// We look for a slash to determine this
		$subdir = '';
		if (($last_slash = strrpos($class, '/')) !== FALSE)
		{
			// Extract the path
			$subdir = substr($class, 0, ++$last_slash);

			// Get the filename from the path
			$class = substr($class, $last_slash);
		}

		// We'll test for both lowercase and capitalized versions of the file name
		foreach (array(ucfirst($class), strtolower($class)) as $class)
		{
			$pre = config_item('subclass_prefix');
			$file = 'libraries/'.$subdir.$pre.$class.'.php';
			foreach ($this->_ci_library_paths as $path)
			{
				// Is this a class extension request?
				$subclass = $path.$file;
				if (file_exists($subclass))
				{
					// Found extension - require base class
					$baseclass = BASEPATH.'libraries/'.ucfirst($class).'.php';
					if ( ! file_exists($baseclass))
					{
						log_message('error', 'Unable to load the requested class: '.$class);
						show_error('Unable to load the requested class: '.$class);
					}

					// Safety: Was the class already loaded by a previous call?
					if (in_array($subclass, $this->_ci_loaded_files))
					{
						// Before we deem this to be a duplicate request, let's see
						// if a custom object name is being supplied. If so, we'll
						// return a new instance of the object
						if ( ! is_null($object_name))
						{
							if ( ! isset($this->CI->$object_name))
							{
								return $this->_ci_init_class($class, $pre, $params, $object_name);
							}
						}

						$is_duplicate = TRUE;
						log_message('debug', $class.' class already loaded. Second attempt ignored.');
						return;
					}

					// Include base class followed by subclass for inheritance
					include_once($baseclass);
					include_once($subclass);
					$this->_ci_loaded_files[] = $subclass;

					return $this->_ci_init_class($class, $pre, $params, $object_name);
				}
			}

			// Lets search for the requested library file and load it.
			$is_duplicate = FALSE;
			foreach ($this->_ci_library_paths as $path)
			{
				$filepath = $path.'libraries/'.$subdir.$class.'.php';

				// Does the file exist? No? Bummer...
				if ( ! file_exists($filepath))
				{
					continue;
				}

				// Safety: Was the class already loaded by a previous call?
				if (in_array($filepath, $this->_ci_loaded_files))
				{
					// Before we deem this to be a duplicate request, let's see
					// if a custom object name is being supplied. If so, we'll
					// return a new instance of the object
					if ( ! is_null($object_name))
					{
						if ( ! isset($this->CI->$object_name))
						{
							return $this->_ci_init_class($class, '', $params, $object_name);
						}
					}

					$is_duplicate = TRUE;
					log_message('debug', $class.' class already loaded. Second attempt ignored.');
					return;
				}

				// If this looks like a driver, make sure the base class is loaded
				if (strtolower($subdir) == strtolower($class).'/' && !class_exists('CI_Driver_Library'))
				{
					// We aren't instantiating an object here, that'll be done by the Library itself
					require BASEPATH.'libraries/Driver.php';
				}

				include_once($filepath);
				$this->_ci_loaded_files[] = $filepath;
				return $this->_ci_init_class($class, '', $params, $object_name);
			}
		} // END FOREACH

		// One last attempt. Maybe the library is in a subdirectory, but it wasn't specified?
		if ($subdir === '')
		{
			$path = strtolower($class).'/'.$class;
			return $this->_ci_load_class($path, $params, $object_name);
		}
		elseif (ucfirst($subdir) != $subdir)
		{
			// Lowercase subdir failed - retry capitalized
			$path = ucfirst($subdir).$class;
			return $this->_ci_load_class($path, $params, $object_name);
		}

		// If we got this far we were unable to find the requested class.
		// We do not issue errors if the load call failed due to a duplicate request
		if ($is_duplicate === FALSE)
		{
			log_message('error', 'Unable to load the requested class: '.$class);
			show_error('Unable to load the requested class: '.$class);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Internal CI Class Instantiator
	 *
	 * @used-by	CI_Loader::_ci_load_class()
	 *
	 * @param	string		$class		Class name
	 * @param	string		$prefix		Class name prefix
	 * @param	array|null|bool	$config		Optional configuration to pass to the class constructor:
	 *						FALSE to skip;
	 *						NULL to search in config paths;
	 *						array containing configuration data
	 * @param	string		$object_name	Optional object name to assign to
	 * @return	void
	 */
	protected function _ci_init_class($class, $prefix = '', $config = FALSE, $object_name = NULL)
	{
		// Do we need to check for configs?
		if ($config === NULL)
		{
			// See if there's a config file for the class
			$file = strtolower($class);
			$data = $this->CI->config->get($file.'.php', 'config');
			if (!is_array($data))
			{
				// Try uppercase
				$data = $this->CI->config->get(ucfirst($file).'.php', 'config');
			}

			// Set config if found
			if (is_array($data))
			{
				$config = $data;
			}
		}

		if ($prefix === '')
		{
			if (class_exists('CI_'.$class))
			{
				$name = 'CI_'.$class;
			}
			elseif (class_exists(config_item('subclass_prefix').$class))
			{
				$name = config_item('subclass_prefix').$class;
			}
			else
			{
				$name = $class;
			}
		}
		else
		{
			$name = $prefix.$class;
		}

		// Is the class name valid?
		if ( ! class_exists($name))
		{
			log_message('error', 'Non-existent class: '.$name);
			show_error('Non-existent class: '.$name);
		}

		// Set the variable name we will assign the class to
		// Was a custom class name supplied? If so we'll use it
		$class = strtolower($class);

		if (is_null($object_name))
		{
			$classvar = isset($this->_ci_varmap[$class]) ? $this->_ci_varmap[$class] : $class;
		}
		else
		{
			$classvar = $object_name;
		}

		// Save the class name and object name
		$this->_ci_classes[$class] = $classvar;

		// Instantiate the class
		if ($config !== NULL)
		{
			$this->CI->$classvar = new $name($config);
		}
		else
		{
			$this->CI->$classvar = new $name();
		}
	}

	// --------------------------------------------------------------------

	/**
	 * CI Autoloader
	 *
	 * The config/autoload.php file contains an array that permits sub-systems,
	 * libraries, and helpers to be loaded automatically.
	 *
	 * This function is public, as it's called from CodeIgniter.php.
	 * However, there is no reason you should ever need to call it directly.
	 *
	 * @used-by	CI_Loader::initialize()
	 * @return	void
	 */
	public function ci_autoloader()
	{
		// Set base classes to prevent overwriting core modules
		$this->_base_classes =& is_loaded();

		// Check for autoload array
		if ( ! isset($this->_ci_autoload))
		{
			return FALSE;
		}

		// Autoload helpers and languages
		foreach (array('helper', 'language') as $type)
		{
			if (isset($this->_ci_autoload[$type]) && count($this->_ci_autoload[$type]) > 0)
			{
				$this->$type($this->_ci_autoload[$type]);
			}
		}

		// Load libraries
		if (isset($this->_ci_autoload['libraries']) && count($this->_ci_autoload['libraries']) > 0)
		{
			// Load the database driver.
			if (in_array('database', $this->_ci_autoload['libraries']))
			{
				$this->database();
				$this->_ci_autoload['libraries'] = array_diff($this->_ci_autoload['libraries'], array('database'));
			}

			// Load all other libraries
			$this->library($this->_ci_autoload['libraries']);
		}

		// Autoload controllers
		if (isset($this->_ci_autoload['controller']))
		{
			// Get controller(s) as an array
			$controller = (array)$this->_ci_autoload['controller'];

			// We have to "manually" feed multiples to controller(), since an array
			// is treated as a router stack instead of more than one controller
			foreach ($controller as $uri)
			{
				$this->controller($uri);
			}
		}

		// Autoload drivers
		if (isset($autoload['drivers']))
		{
			$this->driver($autoload['drivers']);
		}

		// Autoload models
		if (isset($this->_ci_autoload['model']))
		{
			$this->model($this->_ci_autoload['model']);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * CI Object to Array translator
	 *
	 * Takes an object as input and converts the class variables to
	 * an associative array with key/value pairs.
	 *
	 * @param	object	$object	Object data to translate
	 * @return	array
	 */
	protected function _ci_object_to_array($object)
	{
		return is_object($object) ? get_object_vars($object) : $object;
	}

}

/* End of file Loader.php */
/* Location: ./system/core/Loader.php */