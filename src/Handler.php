<?php
namespace Ckcr;

class Handler {

    const SYNTAX_VERSION = 'v-9';

    protected $config = array();

    public function __construct($config) {
        $this->config = $config;
    }

    public function getProxy($srcCode) {

        // TODO 预处理阶段. 因为输出控制不适用CKCR, 所以暂不引入
        if (isset($this->config['preDefined']) && is_array($this->config['preDefined']) && !empty($this->config['preDefined'])) {
            $srcCode = PreProcessor::process($srcCode, $this->config['preDefined']);
        }

        $uniqkey = md5($srcCode);

        $targetClassName = '__CK_CR_' . $uniqkey;
        $compilePath     = rtrim($this->config['compilePath'], '/');
        $syntaxVersion   = self::SYNTAX_VERSION;
        $targetFileName  = "${compilePath}/${syntaxVersion}/${uniqkey}.php";

        if (!is_file($targetFileName)) {
            try {
                $objectCode = Compiler::compile($srcCode, $targetClassName);
            } catch (CompileException $e) {
                $this->warn($e->getMessage(), self::CKCR_COMPILE_ERROR);
                return FALSE;
            }

            if (!is_dir(dirname($targetFileName))) {
                @umask(0);
                @mkdir(dirname($targetFileName), 0777, TRUE);
            }
            file_put_contents($targetFileName, $objectCode);
            @chmod($targetFileName, 0777);
        }
        require_once $targetFileName;

        return new $targetClassName($this->config);
    }
}
