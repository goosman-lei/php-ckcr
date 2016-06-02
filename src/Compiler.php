<?php
namespace Ckcr;

class Compiler {

    // 变量名临时保存
    protected static $currScope;
    protected static $scopes;
    // 前缀深度
    protected static $prefixScope;


    public static function compile($srcCode, $proxyClassName) {
        self::clear();

        $objectCodeFormat = '<' . '?php
class ${PROXY_CLASSNAME} extends \Ckcr\Op {
    public function ckcr(&$input) {
        $input_0_0 = array(
            "base" => &$input, 
        );

${OBJECT_CODE_FRAME}
        return TRUE;
    }
}';
        $objectCodeFrame = '';
        $position   = 0;
        self::recursiveCompile($srcCode, $objectCodeFrame, $position, '$input_0_0');

        return strtr($objectCodeFormat, array(
            '${PROXY_CLASSNAME}' => $proxyClassName,
            '${OBJECT_CODE_FRAME}' => $objectCodeFrame,
        ));
    }

    protected static function clear() {
        self::$currScope = 0;
        self::$scopes    = array(
            array('level' => 0, 'nth' => 0), 
        );
        self::$prefixScope = 2;
    }

    protected static function recursiveCompile($srcCode, &$objectCode, &$position = 0, $baseName = '') {
        $prefix = self::prefixSpace();
        $isIterateField = FALSE;

        // ROOT节点, 直接设置数据变量
        if ($position == 0) {
            $keyName = "'base'";
        // 非ROOT节点, 读取FIELD作为当前数据变量
        } else {
            // 读取FIELD
            $token = self::readToken($srcCode, $position);
            if (!$token->isValid(Token::FIELD)) {
                self::revertToken($srcCode, $position, $token);
                throw new CompileException($srcCode, $position, 'CompilerCompile: Invalid field name');
            }
            // 读取FIELD后的冒号. 并将其忽略
            if (!self::readToken($srcCode, $position)->isValid(Token::FIELD_COLON)) {
                self::revertToken($srcCode, $position, $token);
                throw new CompileException($srcCode, $position, 'CompilerCompile: Expect colon ":" after field name');
            }
            // FIELD是*代表处理当前数组所有元素
            if ($token->literal === '*') {
                $isIterateField = TRUE;
                $keyName = self::pushScope();
                $newName = self::newName();
                $objectCode .= "${prefix}if (is_array(${baseName})) {\n";
                $prefix = self::pushPrefix();
                $objectCode .= "${prefix}foreach (${baseName} as ${keyName} => ${newName}) {\n";
                // 由于插入一个循环, 对前置空格入栈
                $prefix  = self::pushPrefix() ;
            } else {
                $keyName = "'{$token->literal}'";
            }
        }

        // 处理当前FIELD的所有op处理
        do {
            // 读取操作符
            $token = self::readToken($srcCode, $position);
            if (!$token->isValid(Token::IDENTIFIER)) {
                self::revertToken($srcCode, $position, $token);
                throw new CompileException($srcCode, $position, 'CompilerCompile: Expect operator token');
            }
            // 获取到操作符, 并将当前变量作为首参传递
            $objectCode .= "${prefix}if (FALSE === \$this->op_{$token->literal}(${baseName}, ${keyName}";

            // 读取操作符和参数的分隔符冒号":"
            $token = self::readToken($srcCode, $position);
            if ($token->isValid(Token::OP_COLON)) {
                do {
                    $token = self::readToken($srcCode, $position);
                    if (!$token->isValid(Token::STRING | Token::NUMERIC | Token::IDENTIFIER | Token::BOOL_TRUE | Token::BOOL_FALSE | Token::ISNULL | Token::EMPTY_ARRAY)) {
                        self::revertToken($srcCode, $position, $token);
                        throw new CompileException($srcCode, $position, 'CompilerCompile: Expect argument token for operator');
                    }
                    if ($token->isValid(Token::IDENTIFIER)) {
                        $objectCode .= ", '{$token->literal}'";
                    } else {
                        $objectCode .= ", {$token->literal}";
                    }
                    $token = self::readToken($srcCode, $position);
                // 持续解析分号分隔的操作符参数
                } while ($token->isValid(Token::ARG_SPLIT));
            }
            $objectCode .= ")) return FALSE;\n";
        // 持续的解析管道符连接的操作
        } while ($token->isValid(Token::PIPE));

        // 如果当前属性是有块的描述子句, 则递归解析其中描述的每个Field
        while ($token->isValid(Token::BLOK_START)) {
            // 对于块描述串, 在其开始前, 预定义内部要使用的base变量
            $newBaseName = self::newName();
            if ($keyName[0] == "'") {
                $keyNameLiteral = trim($keyName, "'");
                $objectCode .= "${prefix}if (isset(${baseName}[${keyName}])) {\n";
                $prefix = self::pushPrefix();
                $objectCode .= "${prefix}${newBaseName} =& ${baseName}['${keyNameLiteral}'];\n";
            } else {
                $objectCode .= "${prefix}if (isset(${baseName}[${keyName}])) {\n";
                $prefix = self::pushPrefix();
                $objectCode .= "${prefix}${newBaseName} =& ${baseName}[${keyName}];\n";
            }
            while (TRUE) {
                $token = self::readToken($srcCode, $position);
                if ($token->isValid(Token::BLOK_END)) {
                    break;
                }
                self::revertToken($srcCode, $position, $token);
                self::recursiveCompile($srcCode, $objectCode, $position, $newBaseName);
            }
            $prefix = self::popPrefix();
            $objectCode .= "${prefix}}\n";
            $token = self::readToken($srcCode, $position);
        }

        // 所有非字段分隔符(逗号)都还原, 这样就把块中的最后一个逗号可有可无的问题解决了
        if (!$token->isValid(Token::FILED_SPLIT)) {
            self::revertToken($srcCode, $position, $token);
        }

        // FIELD == *: 迭代逻辑. 闭合foreach和if
        if ($isIterateField) {
            self::popScope();
            $objectCode .= self::popPrefix() . "}\n";
            $objectCode .= self::popPrefix() . "}\n";
        }
    }

