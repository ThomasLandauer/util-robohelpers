<?php


namespace Codeception\Util;

use Codeception\Lib\ModuleContainer;

trait DocumentationHelpers
{

    /**
     * @param string $className
     * @param string $documentationFile
     * @param string $sourceMessage
     */
    protected function generateDocumentationForClass($className, $documentationFile, $sourceMessage = '')
    {
        $moduleName = (new \ReflectionClass($className))->getShortName();

        if (isset(ModuleContainer::$packages[$moduleName])) {
            $packageName = ModuleContainer::$packages[$moduleName];
        } else {
            $packageName = 'codeception/module-' . strtolower($moduleName);
        }

        $installationHtml =<<<EOT
# $moduleName
## Installation

If you use Codeception installed using composer, install this module with the following command:

```
composer require --dev $packageName
```

Alternatively, you can enable `$moduleName` module in suite configuration file and run
 
```
codecept init upgrade4
```

This module was bundled with Codeception 2 and 3, but since version 4 it is necessary to install it separately.   
Some modules are bundled with PHAR files.  
Warning. Using PHAR file and composer in the same project can cause unexpected errors.  

## Description

EOT;

        $this->taskGenDoc($documentationFile)
            ->docClass($className)
            ->prepend($installationHtml)
            ->append($sourceMessage)
            ->processClassSignature(false)
            ->processClassDocBlock(function (\ReflectionClass $c, $text) {
                return "$text\n## Actions";
            })
            ->processProperty(false)
            ->filterMethods(function (\ReflectionMethod $method) {
                if ($method->isConstructor() or $method->isDestructor()) {
                    return false;
                }
                if (!$method->isPublic()) {
                    return false;
                }
                if (strpos($method->name, '_') === 0) {
                    $doc = $method->getDocComment();
                    try {
                        $doc = $doc . $method->getPrototype()->getDocComment();
                    } catch (\ReflectionException $e) {
                    }

                    if (strpos($doc, '@api') === false) {
                        return false;
                    }
                };
                return true;
            })->processMethod(function (\ReflectionMethod $method, $text) use ($moduleName) {
                $title = "\n### {$method->name}\n";
                if (strpos($method->name, '_') === 0) {
                    $text = str_replace("@api\n", '', $text);
                    $text = "\n*hidden API method, expected to be used from Helper classes*\n" . $text;
                    $text = str_replace("{{MODULE_NAME}}", $moduleName, $text);
                };

                if (!trim($text)) {
                    return $title . "__not documented__\n";
                }

                $text   = str_replace(
                    [
                        '@since',
                        '@version'
                    ],
                    [
                        ' * `Available since`',
                        ' * `Available since`'
                    ],
                    $text
                );
                $text   = str_replace('@part ', ' * `[Part]` ', $text);
                $text   = str_replace("@return\n", '', $text);
                $text   = str_replace("@return mixed\n", '', $text);
                $text   = preg_replace('~@(return( [^\s]*)?)( (.+))?~', ' * `$1` $4', $text);
                $text   = preg_replace("~^@(.*?)([$\s])~", ' * `$1` $2', $text);
                $result = $title . $text;
                return preg_replace('/\n(\s*\n){2,}/', "\n\n", $result);
            })->processMethodSignature(false)
            ->reorderMethods('ksort')
            ->run();
    }
}
