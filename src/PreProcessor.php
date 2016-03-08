<?php
namespace Ckcr;
// 预处理器
class PreProcessor {

    /**
     * process 
     * 预处理源代码的替换
     * @param mixed $srcCode 
     * @param array $preDefined 
     * @static
     * @access public
     * @return void
     */
    public static function process($srcCode, $preDefined = array()) {
        $deep = 0; // 最大支持10层深度
        while (++ $deep <= 10 && preg_match_all(';\{@([-\w]+)@\};', $srcCode, $matches, PREG_SET_ORDER) && is_array($matches)) {
            $replacedNames = array();
            foreach ($matches as $match) {
                if (array_key_exists($match[1], $replacedNames)) {
                    continue;
                } else {
                    $replacedNames[$match[1]] = TRUE;
                }
                $ckcrCfg = $preDefined[$match[1]];
                if (!is_string($ckcrCfg)) {
                    $ckcrCfg = '';
                }
                $srcCode = str_replace($match[0], $ckcrCfg, $srcCode);
            }
        }
        return $srcCode;
    }
}