    protected static function revertToken($srcCode, &$position, $token) {
        $position = $token->pos;
    }

    /**
     * readToken 
     * 读取一个Token
     * @access private
     * @return void
     */
    protected static function readToken($srcCode, &$position) {
        $srcCodeLen = strlen($srcCode);
        $startPos   = $position;
        $token      = NULL;
        while (is_null($token) && $position < $srcCodeLen) {
            $literal = $srcCode[$position];
            $position ++;
            switch ($literal) {
                // 空白字符. 直接跳过
                case ' ':
                case "\n":
                case "\v":
                case "\f":
                case "\t":
                    $startPos ++;
                    while ($position < $srcCodeLen && strpos(" \n\v\f\t", $srcCode[$position]) !== FALSE) {
                        $position ++;
                        $startPos ++;
                    }
                    break;
                // 注释. 忽略#后本行所有字符
                case '#':
                    $startPos ++;
                    while ($position < $srcCodeLen && $srcCode[$position] !== "\n") {
                        $position ++;
                        $startPos ++;
                    }
                    break;
                case ',':
                    $token = Token::buildToken($literal, $startPos, Token::FILED_SPLIT);
                    break;
                case ';':
                    $token = Token::buildToken($literal, $startPos, Token::ARG_SPLIT);
                    break;
                case '|':
                    $token = Token::buildToken($literal, $startPos, Token::PIPE);
                    break;
                case ':':
                    $token = Token::buildToken($literal, $startPos, Token::OP_COLON | Token::FIELD_COLON);
                    break;
                case '{':
                    $token = Token::buildToken($literal, $startPos, Token::BLOK_START);
                    break;
                case '}':
                    $token = Token::buildToken($literal, $startPos, Token::BLOK_END);
                    break;
                case '"':
                case "'":
                    $quote  = $literal;
                    $quoted = FALSE;
                    while ($position < $srcCodeLen) {
                        $ch = $srcCode[$position];
                        $position ++;
                        // 忽略转义字符. 直接补上下一个字符
                        if ($ch === '\\') {
                            $literal .= $ch;
                            // 转义符非最后一个字符, 则执行转义功能: 拼接下一个字符
                            if ($position < $srcCodeLen) {
                                $literal .= $srcCode[$position];
                                $position ++;
                            }
                        // 引号配对闭合
                        } else if ($ch === $quote) {
                            $literal .= $ch;
                            $quoted  = TRUE;
                            break;
                        // 其他字符直接拼接
                        } else {
                            $literal .= $ch;
                        }
                    }
                    if (!$quoted) {
                        $position = $startPos;
                        throw new CompileException($srcCode, $position, 'CompilerReadToken: Quote have no completed');
                    }
                    $token = Token::buildToken($literal, $startPos, Token::STRING);
                    break;
                case '*':
                    $token = Token::buildToken($literal, $startPos, Token::FIELD);
                    break;
                case 'a': case 'A':
                    if (strtolower(substr($srcCode, $startPos, 5)) === 'array') {
                        $literal = substr($srcCode, $startPos, 5);
                        $position += 4;
                        $token = Token::buildToken($literal . '()', $startPos, Token::EMPTY_ARRAY);
                        break;
                    }
                case 'n': case 'N':
                    if (strtolower(substr($srcCode, $startPos, 4)) === 'null') {
                        $literal = substr($srcCode, $startPos, 4);
                        $position += 3;
                        $token = Token::buildToken($literal, $startPos, Token::ISNULL);
                        break;
                    }
                case 't': case 'T':
                    if (strtolower(substr($srcCode, $startPos, 4)) === 'true') {
                        $literal = substr($srcCode, $startPos, 4);
                        $position += 3;
                        $token = Token::buildToken($literal, $startPos, Token::BOOL_TRUE);
                        break;
                    }
                case 'f': case 'F':
                    if (strtolower(substr($srcCode, $startPos, 5)) === 'false') {
                        $literal = substr($srcCode, $startPos, 5);
                        $position += 4;
                        $token = Token::buildToken($literal, $startPos, Token::BOOL_FALSE);
                        break;
                    }
                case 'a': case 'b': case 'c': case 'd': case 'e': case 'f': case 'g': case 'h': case 'i':
                case 'j': case 'k': case 'l': case 'm': case 'n': case 'o': case 'p': case 'q': case 'r':
                case 's': case 't': case 'u': case 'v': case 'w': case 'x': case 'y': case 'z': case 'A':
                case 'B': case 'C': case 'D': case 'E': case 'F': case 'G': case 'H': case 'I': case 'J':
                case 'K': case 'L': case 'M': case 'N': case 'O': case 'P': case 'Q': case 'R': case 'S':
                case 'T': case 'U': case 'V': case 'W': case 'X': case 'Y': case 'Z': case '_':
                    while ($position < $srcCodeLen 
                        && strpos('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_', $srcCode[$position]) !== FALSE) {
                        $literal .= $srcCode[$position];
                        $position ++;
                    }
                    $token = Token::buildToken($literal, $startPos, Token::FIELD | Token::IDENTIFIER);
                    break;
                case '0': case '1': case '2': case '3': case '4':
                case '5': case '6': case '7': case '8': case '9':
                    $existsDot = FALSE;
                    while ($position < $srcCodeLen && strpos('0123456789.', $srcCode[$position]) !== FALSE) {
                        if ($srcCode[$position] === '.') {
                            if ($existsDot) {
                                $position = $startPos;
                                throw new CompileException($srcCode, $position, 'CompilerReadToken: Here is invalid Numeric');
                            }
                            $existsDot = TRUE;
                        }
                        $literal .= $srcCode[$position];
                        $position ++;
                    }
                    $token = Token::buildToken($literal, $startPos, Token::NUMERIC);
                    break;
                default:
                    $position = $startPos;
                    throw new CompileException($srcCode, $position, 'CompilerReadToken: Unrecognized token');
                    break;
            }
        }
        if (is_null($token)) {
            $token = Token::buildToken('', $startPos, Token::EOF);
        }
        return $token;
    }

