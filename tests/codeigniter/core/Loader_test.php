<?php

class Loader_test extends CI_TestCase {

	private $ci_obj;

	public function set_up()
	{
		// Instantiate a new loader
		$this->load = new Mock_Core_Loader();

		// mock up a ci instance
		$this->ci_obj = new stdClass;

		// Fix get_instance()
		$this->ci_instance($this->ci_obj);
	}

	// --------------------------------------------------------------------

	public function test_library()
	{
		$this->_setup_config_mock();

		// Create libraries directory with test library
		$lib = 'unit_test_lib';
		$class = 'CI_'.ucfirst($lib);
		$content = '<?php class '.$class.' { } ';
		$this->_create_content('libraries', $lib, $content, NULL, TRUE);

		// Test loading as an array.
		$this->assertNull($this->load->library(array($lib)));
		$this->assertTrue(class_exists($class), $class.' does not exist');
		$this->assertAttributeInstanceOf($class, $lib, $this->ci_obj);

		// Test no lib given
		$this->assertEquals(FALSE, $this->load->library());

		// Test a string given to params
		$this->assertEquals(NULL, $this->load->library($lib, ' '));
	}

	// --------------------------------------------------------------------

	public function test_library_config()
	{
		$this->_setup_config_mock();

		// Create libraries directory with test library
		$lib = 'unit_test_config_lib';
		$class = 'CI_'.ucfirst($lib);
		$content = '<?php class '.$class.' { public function __construct($params) { $this->config = $params; } } ';
		$this->_create_content('libraries', $lib, $content, NULL, TRUE);

		// Create config file
		$cfg = array(
			'foo' => 'bar',
			'bar' => 'baz',
			'baz' => false
		);
		$this->_create_content('config', $lib, '<?php $config = '.var_export($cfg, TRUE).';');

		// Test object name and config
		$obj = 'testy';
		$this->assertNull($this->load->library($lib, NULL, $obj));
		$this->assertTrue(class_exists($class), $class.' does not exist');
		$this->assertAttributeInstanceOf($class, $obj, $this->ci_obj);
		$this->assertEquals($cfg, $this->ci_obj->$obj->config);
	}

	// --------------------------------------------------------------------

	public function test_load_library_in_application_dir()
	{
		$this->_setup_config_mock();

		// Create libraries directory in app path with test library
		$lib = 'super_test_library';
		$class = ucfirst($lib);
		$content = '<?php class '.$class.' {} ';
		$this->_create_content('libraries', $lib, $content);

		// Load library
		$this->assertNull($this->load->library($lib));

		// Was the model class instantiated.
		$this->assertTrue(class_exists($class), $class.' does not exist');
		$this->assertAttributeInstanceOf($class, $lib, $this->ci_obj);
	}

	// --------------------------------------------------------------------

	public function test_driver()
	{
		$this->_setup_config_mock();

		// Create libraries directory with test driver
		$driver = 'unit_test_driver';
		$dir = ucfirst($driver);
		$class = 'CI_'.$dir;
		$content = '<?php class '.$class.' { } ';
		$this->_create_content('libraries', $driver, $content, $dir, TRUE);

		// Test loading as an array.
		$this->assertFalse($this->load->driver(array($driver)));
		$this->assertTrue(class_exists($class), $class.' does not exist');
		$this->assertAttributeInstanceOf($class, $driver, $this->ci_obj);

        // Test loading as a library with a name
        $obj = 'testdrive';
		$this->assertFalse($this->load->library($driver, NULL, $obj));
		$this->assertAttributeInstanceOf($class, $obj, $this->ci_obj);

		// Test no driver given
		$this->assertEquals(FALSE, $this->load->driver());

		// Test a string given to params
		$this->assertEquals(NULL, $this->load->driver($driver, ' '));
	}

	// --------------------------------------------------------------------

	private function _setup_config_mock()
	{
		// Mock up a config object until we
		// figure out how to test the library configs
		// $config = $this->getMock('CI_Config', NULL, array(), '', FALSE);
		// $config->expects($this->any())
		// 	   ->method('load')
		// 	   ->will($this->returnValue(TRUE));
        // As long as we have Config test its own loading, we just need paths
		$config = new StdClass();

		// Reinitialize config paths
		$config->_config_paths = array($this->load->app_path);

		// Add the mock to our stdClass
		$this->ci_instance_var('config', $config);
	}

	// --------------------------------------------------------------------

	public function test_non_existent_model()
	{
		$this->setExpectedException(
			'RuntimeException',
			'CI Error: Unable to locate the model you have specified: ci_test_nonexistent_model.php'
		);

		$this->load->model('ci_test_nonexistent_model.php');
	}

	// --------------------------------------------------------------------

	/**
	 * @coverts CI_Loader::model
	 */
	public function test_models()
	{
		$this->ci_set_core_class('model', 'CI_Model');

		// Create models directory with test model
		$model = 'unit_test_model';
		$class = ucfirst($model);
		$content = '<?php class '.$class.' extends CI_Model {} ';
		$this->_create_content('models', $model, $content);

		// Load model
		$this->assertNull($this->load->model($model));

		// Was the model class instantiated.
		$this->assertTrue(class_exists($class));

		// Test no model given
		$this->assertNull($this->load->model(''));
	}

