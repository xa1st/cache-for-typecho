# Cache For Typecho

为TYPECHO添加缓存的类，用于手动需要缓存的一切位置

如果你看完文档还不知道他有什么用，说明他不是你所需要的缓存，你可能找的是以下2个

- TpCache: https://github.com/phpgao/TpCache
- TpCache魔改版: https://github.com/gogobody/TpCache

## 功能特点

- 支持Redis / 文件缓存
- 支持原生写法，支持自定义配置

## 安装方法

下载文件，解压至根目录，覆盖到根目录的var下就好了...

## 配置说明

参考config.inc.php-simple文件中最底下部分的配置说明，

```
$cache = new Typecho_Cache('Redis', [
  'host' => 'redis服务器地址',  // 服务地址，IP或者域名皆可，如果支持TLS，请填tls://redis服务器地址
  'password' => '', // 密钥，没有请留空，如果是自装redis，强烈建议设置并启用
  'port' => 6379，// 端口号,一般是6379, 
  'db' => 0,  // 数据库选择，一般默认为0，如果不是0，请填写具体数据库编号 
  'timeout' => 0,  // 超时时间，默认为0，表示不超时
  'prefix' => 'typecho_' // 缓存前缀，默认typecho_，可以自行修改
]);
Typecho_Cache::setCache($cache);
```

## 使用说明

查:
```php
$cache = Typecho_Cache::getCache();
$cache->has('cache_key');
```

读:
```php
$cache = Typecho_Cache::getCache();
$cache->get('cache_key');
```

写:
```php
$cache = Typecho_Cache::getCache();
$cache->set('cache_key', 'cache_value', 3600);
```

删:
```php
$cache = Typecho_Cache::getCache();
$cache->delete('cache_key');
```

就这么多功能啦...

## 更新日志

### 1.0.1

  - 添加文件缓存
  - 修改REDIS配置说明，让支持tls的用户可以配置

### 1.0.0
  - 初始版本，暂时实现了redis缓存，文件的稍后写

## 技术支持

如有任何问题或建议，请在 GitHub 上提交 Issue 或者联系插件作者。

## 许可协议

本插件采用 MIT 许可协议发布。
