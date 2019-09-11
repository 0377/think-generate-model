# thinkphp5.0快速生成模型的property，让IDE识别更友好

### 安装到dev环境
```php
composer require 0377/think-generate-model --dev
```
### 单文件生成注释
```php
php think generate --model=app\common\model\Test
```
### 批量生成注释
```php
php think generate --path=application/common/model
```