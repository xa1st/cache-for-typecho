<?php
namespace Typecho\Cache;

use Typecho\Cache\Exception as CacheException;

/**
 * 文件缓存适配器
 */
class Local
{
    /**
     * 缓存目录
     * @var string
     */
    private $cacheDir;

    /**
     * 缓存文件扩展名
     * @var string
     */
    private $fileExtension = '.cache';

    /**
     * 是否已初始化
     * @var bool
     */
    private $initialized = false;

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 构造函数不执行任何初始化操作，初始化操作在connect中进行
    }

    /**
     * 连接到缓存服务器
     * 
     * 此方法负责初始化文件缓存系统，包括：
     * 1. 设置缓存目录
     * 2. 检查并创建缓存目录
     * 3. 确保缓存目录可写
     * 
     * @param array $config 配置数组
     * @return void
     * @throws CacheException 如果缓存目录不可写或无法创建，则抛出异常
     */
    public function connect(array $config = []): void
    {
        if ($this->initialized) return;
        // 设置缓存目录，默认为 Typecho根目录/usr/cache
        $this->cacheDir = $config['path'] ?? (__TYPECHO_ROOT_DIR__ . '/usr/cache');
        // 如果缓存目录不存在，则尝试创建
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new CacheException(_t('无法创建缓存目录: ' . $this->cacheDir));
            }
        }
        // 确保缓存目录可写
        if (!is_writable($this->cacheDir)) throw new CacheException(_t('缓存目录不可写: ' . $this->cacheDir));
        // 设置文件扩展名，默认为.cache
        if (isset($config['extension'])) $this->fileExtension = '.' . trim($config['extension'], '.');
        // 设置为已初始化
        $this->initialized = true;
    }

    /**
     * 获取缓存文件路径
     * 
     * @param string $key 缓存键
     * @return string 缓存文件路径
     */
    private function getFilePath(string $key): string
    {
        // 对键名进行MD5哈希处理，确保文件名符合系统要求
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . $this->fileExtension;
    }

    /**
     * 获取缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key, $default = null)
    {
        // 检查键名是否为空，如果为空则返回默认值
        if (empty($key)) return $default;
        // 获取缓存文件路径
        $filePath = $this->getFilePath($key);
        // 检查文件是否存在，如果不存在则返回默认值
        if (!file_exists($filePath)) return $default;
        try {
            // 获取文件内容
            $content = file_get_contents($filePath);
            // 如果获取内容失败，则返回默认值
            if ($content === false) return $default;
            // 解析文件内容
            $data = unserialize($content);
            // 检查数据格式是否正确
            if (!is_array($data) || !isset($data['value'])) return $default;
            // 检查是否过期
            if ($this->isExpired($data)) {
                // 删除过期文件
                @unlink($filePath);
                return $default;
            }
            return $data['value'];
        } catch (\Exception $e) {
            // 如果发生异常，返回默认值
            return $default;
        }
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间（秒）
     * @return bool
     */
    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        //  获取缓存文件路径 
        $filePath = $this->getFilePath($key);
        // 构建缓存数据
        $data = ['value' => $value, 'create' => time()];
        // 如果设置了过期时间，则计算过期时间戳
        $data['expire'] = $expire > 0 ? time() + $expire : 0;
        // 序列化数据
        $content = serialize($data);
        // 写入文件
        try {
            $result = file_put_contents($filePath, $content, LOCK_EX);
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @access public
     * @param string $key 缓存键
     * @return bool
     */
    public function delete($key): bool
    {
        // 获取缓存文件路径
        $filePath = $this->getFilePath($key);
        // 检查文件是否存在
        if (file_exists($filePath))  return @unlink($filePath);
        // 如果文件不存在，则返回true
        return true;
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        // 检查键名是否为空，如果为空则返回false
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) return false;
        // 检查是否过期
        try {
            // 获取文件内容
            $content = file_get_contents($filePath);
            // 如果获取内容失败，则返回false
            if ($content === false) return false;
            // 解析文件内容
            $data = unserialize($content);
            // 检查数据格式是否正确
            if (!is_array($data) || !isset($data['value'])) return false;
            // 如果已过期，则返回false
            if ($this->isExpired($data)) return false;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 清空所有缓存
     *
     * @param string $prefix 缓存前缀
     * @return bool
     */
    public function flush($prefix = ''): bool
    {
        try {
            // 获取缓存目录中的所有文件
            $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*' . $this->fileExtension);
            // 如果没有文件，则返回true
            if (empty($files)) return true;
            // 如果有前缀，则只删除匹配前缀的缓存
            if (!empty($prefix)) {
                foreach ($files as $file) {
                    // 获取文件名，并解析出键名
                    $key = basename($file, $this->fileExtension);
                    // 获取文件内容，并解析出键名
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $data = unserialize($content);
                        if (isset($data['key']) && strpos($data['key'], $prefix) === 0) {
                            @unlink($file);
                        }
                    }
                }
            } else {
                // 没有前缀，则删除所有缓存文件
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 清理过期缓存
     * 
     * @return bool
     */
    public function gc(): bool
    {
        try {
            // 获取缓存目录中的所有文件
            $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*' . $this->fileExtension);
            // 如果没有文件，则返回true
            if (empty($files)) return true;
            foreach ($files as $file) {
                // 获取文件内容
                $content = file_get_contents($file);
                // 如果获取内容失败，则跳过该文件
                if ($content !== false) {
                    $data = unserialize($content);
                    
                    // 检查是否过期
                    if (is_array($data) && isset($data['expire']) && $data['expire'] > 0 && $data['expire'] < time()) {
                        @unlink($file);
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool
    {
        // 文件缓存不需要关闭连接
        return true;
    }

    /**
     * 检查缓存是否过期
     * 
     * @param array $data 缓存数据
     * @return bool 是否过期
     */
    private function isExpired(array $data): bool
    {
        return isset($data['expire']) && $data['expire'] > 0 && $data['expire'] < time();
    }
}