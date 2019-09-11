<?php

namespace ice\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Model;

class Generate extends Command
{
    private $namespace = '';
    private $class_name = '';
    /**
     * @var Model null 模型
     */
    private $model = null;
    /**
     * @var array null 字段
     */
    private $fields = null;

    protected function configure()
    {
        $this
            ->setName('generate')
            ->addOption('path', 'p', Option::VALUE_OPTIONAL, '模型路径，批量生成', null)
            ->addOption('model', 'm', Option::VALUE_OPTIONAL, '模型完整类名，单文件生成', null)
            ->setDescription('thinkphp generate model property');
    }

    protected function execute(Input $input, Output $output)
    {
        $model_text = $input->getOption('model') ?: '';
        $path = $input->getOption('path') ?: '';
        if (empty($model_text) && empty($path)) {
            $output->error("路径或模型必填一个");
            die;
        }
        if (!empty($model_text)) {
            $this->begin($model_text);
        }

        if (!empty($path)) {
            $this->list_file(ROOT_PATH . $path);
        }

        $output->info("Generate Successed!");
    }

    /**
     * 多文件遍历入口
     * @param $date
     * @throws \ReflectionException
     */
    protected function list_file($date)
    {
        //1、首先先读取文件夹
        $temp = scandir($date);
        //遍历文件夹
        foreach ($temp as $v) {
            $a = $date . '/' . $v;
            if (is_dir($a)) {//如果是文件夹则执行
                if ($v == '.' || $v == '..') {//判断是否为系统隐藏的文件.和..  如果是则跳过否则就继续往下走，防止无限循环再这里。
                    continue;
                }
                $this->list_file($a);//因为是文件夹所以再次调用自己这个函数，把这个文件夹下的文件遍历出来
            } else {
                $class = $this->get_class_from_file($a);
                $this->begin($class);
            }

        }
    }

    /**
     * 单文件注释生成
     * @param $model_text
     * @throws \ReflectionException
     */
    protected function begin($model_text)
    {
        $this->model = model($model_text);
        try {
            $this->fields = $this->model->getFieldsType();
        } catch (\Exception $exception) {
            $this->output->info($model_text . ": " . $exception->getMessage());
            return;
        }
        $reflection = new \ReflectionClass($this->model);

        $this->namespace = $reflection->getNamespaceName();
        $a = $reflection->getName();
        $arr = explode('\\', $a);
        if (is_array($arr)) {
            $this->class_name = end($arr);
        } else {
            $this->class_name = $a;
        }


        $file = $reflection->getFileName();
        //通过反射获取类的注释
        $doc = $reflection->getDocComment();
        //如果有注释删除注释
        if ($doc) {
            $code = file_get_contents($file);
            $code = str_replace($doc, '', $code);
            $this->rewrite($file, $code);
        }
        $code = file_get_contents($file);
        //生成代码
        $new_code = $this->generate();
        $code = $this->before_write($code, $new_code);
        $this->rewrite($file, $code);
        $this->output->info($model_text . " Generate Successed!");
    }

    /**
     * 生成注释文件
     * @return string
     */
    protected function generate()
    {
        $code = "/**\n";
        $code .= " * Class " . $this->class_name . "\n";

        foreach ($this->fields as $field => $type) {
            $code .= $this->add($field, $type);
        }
        $code .= " * @package " . $this->namespace . "\n";
        $code .= " */";
        return $code;
    }

    /**
     * 增加属性注释
     * @param $field
     * @param $type
     * @return string
     */
    protected function add($field, $type)
    {

        return " * @property " . $this->trans($type) . " " . $field . "\n";
    }

    /**
     * 数据库类型转php类型
     * @param $type
     * @return string
     */
    protected function trans($type)
    {
        if (strpos($type, 'int') === 0 || strpos($type, 'tinyint') === 0 || strpos($type, 'bigint') === 0) {
            return 'int';
        } elseif (strpos($type, 'decimal') === 0 || strpos($type, 'float') === 0 || strpos($type, 'double') === 0) {
            return 'float';
        } else {
            return 'string';
        }
    }

    /**
     * 使用替换方式更换代码
     * @param $code
     * @param $data
     * @return mixed
     */
    protected function before_write($code, $data)
    {
        $data .= "\n" . 'class ' . $this->class_name . ' extends';
        return str_replace("\n" . 'class ' . $this->class_name . ' extends', $data, $code);
    }

    /**
     * 写入文件
     * @param $to
     * @param $data
     */
    protected function rewrite($to, $data)
    {
        $filenum = fopen($to, "wb");
        flock($filenum, LOCK_EX);
        fwrite($filenum, $data);
        fclose($filenum);
    }

    /**
     * get full qualified class name
     *
     * @param string $path_to_file
     * @return string
     * @author JBYRNE http://jarretbyrne.com/2015/06/197/
     */
    protected function get_class_from_file($path_to_file)
    {
        //Grab the contents of the file
        $contents = file_get_contents($path_to_file);

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {

                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {

                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } elseif ($token === ';') {

                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;
                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {

                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {

                    //Store the token's value as the class name
                    $class = $token[1];

                    //Got what we need, stope here
                    break;
                }
            }
        }

        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }

}