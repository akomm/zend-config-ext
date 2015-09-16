<?php

namespace ZendConfigExt\Writer;

use Zend\Config\Writer\PhpArray;
use Zend\Config\Exception;


/**
 * Class PhpArrayMultiFile
 *
 * @package ZendConfigExt\Writer
 */
class PhpArrayMultiFile extends PhpArray
{
    /**
     * @var string
     */
    protected $configFileTemplate = '*.config.php';

    /**
     * @var string
     */
    protected $stubFileName = 'module.config.php';

    /**
     * @var string[]
     */
    protected $separateByKeys = [];

    /**
     * @param string[] $separateByKeys
     * @return PhpArrayMultiFile
     */
    public function setSeparateByKeys($separateByKeys)
    {
        $this->separateByKeys = $separateByKeys;
        return $this;
    }

    /**
     * @return \string[]
     */
    public function getSeparateByKeys()
    {
        return $this->separateByKeys;
    }

    /**
     * @return string
     */
    public function getConfigFileTemplate()
    {
        return $this->configFileTemplate;
    }

    /**
     * @param string $configFileTemplate
     */
    public function setConfigFileTemplate($configFileTemplate)
    {
        $this->configFileTemplate = $configFileTemplate;
    }

    /**
     * @return string
     */
    public function getStubFileName()
    {
        return $this->stubFileName;
    }

    /**
     * @param string $stubFileName
     */
    public function setStubFileName($stubFileName)
    {
        $this->stubFileName = $stubFileName;
    }

    /**
     * Write configuration
     *
     * @param string $filename stub configuration file name
     * @param array $config
     * @param bool $exclusiveLock
     *
     * @throws \Exception
     */
    public function toFile($filename, $config, $exclusiveLock = true)
    {
        $stubDirName     = dirname($filename);
        $separateAllKeys = count($this->separateByKeys) < 1;
        $renderedStub    = '';

        foreach ($config as $key => $value) {
            if (!is_array($value) || (!$separateAllKeys && !in_array($key, $this->separateByKeys))) {
                continue;
            }

            // Generate a file name for a configuration key
            $fileName = $this->keyToFileName($key);

            // Generate include line for the stub configuration
            $renderedStub .= "    '$key' => include __DIR__ . '/$fileName',\n";

            // Write separated configuration file
            parent::toFile($stubDirName . '/' . $fileName, $value, $exclusiveLock);

            // Remove separated configuration from the main configuration
            unset($config[$key]);
        }

        $arraySyntax = array(
            'open'  => $this->useBracketArraySyntax ? '[' : 'array(',
            'close' => $this->useBracketArraySyntax ? ']' : ')'
        );

        $renderedStub = "<?php\n"
                      . "return " . $arraySyntax['open'] . "\n"
                      . $renderedStub . $this->processIndented($config, $arraySyntax)
                      . $arraySyntax['close'] . ";\n";

        set_error_handler(
            function ($error, $message = '') use ($filename) {
                throw new Exception\RuntimeException(
                    sprintf('Error writing to "%s": %s', $filename, $message),
                    $error
                );
            },
            E_WARNING
        );

        $flags = 0;
        if ($exclusiveLock) {
            $flags |= LOCK_EX;
        }

        try {
            // for Windows, paths are escaped.
            $stubDirName   = str_replace('\\', '\\\\', $stubDirName);
            $renderedStub  = str_replace("'" . $stubDirName, "__DIR__ . '", $renderedStub);

            file_put_contents($filename, $renderedStub, $flags);
        } catch (\Exception $e) {
            restore_error_handler();
            throw $e;
        }

        restore_error_handler();
    }

    /**
     * Resolves a configuration key to a configuration file name
     *
     * @param string $key
     * @return string
     */
    protected function keyToFileName($key)
    {
        return str_replace('*', str_replace(['-', '/', '\\', ' ',], '_', $key), $this->configFileTemplate);
    }
}