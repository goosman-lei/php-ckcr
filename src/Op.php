<?php
namespace Ckcr;
class Op {

    protected $config = array();

    public function __construct($config) {
        $this->config = $config;
    }

/****************************** 分隔: 基本数据类型限定 ******************************/

    /**
     * op_str 
     * str: [ __req | __opt | <default value> ]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_str(&$oprand, $key, $req = '__opt') {
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }
        $oprand[$key] = (string)$oprand[$key];
        return TRUE;
    }

    /**
     * op_int 
     * int: [ __req | __opt | <default value> ]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_int(&$oprand, $key, $req = '__opt') {
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }
        $oprand[$key] = (int)$oprand[$key];
        return TRUE;
    }

    /**
     * op_float 
     * float: [ __req | __opt | <default value> ]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_float(&$oprand, $key, $req = '__opt') {
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }
        $oprand[$key] = (float)$oprand[$key];
        return TRUE;
    }

    /**
     * op_bool 
     * bool: [ __req | __opt | <default value> ]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_bool(&$oprand, $key, $req = '__opt') {
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }
        $oprand[$key] = (bool)$oprand[$key];
        return TRUE;
    }

    /**
     * op_iarr 
     * iarr: [ __req | __opt | <default value> ]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_iarr(&$oprand, $key, $req = '__opt') {
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }
        $oprand[$key] = array_values((array)$oprand[$key]);
        return TRUE;
    }

    /**
     * op_aarr 
     * aarr: [ __req | __opt | <default value> ]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_aarr(&$oprand, $key, $req = '__opt') {
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }
        $oprand[$key] = empty($oprand[$key]) ? (new stdClass()) : (array)$oprand[$key];
        return TRUE;
    }

    /**
     * op_enum 
     * enum: [ (__req | __opt | <default_value>) [item1; item2; item3; ...]]
     * @param mixed $oprand 
     * @param string $req 
     * @access protected
     * @return void
     */
    protected function op_enum(&$oprand, $key, $req = '__opt') {
        $argv = func_get_args();
        array_splice($argv, 0, 3);
        if (!isset($oprand[$key])) {
            if ($req === '__req') {
                return FALSE;
            } else if ($req === '__opt') {
                return TRUE;
            } else {
                $oprand[$key] = $req;
                return TRUE;
            }
        }

        return in_array($oprand[$key], $argv);
    }

/****************************** 分隔: 以下为"判定类操作"定义 ******************************/