	// --------------------------------------------------------------------

	// public function testDatabase()
	// {
	// 	$this->assertEquals(NULL, $this->load->database());
	// 	$this->assertEquals(NULL, $this->load->dbutil());
	// }

	// --------------------------------------------------------------------

	/**
	 * @coverts CI_Loader::view
	 */
	public function test_load_view()
	{
		$this->ci_set_core_class('output', 'CI_Output');

		// Create views directory with test view
		$view = 'unit_test_view';
		$content = 'This is my test page.  <?php echo $hello; ?>';
		$this->_create_content('views', $view, $content);

		// Use the optional return parameter in this test, so the view is not
		// run through the output class.
		$out = $this->load->view($view, array('hello' => "World!"), TRUE);
		$this->assertEquals('This is my test page.  World!', $out);
	}

	// --------------------------------------------------------------------

	/**
	 * @coverts CI_Loader::view
	 */
	public function test_non_existent_view()
	{
		$this->setExpectedException(
			'RuntimeException',
			'CI Error: Unable to load the requested file: ci_test_nonexistent_view.php'
		);

		$this->load->view('ci_test_nonexistent_view', array('foo' => 'bar'));
	}

	// --------------------------------------------------------------------

	public function test_file()
	{
		// Create views directory with test file
		$dir = 'views';
		$file = 'ci_test_mock_file';
		$content = 'Here is a test file, which we will load now.';
		$this->_create_content($dir, $file, $content);

		// Just like load->view(), take the output class out of the mix here.
		$out = $this->load->file($this->load->app_path.$dir.'/'.$file.'.php', TRUE);
		$this->assertEquals($content, $out);

		// Test non-existent file
		$this->setExpectedException(
			'RuntimeException',
			'CI Error: Unable to load the requested file: ci_test_file_not_exists'
		);

		$this->load->file('ci_test_file_not_exists', TRUE);
	}

	// --------------------------------------------------------------------

	public function test_vars()
	{
		$this->assertNull($this->load->vars(array('foo' => 'bar')));
		$this->assertNull($this->load->vars('foo', 'bar'));
	}

	// --------------------------------------------------------------------

	public function test_helper()
	{
		// Create helper directory in app path with test helper
		$helper = 'test';
		$func = '_my_helper_test_func';
		$content = '<?php function '.$func.'() { return true; } ';
		$this->_create_content('helpers', $helper.'_helper', $content);

		// Load helper
		$this->assertEquals(NULL, $this->load->helper($helper));
		$this->assertTrue(function_exists($func), $func.' does not exist');

		// Test non-existent helper
		$this->setExpectedException(
			'RuntimeException',
			'CI Error: Unable to load the requested file: helpers/bad_helper.php'
		);

		$this->load->helper('bad');
	}

	// --------------------------------------------------------------------

	public function test_loading_multiple_helpers()
	{
		// Create helper directory in base path with test helpers
		$helpers = array();
		$funcs = array();
		$files = array();
		for ($i = 1; $i <= 3; ++$i) {
			$helper = 'test'.$i;
			$helpers[] = $helper;
			$func = '_my_helper_test_func'.$i;
			$funcs[] = $func;
			$files[$helper.'_helper'] = '<?php function '.$func.'() { return true; } ';
		}
		$this->_create_content('helpers', $files, NULL, NULL, TRUE);

		// Load helpers
		$this->assertEquals(NULL, $this->load->helpers($helpers));

		// Verify helper existence
		foreach ($funcs as $func) {
			$this->assertTrue(function_exists($func), $func.' does not exist');
		}
	}

	// --------------------------------------------------------------------

	// public function testLanguage()
	// {
	// 	$this->assertEquals(NULL, $this->load->language('test'));
	// }

	// --------------------------------------------------------------------

	// public function test_load_config()
	// {
	// 	$this->_setup_config_mock();
	// 	$this->assertNull($this->load->config('config', FALSE));
	// }

	// --------------------------------------------------------------------

	// public function test_load_bad_config()
	// {
	// 	$this->_setup_config_mock();

	// 	$this->setExpectedException(
	// 		'RuntimeException',
	// 		'CI Error: The configuration file foobar.php does not exist.'
	// 	);

	// 	$this->load->config('foobar', FALSE);
	// }

	// --------------------------------------------------------------------

	private function _create_content($dir, $file, $content, $sub = NULL, $base = FALSE)
	{
		// Create structure containing directory
		$tree = array($dir => array());

        // Check for subdirectory
        if ($sub) {
            // Add subdirectory to tree and get reference
            $tree[$dir][$sub] = array();
            $leaf =& $tree[$dir][$sub];
        }
        else {
            // Get reference to main directory
            $leaf =& $tree[$dir];
        }

		// Check for multiple files
		if (is_array($file)) {
			// Add multiple files to directory
			foreach ($file as $name => $data) {
				$leaf[$name.'.php'] = $data;
			}
		}
		else {
			// Add single file with content
			$leaf[$file.'.php'] = $content;
		}

		// Create structure under app or base path
		vfsStream::create($tree, $base ? $this->load->base_root : $this->load->app_root);
	}

}
