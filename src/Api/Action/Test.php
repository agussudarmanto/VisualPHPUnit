<?php
/**
 * VisualPHPUnit
 *
 * VisualPHPUnit is a visual front-end for PHPUnit.
 *
 * PHP Version 5.6<
 *
 * @author Johannes Skov Frandsen <localgod@heaven.dk>
 * @copyright 2011-2015 VisualPHPUnit
 * @license http://opensource.org/licenses/BSD-3-Clause The BSD License
 * @link https://github.com/VisualPHPUnit/VisualPHPUnit VisualPHPUnit
 */
namespace Visualphpunit\Api\Action;

use \ReflectionClass;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

/**
 * Visualphpunit list tests action
 *
 * @author Johannes Skov Frandsen <localgod@heaven.dk>
 */
class Test extends Action
{

    /**
     * Retrive tests
     *
     * Retrive tests from test folder
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Silex\Application $app
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Request $request, Application $app)
    {
        $data = array();
        foreach ($app['config']['test-directories'] as $suite) {
            $data[] = array(
                'text' => $suite['name'],
                'type' => 'suite',
                'nodes' => $this->parse($suite['path'], $suite['ignoreHidden']),
                'selectable' => false
            );
        }
        return $this->ok($data);
    }

    /**
     * Parse the dir for files
     *
     * @param string $dir
     * @param boolean $ignoreHidden
     *
     * @return mixed[]
     */
    private function parse($dir, $ignoreHidden)
    {
        $files = Finder::create()->ignoreDotFiles($ignoreHidden)
            ->sortByType()
            ->depth(0)
            ->name('*.php')
            ->notName('/bootstrap.php/')
            ->in($dir);
        $directories = Finder::create()->ignoreDotFiles($ignoreHidden)
            ->sortByType()
            ->depth(0)
            ->directories()
            ->append($files)
            ->in($dir);
        
        $list = array();
        
        foreach ($directories as $file) {
            if ($file->getType() == 'dir') {
                $list[] = array(
                    'text' => $file->getBasename('.php'),
                    'type' => $file->getType(),
                    'path' => $file->getRealPath(),
                    'nodes' => $this->parse($file->getRealPath(), $ignoreHidden),
                    'selectable' => false
                );
            } else {
                if ($this->isPhpUnitTestCase($file)) {
                    $list[] = array(
                        'text' => $file->getBasename('.php'),
                        'type' => $file->getType(),
                        'path' => $file->getRealPath(),
                        'selectable' => true,
                        'tags' => $this->getNumberOfMethods($file->getRealPath())
                    );
                }
            }
        }
        
        return $this->excludeEmptyDirectories($list);
    }

    /**
     * Exclude empty Directories
     *
     * @param mixed[] $list
     * @return mixed[]
     */
    private function excludeEmptyDirectories($list)
    {
        foreach ($list as $key => $value) {
            if ($value['type'] == 'dir') {
                if (count($value['nodes']) != 0) {
                    $this->excludeEmptyDirectories($value['nodes']);
                } else {
                    unset($list[$key]);
                }
            }
        }
        
        return $list;
    }

    /**
     * Is this a phpunit testcase
     *
     * @param string $path
     *
     * @return boolean
     */
    private function isPhpUnitTestCase($path)
    {
        $result1 = preg_grep('/PHPUnit_Framework_TestCase$/', file($path));
        return !empty($result1);
    }

    /**
     * Get number of methods in test class
     *
     * @todo likely there are better ways of doing this
     * @param string $path
     *
     * @return integer[]
     */
    private function getNumberOfMethods($path)
    {
        $result1 = preg_grep('/^namespace/', file($path));
        $result2 = preg_grep('/^class/', file($path));
        $matches1 = [];
        $matches2 = [];
        
        preg_match('/^class\s([A-Za-z0-9]+).+$/', array_pop($result2), $matches2);
        if (count($result1) > 0) {
            preg_match('/^namespace\s(.+);$/', array_pop($result1), $matches1);
            $namespace = $matches1[1];
            $result2 = preg_grep('/^class/', file($path));
            preg_match('/^class\s([A-Za-z0-9]+).+$/', array_pop($result2), $matches2);
            $class = $matches2[1];
            require_once $path;
            $obj = new ReflectionClass($namespace . '\\' . $class);
            $methods = [];
            foreach ($obj->getMethods() as $method) {
                if ($method->class == $namespace . '\\' . $class) {
                    if ($method->isPublic()) {
                        $methods[] = $method->name;
                    }
                }
            }
            return [
                count($methods)
            ];
        }
        $result2 = preg_grep('/^class/', file($path));
        if (count($result2) > 0) {
            $class = $matches2[1];
            
            require_once $path;
            $obj = new ReflectionClass($class);
            $methods = [];
            foreach ($obj->getMethods() as $method) {
                if ($method->class == $class) {
                    if ($method->isPublic()) {
                        $methods[] = $method->name;
                    }
                }
            }
            return [
                count($methods)
            ];
        } else {
            return [
                0
            ];
        }
    }
}