    /**
     * pushScope 
     * 入栈(并获取新level当前变量名): 用于变量名空间及生成目标代码缩进控制
     * @access protected
     * @return string
     */
    protected static function pushScope() {
        self::$currScope ++;
        if (!array_key_exists(self::$currScope, self::$scopes)) {
            $lastScope = self::$scopes[self::$currScope - 1];
            array_push(self::$scopes, array('level' => $lastScope['level'] + 1, 'nth' => 0));
        }
        return self::newName();
    }

    /**
     * currName 
     * 获取当前的变量名
     * @access protected
     * @return string
     */
    protected static function currName() {
        $scope = self::$scopes[self::$currScope];
        return '$input_' . $scope['level'] . '_' . $scope['nth'];
    }

    /**
     * newName 
     * 在当前作用域级别, 获取下一个变量名
     * @access protected
     * @return string
     */
    protected static function newName() {
        self::$scopes[self::$currScope]['nth'] ++;
        return self::currName();
    }

    /**
     * popScope 
     * 出栈(并获取新level当前变量名)
     * @access protected
     * @return string
     */
    protected static function popScope() {
        self::$currScope --;
        return self::currName();
    }

    /**
     * prefixSpace 
     * 获取当前level下的产出目标代码行的前置空白
     * @access protected
     * @return string
     */
    protected static function prefixSpace() {
        return str_repeat('    ', self::$prefixScope);
    }

    /**
     * pushPrefix
     * 代码前缀入栈
     * @access protected
     * @return void
     */
    protected static function pushPrefix() {
        self::$prefixScope ++;
        return self::prefixSpace();
    }

    /**
     * popPrefix
     * 代码前缀出栈
     * @access protected
     * @return void
     */
    protected static function popPrefix() {
        self::$prefixScope --;
        return self::prefixSpace();
    }

}