    /**
     * op_match 
     * match:email
     * match:"/regexp/modifier"
     * @param mixed $oprand 
     * @param mixed $pattern 
     * @access protected
     * @return void
     */
    protected function op_match(&$oprand, $key, $pattern) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        switch ($pattern) {
            case 'email':
                $pattern = '/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix';
                break;
            default:
                break;
        }
        return preg_match($pattern, $oprand[$key]);
    }

    /**
     * op_range 
     * range_ele := "_" N | N "_" | MIN "_" MAX
     * range_str := range_ele | range_ele "," range_str
     * range : '"' range_str '"'
     * @param mixed $oprand 
     * @param mixed $range 
     * @access protected
     * @return void
     */
    protected function op_range(&$oprand, $key, $range) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }

        $opValue = $oprand[$key];
        if (!is_numeric($opValue)) {
            return FALSE;
        }
        $rangeEles = explode(',', $range);
        foreach ($rangeEles as $ele) {
            $rangeNums = explode('_', $ele);
            $nNums = count($rangeNums);
            if ($nNums < 1 || $nNums > 2) {
                return FALSE;
            } else if ($nNums === 1) {
                if (!is_numeric($rangeNums[0])) {
                    return FALSE;
                } else if ($opValue == $rangeNums[0]) {
                    return TRUE;
                }
            } else if ($nNums === 2) {
                if (strlen($rangeNums[0]) === 0 && is_numeric($rangeNums[1]) && $opValue <= $rangeNums[1]) {
                    return TRUE;
                } else if (strlen($rangeNums[1]) === 0 && is_numeric($rangeNums[0]) && $opValue >= $rangeNums[0]) {
                    return TRUE;
                } else if (!is_numeric($rangeNums[0]) || !is_numeric($rangeNums[1])) {
                    return FALSE;
                } else if ($opValue >= $rangeNums[0] && $opValue <= $rangeNums[1]) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

/****************************** 分隔: 以下为"修正类操作"定义 ******************************/

    /**
     * op_top 
     * top:n
     * @param mixed $oprand 
     * @param mixed $n 
     * @access protected
     * @return void
     */
    protected function op_top(&$oprand, $key, $n) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        if (!is_array($oprand[$key]) || $n <= 0) {
            return FALSE;
        }
        array_splice($oprand[$key], $n);
        return TRUE;
    }

    /**
     * op_filter 
     * filter: [field1; field2; field3; ...]
     * @param mixed $oprand 
     * @access protected
     * @return void
     */
    protected function op_filter(&$oprand, $key) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }

        $opValue = $oprand[$key];
        if (!is_array($opValue) && !is_object($opValue)) {
            return FALSE;
        }
        $filterKeys = func_get_args();
        array_shift($filterKeys);
        foreach ($filterKeys as $k) {
            if (isset($opValue, $k)) {
                unset($oprand[$key][$k]);
            }
        }

        return FALSE;
    }

    /**
     * op_include 
     * include: [field1; field2; field3; ...]
     * @param mixed $oprand 
     * @access protected
     * @return void
     */
    protected function op_include(&$oprand, $key) {
		if (!isset($oprand[$key])) {
			return TRUE;
		}
		$opValue = (array)$oprand[$key];
        $includeKeys = func_get_args();
        array_shift($includeKeys);
        $retArr = array();
        foreach ($includeKeys as $k) {
			if (isset($opValue[$k])) {
				$retArr[$k] =& $opValue[$k];
			}
        }
        $oprand[$key] = $retArr;
        return TRUE;
    }

    /**
     * op_trim 
     * trim: 无参数
     * @param mixed $oprand 
     * @access protected
     * @return void
     */
    protected function op_trim(&$oprand, $key) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        $oprand[$key] = trim($oprand[$key]);
        return TRUE;
    }

    /**
     * op_left
     * @param mixed $oprand 
     * @access protected
     * @return void
     */
    protected function op_substr(&$oprand, $key, $n, $start = 0) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        $oprand[$key] = mb_substr($oprand[$key], $start, $n, 'utf8');
        return TRUE;
    }

    /**
     * op_xss 
     * xss: 无参数
     * @param mixed $oprand 
     * @access protected
     * @return void
     */
    protected function op_xss(&$oprand, $key) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        $oprand[$key] = htmlspecialchars($oprand[$key]);
        return TRUE;
    }

    /**
     * op_prepend
     * 向前追加字符串
     * @param mixed $oprand
     * @param mixed $key
     * @param mixed $val
     * @access protected
     * @return void
     */
    protected function op_prepend(&$oprand, $key, $val) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        $oprand[$key] = strval($val) . $oprand[$key];
    }

    /**
     * op_append
     * 向后追加字符串
     * @param mixed $oprand
     * @param mixed $key
     * @param mixed $val
     * @access protected
     * @return void
     */
    protected function op_append(&$oprand, $key, $val) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        $oprand[$key] .= strval($val);
    }

    /**
     * op_chname
     * 将指定的key改名
     * @param mixed $oprand
     * @param mixed $key
     * @param mixed $newName
     * @access protected
     * @return void
     */
    protected function op_chname(&$oprand, $key, $newName) {
        if (!isset($oprand[$key])) {
            return TRUE;
        }
        $oprand[$newName] = $oprand[$key];
        unset($oprand[$key]);
        return TRUE;
    }


    /**
     * op_copy
     * 指定的key的值, 来自其他名字的迁移
     * @param mixed $oprand
     * @param mixed $key
     * @param mixed $oldName
     * @access protected
     * @return void
     */
    protected function op_copy(&$oprand, $key, $oldName) {
        if (!isset($oprand[$oldName])) {
            return TRUE;
        }
        $oprand[$key] = $oprand[$oldName];
    }

}
